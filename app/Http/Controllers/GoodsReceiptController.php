<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function index()
    {
        $receipts = GoodsReceipt::with(['receiver', 'supplier', 'items.product', 'items.unit'])
            ->latest()
            ->get();

        $products = Product::with(['units', 'category', 'brand', 'baseUnit'])
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::orderBy('name')->get();
        $locations = \App\Models\Location::orderBy('name')->get();

        $totalReceiptsThisMonth = GoodsReceipt::whereMonth('receipt_date', now()->month)
            ->whereYear('receipt_date', now()->year)
            ->count();

        $totalCostInboundThisMonth = GoodsReceipt::whereMonth('receipt_date', now()->month)
            ->whereYear('receipt_date', now()->year)
            ->sum('total_cost_amount');

        return Inertia::render('GoodsReceipts/Index', [
            'receipts' => $receipts,
            'products' => $products,
            'suppliers' => $suppliers,
            'locations' => $locations,
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

        $supplier = Supplier::create($validated);

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
            $dateCode = now()->format('Ymd');
            $countToday = GoodsReceipt::whereDate('created_at', now()->toDateString())->count() + 1;
            $receiptNumber = 'GR-' . $dateCode . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $defaultLoc = \App\Models\Location::where('is_default', true)->first() ?? \App\Models\Location::first();
            $targetLocId = $validated['location_id'] ?? ($defaultLoc ? $defaultLoc->id : 1);
            $targetLoc = \App\Models\Location::find($targetLocId);
            $targetLocName = $targetLoc ? $targetLoc->name : 'Gudang Utama';

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

            $receipt = GoodsReceipt::create([
                'receipt_number' => $receiptNumber,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'location_id' => $targetLocId,
                'receipt_date' => $validated['receipt_date'],
                'receiver_id' => Auth::id(),
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

                // Update product aggregate physical stock
                $product->increment('stock_physical', $baseQtyAdded);

                // Update specific product_locations physical stock
                $prodLoc = \App\Models\ProductLocation::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $targetLocId],
                    ['stock_physical' => 0, 'min_stock' => 0]
                );
                $prodLoc->increment('stock_physical', $baseQtyAdded);

                // Optionally update unit cost price if provided
                if ($costPerUnit > 0) {
                    $unit->update(['cost_price' => $costPerUnit]);
                }

                // Record stock adjustment log
                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'in',
                    'qty_change' => $baseQtyAdded,
                    'reason' => "Penerimaan Barang [{$receiptNumber}] Lokasi: {$targetLocName} | Supplier: {$supplierName}",
                ]);
            }

            return redirect()->route('goods-receipts.index')->with('success', "Penerimaan Barang {$receiptNumber} berhasil disimpan ke {$targetLocName}. Stok telah otomatis ditambahkan.");
        });
    }
}
