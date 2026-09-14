<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    // Toko/Kasir view of Sales Orders
    public function index()
    {
        $orders = SalesOrder::with(['sales', 'customer', 'items.product.units', 'items.unit'])
            ->orderBy('created_at', 'desc')
            ->get();
        $products = Product::with(['category', 'brand', 'units'])->orderBy('name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();

        return Inertia::render('SalesOrders/Index', [
            'orders' => $orders,
            'products' => $products,
            'customers' => $customers,
            'user' => Auth::user(),
            'settings' => \App\Models\Setting::getSettings(),
        ]);
    }

    // Mobile Sales screen
    public function mobileSales()
    {
        $user = Auth::user();
        $products = Product::with(['category', 'brand', 'units'])->orderBy('name', 'asc')->get();
        $customers = Customer::all();
        $myOrders = SalesOrder::with(['customer', 'items.product', 'items.unit'])
            ->where('sales_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('SalesOrders/MobileSales', [
            'products' => $products,
            'customers' => $customers,
            'myOrders' => $myOrders,
            'user' => $user,
        ]);
    }

    // Store new Sales Order from Mobile Sales
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_date' => 'nullable|date',
            'payment_type' => 'required|in:cash,transfer,tempo',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = Auth::user();
            $soNumber = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $totalAmount = array_sum(array_column($validated['items'], 'subtotal'));

            $salesOrder = SalesOrder::create([
                'so_number' => $soNumber,
                'sales_id' => $user->id,
                'customer_id' => $validated['customer_id'],
                'order_date' => now()->toDateString(),
                'delivery_date' => $validated['delivery_date'] ?? now()->addDays(1)->toDateString(),
                'total_amount' => $totalAmount,
                'payment_type' => $validated['payment_type'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return redirect()->back()->with('success_so', [
                'so_number' => $salesOrder->so_number,
                'total_amount' => $salesOrder->total_amount,
            ]);
        });
    }

    // Update existing Sales Order (from Mobile Sales or Admin)
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_date' => 'nullable|date',
            'payment_type' => 'required|in:cash,transfer,tempo',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $user = Auth::user();
            $order = SalesOrder::with(['items.unit'])->findOrFail($id);

            // Permission check: only admin or the creator sales can edit
            if ($user->role !== 'admin' && $order->sales_id !== $user->id) {
                return back()->with('error', 'Anda tidak memiliki hak untuk mengubah pesanan ini.');
            }

            // Status check: cannot edit completed or cancelled orders
            if (in_array($order->status, ['completed', 'cancelled'])) {
                return back()->with('error', 'Pesanan yang sudah selesai atau dibatalkan tidak dapat diubah.');
            }

            // If order was already confirmed/packed, release old booked stock first
            if (in_array($order->status, ['confirmed', 'packed', 'ready', 'delivered'])) {
                foreach ($order->items as $oldItem) {
                    if ($oldItem->status !== 'out_of_stock') {
                        $baseQty = $oldItem->qty * ($oldItem->unit->conversion_ratio ?? 1);
                        $product = Product::find($oldItem->product_id);
                        if ($product) {
                            $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                        }
                    }
                }
            }

            $totalAmount = array_sum(array_column($validated['items'], 'subtotal'));

            $order->update([
                'customer_id' => $validated['customer_id'],
                'delivery_date' => $validated['delivery_date'] ?? $order->delivery_date,
                'payment_type' => $validated['payment_type'],
                'notes' => $validated['notes'] ?? null,
                'total_amount' => $totalAmount,
            ]);

            // Replace order items
            $order->items()->delete();

            foreach ($validated['items'] as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'status' => 'fulfilled',
                ]);

                // Re-book stock if order was in confirmed state
                if (in_array($order->status, ['confirmed', 'packed', 'ready', 'delivered'])) {
                    $unit = ProductUnit::find($item['product_unit_id']);
                    $baseQty = $item['qty'] * ($unit->conversion_ratio ?? 1);
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        $product->increment('stock_booked', $baseQty);
                    }
                }
            }

            return redirect()->back()->with('success_so', [
                'so_number' => $order->so_number,
                'total_amount' => $order->total_amount,
                'is_update' => true,
            ])->with('success', "Pesanan {$order->so_number} berhasil diperbarui!");
        });
    }

    // Cancel / Delete Sales Order
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $user = Auth::user();
            $order = SalesOrder::with(['items.unit'])->findOrFail($id);

            // Permission check: only admin or the creator sales can delete
            if ($user->role !== 'admin' && $order->sales_id !== $user->id) {
                return back()->with('error', 'Anda tidak memiliki hak untuk membatalkan pesanan ini.');
            }

            if (in_array($order->status, ['completed'])) {
                return back()->with('error', 'Pesanan yang sudah selesai (faktur terbit) tidak dapat dihapus.');
            }

            // Release booked stock if order was confirmed
            if (in_array($order->status, ['confirmed', 'packed', 'ready', 'delivered'])) {
                foreach ($order->items as $item) {
                    if ($item->status !== 'out_of_stock') {
                        $baseQty = $item->qty * ($item->unit->conversion_ratio ?? 1);
                        $product = Product::find($item->product_id);
                        if ($product) {
                            $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                        }
                    }
                }
            }

            $soNumber = $order->so_number;
            $order->items()->delete();
            $order->delete();

            return back()->with('success', "Pesanan {$soNumber} berhasil dibatalkan dan dihapus.");
        });
    }

    // Toko confirms order & books stock
    public function confirm($id)
    {
        return DB::transaction(function () use ($id) {
            $order = SalesOrder::with('items.unit')->findOrFail($id);
            if ($order->status !== 'pending') {
                return back()->with('error', 'Status pesanan tidak valid untuk dikonfirmasi.');
            }

            foreach ($order->items as $item) {
                $baseQty = $item->qty * $item->unit->conversion_ratio;
                $product = Product::findOrFail($item->product_id);
                $product->increment('stock_booked', $baseQty);
            }

            $order->update(['status' => 'confirmed']);
            return back()->with('success', "Pesanan {$order->so_number} berhasil dikonfirmasi dan stok telah di-booking!");
        });
    }

    public function toggleItemStatus(Request $request, $orderId, $itemId)
    {
        return DB::transaction(function () use ($orderId, $itemId, $request) {
            $order = SalesOrder::with(['items.unit', 'items.product'])->findOrFail($orderId);

            if (in_array($order->status, ['completed', 'cancelled'])) {
                return back()->with('error', 'Tidak dapat mengubah status barang pada pesanan yang sudah selesai atau dibatalkan.');
            }

            $item = $order->items()->where('id', $itemId)->firstOrFail();
            $targetStatus = $request->input('status'); // 'fulfilled' or 'out_of_stock'
            if (!$targetStatus) {
                $targetStatus = ($item->status === 'out_of_stock') ? 'fulfilled' : 'out_of_stock';
            }

            if ($item->status === $targetStatus) {
                return back();
            }

            $baseQty = $item->qty * ($item->unit->conversion_ratio ?? 1);
            $product = Product::find($item->product_id);

            if ($targetStatus === 'out_of_stock') {
                // Releasing booked stock
                if (in_array($order->status, ['confirmed', 'processing', 'packed', 'ready', 'delivered'])) {
                    if ($product) {
                        $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                    }
                }
                $item->status = 'out_of_stock';
                $item->fulfillment_note = $request->input('note', 'Stok fisik kosong saat picking gudang');
                $item->save();
                $msg = "Barang {$item->product?->name} ditandai STOK KOSONG. Total tagihan nota disesuaikan!";
            } else {
                // Restoring to fulfilled
                if (in_array($order->status, ['confirmed', 'processing', 'packed', 'ready', 'delivered'])) {
                    if ($product) {
                        $product->increment('stock_booked', $baseQty);
                    }
                }
                $item->status = 'fulfilled';
                $item->fulfillment_note = null;
                $item->save();
                $msg = "Barang {$item->product?->name} berhasil DIPULIHKAN ke pesanan. Total tagihan nota disesuaikan!";
            }

            // Recalculate total_amount (sum of items where status != 'out_of_stock')
            $order->load('items');
            $order->total_amount = $order->items->where('status', '!=', 'out_of_stock')->sum('subtotal');
            $order->save();

            return back()->with('success', $msg);
        });
    }

    public function removeItem($orderId, $itemId)
    {
        return DB::transaction(function () use ($orderId, $itemId) {
            $order = SalesOrder::with(['items.unit', 'items.product'])->findOrFail($orderId);
            
            if (in_array($order->status, ['completed', 'cancelled'])) {
                return back()->with('error', 'Tidak dapat menghapus item dari pesanan yang sudah selesai atau dibatalkan.');
            }

            if ($order->items->count() <= 1) {
                return back()->with('error', 'Pesanan harus memiliki minimal 1 barang. Jika ingin membatalkan seluruh pesanan, gunakan tombol Batalkan Pesanan.');
            }

            $item = $order->items()->where('id', $itemId)->firstOrFail();

            // Release booked stock if this order already booked stock and item wasn't out_of_stock
            if (in_array($order->status, ['confirmed', 'processing', 'packed', 'ready', 'delivered']) && $item->status !== 'out_of_stock') {
                $baseQty = $item->qty * ($item->unit->conversion_ratio ?? 1);
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                }
            }

            $productName = $item->product?->name ?? 'Barang';
            $item->delete();

            // Recalculate total_amount
            $order->load('items');
            $order->total_amount = $order->items->where('status', '!=', 'out_of_stock')->sum('subtotal');
            $order->save();

            return back()->with('success', "Barang {$productName} berhasil dihapus dari pesanan {$order->so_number}. Total tagihan nota disesuaikan!");
        });
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,processing,packed,ready,delivered,completed,cancelled',
            'checked_item_ids' => 'nullable|array',
            'checked_item_ids.*' => 'integer',
        ]);

        return DB::transaction(function () use ($id, $validated, $request) {
            $order = SalesOrder::with(['items.unit', 'items.product'])->findOrFail($id);
            $oldStatus = $order->status;
            $newStatus = $validated['status'];

            // Map UI aliases to DB enum values
            if ($newStatus === 'processing') $newStatus = 'confirmed';
            if ($newStatus === 'ready') $newStatus = 'packed';

            // If checked_item_ids provided (from picking slip confirmation)
            if ($request->has('checked_item_ids')) {
                $checkedIds = array_map('intval', $request->input('checked_item_ids', []));
                
                foreach ($order->items as $item) {
                    $baseQty = $item->qty * ($item->unit->conversion_ratio ?? 1);
                    $product = Product::find($item->product_id);

                    if (!in_array($item->id, $checkedIds)) {
                        // Unchecked => mark as out_of_stock (NOT deleted, so can be recovered if accidental)
                        if ($item->status !== 'out_of_stock') {
                            if (in_array($oldStatus, ['confirmed', 'processing', 'packed', 'ready', 'delivered'])) {
                                if ($product) {
                                    $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                                }
                            }
                            $item->status = 'out_of_stock';
                            $item->fulfillment_note = 'Stok fisik kosong saat picking gudang';
                            $item->save();
                        }
                    } else {
                        // Checked => ensure status is fulfilled
                        if ($item->status === 'out_of_stock') {
                            if (in_array($oldStatus, ['confirmed', 'processing', 'packed', 'ready', 'delivered'])) {
                                if ($product) {
                                    $product->increment('stock_booked', $baseQty);
                                }
                            }
                            $item->status = 'fulfilled';
                            $item->fulfillment_note = null;
                            $item->save();
                        }
                    }
                }

                // Recalculate total_amount
                $order->load('items');
                $order->total_amount = $order->items->where('status', '!=', 'out_of_stock')->sum('subtotal');
            }

            // If cancelling a confirmed/packed/delivered order, release booked stock only for active items
            if ($newStatus === 'cancelled' && in_array($oldStatus, ['confirmed', 'packed', 'delivered'])) {
                foreach ($order->items as $item) {
                    if ($item->status !== 'out_of_stock') {
                        $baseQty = $item->qty * $item->unit->conversion_ratio;
                        $product = Product::findOrFail($item->product_id);
                        $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                    }
                }
            }

            // If completing an order, create official Transaction record, deduct physical stock and release booked stock
            if ($newStatus === 'completed' && in_array($oldStatus, ['pending', 'confirmed', 'packed', 'delivered'])) {
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $isTempo = $order->payment_type === 'tempo';
                
                $transaction = \App\Models\Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'cashier_id' => Auth::id(),
                    'customer_id' => $order->customer_id,
                    'total_gross' => $order->total_amount,
                    'discount' => 0,
                    'total_net' => $order->total_amount,
                    'paid_amount' => $isTempo ? 0 : $order->total_amount,
                    'change_amount' => 0,
                    'payment_method' => $order->payment_type,
                    'payment_status' => $isTempo ? 'tempo' : 'lunas',
                    'due_date' => $isTempo ? ($order->delivery_date ?? now()->addDays(14)->toDateString()) : null,
                    'notes' => "Faktur Penjualan dari Pesanan Sales {$order->so_number}",
                ]);

                foreach ($order->items as $item) {
                    // Only process fulfilled items for official transaction and physical deduction
                    if ($item->status === 'out_of_stock') {
                        continue;
                    }

                    $baseQty = $item->qty * $item->unit->conversion_ratio;
                    $product = Product::findOrFail($item->product_id);
                    
                    \App\Models\TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'product_unit_id' => $item->product_unit_id,
                        'qty' => $item->qty,
                        'conversion_ratio' => $item->unit->conversion_ratio,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'cost_price' => $item->unit->cost_price ?? 0,
                    ]);

                    if (in_array($oldStatus, ['confirmed', 'packed', 'delivered'])) {
                        $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                    }
                    $product->decrement('stock_physical', min($product->stock_physical, $baseQty));
                }

                // If tempo payment, create Debt record and update customer balance
                if ($isTempo && !empty($order->customer_id)) {
                    \App\Models\Debt::create([
                        'customer_id' => $order->customer_id,
                        'transaction_id' => $transaction->id,
                        'total_debt' => $order->total_amount,
                        'remaining_debt' => $order->total_amount,
                        'due_date' => $order->delivery_date ?? now()->addDays(14)->toDateString(),
                        'status' => 'unpaid',
                    ]);

                    $customer = Customer::find($order->customer_id);
                    if ($customer) {
                        $customer->increment('current_debt', $order->total_amount);
                    }
                } else if (!$isTempo && $order->total_amount > 0) {
                    // Record in Cashbox
                    $defaultCashbox = \App\Models\Cashbox::where('is_default', true)->first() ?? \App\Models\Cashbox::first();
                    if ($defaultCashbox) {
                        $defaultCashbox->increment('balance', $order->total_amount);
                        \App\Models\CashTransaction::create([
                            'cashbox_id' => $defaultCashbox->id,
                            'user_id' => Auth::id(),
                            'type' => 'in',
                            'category' => 'Penjualan Sales Lapangan',
                            'amount' => $order->total_amount,
                            'transaction_date' => now()->toDateString(),
                            'reference_type' => 'transaction',
                            'reference_id' => $transaction->id,
                            'description' => "Penjualan Faktur {$invoiceNumber} dari {$order->so_number} (" . strtoupper($order->payment_type) . ")",
                        ]);
                    }
                }
            }

            $order->update(['status' => $newStatus]);
            return back()->with('success', "Pesanan {$order->so_number} berhasil diselesaikan dan Faktur resmi telah diterbitkan.");
        });
    }
}
