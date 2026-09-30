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
        
        $opnames = StockOpname::with(['user', 'category', 'items.product.units', 'voidedBy'])
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
            'items.*.qty_snapshot' => 'required|numeric',  // stok saat mulai opname
            'items.*.qty_system' => 'required|numeric',    // stok terkini sistem (saat terapkan)
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
                $qtySnapshot = (float)$item['qty_snapshot'];
                $qtyPhys = (float)$item['qty_physical'];
                // Selisih dihitung dari SNAPSHOT (bukan stok real-time)
                // agar aman walau ada transaksi POS berjalan saat opname
                $diff = $qtyPhys - $qtySnapshot;
                $costPrice = (float)($item['cost_price'] ?? 0);
                $costDiff = $diff * $costPrice;

                $totalQtySystem += $qtySnapshot;
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
                'is_voided' => false,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);
                if (!$product) continue;

                $qtySnapshot = (float)$item['qty_snapshot'];
                $qtyPhys = (float)$item['qty_physical'];
                // Delta berdasarkan snapshot — aman terhadap concurrent POS transactions
                $delta = $qtyPhys - $qtySnapshot;
                $costPrice = (float)($item['cost_price'] ?? 0);
                $subtotalCostDiff = $delta * $costPrice;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'unit_name' => $item['unit_name'] ?? $product->units[0]->unit_name ?? 'Pcs',
                    'qty_snapshot' => $qtySnapshot,          // stok saat mulai opname
                    'qty_system' => (float)$item['qty_system'], // stok aktual sistem saat terapkan
                    'qty_physical' => $qtyPhys,
                    'qty_difference' => $delta,
                    'cost_price_per_unit' => $costPrice,
                    'subtotal_cost_diff' => $subtotalCostDiff,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Terapkan DELTA ke stok terkini (bukan timpa dengan nilai absolut)
                // Contoh: snapshot=50, fisik=48 → delta=-2
                // Jika ada 3 penjualan selama opname: stok_terkini=47
                // Hasil: 47 + (-2) = 45 ✅  (bukan paksa jadi 48 ✗)
                if (abs($delta) > 0.0001) {
                    $newStock = max(0, $product->stock_physical + $delta);
                    $product->stock_physical = $newStock;
                    $product->save();

                    // Catat ke buku mutasi stok
                    StockAdjustment::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => $delta > 0 ? 'in' : 'out',
                        'qty_change' => $delta,
                        'reason' => "Stok Opname {$opname->opname_number}" . (!empty($item['notes']) ? ": {$item['notes']}" : ""),
                    ]);
                }
            }

            return redirect()->route('stock-opnames.index')->with(
                'success',
                "Dokumen Stok Opname '{$opname->opname_number}' berhasil diterapkan. {$totalDiff} produk disesuaikan stok-nya (metode delta-snapshot)."
            );
        });
    }

    /**
     * Void / Batalkan dokumen opname — balik delta yang sudah diterapkan.
     * Hanya Admin/Gudang yang bisa void. Kasir tidak bisa.
     */
    public function void(Request $request, $id)
    {
        $user = Auth::user();

        // Hanya admin dan gudang yang boleh void
        if (!in_array($user->role, ['admin', 'gudang'])) {
            return back()->with('error', 'Akses ditolak: Hanya Admin atau Gudang yang dapat membatalkan dokumen Stok Opname.');
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($id, $validated, $user) {
            $opname = StockOpname::with('items.product')->findOrFail($id);

            if ($opname->is_voided) {
                return back()->with('error', "Dokumen {$opname->opname_number} sudah pernah dibatalkan sebelumnya.");
            }

            // Balik delta yang pernah diterapkan
            $reversedCount = 0;
            foreach ($opname->items as $item) {
                $delta = (float)$item->qty_difference; // delta yang pernah diterapkan
                if (abs($delta) < 0.0001) continue;

                $product = Product::lockForUpdate()->find($item->product_id);
                if (!$product) continue;

                // Balik: kalau dulu +2, sekarang -2 dan sebaliknya
                $reverseDelta = -$delta;
                $newStock = max(0, $product->stock_physical + $reverseDelta);
                $product->stock_physical = $newStock;
                $product->save();

                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => $reverseDelta > 0 ? 'in' : 'out',
                    'qty_change' => $reverseDelta,
                    'reason' => "Void/Batal Stok Opname {$opname->opname_number}: {$validated['void_reason']}",
                ]);

                $reversedCount++;
            }

            // Tandai dokumen sebagai void
            $opname->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $validated['void_reason'],
                'status' => 'voided',
            ]);

            return back()->with(
                'success',
                "Dokumen {$opname->opname_number} berhasil dibatalkan. {$reversedCount} produk stok-nya telah dikembalikan ke kondisi sebelum opname."
            );
        });
    }
}
