<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockTransferController extends Controller
{
    public function index()
    {
        $transfers = StockTransfer::with([
            'fromLocation',
            'toLocation',
            'user',
            'items.product',
            'items.unit',
        ])
        ->latest()
        ->get();

        $locations = Location::with(['productLocations'])->get();

        $products = Product::with([
            'category',
            'brand',
            'units',
            'productLocations.location',
        ])->get();

        return Inertia::render('StockTransfers/Index', [
            'transfers' => $transfers,
            'locations' => $locations,
            'products' => $products,
            'user' => Auth::user(),
            'settings' => \App\Models\Setting::getSettings(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_unit_id' => 'nullable|exists:product_units,id',
            'items.*.qty' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = Auth::user();
            $todayStr = now()->format('Ymd');
            $countToday = StockTransfer::whereDate('created_at', now()->toDateString())->count() + 1;
            $transferNumber = 'TRF-' . $todayStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $fromLoc = Location::findOrFail($validated['from_location_id']);
            $toLoc = Location::findOrFail($validated['to_location_id']);

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_location_id' => $fromLoc->id,
                'to_location_id' => $toLoc->id,
                'user_id' => $user->id,
                'transfer_date' => $validated['transfer_date'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $conversionRatio = 1;

                if (!empty($itemData['product_unit_id'])) {
                    $unit = ProductUnit::find($itemData['product_unit_id']);
                    if ($unit) {
                        $conversionRatio = (float)$unit->conversion_ratio;
                    }
                }

                $qty = (float)$itemData['qty'];
                $baseQty = $qty * $conversionRatio;

                // 1. Kurangi stok di lokasi asal (From Location)
                $fromProdLoc = ProductLocation::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $fromLoc->id],
                    ['stock_physical' => 0, 'min_stock' => 0]
                );
                $fromProdLoc->decrement('stock_physical', $baseQty);

                // 2. Tambah stok di lokasi tujuan (To Location)
                $toProdLoc = ProductLocation::firstOrCreate(
                    ['product_id' => $product->id, 'location_id' => $toLoc->id],
                    ['stock_physical' => 0, 'min_stock' => 0]
                );
                $toProdLoc->increment('stock_physical', $baseQty);

                // 3. Simpan item transfer
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'product_unit_id' => $itemData['product_unit_id'] ?? null,
                    'qty' => $qty,
                    'conversion_ratio' => $conversionRatio,
                    'base_qty' => $baseQty,
                ]);

                // 4. Catat riwayat log audit mutasi
                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'out',
                    'qty_change' => -$baseQty,
                    'reason' => "Transfer Mutasi ({$transferNumber}) dari [{$fromLoc->name}] ke [{$toLoc->name}]",
                ]);

                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'qty_change' => +$baseQty,
                    'reason' => "Penerimaan Mutasi ({$transferNumber}) di [{$toLoc->name}] dari [{$fromLoc->name}]",
                ]);
            }

            return back()->with('success', "Transfer mutasi stok {$transferNumber} berhasil diproses.");
        });
    }

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:locations,code',
            'type' => 'required|in:warehouse,store,other',
            'address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['is_default'] = $validated['is_default'] ?? false;

        $location = Location::create($validated);

        // Auto create product_locations entries for all products
        $products = Product::all();
        foreach ($products as $p) {
            ProductLocation::firstOrCreate(
                ['product_id' => $p->id, 'location_id' => $location->id],
                ['stock_physical' => 0, 'min_stock' => 0]
            );
        }

        return back()->with('success', "Lokasi baru '{$location->name}' berhasil ditambahkan.");
    }

    public function updateLocation(Request $request, $id)
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:warehouse,store,other',
            'address' => 'nullable|string|max:255',
        ]);

        $location->update($validated);

        return back()->with('success', "Lokasi '{$location->name}' berhasil diperbarui.");
    }
}
