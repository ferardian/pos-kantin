<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->month;
        $thisYear = Carbon::now()->year;
        $lastMonth = Carbon::now()->subMonth()->month;
        $lastMonthYear = Carbon::now()->subMonth()->year;

        // 1. Ringkasan Hari Ini
        $todaySales = (float) Transaction::whereDate('created_at', $today)->sum('total_net');
        $todayTransactionsCount = Transaction::whereDate('created_at', $today)->count();
        $todayItemsCount = (float) TransactionItem::whereHas('transaction', function ($q) use ($today) {
            $q->whereDate('created_at', $today);
        })->sum('qty');
        $todayAverageOrder = $todayTransactionsCount > 0 ? ($todaySales / $todayTransactionsCount) : 0;

        // 2. Perbandingan Bulanan & Pertumbuhan
        $monthSales = (float) Transaction::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->sum('total_net');

        $lastMonthSales = (float) Transaction::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->sum('total_net');

        $monthTransactionsCount = Transaction::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->count();

        $lastMonthTransactionsCount = Transaction::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->count();

        $salesGrowth = 0;
        if ($lastMonthSales > 0) {
            $salesGrowth = round((($monthSales - $lastMonthSales) / $lastMonthSales) * 100, 1);
        }

        $trxGrowth = 0;
        if ($lastMonthTransactionsCount > 0) {
            $trxGrowth = round((($monthTransactionsCount - $lastMonthTransactionsCount) / $lastMonthTransactionsCount) * 100, 1);
        }

        // 3. Tren Penjualan 7 Hari Terakhir
        $sevenDaysTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $daySales = (float) Transaction::whereDate('created_at', $dateStr)->sum('total_net');
            $dayCount = Transaction::whereDate('created_at', $dateStr)->count();

            $sevenDaysTrend[] = [
                'date' => $date->format('d/m'),
                'full_date' => $date->translatedFormat('l, d M Y'),
                'total' => $daySales,
                'count' => $dayCount,
            ];
        }

        // 4. Produk Terlaris Bulan Ini (Top 5 / Top 10)
        $topProducts = TransactionItem::select(
                'products.id',
                'products.name',
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.qty) as total_qty_sold'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereMonth('transactions.created_at', $thisMonth)
            ->whereYear('transactions.created_at', $thisYear)
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->orderByDesc('total_qty_sold')
            ->take(10)
            ->get();

        // 5. Status Penting Kantin & Keuangan
        $totalActiveDebts = (float) \App\Models\EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])->sum('remaining');
        $criticalStockCount = Product::whereColumn('stock_physical', '<=', 'min_stock')->count();
        $pendingOrdersCount = 0;

        // 6. Transaksi Kasir Terakhir
        $recentTransactions = Transaction::with(['cashier', 'items.product'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'todaySales' => $todaySales,
            'todayTransactionsCount' => $todayTransactionsCount,
            'todayItemsCount' => $todayItemsCount,
            'todayAverageOrder' => round($todayAverageOrder),
            'monthSales' => $monthSales,
            'lastMonthSales' => $lastMonthSales,
            'monthTransactionsCount' => $monthTransactionsCount,
            'salesGrowth' => $salesGrowth,
            'trxGrowth' => $trxGrowth,
            'sevenDaysTrend' => $sevenDaysTrend,
            'topProducts' => $topProducts,
            'totalActiveDebts' => $totalActiveDebts,
            'criticalStockCount' => $criticalStockCount,
            'pendingOrdersCount' => $pendingOrdersCount,
            'recentTransactions' => $recentTransactions,
            'user' => Auth::user(),
        ]);
    }
}
