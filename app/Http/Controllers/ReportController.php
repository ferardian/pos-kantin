<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
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
        $totalActiveDebts = (float) Debt::where('status', '!=', 'paid')->sum('remaining_debt');
        $pendingOrdersCount = SalesOrder::where('status', 'pending')->count();

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

        // 7. Sales Leaderboard
        $salesLeaderboard = User::where('role', 'sales')
            ->withCount(['salesOrders as total_orders' => function ($q) use ($startDateTime, $endDateTime) {
                $q->where('status', 'completed')
                  ->whereBetween('created_at', [$startDateTime, $endDateTime]);
            }])
            ->withSum(['salesOrders as total_revenue' => function ($q) use ($startDateTime, $endDateTime) {
                $q->where('status', 'completed')
                  ->whereBetween('created_at', [$startDateTime, $endDateTime]);
            }], 'total_amount')
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
            ->with(['cashier', 'customer', 'items.product', 'items.unit'])
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

        $allUsers = User::select('id', 'name', 'role')->get();

        return Inertia::render('Reports/Index', [
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

        $fileName = 'Laporan_Penjualan_Trisna_Jaya_' . date('Ymd_His') . '.xls';

        $trxQuery = Transaction::whereBetween('created_at', [$startDateTime, $endDateTime]);
        if ($paymentMethod !== 'all') {
            $trxQuery->where('payment_method', $paymentMethod);
        }
        if ($cashierId !== 'all') {
            $trxQuery->where('cashier_id', $cashierId);
        }

        $transactions = $trxQuery->with(['cashier', 'customer', 'items.product', 'items.unit'])->latest()->get();
        $products = Product::with(['category', 'brand', 'baseUnit'])->get();
        $debts = Debt::with('customer')->where('status', '!=', 'paid')->get();

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
            $html .= '.num { text-align: right; mso-number-format:"\#\,\#\#0"; }';
            $html .= '.center { text-align: center; }';
            $html .= '.bold { font-weight: bold; }';
            $html .= '.total-row { background-color: #0f172a !important; color: #ffffff !important; font-weight: bold; }';
            $html .= '.total-row td { border: 1px solid #0f172a; color: #ffffff; }';
            $html .= '</style></head><body>';

            // Title & Meta Info
            $html .= '<div class="title">TRISNA JAYA LISTRIK - LAPORAN KEUANGAN & PENJUALAN TOKO</div>';
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
                $tierLabel = $t->customer ? ucfirst($t->customer->tier) : 'Retail';
                $trxProfit = max(0, $t->total_net - $trxCost);
                $trxMargin = $t->total_net > 0 ? round(($trxProfit / $t->total_net) * 100, 1) : 0;

                $sumGross += $t->total_gross;
                $sumDisc += $t->discount_amount;
                $sumNet += $t->total_net;
                $sumCost += $trxCost;
                $sumProfit += $trxProfit;

                $html .= '<tr>';
                $html .= '<td class="center">' . $no++ . '</td>';
                $html .= '<td class="center">' . $t->created_at->format('d/m/Y H:i') . '</td>';
                $html .= '<td class="bold">' . $t->invoice_number . '</td>';
                $html .= '<td>' . ($t->cashier ? $t->cashier->name : 'Sistem') . '</td>';
                $html .= '<td>' . ($t->customer ? $t->customer->name : 'Pelanggan Umum') . '</td>';
                $html .= '<td class="center">' . $tierLabel . '</td>';
                $html .= '<td>' . $itemsString . '</td>';
                $html .= '<td class="center bold">' . strtoupper($t->payment_method) . '</td>';
                $html .= '<td class="num">' . $t->total_gross . '</td>';
                $html .= '<td class="num">' . $t->discount_amount . '</td>';
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
            $html .= '<div class="section-header">2. Rekapitulasi Nilai Aset Stok Persediaan Barang Listrik</div>';
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

            // SECTION 3: DAFTAR PIUTANG PELANGGAN
            $html .= '<div class="section-header">3. Daftar Piutang Berjalan Pelanggan</div>';
            $html .= '<table>';
            $html .= '<thead><tr>';
            $html .= '<th class="sub" style="width: 40px;">No</th>';
            $html .= '<th class="sub" style="width: 200px;">Nama Pelanggan</th>';
            $html .= '<th class="sub" style="width: 140px;">No. Telepon</th>';
            $html .= '<th class="sub" style="width: 250px;">Alamat</th>';
            $html .= '<th class="sub" style="width: 150px;">Sisa Piutang (Rp)</th>';
            $html .= '<th class="sub" style="width: 120px;">Jatuh Tempo</th>';
            $html .= '</tr></thead><tbody>';

            $noDebt = 1;
            $totalDebtAmount = 0;
            foreach ($debts as $d) {
                $totalDebtAmount += $d->remaining_debt;
                $html .= '<tr>';
                $html .= '<td class="center">' . $noDebt++ . '</td>';
                $html .= '<td class="bold">' . ($d->customer ? htmlspecialchars($d->customer->name) : 'Pelanggan Umum') . '</td>';
                $html .= '<td>' . ($d->customer ? $d->customer->phone : '-') . '</td>';
                $html .= '<td>' . ($d->customer ? htmlspecialchars($d->customer->address) : '-') . '</td>';
                $html .= '<td class="num bold" style="color: #b91c1c;">' . $d->remaining_debt . '</td>';
                $html .= '<td class="center">' . ($d->due_date ? Carbon::parse($d->due_date)->format('d/m/Y') : 'Tempo Bebas') . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr class="total-row">';
            $html .= '<td colspan="4" class="center bold">TOTAL PIUTANG BELUM LUNAS</td>';
            $html .= '<td class="num bold">' . $totalDebtAmount . '</td>';
            $html .= '<td></td>';
            $html .= '</tr>';
            $html .= '</tbody></table>';

            $html .= '</body></html>';

            fwrite($output, $html);
            fclose($output);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
