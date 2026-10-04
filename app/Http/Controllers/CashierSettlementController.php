<?php

namespace App\Http\Controllers;

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
     * Tampilan Halaman Rekap Setoran & Detail Penjualan Kasir
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::today()->toDateString());
        
        // Kasir hanya boleh melihat setorannya sendiri, Admin dapat memilih kasir mana saja
        if ($user->role === 'admin') {
            $cashierId = $request->query('cashier_id', $user->id);
        } else {
            $cashierId = $user->id;
        }

        $settlement = $this->calculateShiftSettlement($cashierId, $date);

        // Ambil daftar kasir untuk filter (khusus admin)
        $cashiers = [];
        if ($user->role === 'admin') {
            $cashiers = User::whereIn('role', ['kasir', 'admin'])
                ->select('id', 'name', 'role')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Cashier/Settlement', [
            'settlement' => $settlement,
            'selectedDate' => $date,
            'selectedCashierId' => (int) $cashierId,
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
        $cashierId = $user->id;

        $settlement = $this->calculateShiftSettlement($cashierId, $date);

        return response()->json([
            'success' => true,
            'settlement' => $settlement,
        ]);
    }

    /**
     * Hitung rekap setoran & detail penjualan item untuk kasir pada tanggal tertentu
     */
    private function calculateShiftSettlement($cashierId, $date)
    {
        $cashier = User::find($cashierId);
        $startDateTime = Carbon::parse($date)->startOfDay();
        $endDateTime = Carbon::parse($date)->endOfDay();

        $trxs = Transaction::where('cashier_id', $cashierId)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'asc')
            ->get();

        $count = $trxs->count();
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

        // Hitung rincian detail barang/produk yang terjual
        $itemsSold = [];
        if ($count > 0) {
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
        }

        $totalItemsQty = array_sum(array_column($itemsSold, 'total_qty'));
        $totalItemsCount = count($itemsSold);

        return [
            'cashier_id' => $cashierId,
            'cashier_name' => $cashier ? $cashier->name : 'Kasir',
            'date' => $date,
            'formatted_date' => Carbon::parse($date)->translatedFormat('l, d F Y'),
            'transaction_count' => $count,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'first_invoice' => $firstInvoice,
            'last_invoice' => $lastInvoice,
            'cash_total' => $cashTotal,
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
        ];
    }

    /**
     * Export Excel Rekap Setoran & Detail Item Kasir ke Keuangan RSIA
     */
    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        $date = $request->query('date', Carbon::today()->toDateString());
        
        if ($user->role === 'admin') {
            $cashierId = $request->query('cashier_id', $user->id);
        } else {
            $cashierId = $user->id;
        }

        $settlement = $this->calculateShiftSettlement($cashierId, $date);
        $fileName = 'Rekap_Setoran_' . str_replace(' ', '_', $settlement['cashier_name']) . '_' . $date . '.xls';

        $response = new StreamedResponse(function () use ($settlement) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="utf-8">';
            echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Rekap Setoran Kasir</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
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
            echo '.total { background-color: #f1f5f9; font-weight: bold; }';
            echo '</style></head><body>';

            // Kop Surat
            echo '<table>';
            echo '<tr><td colspan="5" class="header-title">KOPERASI RSIA AISYIYAH PEKAJANGAN - KANTIN</td></tr>';
            echo '<tr><td colspan="5" class="header-sub">BERITA ACARA REKAPITULASI PENJUALAN & SETORAN KASIR KANTIN</td></tr>';
            echo '<tr><td colspan="5" style="border:none;">Tanggal: ' . $settlement['formatted_date'] . ' | Kasir: ' . $settlement['cashier_name'] . ' | Jam Shift: ' . $settlement['start_time'] . ' - ' . $settlement['end_time'] . ' | Total Trx: ' . $settlement['transaction_count'] . ' Nota</td></tr>';
            echo '</table><br/>';

            // Tabel 1: Ringkasan Rekapitulasi Setoran
            echo '<table>';
            echo '<thead>';
            echo '<tr><th colspan="3" style="text-align:left; font-size:12pt; background:#166534;">I. RINGKASAN SETORAN & KAS MASUK KASIR</th></tr>';
            echo '<tr><th style="width:40px;">No</th><th>Klasifikasi Penerimaan Kasir</th><th style="width:200px;">Jumlah Nominal (Rp)</th></tr>';
            echo '</thead><tbody>';
            echo '<tr class="highlight"><td style="text-align:center;">1</td><td>UANG TUNAI / CASH (WAJIB SETOR FISIK KE KEUANGAN RS)</td><td class="num">' . number_format($settlement['cash_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">2</td><td>Pembayaran QRIS Bank (Masuk Rekening RSIA/Koperasi)</td><td class="num">' . number_format($settlement['qris_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">3</td><td>Pembayaran Transfer Bank</td><td class="num">' . number_format($settlement['transfer_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr><td style="text-align:center;">4</td><td>Bon / Piutang Pegawai RSIA (Tempo / Potong Gaji)</td><td class="num">' . number_format($settlement['tempo_total'], 0, ',', '.') . '</td></tr>';
            echo '<tr class="total"><td colspan="2" style="text-align:right;">TOTAL PENJUALAN BERSIH (OMSET SHIFT):</td><td class="num bold">' . number_format($settlement['total_net'], 0, ',', '.') . '</td></tr>';
            echo '</tbody></table><br/>';

            // Tabel 2: Detail Penjualan Item
            echo '<table>';
            echo '<thead>';
            echo '<tr><th colspan="6" style="text-align:left; font-size:12pt; background:#1e293b;">II. RINCIAN DETAIL PRODUK / MENU KANTIN TERJUAL</th></tr>';
            echo '<tr><th style="width:40px;">No</th><th>Nama Produk / Menu</th><th>Kategori</th><th style="width:80px; text-align:center;">Satuan</th><th style="width:80px; text-align:center;">Qty Terjual</th><th style="width:180px; text-align:right;">Subtotal (Rp)</th></tr>';
            echo '</thead><tbody>';

            if (empty($settlement['items_sold'])) {
                echo '<tr><td colspan="6" style="text-align:center; color:#888;">Tidak ada transaksi penjualan pada shift ini.</td></tr>';
            } else {
                $no = 1;
                foreach ($settlement['items_sold'] as $item) {
                    echo '<tr>';
                    echo '<td style="text-align:center;">' . $no++ . '</td>';
                    echo '<td class="bold">' . htmlspecialchars($item['product_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['category_name']) . '</td>';
                    echo '<td style="text-align:center;">' . htmlspecialchars($item['unit_name']) . '</td>';
                    echo '<td style="text-align:center; font-weight:bold;">' . $item['total_qty'] . '</td>';
                    echo '<td class="num">' . number_format($item['total_subtotal'], 0, ',', '.') . '</td>';
                    echo '</tr>';
                }
                echo '<tr class="total">';
                echo '<td colspan="4" style="text-align:right;">TOTAL ITEM TERJUAL:</td>';
                echo '<td style="text-align:center; font-weight:bold;">' . $settlement['total_items_qty'] . '</td>';
                echo '<td class="num bold">' . number_format($settlement['total_net'], 0, ',', '.') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table><br/><br/>';

            // Lembar Tanda Tangan
            echo '<table>';
            echo '<tr><td colspan="3" style="text-align:center; border:none;">Diserahkan oleh,</td><td colspan="3" style="text-align:center; border:none;">Diterima & Diverifikasi oleh,</td></tr>';
            echo '<tr><td colspan="3" style="text-align:center; border:none; font-weight:bold;">Kasir Bertugas</td><td colspan="3" style="text-align:center; border:none; font-weight:bold;">Bagian Keuangan RSIA</td></tr>';
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
