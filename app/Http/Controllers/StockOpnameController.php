<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockOpnameController extends Controller
{
    /**
     * Tampilkan halaman utama Stok Opname
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Kasir hanya boleh jika diizinkan oleh admin di pengaturan sistem
        if ($user->role === 'kasir') {
            $canAccess = Setting::get('kasir_can_access_stock_opname', '0');
            if ($canAccess !== '1' && $canAccess !== true && $canAccess !== 1) {
                return redirect()->route('pos.index')->with('error', 'Akses Stok Opname untuk Kasir saat ini dinonaktifkan.');
            }
        }

        // Ambil produk aktif beserta unit, kategori, dan lokasi
        $products = Product::with(['units', 'category', 'productLocations.location'])
            ->orderBy('name', 'asc')
            ->get();

        $categories = Category::orderBy('name', 'asc')->get();

        // Riwayat dokumen Stok Opname
        $opnames = StockOpname::with(['user', 'category', 'voidedBy', 'items.product.units'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Pengaturan izin kasir (untuk ditampilkan ke admin di tab pengaturan)
        $canAccessSo = Setting::get('kasir_can_access_stock_opname', '0');
        $allowCashierOpname = ($canAccessSo === '1' || $canAccessSo === true || $canAccessSo === 1);

        // Generate nomor dokumen rekomendasi otomatis (SO-YYYYMM-XXXX)
        $prefix = 'SO-' . date('Ym') . '-';
        $lastOpname = StockOpname::where('opname_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        
        $nextSeq = 1;
        if ($lastOpname) {
            $lastSeq = (int)substr($lastOpname->opname_number, -4);
            $nextSeq = $lastSeq + 1;
        }
        $autoOpnameNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        $stockLogs = StockAdjustment::with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('StockOpnames/Index', [
            'products' => $products,
            'categories' => $categories,
            'opnames' => $opnames,
            'stockLogs' => $stockLogs,
            'allowCashierOpname' => $allowCashierOpname,
            'autoOpnameNumber' => $autoOpnameNumber,
            'currentUser' => $user,
        ]);
    }

    /**
     * Simpan sesi Stok Opname baru dan terapkan selisih (delta) ke stok terkini.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'kasir') {
            $canAccess = Setting::get('kasir_can_access_stock_opname', '0');
            if ($canAccess !== '1' && $canAccess !== true && $canAccess !== 1) {
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
                    'is_voided' => false,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Terapkan DELTA ke stok terkini (bukan timpa dengan nilai absolut)
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
                "Dokumen Stok Opname '{$opname->opname_number}' berhasil diterapkan. {$totalChecked} produk dicatat, {$totalDiff} produk disesuaikan stok-nya."
            );
        });
    }

    /**
     * Void / Batalkan HANYA SATU ITEM tertentu di dalam dokumen opname.
     * Tidak membatalkan item-item lain yang sudah benar.
     */
    public function voidItem(Request $request, $itemId)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'gudang'])) {
            return back()->with('error', 'Akses ditolak: Hanya Admin atau Gudang yang dapat membatalkan item Stok Opname.');
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($itemId, $validated, $user) {
            $item = StockOpnameItem::with(['stockOpname', 'product'])->findOrFail($itemId);
            $opname = $item->stockOpname;

            if ($item->is_voided) {
                return back()->with('error', 'Item ini sudah pernah dibatalkan sebelumnya.');
            }

            if ($opname->is_voided) {
                return back()->with('error', 'Dokumen opname ini sudah dibatalkan secara keseluruhan.');
            }

            $delta = (float)$item->qty_difference;

            // Balik mutasi stok jika ada penyesuaian yang pernah diterapkan
            if (abs($delta) > 0.0001 && $item->product) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    $reverseDelta = -$delta;
                    $newStock = max(0, $product->stock_physical + $reverseDelta);
                    $product->stock_physical = $newStock;
                    $product->save();

                    StockAdjustment::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => $reverseDelta > 0 ? 'in' : 'out',
                        'qty_change' => $reverseDelta,
                        'reason' => "Void Item SO {$opname->opname_number} ({$product->name}): {$validated['void_reason']}",
                    ]);
                }
            }

            // Tandai item sebagai void
            $item->update([
                'is_voided' => true,
                'voided_at' => now(),
                'void_reason' => $validated['void_reason'],
            ]);

            // Hitung ulang rekap selisih pada dokumen opname
            $activeItems = $opname->items()->where('is_voided', false)->get();
            $totalDiff = 0;
            $totalQtyDiff = 0;
            $totalCostDiff = 0;

            foreach ($activeItems as $actItem) {
                $d = (float)$actItem->qty_difference;
                if (abs($d) > 0.0001) {
                    $totalDiff++;
                    $totalQtyDiff += $d;
                    $totalCostDiff += (float)$actItem->subtotal_cost_diff;
                }
            }

            $opname->update([
                'total_items_diff' => $totalDiff,
                'total_qty_diff' => $totalQtyDiff,
                'total_cost_diff' => $totalCostDiff,
            ]);

            $productName = $item->product ? $item->product->name : 'Item';
            return back()->with(
                'success',
                "Koreksi untuk produk '{$productName}' berhasil dibatalkan dan stok dikembalikan. Item lain dalam dokumen tetap sah."
            );
        });
    }

    /**
     * Void / Batalkan SELURUH dokumen opname — balik semua delta yang belum di-void.
     */
    public function void(Request $request, $id)
    {
        $user = Auth::user();

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

            // Balik delta untuk item yang belum pernah di-void per-item
            $reversedCount = 0;
            foreach ($opname->items as $item) {
                if ($item->is_voided) continue; // Jangan double-reverse jika sudah pernah di-void satu per satu

                $delta = (float)$item->qty_difference;
                if (abs($delta) > 0.0001) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $reverseDelta = -$delta;
                        $newStock = max(0, $product->stock_physical + $reverseDelta);
                        $product->stock_physical = $newStock;
                        $product->save();

                        StockAdjustment::create([
                            'product_id' => $product->id,
                            'user_id' => $user->id,
                            'type' => $reverseDelta > 0 ? 'in' : 'out',
                            'qty_change' => $reverseDelta,
                            'reason' => "Void Dokumen SO {$opname->opname_number}: {$validated['void_reason']}",
                        ]);

                        $reversedCount++;
                    }
                }

                $item->update([
                    'is_voided' => true,
                    'voided_at' => now(),
                    'void_reason' => $validated['void_reason'],
                ]);
            }

            // Tandai seluruh dokumen sebagai void
            $opname->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $validated['void_reason'],
                'status' => 'voided',
                'total_items_diff' => 0,
                'total_qty_diff' => 0,
                'total_cost_diff' => 0,
            ]);

            return back()->with(
                'success',
                "Dokumen {$opname->opname_number} berhasil dibatalkan seutuhnya. {$reversedCount} penyesuaian stok produk telah dikembalikan."
            );
        });
    }
}
