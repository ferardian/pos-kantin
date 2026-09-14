<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductUnit;
use App\Models\ReturnItem;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReturnController extends Controller
{
    public function index()
    {
        $returns = ProductReturn::with(['user', 'customer', 'supplier', 'items.product', 'items.unit'])
            ->latest()
            ->get();

        $products = Product::with(['units', 'category', 'brand', 'baseUnit'])
            ->orderBy('name')
            ->get();

        $customers = Customer::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        $recentTransactions = Transaction::with(['customer', 'items.product', 'items.unit'])
            ->latest()
            ->take(50)
            ->get();

        $recentSalesOrders = SalesOrder::with(['customer', 'items.product', 'items.unit'])
            ->latest()
            ->take(50)
            ->get();

        $recentGoodsReceipts = GoodsReceipt::with(['supplier', 'items.product', 'items.unit'])
            ->latest()
            ->take(50)
            ->get();

        $totalSalesReturnsThisMonth = ProductReturn::whereIn('return_type', ['sales_pos', 'sales_order'])
            ->whereMonth('return_date', now()->month)
            ->whereYear('return_date', now()->year)
            ->count();

        $totalSupplierReturnsThisMonth = ProductReturn::where('return_type', 'purchase_supplier')
            ->whereMonth('return_date', now()->month)
            ->whereYear('return_date', now()->year)
            ->count();

        $totalRefundAmountThisMonth = ProductReturn::whereMonth('return_date', now()->month)
            ->whereYear('return_date', now()->year)
            ->sum('total_refund_amount');

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'products' => $products,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'recentTransactions' => $recentTransactions,
            'recentSalesOrders' => $recentSalesOrders,
            'recentGoodsReceipts' => $recentGoodsReceipts,
            'totalSalesReturnsThisMonth' => $totalSalesReturnsThisMonth,
            'totalSupplierReturnsThisMonth' => $totalSupplierReturnsThisMonth,
            'totalRefundAmountThisMonth' => (float)$totalRefundAmountThisMonth,
            'user' => Auth::user(),
            'settings' => \App\Models\Setting::getSettings(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'return_type' => 'required|in:sales_pos,sales_order,purchase_supplier',
            'reference_number' => 'nullable|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'return_date' => 'required|date',
            'resolution_type' => 'required|in:refund_cash,debt_deduction,replacement',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_unit_id' => 'required|exists:product_units,id',
            'items.*.qty_returned' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.condition' => 'required|in:good_restock,damaged_claim',
        ]);

        return DB::transaction(function () use ($validated) {
            $dateCode = now()->format('Ymd');
            $countToday = ProductReturn::whereDate('created_at', now()->toDateString())->count() + 1;
            $returnNumber = 'RET-' . $dateCode . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $totalRefund = 0;
            foreach ($validated['items'] as $it) {
                $totalRefund += (float)$it['qty_returned'] * (float)$it['unit_price'];
            }

            $customerName = $validated['customer_name'] ?? null;
            if (!empty($validated['customer_id'])) {
                $cust = Customer::find($validated['customer_id']);
                if ($cust) $customerName = $cust->name;
            }

            $supplierName = $validated['supplier_name'] ?? null;
            if (!empty($validated['supplier_id'])) {
                $sup = Supplier::find($validated['supplier_id']);
                if ($sup) $supplierName = $sup->name;
            }

            $ret = ProductReturn::create([
                'return_number' => $returnNumber,
                'return_type' => $validated['return_type'],
                'reference_number' => $validated['reference_number'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $customerName,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_name' => $supplierName,
                'return_date' => $validated['return_date'],
                'user_id' => Auth::id(),
                'total_refund_amount' => $totalRefund,
                'resolution_type' => $validated['resolution_type'],
                'reason' => $validated['reason'],
                'status' => 'completed',
            ]);

            foreach ($validated['items'] as $item) {
                $unit = ProductUnit::findOrFail($item['product_unit_id']);
                $product = Product::findOrFail($item['product_id']);

                $ratio = (float)$unit->conversion_ratio;
                $qtyReturned = (float)$item['qty_returned'];
                $baseQty = $qtyReturned * $ratio;
                $unitPrice = (float)$item['unit_price'];
                $subtotal = $qtyReturned * $unitPrice;

                ReturnItem::create([
                    'return_id' => $ret->id,
                    'product_id' => $product->id,
                    'product_unit_id' => $unit->id,
                    'qty_returned' => $qtyReturned,
                    'conversion_ratio' => $ratio,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'condition' => $item['condition'],
                ]);

                // Stock handling based on return type and condition
                if (in_array($validated['return_type'], ['sales_pos', 'sales_order'])) {
                    if ($item['condition'] === 'good_restock') {
                        $product->increment('stock_physical', $baseQty);
                        StockAdjustment::create([
                            'product_id' => $product->id,
                            'user_id' => Auth::id(),
                            'type' => 'in',
                            'qty_change' => $baseQty,
                            'reason' => "Retur Pelanggan [{$returnNumber}] Ref: {$validated['reference_number']} ({$validated['reason']})",
                        ]);
                    }
                } elseif ($validated['return_type'] === 'purchase_supplier') {
                    $product->decrement('stock_physical', $baseQty);
                    StockAdjustment::create([
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'type' => 'out',
                        'qty_change' => -$baseQty,
                        'reason' => "Retur ke Supplier [{$returnNumber}] Ref: {$validated['reference_number']} ({$validated['reason']})",
                    ]);
                }
            }

            return redirect()->route('returns.index')->with('success', "Dokumen Retur {$returnNumber} berhasil diproses dan stok fisik telah disesuaikan secara otomatis.");
        });
    }
}
