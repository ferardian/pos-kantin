<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { appRoute } from '@/Utils/route';
import { 
    Banknote, QrCode, CreditCard, Clock, Calendar, 
    FileSpreadsheet, Printer, Search, RefreshCw,
    Receipt, CheckCircle, AlertCircle, ShoppingBag,
    ArrowUpRight, User, ShieldCheck, ChevronRight
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
        type: Number,
        required: true,
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
    const terbilangCash = numberToWords(s.cash_total);

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
<title>Bukti Setoran Kasir</title>
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
    <div class="bold" style="margin-top: 2px; font-size: 8.5pt;">BUKTI SETORAN KASIR</div>
</div>
<div class="dashed" style="font-size: 7.5pt;">
    <div class="row"><span>Tanggal:</span><span class="val">${s.date}</span></div>
    <div class="row"><span>Kasir:</span><span class="val">${s.cashier_name}</span></div>
    <div class="row"><span>Jam Shift:</span><span class="val">${s.start_time} - ${s.end_time}</span></div>
    <div class="row"><span>Range Nota:</span><span class="val" style="font-size: 6.8pt;">${s.first_invoice || '-'} s/d ${s.last_invoice || '-'}</span></div>
    <div class="row"><span>Jml Trx:</span><span class="val">${s.transaction_count} Nota</span></div>
</div>
<div class="dashed">
    <div class="bold" style="margin-bottom: 2px;">RINCIAN PEMBAYARAN:</div>
    <div class="row" style="background:#f0f0f0; padding: 2px 0;">
        <span class="bold">1. SETOR TUNAI:</span>
        <span class="val" style="font-size: 9pt;">${formatRupiah(s.cash_total)}</span>
    </div>
    <div class="row"><span>2. QRIS (Bank):</span><span class="val">${formatRupiah(s.qris_total)}</span></div>
    <div class="row"><span>3. Transfer:</span><span class="val">${formatRupiah(s.transfer_total)}</span></div>
    <div class="row"><span>4. Bon Pegawai:</span><span class="val">${formatRupiah(s.tempo_total)}</span></div>
    <div class="row bold" style="border-top: 1px solid #000; padding-top: 2px; margin-top: 2px;">
        <span>TOTAL OMSET:</span><span class="val">${formatRupiah(s.total_net)}</span>
    </div>
</div>
<div class="dashed" style="font-size: 7pt; font-style: italic;">
    <div>Terbilang Setor Tunai:</div>
    <div class="bold">${terbilangCash}</div>
</div>
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
        <div class="bold">(${s.cashier_name})</div>
        <div>Kasir</div>
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

        html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Berita Acara Setoran Kasir</title>
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
.terbilang-box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 9.5pt; }
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
        <td style="width: 18%; font-weight: bold;">Kasir Bertugas</td>
        <td style="width: 32%;">: <strong>${s.cashier_name}</strong></td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Jam Shift Tugas</td>
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
    I. RINGKASAN SETORAN & KAS MASUK KASIR
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
            <td colspan="3" style="text-align: right; text-transform: uppercase;">Total Penjualan Bersih Kasir (Omset Shift) :</td>
            <td class="num">${formatRupiah(s.total_net)}</td>
        </tr>
    </tbody>
</table>

<div class="terbilang-box">
    <strong>Terbilang Uang Tunai yang Disetorkan Kasir:</strong><br>
    <span style="font-style: italic; color: #166534; font-weight: bold; font-size: 10.5pt;">"${terbilangCash}"</span>
</div>

<div style="font-weight: 900; font-size: 10.5pt; margin-bottom: 6px; color: #0f172a; text-transform: uppercase;">
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
            <p style="font-weight: bold; margin-top: 4px;">Kasir Bertugas</p>
            <div style="height: 55px;"></div>
            <p style="font-weight: 900; text-decoration: underline;">( ${s.cashier_name} )</p>
            <p style="font-size: 8.5pt; color: #64748b;">Kasir Kantin RSIA</p>
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
    <Head title="Rekap Setoran & Penjualan Shift Kasir" />
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
                                Rekap Setoran & Penjualan Shift
                            </h1>
                            <p class="text-xs text-slate-500">
                                Rekonsiliasi setoran uang kasir ke Keuangan RSIA & Rincian Produk Terjual
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Controls: Date, Cashier, Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Cashier Dropdown (Admin Only) -->
                    <div v-if="user.role === 'admin' && cashiers.length > 0" class="flex items-center gap-1.5">
                        <User class="w-4 h-4 text-slate-400" />
                        <select 
                            v-model="filterCashierId"
                            @change="applyFilter"
                            class="text-xs font-bold border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option v-for="c in cashiers" :key="c.id" :value="c.id">
                                Kasir: {{ c.name }}
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
                            class="text-xs font-bold bg-transparent text-slate-800 focus:outline-none pr-1 py-1"
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
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-300">Kasir:</span>
                            <span class="font-black text-amber-400 text-sm">{{ settlement.cashier_name }}</span>
                            <span class="text-[10px] bg-white/10 px-2 py-0.5 rounded-full text-slate-300 font-medium">
                                {{ settlement.formatted_date }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-300 mt-0.5 flex flex-wrap items-center gap-3">
                            <span>Jam Shift: <strong class="text-white">{{ settlement.start_time }} - {{ settlement.end_time }} WIB</strong></span>
                            <span>•</span>
                            <span>Nota: <strong class="text-white">{{ settlement.transaction_count }} Trx</strong></span>
                            <span>•</span>
                            <span>Rentang Faktur: <code class="text-amber-300 text-[11px]">{{ settlement.first_invoice || '-' }} s/d {{ settlement.last_invoice || '-' }}</code></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 self-end md:self-auto">
                    <div class="text-right">
                        <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Total Omset Shift</div>
                        <div class="text-xl sm:text-2xl font-black text-white font-mono tracking-tight">
                            {{ formatRupiah(settlement.total_net) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown 4 Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Card 1: SETOR TUNAI FISIK (HERO) -->
                <div class="bg-gradient-to-br from-emerald-50 via-emerald-100/50 to-white border-2 border-emerald-500 rounded-2xl p-5 shadow-sm relative overflow-hidden flex flex-col justify-between">
                    <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                                <Banknote class="w-4 h-4 text-emerald-700" />
                                1. Setoran Tunai (Cash)
                            </span>
                            <span class="px-2 py-0.5 bg-emerald-600 text-white text-[9px] font-black rounded-full uppercase tracking-wider">
                                Wajib Fisik
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-emerald-950 font-mono tracking-tight">
                            {{ formatRupiah(settlement.cash_total) }}
                        </div>
                        <div class="mt-1.5 text-[11px] text-emerald-800 italic line-clamp-2">
                            "{{ numberToWords(settlement.cash_total) }}"
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-emerald-200/80 text-[11px] text-emerald-700 font-medium">
                        Uang fisik yang harus diserahkan ke Petugas Keuangan RSIA.
                    </div>
                </div>

                <!-- Card 2: QRIS Bank -->
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                                <QrCode class="w-4 h-4 text-blue-600" />
                                2. QRIS Bank
                            </span>
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[9px] font-bold rounded-full border border-blue-200">
                                Rekening RS
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900 font-mono tracking-tight">
                            {{ formatRupiah(settlement.qris_total) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Masuk rekening Bank Koperasi / RSIA
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                        Otomatis terekonsiliasi via mutasi bank QRIS.
                    </div>
                </div>

                <!-- Card 3: Transfer Bank -->
                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                                <CreditCard class="w-4 h-4 text-purple-600" />
                                3. Transfer Bank
                            </span>
                            <span class="px-2 py-0.5 bg-purple-50 text-purple-700 text-[9px] font-bold rounded-full border border-purple-200">
                                Rekening RS
                            </span>
                        </div>
                        <div class="mt-3 text-2xl font-black text-slate-900 font-mono tracking-tight">
                            {{ formatRupiah(settlement.transfer_total) }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            Masuk rekening Koperasi / RSIA
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                        Bukti transfer diverifikasi bagian administrasi.
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
                        <div class="mt-1 text-xs text-slate-500">
                            Piutang karyawan RSIA Aisyiyah
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                        Dipotong saat penggajian payroll bulanan.
                    </div>
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
                            Rincian kuantitas dan nominal penjualan per produk selama shift kasir ini
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
                                    <p class="text-[11px] text-slate-400 mt-0.5">Pilih tanggal lain atau pastikan transaksi kasir telah selesai.</p>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredItems.length > 0" class="bg-slate-900 text-white font-black text-xs">
                            <tr>
                                <td colspan="5" class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-slate-300">
                                    Total Penjualan Shift :
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
                            <h3 class="text-sm font-black text-slate-900">Cetak Bukti Setoran Shift</h3>
                            <p class="text-[11px] text-slate-500">Pilih format cetak struk kasir atau berita acara A4</p>
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
                            <div>Kasir: <strong class="text-slate-900">{{ settlement.cashier_name }}</strong></div>
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
                                    <tr class="bg-emerald-50/80 font-bold text-emerald-950">
                                        <td class="p-2 text-center">1</td>
                                        <td class="p-2">UANG TUNAI / CASH (WAJIB SETOR FISIK)</td>
                                        <td class="p-2 text-right font-mono">{{ formatRupiah(settlement.cash_total) }}</td>
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
                                        <td colspan="2" class="p-2 text-right uppercase">Total Omset Shift :</td>
                                        <td class="p-2 text-right font-mono">{{ formatRupiah(settlement.total_net) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2.5 bg-emerald-50/80 border border-emerald-200 rounded-xl text-[11px]">
                            <span class="text-emerald-900 font-bold block">Terbilang Uang Tunai yang Disetorkan:</span>
                            <span class="text-emerald-950 font-black italic block">"{{ numberToWords(settlement.cash_total) }}"</span>
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
                                <p class="font-bold text-slate-800">Kasir Bertugas</p>
                                <div class="h-10"></div>
                                <p class="font-black text-slate-900 underline">( {{ settlement.cashier_name }} )</p>
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
                                BUKTI SETORAN KASIR
                            </p>
                        </div>

                        <div class="space-y-0.5 text-[10px]">
                            <div class="flex justify-between"><span>Tanggal:</span> <strong>{{ settlement.date }}</strong></div>
                            <div class="flex justify-between"><span>Kasir:</span> <strong>{{ settlement.cashier_name }}</strong></div>
                            <div class="flex justify-between"><span>Jam Shift:</span> <strong>{{ settlement.start_time }} - {{ settlement.end_time }}</strong></div>
                            <div class="flex justify-between"><span>Range Nota:</span> <span class="font-bold truncate text-[9px]">{{ settlement.first_invoice || '-' }} s/d {{ settlement.last_invoice || '-' }}</span></div>
                            <div class="flex justify-between"><span>Total Nota:</span> <strong>{{ settlement.transaction_count }}</strong></div>
                        </div>

                        <div class="border-t border-dashed border-slate-400 my-1.5"></div>

                        <div class="space-y-1 text-[10px]">
                            <div class="flex justify-between bg-slate-100 p-1 rounded font-bold">
                                <span>1. SETOR TUNAI:</span>
                                <span class="text-emerald-700 text-xs">{{ formatRupiah(settlement.cash_total) }}</span>
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
                                <span class="font-bold">({{ settlement.cashier_name }})</span>
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
