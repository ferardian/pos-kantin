<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\ConsignmentBatch;
use App\Models\ConsignmentItem;
use App\Models\Consignor;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConsignmentController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        // 1. Active batches (belum disettle)
        $activeBatches = ConsignmentBatch::with([
            'consignor',
            'cashier:id,name',
            'items.product.baseUnit',
            'items.unit',
        ])
        ->where('status', 'active')
        ->orderBy('dropoff_date', 'desc')
        ->orderBy('id', 'desc')
        ->get();

        // Tambahkan info real-time stock saat ini untuk tiap item di active batch
        $activeBatches->each(function ($batch) {
            $batch->items->each(function ($item) {
                if ($item->product) {
                    $item->current_stock = (float)$item->product->stock_physical;
                }
            });
        });

        // 2. Settled batches (riwayat pelunasan)
        $settledBatches = ConsignmentBatch::with([
            'consignor',
            'cashier:id,name',
            'cashbox:id,name',
            'items.product',
            'items.unit',
        ])
        ->where('status', 'settled')
        ->orderBy('settlement_date', 'desc')
        ->take(60)
        ->get();

        // 3. Consignors (Master Penitip)
        $consignors = Consignor::withCount(['batches', 'products'])
            ->orderBy('name')
            ->get();

        // 4. Products for easy selection (consignment products & snacks)
        $consignmentProducts = Product::with(['baseUnit', 'category', 'consignor'])
            ->where(function ($q) {
                $q->where('is_consignment', true)
                  ->orWhereNotNull('consignor_id');
            })
            ->orderBy('name')
            ->get();

        $allProducts = Product::with('baseUnit')
            ->select('id', 'name', 'barcode', 'sku', 'stock_physical', 'category_id')
            ->orderBy('name')
            ->get();

        // 5. Cashboxes
        $cashboxes = Cashbox::all();

        // 6. Summary metrics
        $activeCount = ConsignmentBatch::where('status', 'active')->count();
        $todaySettled = ConsignmentBatch::where('status', 'settled')
            ->whereDate('settlement_date', $today)
            ->get();
        
        $totalPaidToday = $todaySettled->sum('total_payable');
        $totalProfitToday = $todaySettled->sum('total_canteen_profit');
        $totalDroppedToday = ConsignmentBatch::whereDate('dropoff_date', $today)->sum('total_qty_dropped');

        return Inertia::render('Consignments/Index', [
            'activeBatches'       => $activeBatches,
            'settledBatches'      => $settledBatches,
            'consignors'          => $consignors,
            'consignmentProducts' => $consignmentProducts,
            'allProducts'         => $allProducts,
            'cashboxes'           => $cashboxes,
            'categories'          => Category::orderBy('name')->get(),
            'settings'            => Setting::getSettings(),
            'summary'             => [
                'active_count'        => $activeCount,
                'total_dropped_today' => (int)$totalDroppedToday,
                'total_paid_today'    => (float)$totalPaidToday,
                'total_profit_today'  => (float)$totalProfitToday,
            ],
        ]);
    }

    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'consignor_id'            => 'required|exists:consignors,id',
            'dropoff_date'            => 'required|date',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'nullable|exists:products,id',
            'items.*.custom_name'     => 'nullable|string|max:255',
            'items.*.qty_dropped'     => 'required|numeric|min:1',
            'items.*.cost_price'      => 'required|numeric|min:0',
            'items.*.selling_price'   => 'required|numeric|min:0',
            'items.*.price_employee'  => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = Auth::user();
            $batchNumber = 'CSG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $totalQtyDropped = 0;
            foreach ($validated['items'] as $it) {
                $totalQtyDropped += $it['qty_dropped'];
            }

            $batch = ConsignmentBatch::create([
                'batch_number'      => $batchNumber,
                'consignor_id'      => $validated['consignor_id'],
                'user_id'           => $user->id,
                'dropoff_date'      => $validated['dropoff_date'],
                'total_qty_dropped' => $totalQtyDropped,
                'total_qty_sold'    => 0,
                'total_qty_returned'=> 0,
                'total_payable'     => 0,
                'total_canteen_profit' => 0,
                'status'            => 'active',
                'notes'             => $validated['notes'] ?? null,
            ]);

            // Default Snack Category
            $defaultCategory = Category::where('name', 'like', '%snack%')->first()
                ?? Category::where('name', 'like', '%makanan%')->first()
                ?? Category::first();

            foreach ($validated['items'] as $itemData) {
                $product = null;

                if (!empty($itemData['product_id'])) {
                    $product = Product::with('baseUnit')->find($itemData['product_id']);
                    // Pastikan ditandai konsinyasi
                    $product->is_consignment = true;
                    if (!$product->consignor_id) {
                        $product->consignor_id = $validated['consignor_id'];
                    }
                    $product->save();

                    // Update harga jual dan harga setor base unit
                    if ($product->baseUnit) {
                        $product->baseUnit->cost_price = $itemData['cost_price'];
                        $product->baseUnit->price_retail = $itemData['selling_price'];
                        if (isset($itemData['price_employee'])) {
                            $product->baseUnit->price_employee = (float)$itemData['price_employee'];
                        }
                        $product->baseUnit->save();
                    }
                } else if (!empty($itemData['custom_name'])) {
                    // Buat produk baru secara instan
                    $sku = 'CSG-' . strtoupper(Str::random(6));
                    $product = Product::create([
                        'name'           => trim($itemData['custom_name']),
                        'sku'            => $sku,
                        'category_id'    => $defaultCategory ? $defaultCategory->id : 1,
                        'consignor_id'   => $validated['consignor_id'],
                        'is_consignment' => true,
                        'stock_physical' => 0,
                        'stock_booked'   => 0,
                    ]);

                    ProductUnit::create([
                        'product_id'       => $product->id,
                        'unit_name'        => 'Pcs',
                        'conversion_ratio' => 1,
                        'cost_price'       => $itemData['cost_price'],
                        'price_retail'     => $itemData['selling_price'],
                        'price_employee'   => $itemData['price_employee'] ?? 0,
                        'is_base_unit'     => true,
                    ]);

                    $product->load('baseUnit');
                }

                if ($product) {
                    $productUnit = $product->baseUnit ?? $product->units()->first();

                    // Tambah stok fisik di kasir
                    $product->increment('stock_physical', $itemData['qty_dropped']);

                    ConsignmentItem::create([
                        'consignment_batch_id' => $batch->id,
                        'product_id'           => $product->id,
                        'product_unit_id'      => $productUnit ? $productUnit->id : null,
                        'qty_dropped'          => $itemData['qty_dropped'],
                        'qty_sold'             => 0,
                        'qty_returned'         => 0,
                        'cost_price'           => $itemData['cost_price'],
                        'selling_price'        => $itemData['selling_price'],
                        'price_employee'       => $itemData['price_employee'] ?? 0,
                        'subtotal_payable'     => 0,
                        'subtotal_profit'      => 0,
                    ]);
                }
            }

            return redirect()->back()->with('success', "Penerimaan titipan {$batch->batch_number} berhasil disimpan. Stok fisik POS telah bertambah!");
        });
    }

    public function settleBatch(Request $request, $id)
    {
        $batch = ConsignmentBatch::with(['consignor', 'items.product'])->findOrFail($id);

        if ($batch->status !== 'active') {
            return redirect()->back()->with('error', 'Batch titipan ini sudah diselesaikan sebelumnya.');
        }

        $validated = $request->validate([
            'cashbox_id'          => 'nullable|exists:cashboxes,id',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.id'          => 'required|exists:consignment_items,id',
            'items.*.qty_returned'=> 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($batch, $validated) {
            $user = Auth::user();

            $totalSold = 0;
            $totalReturned = 0;
            $totalPayable = 0;
            $totalProfit = 0;

            foreach ($validated['items'] as $itemData) {
                $item = ConsignmentItem::where('consignment_batch_id', $batch->id)
                    ->findOrFail($itemData['id']);

                $qtyReturned = (float)$itemData['qty_returned'];
                // Terjual = Dititip - Sisa Fisik
                $qtySold = max(0, $item->qty_dropped - $qtyReturned);

                $subtotalPayable = $qtySold * $item->cost_price;
                $subtotalProfit = $qtySold * ($item->selling_price - $item->cost_price);

                $item->update([
                    'qty_sold'         => $qtySold,
                    'qty_returned'     => $qtyReturned,
                    'subtotal_payable' => $subtotalPayable,
                    'subtotal_profit'  => $subtotalProfit,
                ]);

                $totalSold += $qtySold;
                $totalReturned += $qtyReturned;
                $totalPayable += $subtotalPayable;
                $totalProfit += $subtotalProfit;

                // Retur sisa jajan: kurangi stok fisik yang dibawa pulang penitip
                if ($item->product && $qtyReturned > 0) {
                    $item->product->decrement('stock_physical', min((float)$item->product->stock_physical, $qtyReturned));
                }
            }

            // Pilih kasir/cashbox
            $cashbox = null;
            if (!empty($validated['cashbox_id'])) {
                $cashbox = Cashbox::find($validated['cashbox_id']);
            }
            if (!$cashbox) {
                $cashbox = Cashbox::where('is_default', true)->first() ?? Cashbox::first();
            }

            // Selesaikan batch
            $batch->update([
                'total_qty_sold'       => $totalSold,
                'total_qty_returned'   => $totalReturned,
                'total_payable'        => $totalPayable,
                'total_canteen_profit' => $totalProfit,
                'cashbox_id'           => $cashbox ? $cashbox->id : null,
                'settlement_date'      => Carbon::now(),
                'status'               => 'settled',
                'notes'                => $validated['notes'] ?? $batch->notes,
            ]);

            // Pengeluaran Kas untuk Bayar Hak Penitip
            if ($totalPayable > 0 && $cashbox) {
                $cashbox->decrement('balance', $totalPayable);

                CashTransaction::create([
                    'cashbox_id'       => $cashbox->id,
                    'user_id'          => $user->id,
                    'type'             => 'out',
                    'category'         => 'Pembayaran Konsinyasi',
                    'amount'           => $totalPayable,
                    'transaction_date' => Carbon::now()->toDateString(),
                    'reference_type'   => 'consignment_batch',
                    'reference_id'     => $batch->id,
                    'description'      => "Pelunasan Titipan {$batch->batch_number} - {$batch->consignor->name} (Terjual: {$totalSold} pcs)",
                ]);
            }

            return redirect()->back()->with([
                'success' => "Pelunasan titipan {$batch->batch_number} selesai. Total dibayar ke penitip: Rp " . number_format($totalPayable, 0, ',', '.'),
                'settled_batch_id' => $batch->id,
            ]);
        });
    }

    public function storeConsignor(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $consignor = Consignor::create([
            'name'      => $validated['name'],
            'phone'     => $validated['phone'] ?? null,
            'notes'     => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Penitip {$consignor->name} berhasil didaftarkan.");
    }

    public function updateConsignor(Request $request, $id)
    {
        $consignor = Consignor::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:50',
            'notes'     => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $consignor->update($validated);

        return redirect()->back()->with('success', "Data penitip {$consignor->name} berhasil diperbarui.");
    }

    public function destroyConsignor($id)
    {
        $consignor = Consignor::withCount('batches')->findOrFail($id);

        if ($consignor->batches_count > 0) {
            $consignor->update(['is_active' => false]);
            return redirect()->back()->with('success', "Penitip dinonaktifkan karena sudah memiliki riwayat titipan.");
        }

        $consignor->delete();
        return redirect()->back()->with('success', 'Penitip berhasil dihapus.');
    }

    public function destroyBatch($id)
    {
        $batch = ConsignmentBatch::with(['items.product'])->findOrFail($id);

        return DB::transaction(function () use ($batch) {
            // Jika batch masih aktif (belum settled), kembalikan stok fisik yang tadi ditambahkan saat penerimaan
            if ($batch->status === 'active') {
                foreach ($batch->items as $item) {
                    if ($item->product && $item->qty_dropped > 0) {
                        $item->product->decrement('stock_physical', min((float)$item->product->stock_physical, (float)$item->qty_dropped));
                    }
                }
            }

            $batchNumber = $batch->batch_number;
            $batch->delete();

            return redirect()->back()->with('success', "Batch titipan {$batchNumber} berhasil dihapus dan stok fisik POS telah dikembalikan.");
        });
    }

    public function storeConsignmentProduct(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'nullable|exists:categories,id',
            'consignor_id'   => 'nullable|exists:consignors,id',
            'cost_price'     => 'required|numeric|min:0',
            'price_retail'   => 'required|numeric|min:0',
            'price_employee' => 'nullable|numeric|min:0',
            'barcode'        => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($validated) {
            $defaultCategory = Category::where('name', 'like', '%snack%')->first()
                ?? Category::where('name', 'like', '%makanan%')->first()
                ?? Category::first();

            $sku = 'CSG-' . strtoupper(Str::random(6));

            $product = Product::create([
                'name'           => trim($validated['name']),
                'sku'            => $sku,
                'barcode'        => !empty($validated['barcode']) ? trim($validated['barcode']) : null,
                'category_id'    => $validated['category_id'] ?? ($defaultCategory ? $defaultCategory->id : 1),
                'consignor_id'   => $validated['consignor_id'] ?? null,
                'is_consignment' => true,
                'stock_physical' => 0,
                'stock_booked'   => 0,
            ]);

            ProductUnit::create([
                'product_id'       => $product->id,
                'unit_name'        => 'Pcs',
                'conversion_ratio' => 1,
                'cost_price'       => $validated['cost_price'],
                'price_retail'     => $validated['price_retail'],
                'price_employee'   => $validated['price_employee'] ?? 0,
                'is_base_unit'     => true,
            ]);

            return redirect()->back()->with('success', "Jajan konsinyasi '{$product->name}' berhasil ditambahkan ke katalog!");
        });
    }

    public function updateConsignmentProduct(Request $request, $id)
    {
        $product = Product::with('baseUnit')->findOrFail($id);

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'nullable|exists:categories,id',
            'consignor_id'   => 'nullable|exists:consignors,id',
            'cost_price'     => 'required|numeric|min:0',
            'price_retail'   => 'required|numeric|min:0',
            'price_employee' => 'nullable|numeric|min:0',
            'barcode'        => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($product, $validated) {
            $product->update([
                'name'           => trim($validated['name']),
                'barcode'        => !empty($validated['barcode']) ? trim($validated['barcode']) : null,
                'category_id'    => $validated['category_id'] ?? $product->category_id,
                'consignor_id'   => $validated['consignor_id'] ?? null,
                'is_consignment' => true,
            ]);

            $unit = $product->baseUnit ?? $product->units()->first();
            if ($unit) {
                $unit->update([
                    'cost_price'     => $validated['cost_price'],
                    'price_retail'   => $validated['price_retail'],
                    'price_employee' => $validated['price_employee'] ?? 0,
                ]);
            } else {
                ProductUnit::create([
                    'product_id'       => $product->id,
                    'unit_name'        => 'Pcs',
                    'conversion_ratio' => 1,
                    'cost_price'       => $validated['cost_price'],
                    'price_retail'     => $validated['price_retail'],
                    'price_employee'   => $validated['price_employee'] ?? 0,
                    'is_base_unit'     => true,
                ]);
            }
        });

        return redirect()->back()->with('success', "Data jajan '{$product->name}' berhasil diperbarui!");
    }

    public function destroyConsignmentProduct($id)
    {
        $product = Product::findOrFail($id);

        $isUsed = ConsignmentItem::where('product_id', $id)->exists() 
            || DB::table('sale_items')->where('product_id', $id)->exists();

        if ($isUsed) {
            if ($product->stock_physical > 0) {
                return redirect()->back()->with('error', "Jajan '{$product->name}' masih memiliki sisa stok fisik di POS ({$product->stock_physical} pcs). Harap habiskan atau sesuaikan stok terlebih dahulu.");
            }
            $product->is_consignment = false;
            $product->save();
            return redirect()->back()->with('success', "Jajan '{$product->name}' dinonaktifkan dari katalog konsinyasi karena memiliki riwayat transaksi.");
        }

        DB::transaction(function () use ($product) {
            $product->units()->delete();
            $product->delete();
        });

        return redirect()->back()->with('success', 'Jajan berhasil dihapus dari katalog.');
    }

    public function addItemsToBatch(Request $request, $id)
    {
        $batch = ConsignmentBatch::with(['consignor', 'items'])->findOrFail($id);

        if ($batch->status !== 'active') {
            return redirect()->back()->with('error', 'Batch titipan ini sudah diselesaikan. Tidak dapat menambah barang susulan.');
        }

        $validated = $request->validate([
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'nullable|exists:products,id',
            'items.*.custom_name'     => 'nullable|string|max:255',
            'items.*.qty_dropped'     => 'required|numeric|min:1',
            'items.*.cost_price'      => 'required|numeric|min:0',
            'items.*.selling_price'   => 'required|numeric|min:0',
            'items.*.price_employee'  => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($batch, $validated) {
            $defaultCategory = Category::where('name', 'like', '%snack%')->first()
                ?? Category::where('name', 'like', '%makanan%')->first()
                ?? Category::first();

            $totalAddedQty = 0;

            foreach ($validated['items'] as $itemData) {
                $product = null;

                if (!empty($itemData['product_id'])) {
                    $product = Product::with('baseUnit')->find($itemData['product_id']);
                    $product->is_consignment = true;
                    if (!$product->consignor_id && $batch->consignor_id) {
                        $product->consignor_id = $batch->consignor_id;
                    }
                    $product->save();

                    if ($product->baseUnit) {
                        $product->baseUnit->cost_price = $itemData['cost_price'];
                        $product->baseUnit->price_retail = $itemData['selling_price'];
                        if (isset($itemData['price_employee'])) {
                            $product->baseUnit->price_employee = (float)$itemData['price_employee'];
                        }
                        $product->baseUnit->save();
                    }
                } else if (!empty($itemData['custom_name'])) {
                    $sku = 'CSG-' . strtoupper(Str::random(6));
                    $product = Product::create([
                        'name'           => trim($itemData['custom_name']),
                        'sku'            => $sku,
                        'category_id'    => $defaultCategory ? $defaultCategory->id : 1,
                        'consignor_id'   => $batch->consignor_id,
                        'is_consignment' => true,
                        'stock_physical' => 0,
                        'stock_booked'   => 0,
                    ]);

                    ProductUnit::create([
                        'product_id'       => $product->id,
                        'unit_name'        => 'Pcs',
                        'conversion_ratio' => 1,
                        'cost_price'       => $itemData['cost_price'],
                        'price_retail'     => $itemData['selling_price'],
                        'price_employee'   => $itemData['price_employee'] ?? 0,
                        'is_base_unit'     => true,
                    ]);

                    $product->load('baseUnit');
                }

                if ($product) {
                    $qty = (float)$itemData['qty_dropped'];
                    $totalAddedQty += $qty;

                    // Tambah stok fisik POS
                    $product->increment('stock_physical', $qty);

                    // Cek apakah item produk ini sudah ada di batch ini
                    $existingItem = ConsignmentItem::where('consignment_batch_id', $batch->id)
                        ->where('product_id', $product->id)
                        ->first();

                    if ($existingItem) {
                        $existingItem->increment('qty_dropped', $qty);
                        $existingItem->update([
                            'cost_price'     => $itemData['cost_price'],
                            'selling_price'  => $itemData['selling_price'],
                            'price_employee' => $itemData['price_employee'] ?? $existingItem->price_employee,
                        ]);
                    } else {
                        $productUnit = $product->baseUnit ?? $product->units()->first();
                        ConsignmentItem::create([
                            'consignment_batch_id' => $batch->id,
                            'product_id'           => $product->id,
                            'product_unit_id'      => $productUnit ? $productUnit->id : null,
                            'qty_dropped'          => $qty,
                            'qty_sold'             => 0,
                            'qty_returned'         => 0,
                            'cost_price'           => $itemData['cost_price'],
                            'selling_price'        => $itemData['selling_price'],
                            'price_employee'       => $itemData['price_employee'] ?? 0,
                            'subtotal_payable'     => 0,
                            'subtotal_profit'      => 0,
                        ]);
                    }
                }
            }

            $batch->increment('total_qty_dropped', $totalAddedQty);

            return redirect()->back()->with('success', "Berhasil menambahkan {$totalAddedQty} pcs jajan susulan ke batch {$batch->batch_number} ({$batch->consignor->name})! Stok POS telah diperbarui.");
        });
    }

    public function mergeBatches(Request $request)
    {
        $validated = $request->validate([
            'target_batch_id' => 'required|exists:consignment_batches,id',
            'source_batch_id' => 'required|exists:consignment_batches,id|different:target_batch_id',
        ]);

        return DB::transaction(function () use ($validated) {
            $target = ConsignmentBatch::with(['items', 'consignor'])->findOrFail($validated['target_batch_id']);
            $source = ConsignmentBatch::with(['items', 'consignor'])->findOrFail($validated['source_batch_id']);

            if ($target->status !== 'active' || $source->status !== 'active') {
                return redirect()->back()->with('error', 'Hanya batch titipan berstatus aktif yang dapat digabungkan.');
            }

            if ($target->consignor_id !== $source->consignor_id) {
                return redirect()->back()->with('error', 'Penggabungan batch hanya dapat dilakukan untuk penitip yang sama.');
            }

            foreach ($source->items as $sourceItem) {
                $existing = ConsignmentItem::where('consignment_batch_id', $target->id)
                    ->where('product_id', $sourceItem->product_id)
                    ->first();

                if ($existing) {
                    $existing->increment('qty_dropped', $sourceItem->qty_dropped);
                    $sourceItem->delete();
                } else {
                    $sourceItem->consignment_batch_id = $target->id;
                    $sourceItem->save();
                }
            }

            $target->increment('total_qty_dropped', $source->total_qty_dropped);
            if (!empty($source->notes)) {
                $target->notes = trim(($target->notes ? $target->notes . ' | ' : '') . $source->notes);
                $target->save();
            }

            // Hapus source batch (tanpa kurangi stok fisik POS, karena stok sudah ditambahkan saat pembuatan batch)
            $sourceNumber = $source->batch_number;
            $source->delete();

            return redirect()->back()->with('success', "Batch titipan {$sourceNumber} berhasil digabungkan ke {$target->batch_number} ({$target->consignor->name})!");
        });
    }

    public function destroyBatchItem($batchId, $itemId)
    {
        $batch = ConsignmentBatch::with('items')->findOrFail($batchId);

        if ($batch->status !== 'active') {
            return redirect()->back()->with('error', 'Batch titipan ini sudah diselesaikan. Tidak dapat menghapus item.');
        }

        $item = ConsignmentItem::where('consignment_batch_id', $batch->id)
            ->with('product')
            ->findOrFail($itemId);

        return DB::transaction(function () use ($batch, $item) {
            $productName = $item->product ? $item->product->name : 'Jajan';
            $qty = (float)$item->qty_dropped;

            // Kurangi stok fisik kasir POS
            if ($item->product && $qty > 0) {
                $item->product->decrement('stock_physical', min((float)$item->product->stock_physical, $qty));
            }

            // Kurangi total pcs pada batch
            $batch->decrement('total_qty_dropped', min((float)$batch->total_qty_dropped, $qty));

            // Hapus item
            $item->delete();

            // Jika semua item di batch sudah habis, hapus batch-nya sekalian
            if ($batch->items()->count() === 0) {
                $batchNumber = $batch->batch_number;
                $batch->delete();
                return redirect()->back()->with('success', "Item '{$productName}' telah dihapus dan sesi titipan {$batchNumber} ditutup karena sudah tidak memiliki jajan.");
            }

            return redirect()->back()->with('success', "Item '{$productName}' ({$qty} pcs) berhasil dihapus dari titipan dan stok fisik POS telah dikurangi kembali.");
        });
    }

    public function updateBatchItem(Request $request, $batchId, $itemId)
    {
        $batch = ConsignmentBatch::with('items')->findOrFail($batchId);

        if ($batch->status !== 'active') {
            return redirect()->back()->with('error', 'Batch titipan ini sudah diselesaikan. Tidak dapat mengubah item.');
        }

        $item = ConsignmentItem::where('consignment_batch_id', $batch->id)
            ->with('product.baseUnit')
            ->findOrFail($itemId);

        $validated = $request->validate([
            'qty_dropped'     => 'required|numeric|min:1',
            'cost_price'      => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'price_employee'  => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($batch, $item, $validated) {
            $oldQty = (float)$item->qty_dropped;
            $newQty = (float)$validated['qty_dropped'];
            $diffQty = $newQty - $oldQty;

            // Sesuaikan stok fisik produk di POS jika ada perubahan qty
            if ($item->product && $diffQty != 0) {
                if ($diffQty > 0) {
                    $item->product->increment('stock_physical', $diffQty);
                } else {
                    $item->product->decrement('stock_physical', min((float)$item->product->stock_physical, abs($diffQty)));
                }
            }

            // Sesuaikan total pcs pada batch
            if ($diffQty != 0) {
                if ($diffQty > 0) {
                    $batch->increment('total_qty_dropped', $diffQty);
                } else {
                    $batch->decrement('total_qty_dropped', min((float)$batch->total_qty_dropped, abs($diffQty)));
                }
            }

            // Update item
            $item->update([
                'qty_dropped'    => $newQty,
                'cost_price'     => $validated['cost_price'],
                'selling_price'  => $validated['selling_price'],
                'price_employee' => $validated['price_employee'] ?? $item->price_employee,
            ]);

            // Update juga base unit produk bila ada
            if ($item->product && $item->product->baseUnit) {
                $item->product->baseUnit->update([
                    'cost_price'     => $validated['cost_price'],
                    'price_retail'   => $validated['selling_price'],
                    'price_employee' => $validated['price_employee'] ?? $item->product->baseUnit->price_employee,
                ]);
            }

            $productName = $item->product ? $item->product->name : 'Jajan';

            return redirect()->back()->with('success', "Item '{$productName}' berhasil diperbarui! Stok POS telah disesuaikan.");
        });
    }
}
