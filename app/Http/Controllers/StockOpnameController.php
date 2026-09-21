<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockOpnameController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'brand', 'units'])->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        
        $opnames = StockOpname::with(['user', 'category', 'items.product.units'])
            ->latest()
            ->take(100)
            ->get();

        $stockLogs = StockAdjustment::with(['product.units', 'user'])
            ->latest()
            ->take(50)
            ->get();

        // Generate next nomor dokumen SO otomatis
        $prefix = 'SO-' . date('Ym') . '-';
        $lastSo = StockOpname::where('opname_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;
        if ($lastSo) {
            $lastNum = (int)substr($lastSo->opname_number, -4);
            $nextSeq = $lastNum + 1;
        }
        $autoOpnameNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        return Inertia::render('StockOpnames/Index', [
            'products' => $products,
            'categories' => $categories,
            'opnames' => $opnames,
            'stockLogs' => $stockLogs,
            'autoOpnameNumber' => $autoOpnameNumber,
            'currentUser' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->role === 'kasir') {
            $canAccessSo = \App\Models\Setting::get('kasir_can_access_stock_opname', '0');
            if ($canAccessSo !== '1' && $canAccessSo !== true && $canAccessSo !== 1) {
                return back()->with('error', 'Akses ditolak: Izin Stok Opname untuk Kasir sedang dinonaktifkan oleh Admin.');
            }
        }

        $validated = $request->validate([
            'opname_number' => 'required|string|max:50|unique:stock_opnames,opname_number',
            'opname_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_name' => 'nullable|string',
            'items.*.qty_system' => 'required|numeric',
            'items.*.qty_physical' => 'required|numeric|min:0',
            'items.*.qty_difference' => 'required|numeric',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $user) {
            $totalChecked = count($validated['items']);
            $totalDiff = 0;
            $totalQtySystem = 0;
            $totalQtyPhysical = 0;
            $totalQtyDiff = 0;
            $totalCostDiff = 0;

            foreach ($validated['items'] as $item) {
                $qtySys = (float)$item['qty_system'];
                $qtyPhys = (float)$item['qty_physical'];
                $diff = $qtyPhys - $qtySys;
                $costPrice = (float)($item['cost_price'] ?? 0);
                $costDiff = $diff * $costPrice;

                $totalQtySystem += $qtySys;
                $totalQtyPhysical += $qtyPhys;
                $totalQtyDiff += $diff;
                $totalCostDiff += $costDiff;

                if (abs($diff) > 0.0001) {
                    $totalDiff++;
                }
            }

            $opname = StockOpname::create([
                'opname_number' => $validated['opname_number'],
                'opname_date' => $validated['opname_date'],
                'user_id' => $user->id,
                'category_id' => $validated['category_id'] ?? null,
                'total_items_checked' => $totalChecked,
                'total_items_diff' => $totalDiff,
                'total_qty_system' => $totalQtySystem,
                'total_qty_physical' => $totalQtyPhysical,
                'total_qty_diff' => $totalQtyDiff,
                'total_cost_diff' => $totalCostDiff,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;

                $qtySys = (float)$item['qty_system'];
                $qtyPhys = (float)$item['qty_physical'];
                $diff = $qtyPhys - $qtySys;
                $costPrice = (float)($item['cost_price'] ?? 0);
                $subtotalCostDiff = $diff * $costPrice;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'unit_name' => $item['unit_name'] ?? $product->units[0]->unit_name ?? 'Pcs',
                    'qty_system' => $qtySys,
                    'qty_physical' => $qtyPhys,
                    'qty_difference' => $diff,
                    'cost_price_per_unit' => $costPrice,
                    'subtotal_cost_diff' => $subtotalCostDiff,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Update stok fisik produk jika ada selisih
                if (abs($diff) > 0.0001) {
                    $product->stock_physical = $qtyPhys;
                    $product->save();

                    // Catat ke buku mutasi stok
                    StockAdjustment::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => $diff > 0 ? 'in' : 'out',
                        'qty_change' => $diff,
                        'reason' => "Stok Opname {$opname->opname_number}" . (!empty($item['notes']) ? ": {$item['notes']}" : ""),
                    ]);
                }
            }

            return redirect()->route('stock-opnames.index')->with(
                'success',
                "Dokumen Stok Opname '{$opname->opname_number}' berhasil diselesaikan & disimpan. Sebanyak {$totalDiff} produk disesuaikan stok fisiknya."
            );
        });
    }
}
