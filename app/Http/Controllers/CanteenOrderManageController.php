<?php

namespace App\Http\Controllers;

use App\Models\CanteenOrder;
use App\Models\Cashbox;
use App\Models\EmployeeReceivable;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CanteenOrderManageController extends Controller
{
    /**
     * Layar Monitor Pesanan Online Karyawan untuk Kasir / Dapur
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');

        $query = CanteenOrder::with(['employee', 'items.product', 'items.unit'])
            ->orderBy('created_at', 'desc');

        if ($status === 'active') {
            $query->whereIn('status', ['pending', 'confirmed', 'preparing', 'on_delivery', 'ready_for_pickup']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'pending'   => CanteenOrder::where('status', 'pending')->count(),
            'preparing' => CanteenOrder::whereIn('status', ['confirmed', 'preparing'])->count(),
            'delivering'=> CanteenOrder::whereIn('status', ['on_delivery', 'ready_for_pickup'])->count(),
            'completed' => CanteenOrder::where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];

        return Inertia::render('CanteenOrders/Index', [
            'orders'        => $orders,
            'counts'        => $counts,
            'currentStatus' => $status,
            'user'          => Auth::user(),
        ]);
    }

    /**
     * Endpoint API ringan untuk cek pesanan baru (dipakai suara notifikasi kasir)
     */
    public function checkPendingCount()
    {
        $pendingCount = CanteenOrder::where('status', 'pending')->count();
        $latestPending = CanteenOrder::where('status', 'pending')
            ->latest()
            ->first(['id', 'order_number', 'recipient_name', 'delivery_location', 'total_amount', 'created_at']);

        return response()->json([
            'pending_count'  => $pendingCount,
            'latest_order'   => $latestPending,
        ]);
    }

    /**
     * Update Status Alur Pesanan (Konfirmasi, Siap Diantar, dll)
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,preparing,on_delivery,ready_for_pickup,completed,cancelled',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $order = CanteenOrder::with(['items.unit', 'employee'])->findOrFail($id);
            $targetStatus = $validated['status'];

            if ($targetStatus === 'confirmed') {
                $order->update([
                    'status'       => 'confirmed',
                    'confirmed_at' => now(),
                ]);
                return back()->with('success', "Pesanan {$order->order_number} telah dikonfirmasi!");
            }

            if ($targetStatus === 'preparing') {
                $order->update(['status' => 'preparing']);
                return back()->with('success', "Pesanan {$order->order_number} sedang disiapkan!");
            }

            if (in_array($targetStatus, ['on_delivery', 'ready_for_pickup'])) {
                $order->update(['status' => $targetStatus]);
                $msg = $targetStatus === 'on_delivery' 
                    ? "Pesanan {$order->order_number} sedang diantar ke mess/ruangan!"
                    : "Pesanan {$order->order_number} siap diambil di kantin!";
                return back()->with('success', $msg);
            }

            if ($targetStatus === 'cancelled') {
                // Lepaskan booked stock
                if (in_array($order->status, ['pending', 'confirmed', 'preparing', 'on_delivery', 'ready_for_pickup'])) {
                    foreach ($order->items as $item) {
                        $product = Product::find($item->product_id);
                        if ($product && $product->track_stock) {
                            $baseQty = $item->qty * ($item->unit?->conversion_ratio ?? 1);
                            $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                        }
                    }
                }

                $order->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $validated['cancel_reason'] ?? 'Dibatalkan oleh kasir/kantin',
                    'cancelled_at'  => now(),
                ]);
                return back()->with('success', "Pesanan {$order->order_number} telah dibatalkan.");
            }

            if ($targetStatus === 'completed') {
                $user = Auth::user();
                $invoiceNumber = 'KNT-INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

                $isBon = $order->payment_method === 'bon';

                // 1. Buat Transaksi POS Resmi
                $transaction = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'cashier_id'     => $user->id,
                    'price_type'     => 'karyawan',
                    'employee_id'    => $order->employee_id,
                    'total_gross'    => $order->total_amount,
                    'discount'       => 0,
                    'total_net'      => $order->total_amount,
                    'paid_amount'    => $isBon ? 0 : $order->total_amount,
                    'change_amount'  => 0,
                    'payment_method' => $order->payment_method === 'qris' ? 'qris' : 'cash',
                    'notes'          => "Pesanan Online #{$order->order_number} ({$order->order_type_label})",
                ]);

                // 2. Buat Transaction Items & Potong Stok Fisik
                foreach ($order->items as $item) {
                    $product = Product::findOrFail($item->product_id);
                    $baseQty = $item->qty * ($item->unit?->conversion_ratio ?? 1);

                    TransactionItem::create([
                        'transaction_id'  => $transaction->id,
                        'product_id'      => $product->id,
                        'product_unit_id' => $item->product_unit_id,
                        'qty'             => $item->qty,
                        'conversion_ratio'=> $item->unit?->conversion_ratio ?? 1,
                        'unit_price'      => $item->unit_price,
                        'subtotal'        => $item->subtotal,
                        'cost_price'      => $item->unit?->cost_price ?? 0,
                        'notes'           => $item->notes,
                    ]);

                    if ($product->track_stock) {
                        $product->stock_physical = max(0, $product->stock_physical - $baseQty);
                        $product->decrement('stock_booked', min($product->stock_booked, $baseQty));
                        $product->save();
                    }
                }

                // 3. Jika Bon Karyawan -> Catat ke EmployeeReceivable
                if ($isBon) {
                    EmployeeReceivable::create([
                        'employee_id'    => $order->employee_id,
                        'transaction_id' => $transaction->id,
                        'amount'         => $order->total_amount,
                        'remaining'      => $order->total_amount,
                        'notes'          => "Pesanan Online #{$order->order_number} ({$order->delivery_location})",
                        'status'         => 'unpaid',
                    ]);
                } else {
                    // Jika Cash / QRIS -> Masuk ke Cashbox kasir
                    $cashbox = Cashbox::where('is_default', true)->first() ?? Cashbox::first();
                    if ($cashbox) {
                        $cashbox->increment('balance', $order->total_amount);
                    }
                }

                // 4. Update status order menjadi completed
                $order->update([
                    'status'         => 'completed',
                    'payment_status' => $isBon ? 'unpaid' : 'paid',
                    'completed_at'   => now(),
                    'transaction_id' => $transaction->id,
                ]);

                return back()->with('success', "Pesanan {$order->order_number} selesai dan berhasil dibukukan!");
            }

            return back();
        });
    }
}
