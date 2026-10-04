<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EmployeeReceivable;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Parse Date Range Filters
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());
        $paymentMethod = $request->query('payment_method', 'all');
        $cashierId = $request->query('cashier_id', 'all');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // 2. Base Query for Transactions in Selected Period
        $trxQuery = Transaction::whereBetween('created_at', [$startDateTime, $endDateTime]);

        if ($paymentMethod !== 'all') {
            $trxQuery->where('payment_method', $paymentMethod);
        }

        if ($cashierId !== 'all') {
            $trxQuery->where('cashier_id', $cashierId);
        }

        // Summary in Period
        $periodSalesTotal = (float) (clone $trxQuery)->sum('total_net');
        $periodTransactionsCount = (clone $trxQuery)->count();

        // Modal HPP & Profit in Period
        $periodCostTotal = (float) TransactionItem::whereHas('transaction', function ($q) use ($startDateTime, $endDateTime, $paymentMethod, $cashierId) {
            $q->whereBetween('created_at', [$startDateTime, $endDateTime]);
            if ($paymentMethod !== 'all') {
                $q->where('payment_method', $paymentMethod);
            }
            if ($cashierId !== 'all') {
                $q->where('cashier_id', $cashierId);
            }
        })
        ->join('product_units', 'transaction_items.product_unit_id', '=', 'product_units.id')
        ->selectRaw('SUM(transaction_items.qty * product_units.cost_price) as total_cost')
        ->value('total_cost') ?? 0;

        $periodProfitTotal = max(0, $periodSalesTotal - $periodCostTotal);
        $periodProfitMargin = $periodSalesTotal > 0 ? round(($periodProfitTotal / $periodSalesTotal) * 100, 1) : 0;

        // 3. Payment Method Breakdown in Period
        $cashTotal = (float) (clone $trxQuery)->where('payment_method', 'cash')->sum('total_net');
        $transferTotal = (float) (clone $trxQuery)->where('payment_method', 'transfer')->sum('total_net');
        $tempoTotal = (float) (clone $trxQuery)->where('payment_method', 'tempo')->sum('total_net');

        // 4. Lifetime / Global Financial Cards
        $totalSalesToday = (float) Transaction::whereDate('created_at', Carbon::today())->sum('total_net');
        $totalSalesMonth = (float) Transaction::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_net');
        $totalActiveDebts = (float) EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])->sum('remaining');
        $pendingOrdersCount = 0;

        // 5. Category Breakdown in Period
        $categoryBreakdown = TransactionItem::select(
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.qty) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_omset')
            )
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$startDateTime, $endDateTime])
            ->groupBy('categories.name')
            ->orderByDesc('total_omset')
            ->get();

        // 6. Brand Breakdown in Period
        $brandBreakdown = TransactionItem::select(
                'brands.name as brand_name',
                DB::raw('SUM(transaction_items.qty) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_omset')
            )
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$startDateTime, $endDateTime])
            ->groupBy('brands.name')
            ->orderByDesc('total_omset')
            ->get();

        // 7. Kasir Leaderboard
        $salesLeaderboard = User::whereIn('role', ['kasir', 'admin'])
            ->withCount(['transactions as total_orders' => function ($q) use ($startDateTime, $endDateTime) {
                $q->whereBetween('created_at', [$startDateTime, $endDateTime]);
            }])
            ->withSum(['transactions as total_revenue' => function ($q) use ($startDateTime, $endDateTime) {
                $q->whereBetween('created_at', [$startDateTime, $endDateTime]);
            }], 'total_net')
            ->get();

        // 8. Top Products in Period
        $topProducts = TransactionItem::select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                'brands.name as brand_name',
                DB::raw('SUM(transaction_items.qty) as total_qty_sold'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$startDateTime, $endDateTime])
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name', 'brands.name')
            ->orderByDesc('total_qty_sold')
            ->take(15)
            ->get();

        // 9. Detailed Sales Transactions for Period
        $salesTransactions = (clone $trxQuery)
            ->with(['cashier', 'employee', 'receivable.employee', 'items.product', 'items.unit'])
            ->latest()
            ->get()
            ->map(function ($trx) {
                $trxCost = 0;
                foreach ($trx->items as $it) {
                    $unitCost = $it->unit ? (float)$it->unit->cost_price : 0;
                    $trxCost += ((float)$it->qty * $unitCost);
                }
                $profit = max(0, (float)$trx->total_net - $trxCost);
                $margin = $trx->total_net > 0 ? round(($profit / $trx->total_net) * 100, 1) : 0;

                $trx->estimated_cost = $trxCost;
                $trx->estimated_profit = $profit;
                $trx->profit_margin = $margin;

                $employee = $trx->employee ?? $trx->receivable->first()?->employee;
                $isKaryawan = ($trx->price_type === 'karyawan') || ($employee !== null);
                $trx->customer = (object) [
                    'name' => $employee ? $employee->name : ($isKaryawan ? 'Karyawan RSIA' : 'Pelanggan Umum'),
                    'tier' => $employee ? ($employee->department ? 'Karyawan (' . $employee->department . ')' : 'Karyawan') : ($isKaryawan ? 'Karyawan' : 'Umum'),
                ];

                return $trx;
            });

        // 10. Audit Activities
        $allActivities = StockAdjustment::with(['user', 'product'])
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->latest()
            ->take(100)
            ->get()
            ->map(function ($s) {
                $typeLabel = $s->type === 'in' ? 'Penerimaan Barang Masuk (Gudang)' : ($s->type === 'out' ? 'Pengeluaran Barang (Gudang)' : 'Audit Stok Opname');
                $badge = $s->type === 'in' ? 'bg-blue-50 text-blue-800 border-blue-300' : ($s->type === 'out' ? 'bg-rose-50 text-rose-800 border-rose-300' : 'bg-purple-50 text-purple-800 border-purple-300');
                
                return [
                    'id' => 'adj-' . $s->id,
                    'timestamp' => $s->created_at->format('Y-m-d H:i:s'),
                    'user_name' => $s->user ? $s->user->name : 'Gudang',
                    'user_role' => $s->user ? $s->user->role : 'gudang',
                    'category' => 'gudang',
                    'action_title' => $typeLabel,
                    'description' => "Barang: " . ($s->product ? $s->product->name : '-') . " • Qty: " . ($s->qty_change >= 0 ? '+' : '') . $s->qty_change . " • Alasan: {$s->reason}",
                    'amount' => null,
                    'badge_class' => $badge,
                ];
            });

        // 11. Rekapitulasi Setoran Kasir & Penjualan Harian untuk Keuangan RSIA
        $settlementRaw = (clone $trxQuery)
            ->with('cashier')
            ->orderBy('created_at', 'asc')
            ->get();

        $groupedByCashierDay = $settlementRaw->groupBy(function ($t) {
            return $t->created_at->format('Y-m-d') . '_' . $t->cashier_id;
        });

        $cashierSettlements = [];
        foreach ($groupedByCashierDay as $key => $trxs) {
            $first = $trxs->first();
            $d = $first->created_at->format('Y-m-d');
            $cashier = $first->cashier;

            $cash = (float) $trxs->where('payment_method', 'cash')->sum('total_net');
            $qris = (float) $trxs->where('payment_method', 'qris')->sum('total_net');
            $transfer = (float) $trxs->where('payment_method', 'transfer')->sum('total_net');
            $tempo = (float) $trxs->where('payment_method', 'tempo')->sum('total_net');
            $gross = (float) $trxs->sum('total_gross');
            $discount = (float) $trxs->sum('discount');
            $net = (float) $trxs->sum('total_net');

            $trxIds = $trxs->pluck('id');
            $itemsSold = TransactionItem::whereIn('transaction_id', $trxIds)
                ->with(['product:id,name,sku,category_id', 'product.category:id,name', 'unit:id,unit_name'])
                ->select(
                    'product_id',
                    'product_unit_id',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('SUM(subtotal) as total_subtotal'),
                    DB::raw('AVG(unit_price) as avg_price')
                )
                ->groupBy('product_id', 'product_unit_id')
                ->get()
                ->map(function ($it) {
                    return [
                        'product_id' => $it->product_id,
                        'product_name' => $it->product ? $it->product->name : 'Item Terhapus',
                        'category_name' => $it->product && $it->product->category ? $it->product->category->name : 'Umum',
                        'unit_name' => $it->unit ? $it->unit->unit_name : 'Pcs',
                        'avg_price' => (float) $it->avg_price,
                        'total_qty' => (float) $it->total_qty,
                        'total_subtotal' => (float) $it->total_subtotal,
                    ];
                })
                ->sortByDesc('total_qty')
                ->values()
                ->all();

            $cashierSettlements[] = [
                'id' => $key,
                'date' => $d,
                'formatted_date' => Carbon::parse($d)->translatedFormat('l, d F Y'),
                'cashier_id' => $first->cashier_id,
                'cashier_name' => $cashier ? $cashier->name : 'Kasir',
                'start_time' => Carbon::parse($trxs->min('created_at'))->format('H:i'),
                'end_time' => Carbon::parse($trxs->max('created_at'))->format('H:i'),
                'transaction_count' => $trxs->count(),
                'cash_total' => $cash,
                'non_cash_total' => $qris + $transfer,
                'qris_total' => $qris,
                'transfer_total' => $transfer,
                'tempo_total' => $tempo,
                'total_gross' => $gross,
                'total_discount' => $discount,
                'total_net' => $net,
                'first_invoice' => $trxs->first()->invoice_number,
                'last_invoice' => $trxs->last()->invoice_number,
                'items_sold' => $itemsSold,
                'total_items_qty' => array_sum(array_column($itemsSold, 'total_qty')),
                'total_items_count' => count($itemsSold),
            ];
        }

        usort($cashierSettlements, function ($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['cashier_name'], $b['cashier_name']);
            }
            return strcmp($b['date'], $a['date']);
        });

        // Grouped by Day only (Daily Summaries across all cashiers)
        $groupedByDay = $settlementRaw->groupBy(function ($t) {
            return $t->created_at->format('Y-m-d');
        });

        $dailySummaries = [];
        foreach ($groupedByDay as $d => $trxs) {
            $cash = (float) $trxs->where('payment_method', 'cash')->sum('total_net');
            $qris = (float) $trxs->where('payment_method', 'qris')->sum('total_net');
            $transfer = (float) $trxs->where('payment_method', 'transfer')->sum('total_net');
            $tempo = (float) $trxs->where('payment_method', 'tempo')->sum('total_net');
            $gross = (float) $trxs->sum('total_gross');
            $discount = (float) $trxs->sum('discount');
            $net = (float) $trxs->sum('total_net');

            $cashierNames = $trxs->pluck('cashier.name')->filter()->unique()->values()->all();

            $dailySummaries[] = [
                'date' => $d,
                'formatted_date' => Carbon::parse($d)->translatedFormat('l, d F Y'),
                'cashier_names' => $cashierNames,
                'transaction_count' => $trxs->count(),
                'cash_total' => $cash,
                'non_cash_total' => $qris + $transfer,
                'qris_total' => $qris,
                'transfer_total' => $transfer,
                'tempo_total' => $tempo,
                'total_gross' => $gross,
                'total_discount' => $discount,
                'total_net' => $net,
            ];
        }

        usort($dailySummaries, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $allUsers = User::select('id', 'name', 'role')->get();

        return Inertia::render('Reports/Index', [
            'cashierSettlements' => $cashierSettlements,
            'dailySummaries' => $dailySummaries,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_method' => $paymentMethod,
                'cashier_id' => $cashierId,
            ],
            'periodSalesTotal' => $periodSalesTotal,
            'periodCostTotal' => $periodCostTotal,
            'periodProfitTotal' => $periodProfitTotal,
            'periodProfitMargin' => $periodProfitMargin,
            'periodTransactionsCount' => $periodTransactionsCount,
            'cashTotal' => $cashTotal,
            'transferTotal' => $transferTotal,
            'tempoTotal' => $tempoTotal,
            'totalSalesToday' => $totalSalesToday,
            'totalSalesMonth' => $totalSalesMonth,
            'totalActiveDebts' => $totalActiveDebts,
            'pendingOrdersCount' => $pendingOrdersCount,
            'categoryBreakdown' => $categoryBreakdown,
            'brandBreakdown' => $brandBreakdown,
            'salesLeaderboard' => $salesLeaderboard,
            'topProducts' => $topProducts,
            'salesTransactions' => $salesTransactions,
            'allActivities' => $allActivities,
            'allUsers' => $allUsers,
            'user' => Auth::user(),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());
        $paymentMethod = $request->query('payment_method', 'all');
        $cashierId = $request->query('cashier_id', 'all');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $fileName = 'Laporan_Penjualan_Kantin_RSIA_' . date('Ymd_His') . '.xls';

        $trxQuery = Transaction::whereBetween('created_at', [$startDateTime, $endDateTime]);
        if ($paymentMethod !== 'all') {
            $trxQuery->where('payment_method', $paymentMethod);
        }
        if ($cashierId !== 'all') {
            $trxQuery->where('cashier_id', $cashierId);
        }

        $transactions = $trxQuery->with(['cashier', 'receivable.employee', 'items.product', 'items.unit'])->latest()->get();
        $products = Product::with(['category', 'brand', 'baseUnit'])->get();
        $debts = EmployeeReceivable::with('employee')->whereIn('status', ['unpaid', 'partial'])->get();

        $headers = [
            "Content-Type" => "application/vnd.ms-excel; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"{$fileName}\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($transactions, $products, $debts, $startDate, $endDate) {
            $output = fopen('php://output', 'w');

            // HTML Excel Template with MSO styles
            $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan Penjualan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            $html .= '<style>';
            $html .= 'body { font-family: "Calibri", "Segoe UI", Arial, sans-serif; font-size: 11pt; }';
            $html .= '.title { font-size: 16pt; font-weight: bold; color: #166534; }';
            $html .= '.subtitle { font-size: 11pt; color: #475569; }';
            $html .= '.section-header { font-size: 13pt; font-weight: bold; color: #0f172a; margin-top: 20px; }';
            $html .= 'table { border-collapse: collapse; width: 100%; margin-bottom: 25px; }';
            $html .= 'th { background-color: #15803d; color: #ffffff; font-weight: bold; padding: 10px 8px; border: 1px solid #166534; text-align: center; }';
            $html .= 'th.sub { background-color: #334155; color: #ffffff; border: 1px solid #1e293b; }';
            $html .= 'td { padding: 7px 8px; border: 1px solid #cbd5e1; vertical-align: middle; }';
            $html .= 'tr:nth-child(even) { background-color: #f8fafc; }';
            $html .= '.num { text-align: right; mso-number-format:\"\#\,\#\#0\"; }';
            $html .= '.center { text-align: center; }';
            $html .= '.bold { font-weight: bold; }';
            $html .= '.total-row { background-color: #0f172a !important; color: #ffffff !important; font-weight: bold; }';
            $html .= '.total-row td { border: 1px solid #0f172a; color: #ffffff; }';
            $html .= '</style></head><body>';

            // Title & Meta Info
            $html .= '<div class="title">KOPERASI RSIA AISYIYAH PEKAJANGAN - LAPORAN PENJUALAN KANTIN</div>';
            $html .= '<div class="subtitle">Periode: <strong>' . date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)) . '</strong> | Tanggal Cetak: ' . date('d/m/Y H:i:s') . ' WIB</div><br>';

            // SECTION 1: TRANSAKSI PENJUALAN & MARGIN
            $html .= '<div class="section-header">1. Rekapitulasi Transaksi Penjualan & Margin Laba Kotor HPP</div>';
            $html .= '<table>';
            $html .= '<thead><tr>';
            $html .= '<th style="width: 40px;">No</th>';
            $html .= '<th style="width: 130px;">Tgl & Jam</th>';
            $html .= '<th style="width: 140px;">No Faktur</th>';
            $html .= '<th style="width: 100px;">Kasir</th>';
            $html .= '<th style="width: 140px;">Pelanggan</th>';
            $html .= '<th style="width: 100px;">Strata Harga</th>';
            $html .= '<th style="width: 320px;">Rincian Barang Terjual</th>';
            $html .= '<th style="width: 100px;">Metode Bayar</th>';
            $html .= '<th style="width: 120px;">Total Kotor (Rp)</th>';
            $html .= '<th style="width: 100px;">Diskon (Rp)</th>';
            $html .= '<th style="width: 130px;">Omset Net (Rp)</th>';
            $html .= '<th style="width: 130px;">Modal HPP (Rp)</th>';
            $html .= '<th style="width: 130px;">Laba Kotor (Rp)</th>';
            $html .= '<th style="width: 80px;">Margin %</th>';
            $html .= '</tr></thead><tbody>';

            $no = 1;
            $sumGross = 0;
            $sumDisc = 0;
            $sumNet = 0;
            $sumCost = 0;
            $sumProfit = 0;

            foreach ($transactions as $t) {
                $itemDetails = [];
                $trxCost = 0;
                foreach ($t->items as $it) {
                    $prodName = $it->product ? $it->product->name : '-';
                    $unitName = $it->unit ? $it->unit->unit_name : 'Pcs';
                    $unitCost = $it->unit ? (float)$it->unit->cost_price : 0;
                    $trxCost += ((float)$it->qty * $unitCost);
                    $itemDetails[] = "{$it->qty} {$unitName} {$prodName} (Rp " . number_format($it->subtotal, 0, ',', '.') . ")";
                }

                $itemsString = htmlspecialchars(implode('; ', $itemDetails));
                $employee = $t->receivable->first()?->employee;
                $customerName = $employee ? $employee->name : 'Pelanggan Umum';
                $tierLabel = $employee ? ($employee->department ? 'Karyawan (' . $employee->department . ')' : 'Karyawan') : 'Umum';
                $trxProfit = max(0, $t->total_net - $trxCost);
                $trxMargin = $t->total_net > 0 ? round(($trxProfit / $t->total_net) * 100, 1) : 0;

                $sumGross += $t->total_gross;
                $sumDisc += ($t->discount ?? 0);
                $sumNet += $t->total_net;
                $sumCost += $trxCost;
                $sumProfit += $trxProfit;

                $html .= '<tr>';
                $html .= '<td class="center">' . $no++ . '</td>';
                $html .= '<td class="center">' . $t->created_at->format('d/m/Y H:i') . '</td>';
                $html .= '<td class="bold">' . $t->invoice_number . '</td>';
                $html .= '<td>' . ($t->cashier ? $t->cashier->name : 'Sistem') . '</td>';
                $html .= '<td>' . htmlspecialchars($customerName) . '</td>';
                $html .= '<td class="center">' . $tierLabel . '</td>';
                $html .= '<td>' . $itemsString . '</td>';
                $html .= '<td class="center bold">' . strtoupper($t->payment_method) . '</td>';
                $html .= '<td class="num">' . $t->total_gross . '</td>';
                $html .= '<td class="num">' . ($t->discount ?? 0) . '</td>';
                $html .= '<td class="num bold" style="color: #166534;">' . $t->total_net . '</td>';
                $html .= '<td class="num">' . $trxCost . '</td>';
                $html .= '<td class="num bold" style="color: #0369a1;">' . $trxProfit . '</td>';
                $html .= '<td class="center bold">' . $trxMargin . '%</td>';
                $html .= '</tr>';
            }

            $overallMargin = $sumNet > 0 ? round(($sumProfit / $sumNet) * 100, 1) : 0;
            $html .= '<tr class="total-row">';
            $html .= '<td colspan="8" class="center bold">TOTAL KESELURUHAN</td>';
            $html .= '<td class="num bold">' . $sumGross . '</td>';
            $html .= '<td class="num bold">' . $sumDisc . '</td>';
            $html .= '<td class="num bold">' . $sumNet . '</td>';
            $html .= '<td class="num bold">' . $sumCost . '</td>';
            $html .= '<td class="num bold">' . $sumProfit . '</td>';
            $html .= '<td class="center bold">' . $overallMargin . '%</td>';
            $html .= '</tr>';
            $html .= '</tbody></table><br>';

            // SECTION 2: NILAI ASET PRODUK
            $html .= '<div class="section-header">2. Rekapitulasi Nilai Aset Stok Persediaan Barang Kantin</div>';
            $html .= '<table>';
            $html .= '<thead><tr>';
            $html .= '<th class="sub" style="width: 40px;">No</th>';
            $html .= '<th class="sub" style="width: 100px;">Kode SKU</th>';
            $html .= '<th class="sub" style="width: 120px;">Barcode</th>';
            $html .= '<th class="sub" style="width: 250px;">Nama Produk</th>';
            $html .= '<th class="sub" style="width: 120px;">Kategori</th>';
            $html .= '<th class="sub" style="width: 120px;">Merk / Brand</th>';
            $html .= '<th class="sub" style="width: 90px;">Stok Fisik</th>';
            $html .= '<th class="sub" style="width: 80px;">Satuan</th>';
            $html .= '<th class="sub" style="width: 130px;">Modal HPP (Rp)</th>';
            $html .= '<th class="sub" style="width: 140px;">Total Nilai Aset (Rp)</th>';
            $html .= '</tr></thead><tbody>';

            $noProd = 1;
            $totalAssetValuation = 0;
            foreach ($products as $p) {
                $baseUnit = $p->baseUnit;
                $cost = $baseUnit ? (float)$baseUnit->cost_price : 0;
                $assetValue = (float)$p->stock_physical * $cost;
                $totalAssetValuation += $assetValue;

                $html .= '<tr>';
                $html .= '<td class="center">' . $noProd++ . '</td>';
                $html .= '<td>' . $p->sku . '</td>';
                $html .= '<td>' . ($p->barcode ?? '-') . '</td>';
                $html .= '<td class="bold">' . htmlspecialchars($p->name) . '</td>';
                $html .= '<td>' . ($p->category ? $p->category->name : '-') . '</td>';
                $html .= '<td>' . ($p->brand ? $p->brand->name : '-') . '</td>';
                $html .= '<td class="num bold">' . $p->stock_physical . '</td>';
                $html .= '<td class="center">' . ($baseUnit ? $baseUnit->unit_name : 'Pcs') . '</td>';
                $html .= '<td class="num">' . $cost . '</td>';
                $html .= '<td class="num bold" style="color: #166534;">' . $assetValue . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="total-row">';
            $html .= '<td colspan="9" class="center bold">TOTAL VALUASI NILAI ASET PERSEDIAAN</td>';
            $html .= '<td class="num bold">' . $totalAssetValuation . '</td>';
            $html .= '</tr>';
            $html .= '</tbody></table><br>';

            // SECTION 3: DAFTAR PIUTANG KARYAWAN RSIA
            $html .= '<div class="section-header">3. Daftar Bon / Piutang Karyawan RSIA Aisyiyah</div>';
            $html .= '<table>';
            $html .= '<thead><tr>';
            $html .= '<th class="sub" style="width: 40px;">No</th>';
            $html .= '<th class="sub" style="width: 200px;">Nama Karyawan</th>';
            $html .= '<th class="sub" style="width: 150px;">Unit / Departemen</th>';
            $html .= '<th class="sub" style="width: 140px;">No. Telepon</th>';
            $html .= '<th class="sub" style="width: 200px;">Keterangan / Bon</th>';
            $html .= '<th class="sub" style="width: 150px;">Sisa Bon (Rp)</th>';
            $html .= '</tr></thead><tbody>';

            $noDebt = 1;
            $totalDebtAmount = 0;
            foreach ($debts as $d) {
                $totalDebtAmount += $d->remaining;
                $html .= '<tr>';
                $html .= '<td class="center">' . $noDebt++ . '</td>';
                $html .= '<td class="bold">' . ($d->employee ? htmlspecialchars($d->employee->name) : '-') . '</td>';
                $html .= '<td>' . ($d->employee ? htmlspecialchars($d->employee->department ?? '-') : '-') . '</td>';
                $html .= '<td>' . ($d->employee ? ($d->employee->phone ?? '-') : '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($d->notes ?? '-') . '</td>';
                $html .= '<td class="num bold" style="color: #b91c1c;">' . $d->remaining . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="total-row">';
            $html .= '<td colspan="5" class="center bold">TOTAL PIUTANG BELUM LUNAS</td>';
            $html .= '<td class="num bold">' . $totalDebtAmount . '</td>';
            $html .= '</tr>';
            $html .= '</tbody></table>';

            $html .= '</body></html>';

            fwrite($output, $html);
            fclose($output);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function exportSettlementExcel(Request $request)
    {
        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());
        $cashierId = $request->query('cashier_id', 'all');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $fileName = 'Rekap_Setoran_Keuangan_RSIA_' . date('Ymd_His') . '.xls';

        $trxQuery = Transaction::whereBetween('created_at', [$startDateTime, $endDateTime]);
        if ($cashierId !== 'all') {
            $trxQuery->where('cashier_id', $cashierId);
        }

        $rawTrxs = $trxQuery->with('cashier')->orderBy('created_at', 'asc')->get();
        $rawTrxIds = $rawTrxs->pluck('id');
        $periodItemsSold = TransactionItem::whereIn('transaction_id', $rawTrxIds)
            ->with(['product:id,name,sku,category_id', 'product.category:id,name', 'unit:id,unit_name'])
            ->select(
                'product_id',
                'product_unit_id',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(subtotal) as total_subtotal'),
                DB::raw('AVG(unit_price) as avg_price')
            )
            ->groupBy('product_id', 'product_unit_id')
            ->get()
            ->map(function ($it) {
                return [
                    'product_name' => $it->product ? $it->product->name : 'Item Terhapus',
                    'category_name' => $it->product && $it->product->category ? $it->product->category->name : 'Umum',
                    'unit_name' => $it->unit ? $it->unit->unit_name : 'Pcs',
                    'avg_price' => (float) $it->avg_price,
                    'total_qty' => (float) $it->total_qty,
                    'total_subtotal' => (float) $it->total_subtotal,
                ];
            })
            ->sortByDesc('total_qty')
            ->values()
            ->all();

        $groupedByCashierDay = $rawTrxs->groupBy(function ($t) {
            return $t->created_at->format('Y-m-d') . '_' . $t->cashier_id;
        });

        $settlements = [];
        foreach ($groupedByCashierDay as $key => $trxs) {
            $first = $trxs->first();
            $d = $first->created_at->format('Y-m-d');
            $cashier = $first->cashier;

            $cash = (float) $trxs->where('payment_method', 'cash')->sum('total_net');
            $qris = (float) $trxs->where('payment_method', 'qris')->sum('total_net');
            $transfer = (float) $trxs->where('payment_method', 'transfer')->sum('total_net');
            $tempo = (float) $trxs->where('payment_method', 'tempo')->sum('total_net');
            $gross = (float) $trxs->sum('total_gross');
            $discount = (float) $trxs->sum('discount');
            $net = (float) $trxs->sum('total_net');

            $settlements[] = [
                'date' => $d,
                'cashier_name' => $cashier ? $cashier->name : 'Kasir',
                'start_time' => Carbon::parse($trxs->min('created_at'))->format('H:i'),
                'end_time' => Carbon::parse($trxs->max('created_at'))->format('H:i'),
                'count' => $trxs->count(),
                'cash' => $cash,
                'qris' => $qris,
                'transfer' => $transfer,
                'tempo' => $tempo,
                'gross' => $gross,
                'discount' => $discount,
                'net' => $net,
                'first_inv' => $trxs->first()->invoice_number,
                'last_inv' => $trxs->last()->invoice_number,
            ];
        }

        usort($settlements, function ($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['cashier_name'], $b['cashier_name']);
            }
            return strcmp($b['date'], $a['date']);
        });

        $headers = [
            "Content-Type" => "application/vnd.ms-excel; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"" . $fileName . "\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($settlements, $periodItemsSold, $startDate, $endDate) {
            $output = fopen('php://output', 'w');

            $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Rekap Setoran Kasir</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            $html .= '<style>';
            $html .= 'body { font-family: "Calibri", "Segoe UI", Arial, sans-serif; font-size: 11pt; }';
            $html .= '.title { font-size: 16pt; font-weight: bold; color: #166534; }';
            $html .= '.subtitle { font-size: 11pt; color: #475569; }';
            $html .= 'table { border-collapse: collapse; width: 100%; margin-bottom: 25px; }';
            $html .= 'th { background-color: #0f172a; color: #ffffff; font-weight: bold; padding: 10px 8px; border: 1px solid #1e293b; text-align: center; font-size: 10.5pt; }';
            $html .= 'th.green { background-color: #15803d; border: 1px solid #166534; }';
            $html .= 'th.blue { background-color: #1d4ed8; border: 1px solid #1e40af; }';
            $html .= 'th.amber { background-color: #b45309; border: 1px solid #92400e; }';
            $html .= 'td { padding: 7px 8px; border: 1px solid #cbd5e1; vertical-align: middle; }';
            $html .= 'tr:nth-child(even) { background-color: #f8fafc; }';
            $html .= '.num { text-align: right; mso-number-format:\"\#\,\#\#0\"; }';
            $html .= '.center { text-align: center; }';
            $html .= '.bold { font-weight: bold; }';
            $html .= '.total-row { background-color: #0f172a !important; color: #ffffff !important; font-weight: bold; }';
            $html .= '.total-row td { border: 1px solid #0f172a; color: #ffffff; }';
            $html .= '</style></head><body>';

            $html .= '<div class="title">KOPERASI RSIA AISYIYAH PEKAJANGAN</div>';
            $html .= '<div style="font-size: 13pt; font-weight: bold; color: #0f172a;">BERITA ACARA REKAPITULASI PENJUALAN & SETORAN KASIR KANTIN</div>';
            $html .= '<div class="subtitle">Periode: <strong>' . date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)) . '</strong> | Tanggal Unduh: ' . date('d/m/Y H:i:s') . ' WIB</div><br>';

            $html .= '<table>';
            $html .= '<thead><tr>';
            $html .= '<th style="width: 40px;">No</th>';
            $html .= '<th style="width: 100px;">Tanggal</th>';
            $html .= '<th style="width: 130px;">Kasir Bertugas</th>';
            $html .= '<th style="width: 100px;">Jam Shift</th>';
            $html .= '<th style="width: 60px;">Jml Trx</th>';
            $html .= '<th style="width: 170px;">Range Faktur</th>';
            $html .= '<th class="green" style="width: 140px;">1. SETORAN TUNAI (Uang Fisik Kasir)</th>';
            $html .= '<th class="blue" style="width: 120px;">2. QRIS (Bank)</th>';
            $html .= '<th class="blue" style="width: 120px;">3. Transfer Bank</th>';
            $html .= '<th class="amber" style="width: 140px;">4. Bon Pegawai RSIA (Potong Gaji)</th>';
            $html .= '<th style="width: 140px;">TOTAL OMSET BERSIH</th>';
            $html .= '</tr></thead><tbody>';

            $no = 1;
            $totCash = 0; $totQris = 0; $totTrf = 0; $totTempo = 0; $totNet = 0; $totTrx = 0;

            foreach ($settlements as $s) {
                $totCash += $s['cash'];
                $totQris += $s['qris'];
                $totTrf += $s['transfer'];
                $totTempo += $s['tempo'];
                $totNet += $s['net'];
                $totTrx += $s['count'];

                $html .= '<tr>';
                $html .= '<td class="center">' . $no++ . '</td>';
                $html .= '<td class="center bold">' . date('d/m/Y', strtotime($s['date'])) . '</td>';
                $html .= '<td class="bold">' . htmlspecialchars($s['cashier_name']) . '</td>';
                $html .= '<td class="center">' . $s['start_time'] . ' - ' . $s['end_time'] . '</td>';
                $html .= '<td class="center bold">' . $s['count'] . '</td>';
                $html .= '<td class="center" style="font-family:monospace; font-size:9pt;">' . $s['first_inv'] . ' s/d ' . $s['last_inv'] . '</td>';
                $html .= '<td class="num bold" style="background-color: #f0fdf4; color: #166534;">' . $s['cash'] . '</td>';
                $html .= '<td class="num" style="background-color: #eff6ff; color: #1e40af;">' . $s['qris'] . '</td>';
                $html .= '<td class="num" style="background-color: #eff6ff; color: #1e40af;">' . $s['transfer'] . '</td>';
                $html .= '<td class="num" style="background-color: #fefce8; color: #854d0e;">' . $s['tempo'] . '</td>';
                $html .= '<td class="num bold" style="font-size:11pt;">' . $s['net'] . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="total-row">';
            $html .= '<td colspan="4" class="center bold">TOTAL KESELURUHAN</td>';
            $html .= '<td class="center bold">' . $totTrx . '</td>';
            $html .= '<td class="center">-</td>';
            $html .= '<td class="num bold" style="background-color: #15803d !important; color:#ffffff !important;">' . $totCash . '</td>';
            $html .= '<td class="num bold" style="background-color: #1d4ed8 !important; color:#ffffff !important;">' . $totQris . '</td>';
            $html .= '<td class="num bold" style="background-color: #1d4ed8 !important; color:#ffffff !important;">' . $totTrf . '</td>';
            $html .= '<td class="num bold" style="background-color: #b45309 !important; color:#ffffff !important;">' . $totTempo . '</td>';
            $html .= '<td class="num bold" style="background-color: #0f172a !important; color:#ffffff !important;">' . $totNet . '</td>';
            $html .= '</tr>';
            $html .= '</tbody></table><br><br>';

            // Tabel II: Rincian Produk / Menu Kantin Terjual
            $html .= '<div style="font-size: 13pt; font-weight: bold; color: #0f172a; margin-bottom: 8px;">II. RINCIAN DETAIL PRODUK / MENU KANTIN TERJUAL</div>';
            $html .= '<table><thead><tr>';
            $html .= '<th style="width: 40px;">No</th><th>Nama Produk / Menu Kantin</th><th>Kategori</th><th style="width: 80px; text-align:center;">Satuan</th><th style="width: 90px; text-align:center;">Qty Terjual</th><th style="width: 140px; text-align:right;">Subtotal (Rp)</th>';
            $html .= '</tr></thead><tbody>';

            $itemNo = 1;
            $totItemQty = 0;
            $totItemSubtotal = 0;
            foreach ($periodItemsSold as $item) {
                $totItemQty += $item['total_qty'];
                $totItemSubtotal += $item['total_subtotal'];
                $html .= '<tr>';
                $html .= '<td class="center">' . $itemNo++ . '</td>';
                $html .= '<td class="bold">' . htmlspecialchars($item['product_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($item['category_name']) . '</td>';
                $html .= '<td class="center">' . htmlspecialchars($item['unit_name']) . '</td>';
                $html .= '<td class="center bold">' . $item['total_qty'] . '</td>';
                $html .= '<td class="num bold">' . $item['total_subtotal'] . '</td>';
                $html .= '</tr>';
            }
            $html .= '<tr class="total-row">';
            $html .= '<td colspan="4" class="center bold">TOTAL PRODUK TERJUAL</td>';
            $html .= '<td class="center bold">' . $totItemQty . '</td>';
            $html .= '<td class="num bold" style="background-color: #0f172a !important; color:#ffffff !important;">' . $totItemSubtotal . '</td>';
            $html .= '</tr></tbody></table>';

            // Lembar Tanda Tangan Serah Terima
            $html .= '<br><br>';
            $html .= '<table style="width: 100%; border: none; margin-top: 30px;">';
            $html .= '<tr style="background: none;">';
            $html .= '<td style="width: 50%; text-align: center; border: none; font-size: 11pt;">';
            $html .= '<div>Diserahkan Oleh:</div>';
            $html .= '<div style="font-weight: bold; margin-top: 5px;">Kasir Kantin RSIA</div>';
            $html .= '<br><br><br><br>';
            $html .= '<div style="text-decoration: underline; font-weight: bold;">( .................................................. )</div>';
            $html .= '<div style="color: #64748b; font-size: 9pt;">Nama & Tanda Tangan Kasir</div>';
            $html .= '</td>';
            $html .= '<td style="width: 50%; text-align: center; border: none; font-size: 11pt;">';
            $html .= '<div>Diterima & Diverifikasi Oleh:</div>';
            $html .= '<div style="font-weight: bold; margin-top: 5px;">Bagian Keuangan RSIA Aisyiyah Pekajangan</div>';
            $html .= '<br><br><br><br>';
            $html .= '<div style="text-decoration: underline; font-weight: bold;">( .................................................. )</div>';
            $html .= '<div style="color: #64748b; font-size: 9pt;">Nama & Tanda Tangan Bag. Keuangan</div>';
            $html .= '</td>';
            $html .= '</tr></table>';

            $html .= '</body></html>';

            fwrite($output, $html);
            fclose($output);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
