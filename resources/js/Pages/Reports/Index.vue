<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    BarChart3, DollarSign, TrendingUp, Users, Clock, 
    ArrowUpRight, Package, UserCheck, CheckCircle2, 
    Calendar, Filter, Search, ShieldCheck, ShoppingCart, 
    Truck, FileText, CreditCard, Sparkles, Activity,
    Download, Printer, FileSpreadsheet, FileDown, Layers,
    Receipt, ArrowDownRight, Tag, CheckCircle, X, Phone,
    Building2, Wallet, ArrowRightLeft, RefreshCw
} from 'lucide-vue-next';

const props = defineProps({
    filters: Object,
    periodSalesTotal: Number,
    periodCostTotal: Number,
    periodProfitTotal: Number,
    periodProfitMargin: Number,
    periodTransactionsCount: Number,
    cashTotal: Number,
    transferTotal: Number,
    tempoTotal: Number,
    totalSalesToday: Number,
    totalSalesMonth: Number,
    totalActiveDebts: Number,
    pendingOrdersCount: Number,
    categoryBreakdown: Array,
    brandBreakdown: Array,
    salesLeaderboard: Array,
    topProducts: Array,
    salesTransactions: Array,
    allActivities: Array,
    allUsers: Array,
    user: Object,
});

const page = usePage();
const settings = computed(() => page.props.settings || {});

const activeTab = ref('sales'); // 'sales', 'categories', 'performance', 'products', 'audit'
const searchQuery = ref('');
const filterStartDate = ref(props.filters?.start_date || new Date().toISOString().split('T')[0]);
const filterEndDate = ref(props.filters?.end_date || new Date().toISOString().split('T')[0]);
const filterPayment = ref(props.filters?.payment_method || 'all');
const filterCashier = ref(props.filters?.cashier_id || 'all');

// Reprint Receipt Modal State
const isReceiptOpen = ref(false);
const selectedPrintFormat = ref('invoice'); // 'thermal', 'dot_matrix', 'invoice'
const lastTransaction = ref(null);

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const getTierLabel = (tier) => {
    const map = {
        'eceran': 'Retail',
        'tukang': 'Bronze',
        'kontraktor': 'Gold',
        'grosir': 'Diamond'
    };
    return map[tier] || tier || 'Retail';
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

// Filter presets
const setDatePreset = (preset) => {
    const today = new Date();
    if (preset === 'today') {
        const d = today.toISOString().split('T')[0];
        filterStartDate.value = d;
        filterEndDate.value = d;
    } else if (preset === 'yesterday') {
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        const d = y.toISOString().split('T')[0];
        filterStartDate.value = d;
        filterEndDate.value = d;
    } else if (preset === '7days') {
        const s = new Date(today);
        s.setDate(s.getDate() - 6);
        filterStartDate.value = s.toISOString().split('T')[0];
        filterEndDate.value = today.toISOString().split('T')[0];
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        filterStartDate.value = firstDay.toISOString().split('T')[0];
        filterEndDate.value = today.toISOString().split('T')[0];
    } else if (preset === 'last_month') {
        const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        filterStartDate.value = firstDayLastMonth.toISOString().split('T')[0];
        filterEndDate.value = lastDayLastMonth.toISOString().split('T')[0];
    }
    applyFilters();
};

const applyFilters = () => {
    router.get('/reports', {
        start_date: filterStartDate.value,
        end_date: filterEndDate.value,
        payment_method: filterPayment.value,
        cashier_id: filterCashier.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportExcel = () => {
    const params = new URLSearchParams({
        start_date: filterStartDate.value,
        end_date: filterEndDate.value,
        payment_method: filterPayment.value,
        cashier_id: filterCashier.value,
    }).toString();
    window.location.href = `/reports/export-excel?${params}`;
};

// Filtered Transactions in Table
const filteredTransactions = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return props.salesTransactions;
    return props.salesTransactions.filter(t => 
        t.invoice_number.toLowerCase().includes(q) || 
        (t.customer?.name && t.customer.name.toLowerCase().includes(q)) || 
        (t.cashier?.name && t.cashier.name.toLowerCase().includes(q)) ||
        (t.items && t.items.some(it => it.product?.name?.toLowerCase().includes(q)))
    );
});

// Print modal
const openReprint = (trx) => {
    lastTransaction.value = {
        id: trx.id,
        invoice_number: trx.invoice_number,
        customer: trx.customer,
        customer_name: trx.customer?.name || 'Pelanggan Umum',
        tier_label: getTierLabel(trx.customer?.tier || 'eceran'),
        items: (trx.items || []).map(it => ({
            product: it.product || { name: 'Item Penjualan' },
            unit: it.unit || { unit_name: 'Pcs' },
            qty: it.qty,
            unit_price: it.unit_price,
            subtotal: it.subtotal,
        })),
        total_gross: trx.total_gross,
        discount_amount: trx.discount_amount,
        total_net: trx.total_net,
        payment_method: trx.payment_method,
        cash_paid: trx.cash_paid,
        change_returned: trx.change_returned,
        created_at: trx.created_at,
    };
    isReceiptOpen.value = true;
};
</script>

<template>
    <MainLayout>
        <Head title="Laporan Penjualan & Analisis Omset" />

        <div class="p-6 w-full space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <BarChart3 class="w-6 h-6 text-emerald-600" />
                        <span>Laporan Penjualan & Analisis Omset</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Rekap transaksi kasir, analisis margin laba kotor HPP, omset per metode bayar, dan audit operasional toko.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <button 
                        @click="exportExcel"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-200" />
                        <span>Export File Excel (.xls)</span>
                    </button>
                </div>
            </div>

            <!-- FILTER CONTROLS BAR (Date Range & Presets) -->
            <div class="bg-white border border-slate-200 rounded-3xl p-4 shadow-xs">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Left: Quick Presets (Pills) -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
                        <span class="text-[11px] font-bold text-slate-400 mr-1 flex items-center gap-1 shrink-0">
                            <Calendar class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Periode:</span>
                        </span>
                        
                        <div class="inline-flex items-center gap-1 bg-slate-100/90 p-1 rounded-2xl border border-slate-200/60 shrink-0">
                            <button 
                                @click="setDatePreset('today')"
                                :class="activePreset === 'today' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70 font-bold'"
                                class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                            >
                                Hari Ini
                            </button>
                            <button 
                                @click="setDatePreset('yesterday')"
                                :class="activePreset === 'yesterday' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70 font-bold'"
                                class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                            >
                                Kemarin
                            </button>
                            <button 
                                @click="setDatePreset('7days')"
                                :class="activePreset === '7days' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70 font-bold'"
                                class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                            >
                                7 Hari Terakhir
                            </button>
                            <button 
                                @click="setDatePreset('this_month')"
                                :class="activePreset === 'this_month' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70 font-bold'"
                                class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                            >
                                Bulan Ini
                            </button>
                            <button 
                                @click="setDatePreset('last_month')"
                                :class="activePreset === 'last_month' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/70 font-bold'"
                                class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer"
                            >
                                Bulan Lalu
                            </button>
                        </div>
                    </div>

                    <!-- Right: Custom Inputs & Dropdowns -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <div class="inline-flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-2xl px-2.5 py-1">
                            <input 
                                v-model="filterStartDate"
                                @change="activePreset = 'custom'"
                                type="date"
                                class="bg-transparent border-0 font-bold text-slate-800 text-xs focus:outline-none cursor-pointer"
                            />
                            <span class="text-slate-400 font-bold text-[11px]">s/d</span>
                            <input 
                                v-model="filterEndDate"
                                @change="activePreset = 'custom'"
                                type="date"
                                class="bg-transparent border-0 font-bold text-slate-800 text-xs focus:outline-none cursor-pointer"
                            />
                        </div>

                        <select 
                            v-model="filterPayment"
                            @change="activePreset = 'custom'"
                            class="bg-slate-50 border border-slate-200 rounded-2xl px-3 py-2 font-bold text-slate-800 text-xs focus:outline-none focus:border-emerald-500 cursor-pointer"
                        >
                            <option value="all">Semua Pembayaran</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="transfer">Transfer Bank / QRIS</option>
                            <option value="tempo">Tempo (Piutang)</option>
                        </select>

                        <button 
                            @click="applyFilters"
                            class="bg-slate-900 hover:bg-slate-800 text-white font-black px-4 py-2 rounded-2xl transition cursor-pointer flex items-center gap-1.5 shadow-xs active:scale-95 shrink-0"
                        >
                            <Filter class="w-3.5 h-3.5 text-amber-400" />
                            <span>Terapkan</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECTION 1: KEY FINANCIAL KPI CARDS IN PERIOD -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Omset Periode -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Omset Bersih (Periode)</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ formatRupiah(periodSalesTotal) }}</h3>
                        <p class="text-[10px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                            <Receipt class="w-3 h-3" /> {{ periodTransactionsCount }} Struk Transaksi
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <DollarSign class="w-5 h-5" />
                    </div>
                </div>

                <!-- Total Estimasi Modal HPP -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Modal HPP Barang</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ formatRupiah(periodCostTotal) }}</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Biaya Pokok Pembelian</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <Package class="w-5 h-5" />
                    </div>
                </div>

                <!-- Estimasi Laba Kotor (Gross Profit) & Margin % -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Laba Kotor (Gross Profit)</p>
                            <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black bg-emerald-100 text-emerald-800">
                                {{ periodProfitMargin }}% Margin
                            </span>
                        </div>
                        <h3 class="text-xl font-black text-emerald-700 mt-1">{{ formatRupiah(periodProfitTotal) }}</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Omset dikurangi Modal HPP</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <TrendingUp class="w-5 h-5" />
                    </div>
                </div>

                <!-- Total Piutang Berjalan -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Piutang Belum Lunas</p>
                        <h3 class="text-xl font-black text-rose-600 mt-1">{{ formatRupiah(totalActiveDebts) }}</h3>
                        <p class="text-[10px] text-rose-500 font-bold mt-1 flex items-center gap-1">
                            <CreditCard class="w-3 h-3" /> Tagihan Tempo Pelanggan
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                        <Clock class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- SECTION 2: BREAKDOWN METODE PEMBAYARAN -->
            <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-3.5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                            <Wallet class="w-4 h-4 text-emerald-600" />
                            <span>Rekapitulasi Arus Pembayaran Kasir (Periode Terpilih)</span>
                        </h3>
                        <p class="text-[11px] text-slate-500">Rincian uang tunai masuk, transfer bank, dan nota tempo.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-emerald-50/50 rounded-2xl p-4 border border-emerald-200/80 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider">1. Tunai (Cash di Laci Kasir)</span>
                            <h4 class="text-xl font-black text-emerald-700 mt-0.5">{{ formatRupiah(cashTotal) }}</h4>
                        </div>
                        <span class="text-xs font-mono font-black text-emerald-800 bg-emerald-100/80 px-2.5 py-1 rounded-xl border border-emerald-200">
                            {{ periodSalesTotal > 0 ? Math.round((cashTotal / periodSalesTotal) * 100) : 0 }}%
                        </span>
                    </div>

                    <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-200/80 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-blue-800 uppercase tracking-wider">2. Transfer Bank / QRIS</span>
                            <h4 class="text-xl font-black text-blue-700 mt-0.5">{{ formatRupiah(transferTotal) }}</h4>
                        </div>
                        <span class="text-xs font-mono font-black text-blue-800 bg-blue-100/80 px-2.5 py-1 rounded-xl border border-blue-200">
                            {{ periodSalesTotal > 0 ? Math.round((transferTotal / periodSalesTotal) * 100) : 0 }}%
                        </span>
                    </div>

                    <div class="bg-amber-50/50 rounded-2xl p-4 border border-amber-200/80 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">3. Tempo (Piutang Pelanggan)</span>
                            <h4 class="text-xl font-black text-amber-700 mt-0.5">{{ formatRupiah(tempoTotal) }}</h4>
                        </div>
                        <span class="text-xs font-mono font-black text-amber-800 bg-amber-100/80 px-2.5 py-1 rounded-xl border border-amber-200">
                            {{ periodSalesTotal > 0 ? Math.round((tempoTotal / periodSalesTotal) * 100) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- TABS SELECTOR -->
            <div class="flex items-center gap-1.5 p-1.5 bg-white border border-slate-200 rounded-2xl shadow-xs overflow-x-auto">
                <button 
                    @click="activeTab = 'sales'"
                    :class="activeTab === 'sales' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <Receipt class="w-3.5 h-3.5 text-amber-400" />
                    <span>Daftar Transaksi & Margin</span>
                </button>
                <button 
                    @click="activeTab = 'categories'"
                    :class="activeTab === 'categories' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <Layers class="w-3.5 h-3.5 text-blue-400" />
                    <span>Omset per Kategori & Merk</span>
                </button>
                <button 
                    @click="activeTab = 'products'"
                    :class="activeTab === 'products' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <Package class="w-3.5 h-3.5 text-emerald-400" />
                    <span>Top 15 Produk Terlaris</span>
                </button>
                <button 
                    @click="activeTab = 'performance'"
                    :class="activeTab === 'performance' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <Users class="w-3.5 h-3.5 text-purple-400" />
                    <span>Leaderboard Sales</span>
                </button>
                <button 
                    @click="activeTab = 'audit'"
                    :class="activeTab === 'audit' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 cursor-pointer"
                >
                    <Activity class="w-3.5 h-3.5 text-rose-400" />
                    <span>Audit Log Karyawan</span>
                </button>
            </div>

            <!-- TAB 1: DAFTAR TRANSAKSI & ANALISIS MARGIN LABA -->
            <div v-if="activeTab === 'sales'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                        Rincian Transaksi Penjualan ({{ filteredTransactions.length }} Transaksi)
                    </h3>
                    
                    <div class="relative w-full sm:w-80">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari No Faktur, Pelanggan, Barang..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 bg-slate-50/50">
                                <th class="py-3.5 px-4">No. Faktur & Waktu</th>
                                <th class="py-3.5 px-4">Pelanggan & Strata</th>
                                <th class="py-3.5 px-4">Rincian Barang Terjual</th>
                                <th class="py-3.5 px-4">Metode Bayar</th>
                                <th class="py-3.5 px-4 text-right">Omset Net</th>
                                <th class="py-3.5 px-4 text-right">Modal HPP</th>
                                <th class="py-3.5 px-4 text-right">Laba Kotor</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="trx in filteredTransactions" :key="trx.id" class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4">
                                    <div class="font-black text-slate-900 font-mono">{{ trx.invoice_number }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ new Date(trx.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }} &bull; {{ trx.cashier?.name || 'Kasir' }}
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ trx.customer?.name || 'Pelanggan Umum' }}</div>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-slate-100 text-slate-600 mt-0.5">
                                        {{ getTierLabel(trx.customer?.tier) }}
                                    </span>
                                </td>

                                <td class="py-3 px-4 max-w-xs">
                                    <div class="space-y-0.5">
                                        <p v-for="(it, i) in (trx.items || []).slice(0, 2)" :key="i" class="text-[11px] text-slate-700 truncate">
                                            &bull; {{ it.qty }} {{ it.unit?.unit_name || 'Pcs' }} {{ it.product?.name }}
                                        </p>
                                        <p v-if="(trx.items || []).length > 2" class="text-[10px] text-slate-400 italic">
                                            + {{ trx.items.length - 2 }} barang lainnya...
                                        </p>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span 
                                        :class="[
                                            trx.payment_method === 'cash' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 
                                            trx.payment_method === 'transfer' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200',
                                            'px-2 py-0.5 rounded-lg text-[10px] font-bold border capitalize inline-block'
                                        ]"
                                    >
                                        {{ trx.payment_method }}
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-right font-black text-slate-900 font-mono">
                                    {{ formatRupiah(trx.total_net) }}
                                </td>

                                <td class="py-3 px-4 text-right font-bold text-slate-500 font-mono">
                                    {{ formatRupiah(trx.estimated_cost) }}
                                </td>

                                <td class="py-3 px-4 text-right font-black text-emerald-700 font-mono">
                                    <div>{{ formatRupiah(trx.estimated_profit) }}</div>
                                    <span class="text-[9px] text-emerald-600 font-bold">({{ trx.profit_margin }}%)</span>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <button 
                                        @click="openReprint(trx)"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[10px] flex items-center gap-1 transition cursor-pointer"
                                        title="Cetak Ulang Faktur / Struk"
                                    >
                                        <Printer class="w-3.5 h-3.5 text-amber-600" />
                                        <span>Cetak Ulang</span>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="filteredTransactions.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-400">
                                    Tidak ada transaksi pada periode filter yang dipilih.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: OMSET PER KATEGORI & MERK -->
            <div v-if="activeTab === 'categories'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Omset per Kategori -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                        <Layers class="w-4 h-4 text-blue-600" />
                        <span>Penjualan per Kategori Barang</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-100 bg-slate-50">
                                    <th class="py-2.5 px-3">Kategori</th>
                                    <th class="py-2.5 px-3 text-center">Qty Terjual</th>
                                    <th class="py-2.5 px-3 text-right">Total Omset</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(cat, idx) in categoryBreakdown" :key="idx" class="hover:bg-slate-50">
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ cat.category_name || 'Lain-lain / Umum' }}</td>
                                    <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ cat.total_qty }} unit</td>
                                    <td class="py-2.5 px-3 text-right font-black text-emerald-700 font-mono">{{ formatRupiah(cat.total_omset) }}</td>
                                </tr>
                                <tr v-if="categoryBreakdown.length === 0">
                                    <td colspan="3" class="py-8 text-center text-slate-400">Belum ada data kategori.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Omset per Brand / Merk -->
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-amber-600" />
                        <span>Penjualan per Brand / Merk Pabrikan</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-100 bg-slate-50">
                                    <th class="py-2.5 px-3">Merk / Pabrikan</th>
                                    <th class="py-2.5 px-3 text-center">Qty Terjual</th>
                                    <th class="py-2.5 px-3 text-right">Total Omset</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(br, idx) in brandBreakdown" :key="idx" class="hover:bg-slate-50">
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ br.brand_name || 'Non-Merk' }}</td>
                                    <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ br.total_qty }} unit</td>
                                    <td class="py-2.5 px-3 text-right font-black text-emerald-700 font-mono">{{ formatRupiah(br.total_omset) }}</td>
                                </tr>
                                <tr v-if="brandBreakdown.length === 0">
                                    <td colspan="3" class="py-8 text-center text-slate-400">Belum ada data merk.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: TOP 15 PRODUK TERLARIS -->
            <div v-if="activeTab === 'products'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs p-5 space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                    Ranking 15 Produk Terlaris (Periode Terpilih)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-100 bg-slate-50">
                                <th class="py-3 px-3 text-center">Rank</th>
                                <th class="py-3 px-4">Nama Produk & SKU</th>
                                <th class="py-3 px-4">Kategori / Merk</th>
                                <th class="py-3 px-4 text-center">Total Kuantiti Terjual</th>
                                <th class="py-3 px-4 text-right">Total Omset Nilai Jual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(prod, idx) in topProducts" :key="prod.id" class="hover:bg-slate-50">
                                <td class="py-3 px-3 text-center">
                                    <span 
                                        :class="idx === 0 ? 'bg-amber-100 text-amber-900 font-black border-amber-300' : (idx === 1 ? 'bg-slate-200 text-slate-900' : (idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-700'))"
                                        class="w-6 h-6 rounded-lg inline-flex items-center justify-center font-bold text-xs border"
                                    >
                                        {{ idx + 1 }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ prod.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ prod.sku }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-700">{{ prod.category_name || '-' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ prod.brand_name || '-' }}</div>
                                </td>
                                <td class="py-3 px-4 text-center font-black text-slate-900">
                                    {{ prod.total_qty_sold }} unit
                                </td>
                                <td class="py-3 px-4 text-right font-black text-emerald-700 font-mono">
                                    {{ formatRupiah(prod.total_revenue) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: LEADERBOARD SALES -->
            <div v-if="activeTab === 'performance'" class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                    Performa Sales Lapangan (Periode Terpilih)
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="s in salesLeaderboard" 
                        :key="s.id"
                        class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2 hover:border-emerald-400 transition"
                    >
                        <div class="flex items-center justify-between">
                            <h4 class="font-black text-slate-900 text-sm">{{ s.name }}</h4>
                            <span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded-md text-[10px] font-bold uppercase">
                                {{ s.role }}
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Order Selesai:</span>
                            <strong class="text-slate-900">{{ s.total_orders || 0 }} Pesanan</strong>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500">Omset Penjualan:</span>
                            <strong class="text-emerald-700 font-mono font-bold">{{ formatRupiah(s.total_revenue || 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: AUDIT LOG KARYAWAN -->
            <div v-if="activeTab === 'audit'" class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                    Jejak Audit Aktivitas Stok & Opname Karyawan
                </h3>
                <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                    <div v-for="act in allActivities" :key="act.id" class="py-3 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span :class="act.badge_class" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                                    {{ act.action_title }}
                                </span>
                                <span class="text-xs font-bold text-slate-800">{{ act.user_name }}</span>
                                <span class="text-[10px] text-slate-400">({{ act.timestamp }})</span>
                            </div>
                            <p class="text-xs text-slate-600">{{ act.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL CETAK ULANG FAKTUR / STRUK -->
        <div v-if="isReceiptOpen && lastTransaction" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between no-print bg-white">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Cetak Ulang Faktur Penjualan</h3>
                        <p class="text-xs font-mono text-slate-500">{{ lastTransaction.invoice_number }}</p>
                    </div>
                    <button @click="isReceiptOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Format Switcher -->
                <div class="px-6 py-3 bg-slate-50 border-b border-slate-200 flex items-center gap-2 text-xs no-print">
                    <span class="font-bold text-slate-500 mr-2">Pilih Format:</span>
                    <button 
                        @click="selectedPrintFormat = 'invoice'"
                        :class="selectedPrintFormat === 'invoice' ? 'bg-slate-900 text-white font-bold' : 'bg-white text-slate-700 border border-slate-200'"
                        class="px-3 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        Faktur Standar (A4/Setengah Folio)
                    </button>
                    <button 
                        @click="selectedPrintFormat = 'thermal'"
                        :class="selectedPrintFormat === 'thermal' ? 'bg-slate-900 text-white font-bold' : 'bg-white text-slate-700 border border-slate-200'"
                        class="px-3 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        Struk Kasir (Thermal 58/80mm)
                    </button>
                    <button 
                        @click="selectedPrintFormat = 'dot_matrix'"
                        :class="selectedPrintFormat === 'dot_matrix' ? 'bg-slate-900 text-white font-bold' : 'bg-white text-slate-700 border border-slate-200'"
                        class="px-3 py-1.5 rounded-xl transition cursor-pointer"
                    >
                        Nota Dot Matrix
                    </button>
                </div>

                <!-- Preview Area -->
                <div class="p-6 overflow-y-auto flex-1 bg-slate-100 flex justify-center text-xs">
                    <!-- Format Faktur Standar -->
                    <div v-if="selectedPrintFormat === 'invoice'" class="w-full bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                        <div class="flex justify-between items-start border-b-2 border-slate-900 pb-3">
                            <div>
                                <h2 class="text-base font-black text-slate-900 uppercase tracking-wide">TRISNA JAYA LISTRIK</h2>
                                <p class="text-[11px] text-slate-500">Pusat Alat Listrik, Kabel, Lampu & Instalasi Proyek</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-black bg-slate-900 text-white px-2 py-0.5 rounded uppercase">FAKTUR PENJUALAN</span>
                                <p class="text-xs font-mono font-bold text-slate-900 mt-1">{{ lastTransaction.invoice_number }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Kepada Yth:</span>
                                <strong class="text-slate-900">{{ lastTransaction.customer_name }}</strong>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Tanggal & Pembayaran:</span>
                                <span class="text-slate-900 font-bold">{{ new Date(lastTransaction.created_at).toLocaleDateString('id-ID') }} &bull; {{ lastTransaction.payment_method.toUpperCase() }}</span>
                            </div>
                        </div>

                        <table class="w-full text-left text-xs border border-slate-200">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-700">
                                <tr>
                                    <th class="p-2 border-r border-slate-200">Barang</th>
                                    <th class="p-2 text-center border-r border-slate-200">Qty</th>
                                    <th class="p-2 text-right border-r border-slate-200">Harga Satuan</th>
                                    <th class="p-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(it, i) in lastTransaction.items" :key="i">
                                    <td class="p-2 border-r border-slate-100 font-bold text-slate-900">{{ it.product?.name }}</td>
                                    <td class="p-2 text-center border-r border-slate-100">{{ it.qty }} {{ it.unit?.unit_name }}</td>
                                    <td class="p-2 text-right border-r border-slate-100 font-mono">{{ formatRupiah(it.unit_price) }}</td>
                                    <td class="p-2 text-right font-mono font-bold">{{ formatRupiah(it.subtotal) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50 font-bold">
                                <tr>
                                    <td colspan="3" class="p-2 text-right uppercase text-[10px]">Total Net:</td>
                                    <td class="p-2 text-right font-black font-mono text-emerald-800">{{ formatRupiah(lastTransaction.total_net) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="flex justify-between items-end pt-3 text-center text-xs">
                            <div>
                                <p class="text-slate-500 font-medium">Tanda Terima,</p>
                                <div class="h-4"></div>
                                <p class="font-bold text-slate-800">( .............................. )</p>
                            </div>
                            <div>
                                <p class="text-slate-500 font-medium">Hormat Kami,</p>
                                <div class="h-4"></div>
                                <p class="font-bold text-slate-800">( TRISNA JAYA LISTRIK )</p>
                            </div>
                        </div>
                    </div>

                    <!-- Format Struk Kasir Thermal -->
                    <div v-else-if="selectedPrintFormat === 'thermal'" class="w-72 bg-white p-4 font-mono text-[11px] space-y-2 border border-slate-300 shadow-xs">
                        <div class="text-center space-y-1">
                            <h3 class="font-black text-sm">TRISNA JAYA LISTRIK</h3>
                            <p class="text-[9px] text-slate-500">Pusat Peralatan Listrik</p>
                            <p class="text-[9px]">{{ lastTransaction.invoice_number }} &bull; {{ new Date(lastTransaction.created_at).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div class="border-t border-dashed border-slate-400 my-2"></div>
                        <div v-for="(it, i) in lastTransaction.items" :key="i" class="space-y-0.5">
                            <div class="font-bold truncate">{{ it.product?.name }}</div>
                            <div class="flex justify-between text-slate-600 text-[10px]">
                                <span>{{ it.qty }} x {{ formatRupiah(it.unit_price) }}</span>
                                <span class="font-bold">{{ formatRupiah(it.subtotal) }}</span>
                            </div>
                        </div>
                        <div class="border-t border-dashed border-slate-400 my-2"></div>
                        <div class="flex justify-between font-black">
                            <span>TOTAL:</span>
                            <span>{{ formatRupiah(lastTransaction.total_net) }}</span>
                        </div>
                        <div class="text-center text-[9px] text-slate-400 mt-3">
                            *** Terima Kasih Atas Kunjungan Anda ***
                        </div>
                    </div>

                    <!-- Format Dot Matrix -->
                    <div v-else class="w-full bg-white p-5 font-mono text-[11px] space-y-3 border border-slate-300">
                        <div class="flex justify-between border-b border-dashed border-slate-400 pb-2">
                            <div>
                                <strong class="text-sm">TRISNA JAYA LISTRIK</strong>
                                <p class="text-[10px]">NOTA PENJUALAN</p>
                            </div>
                            <div class="text-right">
                                <p>No: {{ lastTransaction.invoice_number }}</p>
                                <p>Tgl: {{ new Date(lastTransaction.created_at).toLocaleDateString('id-ID') }}</p>
                            </div>
                        </div>
                        <div v-for="(it, i) in lastTransaction.items" :key="i" class="flex justify-between">
                            <span>{{ it.qty }} {{ it.unit?.unit_name }} - {{ it.product?.name }}</span>
                            <span class="font-bold">{{ formatRupiah(it.subtotal) }}</span>
                        </div>
                        <div class="border-t border-dashed border-slate-400 pt-2 flex justify-between font-bold">
                            <span>TOTAL BAYAR:</span>
                            <span>{{ formatRupiah(lastTransaction.total_net) }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-100 flex justify-end gap-2 bg-white no-print">
                    <button 
                        @click="printReceiptDirect"
                        class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-md"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Sekarang</span>
                    </button>
                    <button 
                        @click="isReceiptOpen = false"
                        class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
