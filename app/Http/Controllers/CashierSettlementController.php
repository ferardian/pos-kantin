<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\ConsignmentBatch;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashierSettlementController extends Controller
{
    /**
     * Tampilan Halaman Rekap Penjualan & Detail Item
     * Dapat melihat per kasir maupun Semua Kasir (Rekap Harian Akhir/Malam)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::today()->toDateString());
        
        // Pilihan filter kasir: 'all' (Semua Kasir) atau ID kasir tertentu.
        // Default: 'all' agar petugas di akhir shift / malam bisa langsung melihat rekap total harian.
        $cashierId = $request->query('cashier_id', 'all');

        $settlement = $this->calculateShiftSettlement($cashierId, $date);

        // Ambil daftar kasir untuk filter
        $cashiers = User::whereIn('role', ['kasir', 'admin'])
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cashier/Settlement', [
            'settlement' => $settlement,
            'selectedDate' => $date,
            'selectedCashierId' => $cashierId,
            'cashiers' => $cashiers,
            'user' => $user,
        ]);
    }

    /**
     * API JSON untuk live modal langsung dari layar POS Kasir
     */
    public function currentShift(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::today()->toDateString());
        $cashierId = $request->query('cashier_id', 'all');

        $settlement = $this->calculateShiftSettlement($cashierId, $date);

        return response()->json([
            'success' => true,
            'settlement' => $settlement,
        ]);
    }

    /**
     * Hitung rekap penjualan & detail item untuk kasir tertentu atau semua kasir pada tanggal tertentu.
     * Mengimplementasikan Best Practice Kantin RS:
     * Kasir bayar langsung jajan titipan di kasir sore hari -> Sisa uang fisik bersih disetor ke Keuangan RSIA.
     */
    private function calculateShiftSettlement($cashierId, $date)
    {
        $startDateTime = Carbon::parse($date)->startOfDay();
        $endDateTime = Carbon::parse($date)->endOfDay();

        // 1. Ambil transaksi penjualan kasir
        $query = Transaction::with('cashier')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'asc');

        if ($cashierId !== 'all' && is_numeric($cashierId)) {
            $query->where('cashier_id', (int) $cashierId);
        }

        $trxs = $query->get();
        $count = $trxs->count();

        // Tentukan nama kasir / label rekap
        if ($cashierId === 'all') {
            $activeNames = $trxs->pluck('cashier.name')->filter()->unique()->values()->all();
            $cashierName = count($activeNames) > 0 ? implode(', ', $activeNames) : 'Semua Kasir';
            $cashierTitle = 'Semua Kasir (Rekap Harian Gabungan)';
        } else {
            $selectedCashier = User::find($cashierId);
            $cashierName = $selectedCashier ? $selectedCashier->name : 'Kasir';
            $cashierTitle = $cashierName;
        }

        $cashTotal = (float) $trxs->where('payment_method', 'cash')->sum('total_net');
        $qrisTotal = (float) $trxs->where('payment_method', 'qris')->sum('total_net');
        $transferTotal = (float) $trxs->where('payment_method', 'transfer')->sum('total_net');
        $tempoTotal = (float) $trxs->where('payment_method', 'tempo')->sum('total_net');
        $grossTotal = (float) $trxs->sum('total_gross');
        $discountTotal = (float) $trxs->sum('discount');
        $netTotal = (float) $trxs->sum('total_net');

        $startTime = $count > 0 ? Carbon::parse($trxs->min('created_at'))->format('H:i') : '-';
        $endTime = $count > 0 ? Carbon::parse($trxs->max('created_at'))->format('H:i') : '-';
        $firstInvoice = $count > 0 ? $trxs->first()->invoice_number : '-';
        $lastInvoice = $count > 0 ? $trxs->last()->invoice_number : '-';

        // 2. Ambil pengeluaran kasir untuk pembayaran titipan (konsinyasi) pada tanggal ini
        $consignmentQuery = ConsignmentBatch::with(['consignor', 'cashier:id,name'])
            ->where('status', 'settled')
            ->whereDate('settlement_date', $date);

        if ($cashierId !== 'all' && is_numeric($cashierId)) {
            $consignmentQuery->where('user_id', (int) $cashierId);
        }

        $settledConsignments = $consignmentQuery->orderBy('settlement_date', 'asc')->get();
        $consignmentPaidTotal = (float) $settledConsignments->sum('total_payable');
        $consignmentProfitTotal = (float) $settledConsignments->sum('total_canteen_profit');
        $consignmentSettledList = $settledConsignments->map(function ($b) {
            return [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'consignor_name' => $b->consignor ? $b->consignor->name : 'Penitip',
                'cashier_id' => $b->user_id,
                'cashier_name' => $b->cashier ? $b->cashier->name : '-',
                'total_qty_sold' => (int) $b->total_qty_sold,
                'total_payable' => (float) $b->total_payable,
                'total_canteen_profit' => (float) $b->total_canteen_profit,
                'settlement_time' => Carbon::parse($b->settlement_date)->format('H:i'),
            ];
        })->all();

        // 3. Pengeluaran kasir operasional lainnya (jika ada, selain konsinyasi)
        $otherExpensesQuery = CashTransaction::where('type', 'out')
            ->whereDate('transaction_date', $date)
            ->where(function ($q) {
                $q->whereNull('reference_type')
                  ->orWhere('reference_type', '!=', 'consignment_batch');
            })
            ->where('category', '!=', 'Pembayaran Konsinyasi');

        if ($cashierId !== 'all' && is_numeric($cashierId)) {
            $otherExpensesQuery->where('user_id', (int) $cashierId);
        }
        $otherExpensesTotal = (float) $otherExpensesQuery->sum('amount');

        // 4. Hitung Rekonsiliasi Kas Bersih (Cash on Hand yang disetor kasir ke Keuangan RSIA)
        $totalCashOut = $consignmentPaidTotal + $otherExpensesTotal;
        $netCashDeposit = max(0, $cashTotal - $totalCashOut);

        // Ringkasan per kasir jika mode 'all'
        $cashierBreakdown = [];
        if ($cashierId === 'all' && $count > 0) {
            $grouped = $trxs->groupBy('cashier_id');
            foreach ($grouped as $cId => $cTrxs) {
                $cUser = $cTrxs->first()->cashier;
                $cCash = (float) $cTrxs->where('payment_method', 'cash')->sum('total_net');
                $cConsignmentPaid = (float) $settledConsignments->where('user_id', $cId)->sum('total_payable');
                $cNetCash = max(0, $cCash - $cConsignmentPaid);

                $cashierBreakdown[] = [
                    'cashier_id' => $cId,
                    'cashier_name' => $cUser ? $cUser->name : 'Kasir #' . $cId,
                    'transaction_count' => $cTrxs->count(),
                    'cash_total' => $cCash,
                    'consignment_paid' => $cConsignmentPaid,
                    'net_cash_deposit' => $cNetCash,
                    'qris_total' => (float) $cTrxs->where('payment_method', 'qris')->sum('total_net'),
                    'transfer_total' => (float) $cTrxs->where('payment_method', 'transfer')->sum('total_net'),
                    'tempo_total' => (float) $cTrxs->where('payment_method', 'tempo')->sum('total_net'),
                    'total_net' => (float) $cTrxs->sum('total_net'),
                ];
            }
            usort($cashierBreakdown, fn($a, $b) => strcmp($a['cashier_name'], $b['cashier_name']));
        }

        // 5. Hitung rincian barang/produk yang terjual serta pemisahan porsi omzet toko vs titipan
        $itemsSold = [];
        $consignmentGrossSales = 0;
        $consignmentCostEst = 0;
        $ownProductsGrossSales = 0;

        if ($count > 0) {
            $trxIds = $trxs->pluck('id');
            $rawItems = TransactionItem::whereIn('transaction_id', $trxIds)
                ->with(['product:id,name,sku,category_id,is_consignment', 'product.category:id,name', 'unit:id,unit_name'])
                ->get();

            foreach ($rawItems as $it) {
                $isCsg = $it->product ? (bool) $it->product->is_consignment : false;
                if ($isCsg) {
                    $consignmentGrossSales += (float) $it->subtotal;
                    $consignmentCostEst += (float) ($it->qty * $it->cost_price);
                } else {
                    $ownProductsGrossSales += (float) $it->subtotal;
                }
            }

            $itemsSold = $rawItems->groupBy(function ($i) {
                return $i->product_id . '_' . $i->product_unit_id;
            })->map(function ($group) {
                $first = $group->first();
                $totalQty = $group->sum('qty');
                $totalSubtotal = $group->sum('subtotal');
                $avgPrice = $totalQty > 0 ? $totalSubtotal / $totalQty : $first->unit_price;

                return [
                    'product_id' => $first->product_id,
                    'product_name' => $first->product ? $first->product->name : 'Item Terhapus',
                    'is_consignment' => $first->product ? (bool) $first->product->is_consignment : false,
                    'category_name' => $first->product && $first->product->category ? $first->product->category->name : 'Umum',
                    'unit_name' => $first->unit ? $first->unit->unit_name : 'Pcs',
                    'avg_price' => (float) $avgPrice,
                    'total_qty' => (float) $totalQty,
                    'total_subtotal' => (float) $totalSubtotal,
                ];
            })
            ->sortByDesc('total_qty')
            ->values()
            ->all();
        }

        $totalItemsQty = array_sum(array_column($itemsSold, 'total_qty'));
        $totalItemsCount = count($itemsSold);
        $consignmentMarginEst = max(0, $consignmentGrossSales - $consignmentCostEst);

        return [
            'cashier_id' => $cashierId,
            'cashier_name' => $cashierName,
            'cashier_title' => $cashierTitle,
            'is_all_cashiers' => ($cashierId === 'all'),
            'date' => $date,
            'formatted_date' => Carbon::parse($date)->translatedFormat('l, d F Y'),
            'transaction_count' => $count,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'first_invoice' => $firstInvoice,
            'last_invoice' => $lastInvoice,
            'gross_cash_total' => $cashTotal,
            'cash_total' => $cashTotal, // Tetap disertakan untuk kompatibilitas
            'consignment_paid_total' => $consignmentPaidTotal,
            'consignment_profit_total' => $consignmentProfitTotal,
            'consignment_settled_list' => $consignmentSettledList,
            'other_expenses_total' => $otherExpensesTotal,
            'total_cash_out' => $totalCashOut,
            'net_cash_deposit' => $netCashDeposit,
            'own_products_sales' => $ownProductsGrossSales,
            'consignment_sales' => $consignmentGrossSales,
            'consignment_payable' => $consignmentCostEst,
            'consignment_margin' => $consignmentMarginEst,
            'qris_total' => $qrisTotal,
            'transfer_total' => $transferTotal,
            'non_cash_total' => $qrisTotal + $transferTotal,
            'tempo_total' => $tempoTotal,
            'total_gross' => $grossTotal,
            'total_discount' => $discountTotal,
            'total_net' => $netTotal,
            'items_sold' => $itemsSold,
            'total_items_qty' => $totalItemsQty,
            'total_items_count' => $totalItemsCount,
            'cashier_breakdown' => $cashierBreakdown,
        ];
    }

    /**
     * Export Excel Rekap Penjualan & Detail Item ke Keuangan RSIA
     */
    public function exportExcel(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $cashierId = $request->query('cashier_id', 'all');

        $settlement = $this->calculateShiftSettlement($cashierId, $date);
        $safeName = $settlement['is_all_cashiers'] ? 'Semua_Kasir' : str_replace(' ', '_', $settlement['cashier_name']);
        $fileName = 'Rekap_Penjualan_' . $safeName . '_' . $date . '.xls';

        $response = new StreamedResponse(function () use ($settlement) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="utf-8">';
            echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Rekap Penjualan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            echo '<style>';
            echo 'body { font-family: Arial, sans-serif; }';
            echo 'table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }';
            echo 'th, td { border: 1px solid #999; padding: 6px 10px; font-size: 11pt; }';
            echo 'th { background-color: #1e293b; color: #ffffff; }';
            echo '.header-title { font-size: 14pt; font-weight: bold; border: none; text-align: left; }';
            echo '.header-sub { font-size: 11pt; color: #555; border: none; text-align: left; }';
            echo '.num { text-align: right; }';
            echo '.bold { font-weight: bold; }';
            echo '.highlight { background-color: #dcfce7; font-weight: bold; }';
            echo '.deduction { background-color: #fee2e2; color: #991b1b; }';
            echo '.total { background-color: #f1f5f9; font-weight: bold; }';
            echo '</style></head><body>';

            // Kop Surat
            echo '<table>';
            echo '<tr><td colspan="6" class="header-title">KOPERASI RSIA AISYIYAH PEKAJANGAN - KANTIN</td></tr>';
            echo '<tr><td colspan="6" class="header-sub">BERITA ACARA REKAPITULASI PENJUALAN & SETORAN KASIR</td></tr>';
            echo '<tr><td colspan="6" style="border:none;">Tanggal: ' . $settlement['formatted_date'] . ' | Kasir: ' . $settlement['cashier_title'] . ' | Jam Shift: ' . $settlement['start_time'] . ' - ' . $settlement['end_time'] . ' | Total Trx: ' . $settlement['transaction_count'] . ' Nota</td></tr>';
            echo '</table><br/>';

            // Tabel 1: Ringkasan Rekapitulasi Kas & Setoran Kasir (Model Bersih)
            echo '<table>';
            echo '<thead>';
            echo '<tr><th colspan="3" style="text-align:left; font-size:12pt; background:#166534;">I. REKONSILIASI KAS SETORAN & PENERIMAAN KASIR</th></tr>';
            echo '<tr><th style="width:40px;">No</th><th>Uraian Rekonsiliasi Kasir</th><th style="width:200px;">Jumlah Nominal (Rp)</th></tr>';
            echo '</thead><tbody>';
            echo '<tr><td style="text-align:center;">1</td><td>Penerimaan Tunai Penjualan di Mesin Kasir (Gross Cash)</td><td class="num bold">' . number_format($settlement['gross_cash_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr class="deduction"><td style="text-align:center;">2</td><td>(-) Pengeluaran Pelunasan Jajan Titipan Sore (Bukti Struk Terlampir)</td><td class="num">(' . number_format($settlement['consignment_paid_total'], 0, ',', '.') . ')</td></tr>';
            if ($settlement['other_expenses_total'] > 0) {
                echo '<tr class="deduction"><td style="text-align:center;">3</td><td>(-) Pengeluaran Operasional Kasir Lainnya</td><td class="num">(' . number_format($settlement['other_expenses_total'], 0, ',', '.') . ')</td></tr>';
            }
            echo '<tr class="highlight"><td style="text-align:center; font-weight:bold;">=</td><td class="bold">TOTAL UANG FISIK WAJIB DISETOR KE KEUANGAN RSIA (NET CASH)</td><td class="num bold" style="font-size:12pt; color:#14532d;">' . number_format($settlement['net_cash_deposit'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">+</td><td>Pembayaran QRIS Bank (Masuk Rekening RSIA/Koperasi)</td><td class="num">' . number_format($settlement['qris_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">+</td><td>Pembayaran Transfer Bank</td><td class="num">' . number_format($settlement['transfer_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">+</td><td>Bon / Piutang Pegawai RSIA (Tempo / Potong Gaji)</td><td class="num">' . number_format($settlement['tempo_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr class="total"><td colspan="2" style="text-align:right;">TOTAL PERTANGGUNGJAWABAN PENJUALAN (OMSET):</td><td class="num bold">' . number_format($settlement['total_net'], 0, ',', '.') . '</td></tr>';
            echo '</tbody></table><br/>';

            // Tabel 2: Rincian Pelunasan Jajan Titipan Sore (jika ada)
            if (!empty($settlement['consignment_settled_list'])) {
                echo '<table>';
                echo '<thead>';
                echo '<tr><th colspan="7" style="text-align:left; font-size:12pt; background:#854d0e;">II. RINCIAN PENGELUARAN PELUNASAN JAJAN TITIPAN (LAMPIRAN STRUK KASIR)</th></tr>';
                echo '<tr><th>No</th><th>No Batch</th><th>Nama Penitip</th><th>Jam Bayar</th><th>Kasir</th><th style="text-align:center;">Pcs Terjual</th><th style="text-align:right;">Uang Dibayar (Rp)</th></tr>';
                echo '</thead><tbody>';
                $csNo = 1;
                foreach ($settlement['consignment_settled_list'] as $cs) {
                    echo '<tr>';
                    echo '<td style="text-align:center;">' . $csNo++ . '</td>';
                    echo '<td style="font-family:monospace;">' . htmlspecialchars($cs['batch_number']) . '</td>';
                    echo '<td class="bold">' . htmlspecialchars($cs['consignor_name']) . '</td>';
                    echo '<td style="text-align:center;">' . $cs['settlement_time'] . '</td>';
                    echo '<td>' . htmlspecialchars($cs['cashier_name']) . '</td>';
                    echo '<td style="text-align:center; font-weight:bold;">' . $cs['total_qty_sold'] . ' pcs</td>';
                    echo '<td class="num bold" style="color:#b91c1c;">' . number_format($cs['total_payable'], 0, ',', '.') . '</td>';
                    echo '</tr>';
                }
                echo '<tr class="total">';
                echo '<td colspan="6" style="text-align:right;">TOTAL PENGELUARAN TITIPAN DIBAYAR KASIR:</td>';
                echo '<td class="num bold" style="color:#b91c1c;">' . number_format($settlement['consignment_paid_total'], 0, ',', '.') . '</td>';
                echo '</tr>';
                echo '</tbody></table><br/>';
            }

            // Tabel Tambahan jika Rekap Semua Kasir: Breakdown per Kasir
            if (!empty($settlement['cashier_breakdown'])) {
                echo '<table>';
                echo '<thead>';
                echo '<tr><th colspan="8" style="text-align:left; font-size:12pt; background:#0f172a;">RINCIAN REKAPITULASI PER PETUGAS KASIR</th></tr>';
                echo '<tr><th>No</th><th>Nama Kasir</th><th style="text-align:center;">Jml Nota</th><th style="text-align:right;">Tunai Bruto</th><th style="text-align:right;">Bayar Titipan</th><th style="text-align:right;">Net Setor Tunai</th><th style="text-align:right;">Non-Tunai</th><th style="text-align:right;">Total Omset</th></tr>';
                echo '</thead><tbody>';
                $cNo = 1;
                foreach ($settlement['cashier_breakdown'] as $cb) {
                    echo '<tr>';
                    echo '<td style="text-align:center;">' . $cNo++ . '</td>';
                    echo '<td class="bold">' . htmlspecialchars($cb['cashier_name']) . '</td>';
                    echo '<td style="text-align:center;">' . $cb['transaction_count'] . '</td>';
                    echo '<td class="num">' . number_format($cb['cash_total'], 0, ',', '.') . '</td>';
                    echo '<td class="num" style="color:#b91c1c;">(' . number_format($cb['consignment_paid'], 0, ',', '.') . ')</td>';
                    echo '<td class="num highlight">' . number_format($cb['net_cash_deposit'], 0, ',', '.') . '</td>';
                    echo '<td class="num">' . number_format($cb['qris_total'] + $cb['transfer_total'], 0, ',', '.') . '</td>';
                    echo '<td class="num bold">' . number_format($cb['total_net'], 0, ',', '.') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table><br/>';
            }

            // Tabel 3: Detail Penjualan Item
            echo '<table>';
            echo '<thead>';
            echo '<tr><th colspan="7" style="text-align:left; font-size:12pt; background:#1e293b;">III. RINCIAN DETAIL PRODUK / MENU KANTIN TERJUAL</th></tr>';
            echo '<tr><th style="width:40px;">No</th><th>Nama Produk / Menu</th><th>Jenis</th><th>Kategori</th><th style="width:80px; text-align:center;">Satuan</th><th style="width:80px; text-align:center;">Qty Terjual</th><th style="width:180px; text-align:right;">Subtotal (Rp)</th></tr>';
            echo '</thead><tbody>';

            if (empty($settlement['items_sold'])) {
                echo '<tr><td colspan="7" style="text-align:center; color:#888;">Tidak ada transaksi penjualan pada shift ini.</td></tr>';
            } else {
                $no = 1;
                foreach ($settlement['items_sold'] as $item) {
                    echo '<tr>';
                    echo '<td style="text-align:center;">' . $no++ . '</td>';
                    echo '<td class="bold">' . htmlspecialchars($item['product_name']) . '</td>';
                    echo '<td>' . (!empty($item['is_consignment']) ? '<span style="color:#b45309; font-weight:bold;">Titipan</span>' : 'Toko') . '</td>';
                    echo '<td>' . htmlspecialchars($item['category_name']) . '</td>';
                    echo '<td style="text-align:center;">' . htmlspecialchars($item['unit_name']) . '</td>';
                    echo '<td style="text-align:center; font-weight:bold;">' . $item['total_qty'] . '</td>';
                    echo '<td class="num">' . number_format($item['total_subtotal'], 0, ',', '.') . '</td>';
                    echo '</tr>';
                }
                echo '<tr class="total">';
                echo '<td colspan="5" style="text-align:right;">TOTAL ITEM TERJUAL:</td>';
                echo '<td style="text-align:center; font-weight:bold;">' . $settlement['total_items_qty'] . '</td>';
                echo '<td class="num bold">' . number_format($settlement['total_net'], 0, ',', '.') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table><br/><br/>';

            // Lembar Tanda Tangan
            echo '<table>';
            echo '<tr><td colspan="3" style="text-align:center; border:none;">Diserahkan oleh,</td><td colspan="3" style="text-align:center; border:none;">Diterima & Diverifikasi oleh,</td></tr>';
            echo '<tr><td colspan="3" style="text-align:center; border:none; font-weight:bold;">Kasir / Penanggung Jawab</td><td colspan="3" style="text-align:center; border:none; font-weight:bold;">Bagian Keuangan RSIA</td></tr>';
            echo '<tr><td colspan="3" style="height:60px; border:none;"></td><td colspan="3" style="height:60px; border:none;"></td></tr>';
            echo '<tr><td colspan="3" style="text-align:center; border:none; font-weight:bold;">( ' . htmlspecialchars($settlement['cashier_name']) . ' )</td><td colspan="3" style="text-align:center; border:none; font-weight:bold;">( ......................................... )</td></tr>';
            echo '</table>';

            echo '</body></html>';
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
