<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function index()
    {
        $receipts = GoodsReceipt::with(['receiver', 'approver', 'supplier', 'location', 'items.product', 'items.unit'])
            ->latest()
            ->get();

        $products = Product::with(['units', 'category', 'brand', 'baseUnit'])
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        $pendingCount = GoodsReceipt::where('status', 'pending')->count();

        $totalReceiptsThisMonth = GoodsReceipt::where('status', 'approved')
            ->whereMonth('receipt_date', now()->month)
            ->whereYear('receipt_date', now()->year)
            ->count();

        $totalCostInboundThisMonth = GoodsReceipt::where('status', 'approved')
            ->whereMonth('receipt_date', now()->month)
            ->whereYear('receipt_date', now()->year)
            ->sum('total_cost_amount');

        return Inertia::render('GoodsReceipts/Index', [
            'receipts' => $receipts,
            'products' => $products,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'pendingCount' => $pendingCount,
            'totalReceiptsThisMonth' => $totalReceiptsThisMonth,
            'totalCostInboundThisMonth' => (float)$totalCostInboundThisMonth,
            'user' => Auth::user(),
        ]);
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'address' => 'nullable|string',
        ]);

        $name = trim($validated['name']);

        // Cegah duplikasi supplier (case-insensitive)
        $supplier = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();

        if ($supplier) {
            $dirty = false;
            if (!empty($validated['phone']) && empty($supplier->phone)) {
                $supplier->phone = $validated['phone'];
                $dirty = true;
            }
            if (!empty($validated['contact_person']) && empty($supplier->contact_person)) {
                $supplier->contact_person = $validated['contact_person'];
                $dirty = true;
            }
            if (!empty($validated['address']) && empty($supplier->address)) {
                $supplier->address = $validated['address'];
                $dirty = true;
            }
            if ($dirty) {
                $supplier->save();
            }

            return redirect()->back()->with('success', "Supplier \"{$supplier->name}\" sudah terdaftar dan siap digunakan.");
        }

        $supplier = Supplier::create([
            'name' => $name,
            'phone' => $validated['phone'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        return redirect()->back()->with('success', "Supplier \"{$supplier->name}\" berhasil ditambahkan.");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty_received' => 'required|numeric|min:0.01',
            'items.*.cost_price_per_unit' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = Auth::user();
            $dateCode = now()->format('Ymd');
            $countToday = GoodsReceipt::whereDate('created_at', now()->toDateString())->count() + 1;
            $receiptNumber = 'GR-' . $dateCode . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $defaultLoc = Location::where('is_default', true)->first() ?? Location::first();
            $targetLocId = $validated['location_id'] ?? ($defaultLoc ? $defaultLoc->id : 1);

            $totalCost = 0;
            foreach ($validated['items'] as $it) {
                $cost = (float)($it['cost_price_per_unit'] ?? 0);
                $totalCost += (float)$it['qty_received'] * $cost;
            }

            $supplierName = $validated['supplier_name'];
            if (!empty($validated['supplier_id'])) {
                $s = Supplier::find($validated['supplier_id']);
                if ($s) {
                    $supplierName = $s->name;
                }
            }

            // Per requirement: Seluruh entri kasir masuk ke status 'pending' untuk persetujuan Admin
            $status = 'pending';
            $approvedBy = null;
            $approvedAt = null;

            $receipt = GoodsReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'location_id' => $targetLocId,
                'receipt_date' => $validated['receipt_date'],
                'receiver_id' => $user->id,
                'status' => $status,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'total_cost_amount' => $totalCost,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $unit = ProductUnit::findOrFail($item['product_unit_id']);
                $product = Product::findOrFail($item['product_id']);

                $ratio = (float)$unit->conversion_ratio;
                $qtyReceived = (float)$item['qty_received'];
                $baseQtyAdded = $qtyReceived * $ratio;
                $costPerUnit = (float)($item['cost_price_per_unit'] ?? $unit->cost_price);
                $subtotalCost = $qtyReceived * $costPerUnit;

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'qty_received' => $qtyReceived,
                    'conversion_ratio' => $ratio,
                    'base_qty_added' => $baseQtyAdded,
                    'cost_price_per_unit' => $costPerUnit,
                    'subtotal_cost' => $subtotalCost,
                ]);
            }

            return redirect()->route('goods-receipts.index')->with('success', "Dokumen penerimaan {$receiptNumber} berhasil disimpan. Menunggu persetujuan Admin untuk penambahan stok.");
        });
    }

    public function approve($id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return back()->with('error', 'Hanya Administrator yang berwenang menyetujui penerimaan barang.');
        }

        $receipt = GoodsReceipt::with(['items.unit', 'items.product', 'location'])->findOrFail($id);

        if ($receipt->status !== 'pending') {
            return back()->with('error', "Dokumen {$receipt->receipt_number} sudah diproses sebelumnya ({$receipt->status}).");
        }

        return DB::transaction(function () use ($receipt, $user) {
            $targetLocId = $receipt->location_id ?? 1;
            $targetLocName = $receipt->location ? $receipt->location->name : 'Kantin Utama';

            foreach ($receipt->items as $item) {
                $product = $item->product;
                $unit = $item->unit;
                $baseQty = (float)$item->base_qty_added;

                // Tambah stok fisik agregat produk
                $product->increment('stock_physical', $baseQty);

                // Tambah stok fisik per lokasi
                $prodLoc = ProductLocation::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $targetLocId],
                    ['stock_physical' => 0, 'min_stock' => 0]
                );
                $prodLoc->increment('stock_physical', $baseQty);

                // Update harga modal jika ada
                if ((float)$item->cost_price_per_unit > 0 && $unit) {
                    $unit->update(['cost_price' => (float)$item->cost_price_per_unit]);
                }

                // Catat log kartu stok
                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'qty_change' => $baseQty,
                    'reason' => "Penerimaan Barang [{$receipt->receipt_number}] Disetujui Admin. Supplier: {$receipt->supplier_name}",
                ]);
            }

            $receipt->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return redirect()->route('goods-receipts.index')->with('success', "Penerimaan Barang {$receipt->receipt_number} telah DISETUJUI. Stok produk telah otomatis bertambah.");
        });
    }

    public function reject(Request $request, $id)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return back()->with('error', 'Hanya Administrator yang berwenang menolak penerimaan barang.');
        }

        $receipt = GoodsReceipt::findOrFail($id);

        if ($receipt->status !== 'pending') {
            return back()->with('error', "Dokumen {$receipt->receipt_number} sudah diproses sebelumnya ({$receipt->status}).");
        }

        $receipt->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason', 'Ditolak oleh Administrator.'),
        ]);

        return redirect()->route('goods-receipts.index')->with('success', "Penerimaan Barang {$receipt->receipt_number} telah DITOLAK. Stok fisik tidak mengalami perubahan.");
    }
}
