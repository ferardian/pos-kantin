<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'brand', 'units'])
            ->where('stock_physical', '>', 0)
            ->orWhereHas('units')
            ->get();

        $recentTransactions = Transaction::with(['cashier', 'employee', 'receivable.employee', 'items.product', 'items.unit'])
            ->latest()
            ->take(30)
            ->get();

        $employees = \App\Services\EmployeeService::getActiveEmployees();

        return Inertia::render('Pos/Index', [
            'products'           => $products,
            'recentTransactions' => $recentTransactions,
            'employees'          => $employees,
            'user'               => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty'             => 'required|numeric|min:0.01',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.subtotal'        => 'required|numeric|min:0',
            'total_gross'    => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
            'total_net'      => 'required|numeric|min:0',
            'paid_amount'    => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,qris',
            'notes'          => 'nullable|string',
            'price_type'     => 'nullable|in:umum,karyawan',
            'employee_id'    => 'nullable|exists:employees,id',
            // Piutang karyawan (kembalian dibawa)
            'employee_receivable'             => 'nullable|array',
            'employee_receivable.employee_id' => 'nullable|exists:employees,id',
            'employee_receivable.amount'      => 'nullable|numeric|min:1',
            'employee_receivable.notes'       => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $user = Auth::user();
            $invoiceNumber = 'KNT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $changeAmount = max(0, $validated['paid_amount'] - $validated['total_net']);

            $empId = !empty($validated['employee_id']) 
                ? $validated['employee_id'] 
                : (!empty($validated['employee_receivable']['employee_id']) ? $validated['employee_receivable']['employee_id'] : null);

            $priceType = !empty($validated['price_type']) 
                ? $validated['price_type'] 
                : (!empty($empId) ? 'karyawan' : 'umum');

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'cashier_id'     => $user->id,
                'price_type'     => $priceType,
                'employee_id'    => $empId,
                'total_gross'    => $validated['total_gross'],
                'discount'       => $validated['discount'] ?? 0,
                'total_net'      => $validated['total_net'],
                'paid_amount'    => $validated['paid_amount'],
                'change_amount'  => $changeAmount,
                'payment_method' => $validated['payment_method'],
                'notes'          => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $unit    = ProductUnit::findOrFail($item['product_unit_id']);
                $product = Product::findOrFail($item['product_id']);
                $baseQty = $item['qty'] * $unit->conversion_ratio;

                TransactionItem::create([
                    'transaction_id'  => $transaction->id,
                    'product_id'      => $product->id,
                    'product_unit_id' => $unit->id,
                    'qty'             => $item['qty'],
                    'conversion_ratio'=> $unit->conversion_ratio,
                    'unit_price'      => $item['unit_price'],
                    'subtotal'        => $item['subtotal'],
                    'cost_price'      => $unit->cost_price,
                ]);

                // Kurangi stok fisik
                $product->stock_physical = max(0, $product->stock_physical - $baseQty);
                $product->save();
            }

            // Catat ke kas
            $effectiveCash = (float)$validated['paid_amount'];
            if ($effectiveCash > 0) {
                $cashbox = \App\Models\Cashbox::where('is_default', true)->first()
                    ?? \App\Models\Cashbox::first();
                if ($cashbox) {
                    $cashbox->increment('balance', $validated['total_net']);
                    \App\Models\CashTransaction::create([
                        'cashbox_id'       => $cashbox->id,
                        'user_id'          => $user->id,
                        'type'             => 'in',
                        'category'         => 'Penjualan Kasir Kantin',
                        'amount'           => $validated['total_net'],
                        'transaction_date' => now()->toDateString(),
                        'reference_type'   => 'transaction',
                        'reference_id'     => $transaction->id,
                        'description'      => "Penjualan {$invoiceNumber} (" . strtoupper($validated['payment_method']) . ")",
                    ]);
                }
            }

            // Catat piutang karyawan jika ada kembalian yang dibawa
            if (!empty($validated['employee_receivable']['employee_id'])
                && !empty($validated['employee_receivable']['amount'])) {
                \App\Models\EmployeeReceivable::create([
                    'employee_id'    => $validated['employee_receivable']['employee_id'],
                    'transaction_id' => $transaction->id,
                    'amount'         => $validated['employee_receivable']['amount'],
                    'remaining'      => $validated['employee_receivable']['amount'],
                    'notes'          => $validated['employee_receivable']['notes'] ?? "Kembalian dari {$invoiceNumber}",
                    'status'         => 'unpaid',
                ]);
            }

            return redirect()->back()->with('success_transaction', [
                'id'             => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'total_net'      => $transaction->total_net,
                'paid_amount'    => $transaction->paid_amount,
                'change_amount'  => $transaction->change_amount,
                'payment_method' => $transaction->payment_method,
                'price_type'     => $transaction->price_type,
                'customer_name'  => $transaction->customer->name,
                'created_at'     => $transaction->created_at->format('d/m/Y H:i'),
            ]);
        });
    }
}
