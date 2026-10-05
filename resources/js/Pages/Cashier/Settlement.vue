<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { appRoute } from '@/Utils/route';
import { 
    Banknote, QrCode, Wallet, ArrowDownRight, Layers, CreditCard, Clock, Calendar, 
    FileSpreadsheet, Printer, Search, RefreshCw,
    Receipt, CheckCircle, AlertCircle, ShoppingBag,
    ArrowUpRight, User, Users, ShieldCheck, ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    settlement: {
        type: Object,
        required: true,
    },
    selectedDate: {
        type: String,
        required: true,
    },
    selectedCashierId: {
        type: [String, Number],
        default: 'all',
    },
    cashiers: {
        type: Array,
        default: () => [],
    },
    user: {
        type: Object,
        required: true,
    },
});

const filterDate = ref(props.selectedDate);
const filterCashierId = ref(props.selectedCashierId);
const itemSearchQuery = ref('');

// Print Modal State
const isPrintModalOpen = ref(false);
const printFormat = ref('thermal'); // 'thermal' | 'a4'

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(val || 0);
};

const numberToWords = (num) => {
    if (!num || isNaN(num)) return 'Nol Rupiah';
    const satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    
    function terbilang(n) {
        if (n < 12) return satuan[n];
        if (n < 20) return terbilang(n - 10) + ' Belas';
        if (n < 100) return terbilang(Math.floor(n / 10)) + ' Puluh ' + terbilang(n % 10);
        if (n < 200) return 'Seratus ' + terbilang(n - 100);
        if (n < 1000) return terbilang(Math.floor(n / 100)) + ' Ratus ' + terbilang(n % 100);
        if (n < 2000) return 'Seribu ' + terbilang(n - 1000);
        if (n < 1000000) return terbilang(Math.floor(n / 1000)) + ' Ribu ' + terbilang(n % 1000);
        if (n < 1000000000) return terbilang(Math.floor(n / 1000000)) + ' Juta ' + terbilang(n % 1000000);
        if (n < 1000000000000) return terbilang(Math.floor(n / 1000000000)) + ' Miliar ' + terbilang(n % 1000000000);
        return terbilang(Math.floor(n / 1000000000000)) + ' Triliun ' + terbilang(n % 1000000000000);
    }
    
    return (terbilang(num).trim().replace(/\s+/g, ' ') + ' Rupiah');
};

const applyFilter = () => {
    router.get(appRoute('/cashier/settlement'), {
        date: filterDate.value,
        cashier_id: filterCashierId.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const setToday = () => {
    const today = new Date().toISOString().split('T')[0];
    filterDate.value = today;
    applyFilter();
};

const selectCashier = (id) => {
    filterCashierId.value = id;
    applyFilter();
};

const filteredItems = computed(() => {
    const items = props.settlement.items_sold || [];
    if (!itemSearchQuery.value) return items;
    const q = itemSearchQuery.value.toLowerCase().trim();
    return items.filter(it => 
        (it.product_name && it.product_name.toLowerCase().includes(q)) ||
        (it.category_name && it.category_name.toLowerCase().includes(q))
    );
});

const exportExcelUrl = computed(() => {
    return `${appRoute('/cashier/settlement/export-excel')}?date=${filterDate.value}&cashier_id=${filterCashierId.value}`;
});

// Cetak Bukti Setoran Direct via Iframe
const printDirect = () => {
    const s = props.settlement;
    const isThermal = printFormat.value === 'thermal';

    let iframe = document.getElementById('cashier-settlement-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'cashier-settlement-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '300px';
        iframe.style.height = '300px';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
    }

    const doc = iframe.contentWindow.document;
    doc.open();

    const nowStr = new Date().toLocaleString('id-ID');
    const netDeposit = s.net_cash_deposit !== undefined ? s.net_cash_deposit : s.cash_total;
    const terbilangNetCash = numberToWords(netDeposit);

    let html = '';
    if (isThermal) {
        let itemsHtml = '';
        if (s.items_sold && s.items_sold.length > 0) {
            itemsHtml = s.items_sold.map(it => `
                <div style="margin-bottom: 3px;">
                    <div>${it.product_name}</div>
                    <div class="row" style="color: #444; font-size: 7.2pt;">
                        <span>${it.total_qty} ${it.unit_name} x ${formatRupiah(it.avg_price)}</span>
                        <span class="val" style="color:#000;">${formatRupiah(it.total_subtotal)}</span>
                    </div>
                </div>
            `).join('');
        } else {
            itemsHtml = '<div style="color:#777; text-align:center;">(Tidak ada rincian item)</div>';
        }

        html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Bukti Rekap Penjualan</title>
<style>
@page { margin: 0; size: auto; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
    font-size: 8pt;
    line-height: 1.25;
    padding: 3mm 2mm;
    color: #000;
    width: 48mm;
    max-width: 48mm;
}
.center { text-align: center; }
.bold { font-weight: bold; }
.dashed { border-bottom: 1px dashed #000; margin: 3px 0; padding-bottom: 3px; }
.row { display: flex; justify-content: space-between; margin-bottom: 2px; }
.val { font-weight: bold; text-align: right; }
.signatures { display: flex; justify-content: space-between; text-align: center; margin-top: 8px; font-size: 7pt; }
</style>
</head>
<body>
<div class="center dashed">
    <div class="bold" style="font-size: 9pt;">KOPERASI RSIA AISYIYAH</div>
    <div style="font-size: 7.5pt;">PEKAJANGAN - KANTIN</div>
    <div class="bold" style="margin-top: 2px; font-size: 8.5pt;">BUKTI REKAP PENJUALAN</div>
</div>
<div class="dashed" style="font-size: 7.5pt;">
    <div class="row"><span>Tanggal:</span><span class="val">${s.date}</span></div>
    <div class="row"><span>Kasir:</span><span class="val">${s.cashier_title || s.cashier_name}</span></div>
    <div class="row"><span>Jam Shift:</span><span class="val">${s.start_time} - ${s.end_time}</span></div>
    <div class="row"><span>Range Nota:</span><span class="val" style="font-size: 6.8pt;">${s.first_invoice || '-'} s/d ${s.last_invoice || '-'}</span></div>
    <div class="row"><span>Jml Trx:</span><span class="val">${s.transaction_count} Nota</span></div>
</div>
<div class="dashed">
    <div class="bold" style="margin-bottom: 2px;">REKONSILIASI KAS SETORAN:</div>
    <div class="row">
        <span>Penerimaan Tunai Kasir:</span>
        <span class="val">${formatRupiah(s.gross_cash_total || s.cash_total)}</span>
    </div>
    ${(s.consignment_paid_total > 0) ? `
    <div class="row" style="color: #b91c1c;">
        <span>(-) Bayar Titipan Sore:</span>
        <span class="val">(${formatRupiah(s.consignment_paid_total)})</span>
    </div>` : ''}
    <div class="row" style="background:#f0f0f0; padding: 2px 0; margin-top: 2px;">
        <span class="bold">1. SETOR FISIK RSIA:</span>
        <span class="val" style="font-size: 9pt;">${formatRupiah(netDeposit)}</span>
    </div>
    <div class="row" style="color: #555; font-size: 7.2pt;">
        <span>2. QRIS (Rekening RS):</span><span class="val">${formatRupiah(s.qris_total)}</span>
    </div>
    <div class="row" style="color: #555; font-size: 7.2pt;">
        <span>3. Transfer Bank:</span><span class="val">${formatRupiah(s.transfer_total)}</span>
    </div>
    <div class="row" style="color: #555; font-size: 7.2pt;">
        <span>4. Bon Pegawai:</span><span class="val">${formatRupiah(s.tempo_total)}</span>
    </div>
    <div class="row bold" style="border-top: 1px solid #000; padding-top: 2px; margin-top: 2px;">
        <span>TOTAL OMSET:</span><span class="val">${formatRupiah(s.total_net)}</span>
    </div>
</div>
<div class="dashed" style="font-size: 7pt; font-style: italic;">
    <div>Terbilang Wajib Setor Fisik:</div>
    <div class="bold">${terbilangNetCash}</div>
</div>
${(s.consignment_settled_list && s.consignment_settled_list.length > 0) ? `
<div class="dashed" style="font-size: 7pt;">
    <div class="bold" style="margin-bottom: 2px;">STRUK BAYAR TITIPAN SORE:</div>
    ${s.consignment_settled_list.map(c => `
        <div class="row">
            <span>${c.consignor_name} (${c.total_qty_sold}pcs)</span>
            <span class="val" style="color:#b91c1c;">${formatRupiah(c.total_payable)}</span>
        </div>
    `).join('')}
    <div class="row bold" style="border-top: 1px dashed #000; padding-top: 2px; margin-top: 2px;">
        <span>TOTAL TITIPAN:</span><span class="val" style="color:#b91c1c;">${formatRupiah(s.consignment_paid_total)}</span>
    </div>
</div>` : ''}
<div class="dashed">
    <div class="bold" style="margin-bottom: 2px;">RINCIAN BARANG TERJUAL:</div>
    ${itemsHtml}
    <div class="row bold" style="border-top: 1px dashed #000; padding-top: 2px; margin-top: 2px;">
        <span>TOTAL ITEM:</span><span class="val">${s.total_items_qty || 0} unit</span>
    </div>
</div>
<div class="signatures">
    <div>
        <div>Diserahkan,</div>
        <div style="height: 25px;"></div>
        <div class="bold">(${s.is_all_cashiers ? 'Kasir Bertugas' : s.cashier_name})</div>
        <div>Petugas Kasir</div>
    </div>
    <div>
        <div>Diterima,</div>
        <div style="height: 25px;"></div>
        <div class="bold">( ............ )</div>
        <div>Bag. Keuangan</div>
    </div>
</div>
<div class="center" style="font-size: 6.5pt; color: #555; margin-top: 5px;">
    Dicetak: ${nowStr}
</div>
</body>
</html>`;
    } else {
        // Format A4 Berita Acara
        let itemsA4Html = '';
        if (s.items_sold && s.items_sold.length > 0) {
            let no = 1;
            itemsA4Html = s.items_sold.map(it => `
                <tr>
                    <td style="text-align: center;">${no++}</td>
                    <td style="font-weight: bold;">${it.product_name}</td>
                    <td>${it.category_name}</td>
                    <td style="text-align: center;">${it.unit_name}</td>
                    <td style="text-align: center; font-weight: bold;">${it.total_qty}</td>
                    <td style="text-align: right;">${formatRupiah(it.avg_price)}</td>
                    <td class="num">${formatRupiah(it.total_subtotal)}</td>
                </tr>
            `).join('');
        } else {
            itemsA4Html = '<tr><td colspan="7" style="text-align: center; color: #64748b;">Tidak ada rincian item.</td></tr>';
        }

        // Breakdown per kasir jika mode Semua Kasir
        let cashierBreakdownA4 = '';
        if (s.cashier_breakdown && s.cashier_breakdown.length > 0) {
            let cbNo = 1;
            const cbRows = s.cashier_breakdown.map(cb => `
                <tr>
                    <td style="text-align: center;">${cbNo++}</td>
                    <td style="font-weight: bold;">${cb.cashier_name}</td>
                    <td style="text-align: center;">${cb.transaction_count} Nota</td>
                    <td class="num" style="color: #166534; font-weight: bold;">${formatRupiah(cb.cash_total)}</td>
                    <td class="num">${formatRupiah(cb.qris_total)}</td>
                    <td class="num">${formatRupiah(cb.tempo_total)}</td>
                    <td class="num" style="font-weight: bold;">${formatRupiah(cb.total_net)}</td>
                </tr>
            `).join('');

            cashierBreakdownA4 = `
                <div style="font-weight: 900; font-size: 10.5pt; margin-top: 14px; margin-bottom: 6px; color: #0f172a; text-transform: uppercase;">
                    RINCIAN KONTRIBUSI PER PETUGAS KASIR
                </div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">No</th>
                            <th>Nama Petugas Kasir</th>
                            <th style="width: 90px; text-align: center;">Jml Nota</th>
                            <th style="width: 120px; text-align: right;">Setor Tunai</th>
                            <th style="width: 100px; text-align: right;">QRIS Bank</th>
                            <th style="width: 100px; text-align: right;">Bon Pegawai</th>
                            <th style="width: 130px; text-align: right;">Total Omset</th>
                        </tr>
                    </thead>
                    <tbody>${cbRows}</tbody>
                </table>
            `;
        }

        html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Berita Acara Rekap Penjualan Kasir</title>
<style>
@page { margin: 15mm; size: A4 portrait; }
* { box-sizing: border-box; }
body {
    font-family: 'Segoe UI', Calibri, Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.4;
    color: #0f172a;
    padding: 10px;
}
.header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 16px; }
.title { font-size: 14pt; font-weight: 900; text-transform: uppercase; color: #166534; }
.subtitle { font-size: 11pt; font-weight: bold; color: #1e293b; }
.meta-table { width: 100%; margin-bottom: 16px; border-collapse: collapse; font-size: 9.5pt; }
.meta-table td { padding: 4px 6px; }
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.items-table th { background: #0f172a; color: #fff; font-weight: bold; padding: 8px 10px; border: 1px solid #0f172a; text-align: left; }
.items-table td { padding: 8px 10px; border: 1px solid #cbd5e1; }
.num { text-align: right; font-weight: bold; font-family: monospace; font-size: 10.5pt; }
.highlight-row { background: #f0fdf4; font-weight: bold; font-size: 11pt; }
.total-row { background: #0f172a; color: #fff; font-weight: 900; font-size: 11pt; }
.total-row td { border: 1px solid #0f172a; color: #fff; }
.terbilang-box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 9.5pt; }
.sign-table { width: 100%; margin-top: 25px; border-collapse: collapse; }
.sign-table td { width: 50%; text-align: center; vertical-align: top; }
</style>
</head>
<body>
<div class="header">
    <div class="title">KOPERASI RSIA AISYIYAH PEKAJANGAN</div>
    <div class="subtitle">BERITA ACARA REKAPITULASI PENJUALAN & SETORAN KASIR KANTIN</div>
    <div style="font-size: 9pt; color: #64748b;">Kantin RSIA Aisyiyah Pekajangan • Jl. Raya Karanganyar, Kebonsari, Pekalongan</div>
</div>

<table class="meta-table">
    <tr>
        <td style="width: 18%; font-weight: bold;">Hari / Tanggal</td>
        <td style="width: 32%;">: <strong>${s.formatted_date || s.date}</strong></td>
        <td style="width: 18%; font-weight: bold;">Kasir / Shift</td>
        <td style="width: 32%;">: <strong>${s.cashier_title || s.cashier_name}</strong></td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Jam Operasional</td>
        <td>: ${s.start_time} s/d ${s.end_time} WIB</td>
        <td style="font-weight: bold;">Jumlah Transaksi</td>
        <td>: <strong>${s.transaction_count} Transaksi (Nota)</strong></td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Rentang Nomor Faktur</td>
        <td>: ${s.first_invoice || '-'} s/d ${s.last_invoice || '-'}</td>
        <td style="font-weight: bold;">Waktu Cetak</td>
        <td>: ${nowStr} WIB</td>
    </tr>
</table>

<div style="font-weight: 900; font-size: 10.5pt; margin-bottom: 6px; color: #166534; text-transform: uppercase;">
    I. RINGKASAN SETORAN & PENERIMAAN KAS MASUK
</div>
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 45px; text-align: center;">No</th>
            <th>Klasifikasi Penerimaan Kasir</th>
            <th>Keterangan / Tujuan Rekonsiliasi</th>
            <th style="width: 180px; text-align: right;">Jumlah Nominal (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="highlight-row">
            <td style="text-align: center;">1</td>
            <td style="color: #166534;">UANG TUNAI / CASH (WAJIB SETOR FISIK)</td>
            <td style="font-size: 9pt; font-weight: normal; color: #166534;">Diserahkan tunai fisik ke Petugas Keuangan RSIA</td>
            <td class="num" style="color: #166534; font-size: 11.5pt;">${formatRupiah(s.cash_total)}</td>
        </tr>
        <tr>
            <td style="text-align: center;">2</td>
            <td>Pembayaran QRIS</td>
            <td style="font-size: 9pt; color: #64748b;">Langsung masuk ke rekening bank RSIA / Koperasi</td>
            <td class="num">${formatRupiah(s.qris_total)}</td>
        </tr>
        <tr>
            <td style="text-align: center;">3</td>
            <td>Pembayaran Transfer Bank</td>
            <td style="font-size: 9pt; color: #64748b;">Langsung masuk ke rekening bank RSIA / Koperasi</td>
            <td class="num">${formatRupiah(s.transfer_total)}</td>
        </tr>
        <tr>
            <td style="text-align: center;">4</td>
            <td>Bon / Piutang Pegawai RSIA (Tempo)</td>
            <td style="font-size: 9pt; color: #854d0e;">Ditagihkan / potong gaji payroll RSIA</td>
            <td class="num" style="color: #854d0e;">${formatRupiah(s.tempo_total)}</td>
        </tr>
        <tr class="total-row">
            <td colspan="3" style="text-align: right; text-transform: uppercase;">Total Penjualan Bersih (Omset) :</td>
            <td class="num">${formatRupiah(s.total_net)}</td>
        </tr>
    </tbody>
</table>

<div class="terbilang-box">
    <strong>Terbilang Uang Tunai yang Disetorkan:</strong><br>
    <span style="font-style: italic; color: #166534; font-weight: bold; font-size: 10.5pt;">"${terbilangCash}"</span>
</div>

${cashierBreakdownA4}

<div style="font-weight: 900; font-size: 10.5pt; margin-top: 14px; margin-bottom: 6px; color: #0f172a; text-transform: uppercase;">
    II. RINCIAN DETAIL PRODUK / MENU KANTIN TERJUAL
</div>
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 35px; text-align: center;">No</th>
            <th>Nama Produk / Menu Kantin</th>
            <th style="width: 120px;">Kategori</th>
            <th style="width: 60px; text-align: center;">Satuan</th>
            <th style="width: 55px; text-align: center;">Qty</th>
            <th style="width: 100px; text-align: right;">Harga Satuan</th>
            <th style="width: 120px; text-align: right;">Subtotal (Rp)</th>
        </tr>
    </thead>
    <tbody>
        ${itemsA4Html}
        <tr class="total-row">
            <td colspan="4" style="text-align: right;">TOTAL ITEM TERJUAL:</td>
            <td style="text-align: center;">${s.total_items_qty || 0}</td>
            <td></td>
            <td class="num">${formatRupiah(s.total_net)}</td>
        </tr>
    </tbody>
</table>

<table class="sign-table">
    <tr>
        <td>
            <p>Diserahkan oleh,</p>
            <p style="font-weight: bold; margin-top: 4px;">Kasir / Penanggung Jawab</p>
            <div style="height: 55px;"></div>
            <p style="font-weight: 900; text-decoration: underline;">( ${s.is_all_cashiers ? 'Petugas Kasir' : s.cashier_name} )</p>
            <p style="font-size: 8.5pt; color: #64748b;">Kantin RSIA</p>
        </td>
        <td>
            <p>Diterima & Diverifikasi oleh,</p>
            <p style="font-weight: bold; margin-top: 4px;">Bagian Keuangan RSIA</p>
            <div style="height: 55px;"></div>
            <p style="font-weight: 900; text-decoration: underline;">( .................................................... )</p>
            <p style="font-size: 8.5pt; color: #64748b;">Petugas Bagian Keuangan</p>
        </td>
    </tr>
</table>
</body>
</html>`;
    }

    doc.write(html);
    doc.close();

    setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }, 300);
};
</script>

<template>
    <Head title="Rekap Penjualan" />
    <MainLayout>
        <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
            
            <!-- Page Header & Filter Bar -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200/60">
                            <Banknote class="w-6 h-6" />
                        </div>
                        <div>
                            <h1 class="text-xl font-black text-slate-900 tracking-tight">
                                Rekap Penjualan
                            </h1>
                            <p class="text-xs text-slate-500">
                                Rekonsiliasi transaksi kasir, penerimaan uang setoran, dan rincian item terjual
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Controls: Date, Cashier, Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Cashier Selector Dropdown (Semua Kasir atau Kasir Tertentu) -->
                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1">
                        <Users class="w-4 h-4 text-emerald-700 shrink-0" />
                        <select 
                            v-model="filterCashierId"
                            @change="applyFilter"
                            class="text-xs font-bold bg-transparent text-slate-800 focus:outline-none cursor-pointer py-1 pr-1"
                        >
                            <option value="all">
                                📊 Semua Kasir (Rekap Harian Gabungan)
                            </option>
                            <option v-for="c in cashiers" :key="c.id" :value="c.id">
                                👤 Kasir: {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date Picker -->
                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl p-1">
                        <Calendar class="w-3.5 h-3.5 text-slate-400 ml-1.5" />
                        <input 
                            type="date"
                            v-model="filterDate"
                            @change="applyFilter"
                            class="text-xs font-bold bg-transparent text-slate-800 focus:outline-none pr-1 py-1 cursor-pointer"
                        />
                        <button 
                            @click="setToday" 
                            type="button"
                            class="px-2 py-1 text-[10px] font-bold bg-white text-emerald-700 hover:bg-emerald-50 rounded-lg border border-slate-200 shadow-2xs cursor-pointer transition active:scale-95"
                        >
                            Hari Ini
                        </button>
                    </div>

                    <!-- Tombol Cetak Bukti Setoran -->
                    <button 
                        @click="isPrintModalOpen = true"
                        type="button"
                        class="px-4 py-2 bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-md shadow-emerald-900/10 cursor-pointer transition active:scale-95"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Bukti Setoran</span>
                    </button>

                    <!-- Tombol Export Excel Keuangan RSIA -->
                    <a 
                        :href="exportExcelUrl"
                        target="_blank"
                        class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 flex items-center gap-1.5 shadow-2xs transition active:scale-95"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span class="hidden sm:inline">Export Excel</span>
                    </a>
                </div>
            </div>

            <!-- Shift Info Banner -->
            <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-4 sm:p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-amber-400 font-bold shrink-0">
                        <Clock class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold text-slate-300">Petugas / Shift:</span>
                            <span class="font-black text-amber-400 text-sm">{{ settlement.cashier_title || settlement.cashier_name }}</span>
                            <span class="text-[10px] bg-white/10 px-2 py-0.5 rounded-full text-slate-300 font-medium">
                                {{ settlement.formatted_date }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-300 mt-0.5 flex flex-wrap items-center gap-3">
                            <span>Jam Operasional: <strong class="text-white">{{ settlement.start_time }} - {{ settlement.end_time }} WIB</strong></span>
                            <span>•</span>
                            <span>Nota: <strong class="text-white">{{ settlement.transaction_count }} Trx</strong></span>
                            <span>•</span>
                            <span>Rentang Faktur: <code class="text-amber-300 text-[11px]">{{ settlement.first_invoice || '-' }} s/d {{ settlement.last_invoice || '-' }}</code></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-end md:self-auto">
                    <div class="text-right">
                        <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Total Penjualan Bersih</div>
                        <div class="text-xl sm:text-2xl font-black text-white font-mono tracking-tight">
                            {{ formatRupiah(settlement.total_net) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown 4 Cards (Opsi 1: Best Practice Rekonsiliasi Kasir) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Card 1: SETOR TUNAI BERSIH KE KEUANGAN RSIA (HERO) -->
                <div class="bg-gradient-to-br from-emerald-50 via-emerald-100/60 to-white border-2 border-emerald-500 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                                <Banknote class="w-4 h-4 text-emerald-700" />
                                1. Setor Fisik ke RSIA
                            </span>
                            <span class="px-2 py-0.5 bg-emerald-600 text-white text-[9px] font-black rounded-full uppercase tracking-wider shadow-2xs">
                                Wajib Fisik
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-emerald-950 font-mono tracking-tight">
                            {{ formatRupiah(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}
                        </div>
                        <div class="mt-1 text-[10px] text-emerald-800 font-medium">
                            Tunai Masuk: <strong>{{ formatRupiah(settlement.gross_cash_total || settlement.cash_total) }}</strong>
                            <span v-if="settlement.consignment_paid_total > 0" class="text-rose-700"> • Bayar Titipan: -{{ formatRupiah(settlement.consignment_paid_total) }}</span>
                        </div>
                        <div class="mt-1.5 text-[10px] text-emerald-800 italic line-clamp-2">
                            "{{ numberToWords(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}"
                        </div>
                    </div>
                </div>

                <!-- Card 2: Pengeluaran Bayar Titipan Sore (Konsinyasi) -->
                <div class="bg-gradient-to-br from-amber-50/60 via-amber-100/30 to-white border border-amber-300 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1">
                                <ShoppingBag class="w-4 h-4 text-amber-700" />
                                2. Bayar Titipan Sore
                            </span>
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-900 text-[9px] font-bold rounded-full border border-amber-300">
                                Lampiran Struk
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-amber-950 font-mono tracking-tight">
                            {{ formatRupiah(settlement.consignment_paid_total || 0) }}
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            {{ settlement.consignment_settled_list?.length || 0 }} penitip dilunasi kasir sore ini
                        </p>
                    </div>
                    <div class="text-[10px] text-amber-800 font-medium pt-2 border-t border-amber-200/60">
                        Struk pelunasan dilampirkan ke Keuangan
                    </div>
                </div>

                <!-- Card 3: Non-Tunai (QRIS & Transfer Bank) -->
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                                <QrCode class="w-4 h-4 text-blue-600" />
                                3. QRIS & Transfer Bank
                            </span>
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[9px] font-bold rounded-full border border-blue-200">
                                Rekening RS
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900 font-mono tracking-tight">
                            {{ formatRupiah(settlement.non_cash_total || (settlement.qris_total + settlement.transfer_total)) }}
                        </div>
                        <div class="mt-1 text-[10px] text-slate-500 flex items-center gap-2">
                            <span>QRIS: <strong class="text-slate-700 font-mono">{{ formatRupiah(settlement.qris_total) }}</strong></span>
                            <span>•</span>
                            <span>TF: <strong class="text-slate-700 font-mono">{{ formatRupiah(settlement.transfer_total) }}</strong></span>
                        </div>
                    </div>
                    <div class="text-[10px] text-slate-400 pt-2 border-t border-slate-100">
                        Otomatis masuk ke rekening RSIA
                    </div>
                </div>

                <!-- Card 4: Bon Pegawai RSIA (Tempo) -->
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1">
                                <Receipt class="w-4 h-4 text-amber-600" />
                                4. Bon Pegawai (Tempo)
                            </span>
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-800 text-[9px] font-bold rounded-full border border-amber-200">
                                Potong Gaji
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-amber-900 font-mono tracking-tight">
                            {{ formatRupiah(settlement.tempo_total) }}
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Piutang belanja pegawai rumah sakit
                        </p>
                    </div>
                    <div class="text-[10px] text-slate-400 pt-2 border-t border-slate-100">
                        Diserahkan ke SDM/Keuangan via potong gaji
                    </div>
                </div>

            </div>

            <!-- Rekonsiliasi Kas Bersih Kasir Banner (Penjelasan Pertanggungjawaban) -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-700 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-300 font-black text-[10px] uppercase rounded-full tracking-wider border border-emerald-500/30">
                            Rekonsiliasi Kas Laci Kasir
                        </span>
                        <span class="text-xs text-slate-300 font-medium">Model Setoran Bersih RSIA</span>
                    </div>
                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                        Uang tunai hasil penjualan jajan titipan digunakan kasir untuk <strong>melunasi hak penitip di sore hari</strong>. Kasir menyetorkan <strong>Uang Tunai Fisik Bersih</strong> ke Keuangan RSIA ditambah <strong>Lampiran Bukti Struk Pelunasan</strong>.
                    </p>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 flex-wrap bg-black/30 p-3 rounded-xl border border-white/10 text-xs shrink-0">
                    <div class="text-right">
                        <div class="text-[10px] text-slate-400">Penerimaan Kasir</div>
                        <div class="font-bold text-white font-mono">{{ formatRupiah(settlement.gross_cash_total || settlement.cash_total) }}</div>
                    </div>
                    <div class="text-slate-400 font-black">-</div>
                    <div class="text-right">
                        <div class="text-[10px] text-rose-300">Bayar Titipan</div>
                        <div class="font-bold text-rose-300 font-mono">({{ formatRupiah(settlement.consignment_paid_total || 0) }})</div>
                    </div>
                    <div class="text-slate-400 font-black">=</div>
                    <div class="text-right bg-emerald-600 px-3 py-1.5 rounded-lg shadow-sm">
                        <div class="text-[9px] text-emerald-100 font-bold uppercase tracking-wider">Setor Fisik ke RSIA</div>
                        <div class="text-sm font-black text-white font-mono">{{ formatRupiah(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}</div>
                    </div>
                </div>
            </div>

            <!-- Pemisahan Porsi Omzet Toko Sendiri vs Jajan Titipan Konsinyasi -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Box 1: Barang Kantin Sendiri -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-2xs">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                                <Layers class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Barang Toko Sendiri</h3>
                                <p class="text-[11px] text-slate-500">Barang inventaris & kulakan kantin RSIA</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 text-[10px] font-bold rounded-md">
                            100% Hak RSIA
                        </span>
                    </div>
                    <div class="text-xl font-black text-slate-900 font-mono">
                        {{ formatRupiah(settlement.own_products_sales || 0) }}
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Seluruh hasil penjualan barang ini murni menjadi pendapatan kas kantin RSIA.
                    </p>
                </div>

                <!-- Box 2: Jajan Titipan (Konsinyasi) -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-2xs">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                                <ShoppingBag class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Barang Titipan (Konsinyasi)</h3>
                                <p class="text-[11px] text-slate-500">Kue basah, jajan pasar & snack mitra luar</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 bg-amber-50 text-amber-900 text-[10px] font-bold rounded-md">
                            Bagi Hasil
                        </span>
                    </div>
                    <div class="text-xl font-black text-slate-900 font-mono">
                        {{ formatRupiah(settlement.consignment_sales || 0) }}
                    </div>
                    <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Porsi Hak Penitip: <strong class="text-rose-700 font-mono">{{ formatRupiah(settlement.consignment_payable || 0) }}</strong></span>
                        <span class="text-slate-500">Margin Kantin: <strong class="text-emerald-700 font-mono">+{{ formatRupiah(settlement.consignment_margin || 0) }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Tabel Rincian Pelunasan Titipan Sore Hari Ini (Lampiran Struk Kasir) -->
            <div v-if="settlement.consignment_settled_list && settlement.consignment_settled_list.length > 0" class="bg-white border border-amber-200/80 rounded-2xl shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 bg-gradient-to-r from-amber-50/70 to-white border-b border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-amber-950 flex items-center gap-2">
                            <Receipt class="w-4 h-4 text-amber-700" />
                            <span>Lampiran Struk: Rincian Pelunasan Jajan Titipan Sore</span>
                        </h2>
                        <p class="text-xs text-amber-800/80 mt-0.5">
                            Daftar pengeluaran kas laci kasir untuk membayar penitip kue sore ini (struk dilampirkan ke Keuangan)
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-slate-500 uppercase font-semibold">Total Titipan Dilunasi:</div>
                        <div class="text-base font-black text-rose-700 font-mono">
                            {{ formatRupiah(settlement.consignment_paid_total) }}
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-amber-50/50 text-amber-900 border-b border-amber-100 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 w-12 text-center">No</th>
                                <th class="py-2.5 px-4">No Batch Titipan</th>
                                <th class="py-2.5 px-4">Nama Penitip</th>
                                <th class="py-2.5 px-4 text-center">Jam Bayar</th>
                                <th class="py-2.5 px-4">Kasir Pembayar</th>
                                <th class="py-2.5 px-4 text-center">Pcs Terjual</th>
                                <th class="py-2.5 px-4 text-right">Uang Dibayar (Rp)</th>
                                <th class="py-2.5 px-4 text-right">Laba Kantin (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="(cs, idx) in settlement.consignment_settled_list" :key="cs.id" class="hover:bg-amber-50/30 transition">
                                <td class="py-3 px-4 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ cs.batch_number }}</td>
                                <td class="py-3 px-4 font-black text-slate-900">{{ cs.consignor_name }}</td>
                                <td class="py-3 px-4 text-center font-mono text-slate-600">{{ cs.settlement_time }} WIB</td>
                                <td class="py-3 px-4 text-slate-600">{{ cs.cashier_name }}</td>
                                <td class="py-3 px-4 text-center font-bold text-blue-700">{{ cs.total_qty_sold }} pcs</td>
                                <td class="py-3 px-4 text-right font-mono font-black text-rose-700 bg-rose-50/30">
                                    {{ formatRupiah(cs.total_payable) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                                    +{{ formatRupiah(cs.total_canteen_profit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Tambahan jika Memilih Rekap Semua Kasir: Breakdown per Kasir -->
            <div v-if="settlement.is_all_cashiers && settlement.cashier_breakdown && settlement.cashier_breakdown.length > 0" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <Users class="w-4 h-4 text-emerald-700" />
                            <span>Kontribusi Penjualan per Petugas Kasir Hari Ini</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Rincian uang kas dan metode pembayaran masing-masing kasir yang bertugas
                        </p>
                    </div>
                    <span class="text-xs font-bold text-slate-400">
                        {{ settlement.cashier_breakdown.length }} Kasir Aktif
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 border-b border-slate-100 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 w-12 text-center">No</th>
                                <th class="py-2.5 px-4">Nama Petugas Kasir</th>
                                <th class="py-2.5 px-4 text-center">Jml Nota</th>
                                <th class="py-2.5 px-4 text-right">Tunai Bruto</th>
                                <th class="py-2.5 px-4 text-right">Bayar Titipan</th>
                                <th class="py-2.5 px-4 text-right">Net Setor Fisik</th>
                                <th class="py-2.5 px-4 text-right">Non-Tunai</th>
                                <th class="py-2.5 px-4 text-right">Total Omset Kasir</th>
                                <th class="py-2.5 px-4 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="(cb, idx) in settlement.cashier_breakdown" :key="cb.cashier_id" class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                <td class="py-3 px-4 font-black text-slate-900">{{ cb.cashier_name }}</td>
                                <td class="py-3 px-4 text-center font-bold text-slate-600">{{ cb.transaction_count }}</td>
                                <td class="py-3 px-4 text-right font-mono text-slate-700">
                                    {{ formatRupiah(cb.cash_total) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-rose-700">
                                    <span v-if="cb.consignment_paid > 0">({{ formatRupiah(cb.consignment_paid) }})</span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-black text-emerald-800 bg-emerald-50/50">
                                    {{ formatRupiah(cb.net_cash_deposit !== undefined ? cb.net_cash_deposit : cb.cash_total) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-700">{{ formatRupiah(cb.qris_total + cb.transfer_total) }}</td>
                                <td class="py-3 px-4 text-right font-mono font-black text-slate-900">{{ formatRupiah(cb.total_net) }}</td>
                                <td class="py-3 px-4 text-center">
                                    <button 
                                        @click="selectCashier(cb.cashier_id)"
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 text-slate-600 font-bold rounded-lg text-[10px] transition cursor-pointer"
                                    >
                                        Filter Kasir Ini
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detail Penjualan Item (Itemized Sales Breakdown) -->
            <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-black text-slate-900">
                                Detail Penjualan Item Terjual
                            </h2>
                            <span class="px-2.5 py-0.5 bg-slate-100 text-slate-700 text-xs font-black rounded-full">
                                {{ settlement.total_items_count || 0 }} Menu / Produk
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Rincian kuantitas dan nominal penjualan per produk selama periode/shift kasir ini
                        </p>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="itemSearchQuery"
                            type="text"
                            placeholder="Cari nama menu / produk..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 border-b border-slate-100 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4">Nama Produk / Menu Kantin</th>
                                <th class="py-3 px-4">Kategori</th>
                                <th class="py-3 px-4 text-center">Satuan</th>
                                <th class="py-3 px-4 text-right">Harga Satuan</th>
                                <th class="py-3 px-4 text-center">Qty Terjual</th>
                                <th class="py-3 px-4 text-right">Subtotal Omset</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr 
                                v-for="(item, idx) in filteredItems" 
                                :key="item.product_id + '-' + item.unit_name"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <td class="py-3 px-4 text-center font-mono text-slate-400">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-black text-slate-900 block text-xs">
                                        {{ item.product_name }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md text-[10px] font-bold">
                                        {{ item.category_name }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-slate-600 font-bold uppercase text-[11px]">
                                        {{ item.unit_name }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-600">
                                    {{ formatRupiah(item.avg_price) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-900 border border-amber-200/80 rounded-lg font-black text-xs">
                                        {{ item.total_qty }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                                    {{ formatRupiah(item.total_subtotal) }}
                                </td>
                            </tr>

                            <tr v-if="filteredItems.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <ShoppingBag class="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                    <p class="font-bold text-xs">Tidak ada data penjualan pada tanggal ini.</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Pilih tanggal lain atau ganti filter kasir.</p>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredItems.length > 0" class="bg-slate-900 text-white font-black text-xs">
                            <tr>
                                <td colspan="5" class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-slate-300">
                                    Total Penjualan Shift / Harian :
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-amber-400 text-sm">
                                    {{ settlement.total_items_qty || 0 }} unit
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-white text-sm">
                                    {{ formatRupiah(settlement.total_net) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        <!-- Modal Cetak Bukti Setoran Kasir -->
        <div 
            v-if="isPrintModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs overflow-y-auto"
            @click.self="isPrintModalOpen = false"
        >
            <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh] border border-slate-200">
                
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 bg-emerald-100 text-emerald-800 rounded-xl">
                            <Printer class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Cetak Bukti Rekap Penjualan</h3>
                            <p class="text-[11px] text-slate-500">Pilih format struk kasir atau berita acara resmi A4</p>
                        </div>
                    </div>

                    <!-- Print Format Selector Tabs -->
                    <div class="flex items-center bg-slate-200/80 p-1 rounded-xl text-xs font-bold">
                        <button 
                            @click="printFormat = 'thermal'"
                            :class="printFormat === 'thermal' ? 'bg-white text-emerald-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-lg transition cursor-pointer"
                        >
                            🧾 Struk Kasir (Thermal)
                        </button>
                        <button 
                            @click="printFormat = 'a4'"
                            :class="printFormat === 'a4' ? 'bg-white text-emerald-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3 py-1 rounded-lg transition cursor-pointer"
                        >
                            📄 Berita Acara (A4)
                        </button>
                    </div>
                </div>

                <!-- Modal Preview Body -->
                <div class="p-5 overflow-y-auto flex-1 bg-slate-100/60 flex justify-center">
                    
                    <!-- Preview A4 -->
                    <div v-if="printFormat === 'a4'" class="w-full max-w-xl bg-white p-6 rounded-2xl shadow-xs border border-slate-200 text-xs space-y-4 text-slate-800">
                        <div class="border-b-2 border-slate-900 pb-3 text-center">
                            <h2 class="text-sm font-black text-emerald-900">KOPERASI RSIA AISYIYAH PEKAJANGAN</h2>
                            <p class="text-xs font-bold text-slate-800">BERITA ACARA REKAPITULASI PENJUALAN & SETORAN KASIR</p>
                            <p class="text-[10px] text-slate-500">Kantin RSIA • Tanggal: {{ settlement.formatted_date }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
                            <div>Kasir: <strong class="text-slate-900">{{ settlement.cashier_title || settlement.cashier_name }}</strong></div>
                            <div>Shift: <strong class="text-slate-900">{{ settlement.start_time }} - {{ settlement.end_time }} WIB</strong></div>
                            <div>Total Nota: <strong class="text-slate-900">{{ settlement.transaction_count }} Trx</strong></div>
                            <div>Faktur: <span class="font-mono text-[10px]">{{ settlement.first_invoice || '-' }} s/d {{ settlement.last_invoice || '-' }}</span></div>
                        </div>

                        <!-- Table Summary -->
                        <div>
                            <div class="font-black text-[11px] text-emerald-900 uppercase mb-1.5">I. Ringkasan Kas & Penerimaan Kasir</div>
                            <table class="w-full text-left border-collapse border border-slate-200 text-[11px]">
                                <thead class="bg-slate-900 text-white font-bold">
                                    <tr>
                                        <th class="p-2 w-10 text-center">No</th>
                                        <th class="p-2">Metode Penerimaan</th>
                                        <th class="p-2 text-right">Nominal (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr>
                                        <td class="p-2 text-center">1</td>
                                        <td class="p-2 font-medium">Penerimaan Tunai Kasir (Gross Cash)</td>
                                        <td class="p-2 text-right font-mono">{{ formatRupiah(settlement.gross_cash_total || settlement.cash_total) }}</td>
                                    </tr>
                                    <tr class="bg-rose-50/50 text-rose-800">
                                        <td class="p-2 text-center">2</td>
                                        <td class="p-2">(-) Pengeluaran Bayar Titipan Sore (Struk Terlampir)</td>
                                        <td class="p-2 text-right font-mono font-bold">({{ formatRupiah(settlement.consignment_paid_total || 0) }})</td>
                                    </tr>
                                    <tr class="bg-emerald-50/90 font-black text-emerald-950">
                                        <td class="p-2 text-center">=</td>
                                        <td class="p-2 uppercase tracking-wide">TOTAL UANG FISIK WAJIB DISETOR KE RSIA</td>
                                        <td class="p-2 text-right font-mono text-sm text-emerald-900">{{ formatRupiah(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 text-center">2</td>
                                        <td class="p-2">QRIS Bank (Masuk Rekening RSIA)</td>
                                        <td class="p-2 text-right font-mono font-bold">{{ formatRupiah(settlement.qris_total) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 text-center">3</td>
                                        <td class="p-2">Transfer Bank</td>
                                        <td class="p-2 text-right font-mono font-bold">{{ formatRupiah(settlement.transfer_total) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 text-center">4</td>
                                        <td class="p-2">Bon Pegawai RSIA (Tempo)</td>
                                        <td class="p-2 text-right font-mono font-bold text-amber-800">{{ formatRupiah(settlement.tempo_total) }}</td>
                                    </tr>
                                    <tr class="bg-slate-900 text-white font-black">
                                        <td colspan="2" class="p-2 text-right uppercase">Total Penjualan Bersih :</td>
                                        <td class="p-2 text-right font-mono">{{ formatRupiah(settlement.total_net) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2.5 bg-emerald-50/80 border border-emerald-200 rounded-xl text-[11px]">
                            <span class="text-emerald-900 font-bold block">Terbilang Uang Tunai yang Disetorkan:</span>
                            <span class="text-emerald-950 font-black italic block">"{{ numberToWords(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}"</span>
                        </div>

                        <!-- Table Items Preview -->
                        <div>
                            <div class="font-black text-[11px] text-slate-900 uppercase mb-1.5">II. Rincian Produk Terjual</div>
                            <div class="max-h-40 overflow-y-auto border border-slate-200 rounded-xl">
                                <table class="w-full text-left border-collapse text-[10px]">
                                    <thead class="bg-slate-100 text-slate-700 sticky top-0">
                                        <tr>
                                            <th class="p-1.5 w-8 text-center">No</th>
                                            <th class="p-1.5">Nama Produk</th>
                                            <th class="p-1.5 text-center">Satuan</th>
                                            <th class="p-1.5 text-center">Qty</th>
                                            <th class="p-1.5 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(it, idx) in settlement.items_sold" :key="idx">
                                            <td class="p-1.5 text-center">{{ idx + 1 }}</td>
                                            <td class="p-1.5 font-bold">{{ it.product_name }}</td>
                                            <td class="p-1.5 text-center uppercase">{{ it.unit_name }}</td>
                                            <td class="p-1.5 text-center font-bold">{{ it.total_qty }}</td>
                                            <td class="p-1.5 text-right font-mono">{{ formatRupiah(it.total_subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-center text-[11px] pt-3 border-t border-slate-200">
                            <div>
                                <p class="text-slate-500">Diserahkan oleh,</p>
                                <p class="font-bold text-slate-800">Kasir / Penanggung Jawab</p>
                                <div class="h-10"></div>
                                <p class="font-black text-slate-900 underline">( {{ settlement.is_all_cashiers ? 'Petugas Kasir' : settlement.cashier_name }} )</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Diterima oleh,</p>
                                <p class="font-bold text-slate-800">Bagian Keuangan RSIA</p>
                                <div class="h-10"></div>
                                <p class="font-black text-slate-900 underline">( ........................................ )</p>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Thermal Receipt -->
                    <div v-else class="w-72 bg-white p-4 font-mono text-[11px] space-y-2 border border-slate-300 shadow-xs">
                        <div class="text-center space-y-0.5">
                            <h3 class="font-black text-xs">KOPERASI RSIA AISYIYAH</h3>
                            <p class="text-[9px] text-slate-500">PEKAJANGAN - KANTIN</p>
                            <p class="text-[10px] font-bold text-slate-800 border-t border-b border-dashed border-slate-400 py-1 my-1">
                                BUKTI REKAP PENJUALAN
                            </p>
                        </div>

                        <div class="space-y-0.5 text-[10px]">
                            <div class="flex justify-between"><span>Tanggal:</span> <strong>{{ settlement.date }}</strong></div>
                            <div class="flex justify-between"><span>Kasir:</span> <strong>{{ settlement.cashier_title || settlement.cashier_name }}</strong></div>
                            <div class="flex justify-between"><span>Jam Shift:</span> <strong>{{ settlement.start_time }} - {{ settlement.end_time }}</strong></div>
                            <div class="flex justify-between"><span>Range Nota:</span> <span class="font-bold truncate text-[9px]">{{ settlement.first_invoice || '-' }} s/d {{ settlement.last_invoice || '-' }}</span></div>
                            <div class="flex justify-between"><span>Total Nota:</span> <strong>{{ settlement.transaction_count }}</strong></div>
                        </div>

                        <div class="border-t border-dashed border-slate-400 my-1.5"></div>

                        <div class="space-y-1 text-[10px]">
                            <div class="flex justify-between">
                                <span>Penerimaan Kasir:</span>
                                <span>{{ formatRupiah(settlement.gross_cash_total || settlement.cash_total) }}</span>
                            </div>
                            <div v-if="settlement.consignment_paid_total > 0" class="flex justify-between text-rose-700 font-medium">
                                <span>(-) Bayar Titipan:</span>
                                <span>({{ formatRupiah(settlement.consignment_paid_total) }})</span>
                            </div>
                            <div class="flex justify-between bg-emerald-50 p-1 rounded font-black text-emerald-900">
                                <span>SETOR FISIK RSIA:</span>
                                <span class="text-xs">{{ formatRupiah(settlement.net_cash_deposit !== undefined ? settlement.net_cash_deposit : settlement.cash_total) }}</span>
                            </div>
                            <div class="flex justify-between"><span>2. QRIS (Bank):</span> <span>{{ formatRupiah(settlement.qris_total) }}</span></div>
                            <div class="flex justify-between"><span>3. Transfer:</span> <span>{{ formatRupiah(settlement.transfer_total) }}</span></div>
                            <div class="flex justify-between"><span>4. Bon Pegawai:</span> <span>{{ formatRupiah(settlement.tempo_total) }}</span></div>
                            <div class="flex justify-between font-black pt-1 border-t border-slate-200">
                                <span>TOTAL OMSET:</span>
                                <span>{{ formatRupiah(settlement.total_net) }}</span>
                            </div>
                        </div>

                        <div class="border-t border-dashed border-slate-400 my-1.5"></div>

                        <!-- Items in Thermal Preview -->
                        <div class="text-[9.5px] space-y-1">
                            <div class="font-bold text-[10px]">RINCIAN BARANG:</div>
                            <div v-for="(it, idx) in settlement.items_sold" :key="idx" class="space-y-0.5">
                                <div class="truncate">{{ it.product_name }}</div>
                                <div class="flex justify-between text-slate-500 text-[9px]">
                                    <span>{{ it.total_qty }} {{ it.unit_name }} x {{ formatRupiah(it.avg_price) }}</span>
                                    <span class="text-slate-900 font-bold">{{ formatRupiah(it.total_subtotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-dashed border-slate-400 my-2"></div>

                        <div class="grid grid-cols-2 text-center text-[9px] gap-2 pt-1">
                            <div>
                                <span>Diserahkan,</span>
                                <div class="h-6"></div>
                                <span class="font-bold">({{ settlement.is_all_cashiers ? 'Petugas Kasir' : settlement.cashier_name }})</span>
                            </div>
                            <div>
                                <span>Diterima,</span>
                                <div class="h-6"></div>
                                <span class="font-bold">(Keuangan)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-slate-100 flex justify-end gap-2 bg-white">
                    <button 
                        @click="printDirect"
                        type="button"
                        class="px-5 py-2.5 bg-emerald-800 hover:bg-emerald-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition active:scale-95"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Bukti Setoran</span>
                    </button>
                    <button 
                        @click="isPrintModalOpen = false"
                        type="button"
                        class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer hover:bg-slate-200 transition"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </MainLayout>
</template>
