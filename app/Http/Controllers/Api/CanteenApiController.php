<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CanteenOrder;
use App\Models\CanteenOrderItem;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanteenApiController extends Controller
{
    /**
     * Daftar Menu Makanan & Minuman untuk Aplikasi Karyawan
     */
    public function menu(Request $request): JsonResponse
    {
        try {
            $categories = Category::orderBy('name')->get(['id', 'name', 'slug', 'icon']);

            $query = Product::with(['category', 'units', 'baseUnit'])
                ->whereHas('units');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            }

            $products = $query->orderBy('name')->get();

            $formattedProducts = $products->map(function ($product) {
                $baseUnit = $product->baseUnit ?? $product->units->first();
                $employeePrice = (float) ($baseUnit?->price_employee > 0 
                    ? $baseUnit->price_employee 
                    : $baseUnit?->price_retail);
                $retailPrice = (float) ($baseUnit?->price_retail ?? 0);

                $stockAvailable = (float) max(0, $product->stock_physical - $product->stock_booked);
                $isReady = !$product->track_stock || ($stockAvailable > 0);

                return [
                    'id'               => $product->id,
                    'sku'              => $product->sku,
                    'barcode'          => $product->barcode,
                    'name'             => $product->name,
                    'category_id'      => $product->category_id,
                    'category_name'    => $product->category?->name ?? 'Lain-lain',
                    'image_url'        => $product->image_url,
                    'description'      => $product->description,
                    'track_stock'      => (bool) $product->track_stock,
                    'stock_physical'   => (float) $product->stock_physical,
                    'stock_available'  => $stockAvailable,
                    'is_ready'         => $isReady,
                    'is_consignment'   => (bool) $product->is_consignment,
                    'base_unit'        => [
                        'id'             => $baseUnit?->id,
                        'unit_name'      => $baseUnit?->unit_name ?? 'Pcs',
                        'price_employee' => $employeePrice,
                        'price_retail'   => $retailPrice,
                        'has_discount'   => $employeePrice < $retailPrice && $retailPrice > 0,
                    ],
                    'available_units'  => $product->units->map(fn ($u) => [
                        'id'             => $u->id,
                        'unit_name'      => $u->unit_name,
                        'conversion'     => (float) $u->conversion_ratio,
                        'price_employee' => (float) ($u->price_employee > 0 ? $u->price_employee : $u->price_retail),
                        'price_retail'   => (float) $u->price_retail,
                    ]),
                ];
            });

            return response()->json([
                'success'    => true,
                'categories' => $categories,
                'data'       => $formattedProducts,
            ]);
        } catch (\Throwable $e) {
            Log::error('CanteenApiController::menu error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil menu kantin: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lokasi Pengantaran Populer di Lingkungan RSIA
     */
    public function locations(): JsonResponse
    {
        $locations = [
            'Mess Putra',
            'Mess Putri',
            'Ruang Rawat Inap (Rana)',
            'Poli Rawat Jalan',
            'IGD',
            'VK / Kamar Bersalin',
            'Ruang Operasi (OK)',
            'Ruang Perinatologi',
            'Farmasi',
            'Laboratorium',
            'Radiologi',
            'Kantor SDI / Manajemen',
            'Ruang IT',
            'Dapur Gizi',
            'Pos Satpam',
            'Lainnya',
        ];

        return response()->json([
            'success' => true,
            'data'    => $locations,
        ]);
    }

    /**
     * Submit Pesanan Baru Karyawan
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik'                 => 'required|string|max:50',
            'name'                => 'nullable|string|max:100',
            'department'          => 'nullable|string|max:100',
            'phone'               => 'nullable|string|max:30',
            'order_type'          => 'required|in:delivery,pickup',
            'delivery_location'   => 'nullable|string|max:255',
            'payment_method'      => 'required|in:bon,qris,cash',
            'notes'               => 'nullable|string|max:500',
            'items'               => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty'             => 'required|numeric|min:1',
            'items.*.notes'           => 'nullable|string|max:255',
        ]);

        if ($validated['order_type'] === 'delivery' && empty($validated['delivery_location'])) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi pengantaran (Mess / Ruangan & Kamar) wajib diisi untuk opsi antar.',
            ], 422);
        }

        try {
            return DB::transaction(function () use ($validated, $request) {
                // Cari atau buat profil Employee lokal berdasarkan NIK
                $employee = Employee::firstOrCreate(
                    ['nik' => trim($validated['nik'])],
                    [
                        'name'       => $validated['name'] ?? 'Karyawan (' . $validated['nik'] . ')',
                        'department' => $validated['department'] ?? '-',
                        'phone'      => $validated['phone'] ?? null,
                        'is_active'  => true,
                    ]
                );

                // Update info jika ada nama/department baru yang dikirimkan
                if (!empty($validated['name']) && $employee->name !== $validated['name']) {
                    $employee->update([
                        'name'       => $validated['name'],
                        'department' => $validated['department'] ?? $employee->department,
                        'phone'      => $validated['phone'] ?? $employee->phone,
                    ]);
                }

                $orderNumber = 'KNT-ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $totalItems  = 0;
                $totalAmount = 0;
                $orderItemsData = [];

                foreach ($validated['items'] as $itemInput) {
                    $product = Product::findOrFail($itemInput['product_id']);
                    $unit    = ProductUnit::where('id', $itemInput['product_unit_id'])
                        ->where('product_id', $product->id)
                        ->firstOrFail();

                    $qty = (float) $itemInput['qty'];
                    $baseQty = $qty * ($unit->conversion_ratio ?? 1);

                    // Validasi stok jika produk melacak stok
                    if ($product->track_stock) {
                        $available = (float) max(0, $product->stock_physical - $product->stock_booked);
                        if ($available < $baseQty) {
                            return response()->json([
                                'success' => false,
                                'message' => "Stok {$product->name} tidak mencukupi (Tersisa: {$available}).",
                            ], 422);
                        }
                    }

                    // Gunakan harga karyawan (atau harga retail jika harga karyawan kosong)
                    $price = (float) ($unit->price_employee > 0 ? $unit->price_employee : $unit->price_retail);
                    $subtotal = $qty * $price;

                    $totalItems  += $qty;
                    $totalAmount += $subtotal;

                    $orderItemsData[] = [
                        'product'   => $product,
                        'unit'      => $unit,
                        'qty'       => $qty,
                        'base_qty'  => $baseQty,
                        'price'     => $price,
                        'subtotal'  => $subtotal,
                        'notes'     => $itemInput['notes'] ?? null,
                    ];
                }

                $canteenOrder = CanteenOrder::create([
                    'order_number'      => $orderNumber,
                    'employee_id'       => $employee->id,
                    'order_type'        => $validated['order_type'],
                    'delivery_location' => $validated['order_type'] === 'delivery' ? $validated['delivery_location'] : null,
                    'recipient_name'    => $employee->name,
                    'recipient_phone'   => $employee->phone,
                    'payment_method'    => $validated['payment_method'],
                    'payment_status'    => 'unpaid',
                    'status'            => 'pending',
                    'total_items'       => $totalItems,
                    'total_amount'      => $totalAmount,
                    'notes'             => $validated['notes'] ?? null,
                ]);

                foreach ($orderItemsData as $itemData) {
                    CanteenOrderItem::create([
                        'canteen_order_id' => $canteenOrder->id,
                        'product_id'       => $itemData['product']->id,
                        'product_unit_id'  => $itemData['unit']->id,
                        'product_name'     => $itemData['product']->name,
                        'unit_name'        => $itemData['unit']->unit_name,
                        'qty'              => $itemData['qty'],
                        'unit_price'       => $itemData['price'],
                        'subtotal'         => $itemData['subtotal'],
                        'notes'            => $itemData['notes'],
                    ]);

                    // Booking stok agar tidak bentrok dengan transaksi kasir langsung
                    if ($itemData['product']->track_stock) {
                        $itemData['product']->increment('stock_booked', $itemData['base_qty']);
                    }
                }

                $canteenOrder->load(['items', 'employee']);

                return response()->json([
                    'success' => true,
                    'message' => "Pesanan {$orderNumber} berhasil dikirim ke Kantin RSIA!",
                    'data'    => $canteenOrder,
                ], 201);
            });
        } catch (\Throwable $e) {
            Log::error('CanteenApiController::createOrder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Riwayat Pesanan Karyawan
     */
    public function myOrders(Request $request): JsonResponse
    {
        $request->validate([
            'nik' => 'required|string',
        ]);

        try {
            $employee = Employee::where('nik', trim($request->nik))->first();
            if (!$employee) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                ]);
            }

            $query = CanteenOrder::with(['items'])
                ->where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'data'    => $orders,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat pesanan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail & Live Tracking Status Pesanan
     */
    public function showOrder($orderNumber): JsonResponse
    {
        try {
            $order = CanteenOrder::with(['items', 'employee', 'transaction'])
                ->where('order_number', $orderNumber)
                ->orWhere('id', $orderNumber)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data'    => $order,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }
    }

    /**
     * Batalkan Pesanan (Hanya jika masih berstatus pending)
     */
    public function cancelOrder(Request $request, $orderNumber): JsonResponse
    {
        try {
            return DB::transaction(function () use ($orderNumber, $request) {
                $order = CanteenOrder::with('items.unit')->where('order_number', $orderNumber)->firstOrFail();

                if ($order->status !== 'pending') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pesanan tidak dapat dibatalkan karena sudah dalam proses persiapan oleh kantin.',
                    ], 422);
                }

                // Kembalikan stok yang sempat di-book
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->track_stock) {
                        $baseQty = $item->qty * ($item->unit->conversion_ratio ?? 1);
                        $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                    }
                }

                $order->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $request->input('reason', 'Dibatalkan oleh pemesan'),
                    'cancelled_at'  => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Pesanan {$order->order_number} berhasil dibatalkan.",
                    'data'    => $order,
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan pesanan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
