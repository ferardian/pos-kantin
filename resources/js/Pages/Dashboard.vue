<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    LayoutDashboard, Wallet, Receipt, Package, TrendingUp, 
    ArrowUpRight, ArrowDownRight, Calendar, ShoppingCart, 
    AlertTriangle, Users, ClipboardList, Clock, CheckCircle2,
    Sparkles, ArrowRight, RefreshCw
} from 'lucide-vue-next';

const props = defineProps({
    todaySales: Number,
    todayTransactionsCount: Number,
    todayItemsCount: Number,
    todayAverageOrder: Number,
    monthSales: Number,
    lastMonthSales: Number,
    monthTransactionsCount: Number,
    salesGrowth: Number,
    trxGrowth: Number,
    sevenDaysTrend: Array,
    topProducts: Array,
    totalActiveDebts: Number,
    criticalStockCount: Number,
    pendingOrdersCount: Number,
    recentTransactions: Array,
    user: Object,
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const currentDateFormatted = computed(() => {
    return new Date().toLocaleDateString('id-ID', { 
        weekday: 'long', 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric' 
    });
});

// Calculate Max Value for 7-Day Chart scaling
const maxChartValue = computed(() => {
    if (!props.sevenDaysTrend || props.sevenDaysTrend.length === 0) return 100000;
    const maxVal = Math.max(...props.sevenDaysTrend.map(d => d.total));
    return maxVal > 0 ? maxVal : 100000;
});

const reloadPage = () => {
    window.location.reload();
};
</script>

<template>
    <MainLayout>
        <Head title="Dasbor Bisnis - Ringkasan Eksekutif" />

        <div class="p-6 w-full space-y-6 max-w-7xl mx-auto">
            <!-- Welcome Header Banner -->
            <div class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-800 rounded-3xl p-6 sm:p-7 text-white shadow-lg relative overflow-hidden flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1.5 relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-emerald-100 text-xs font-bold">
                        <Sparkles class="w-3.5 h-3.5 text-amber-300" />
                        <span>Dasbor Ringkasan Bisnis</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">
                        Selamat Datang, {{ user?.name || 'Owner' }}!
                    </h1>
                    <p class="text-xs sm:text-sm text-emerald-100/90 flex items-center gap-1.5">
                        <Calendar class="w-4 h-4 text-emerald-200" />
                        <span>Ringkasan performa penjualan & operasional per <strong>{{ currentDateFormatted }}</strong></span>
                    </p>
                </div>

                <div class="flex items-center gap-2 relative z-10">
                    <button 
                        @click="reloadPage" 
                        class="p-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white backdrop-blur-md transition cursor-pointer active:scale-95 flex items-center gap-1.5 text-xs font-bold"
                        title="Perbarui Data"
                    >
                        <RefreshCw class="w-4 h-4" />
                        <span class="hidden sm:inline">Refresh Data</span>
                    </button>
                    <Link 
                        href="/pos" 
                        class="bg-amber-400 hover:bg-amber-300 text-slate-950 font-black px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md active:scale-95"
                    >
                        <ShoppingCart class="w-4 h-4" />
                        <span>Buka Kasir POS</span>
                    </Link>
                </div>

                <!-- Decorative Background Pattern -->
                <div class="absolute -right-8 -bottom-8 w-48 h-48 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
            </div>

            <!-- SECTION 1: RINGKASAN HARI INI -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Ringkasan Hari Ini</span>
                    </h2>
                    <span class="text-[11px] font-bold text-slate-400">Live Transaksi Toko</span>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
                    <!-- Pendapatan Hari Ini -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-emerald-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Pendapatan</span>
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                <Wallet class="w-4.5 h-4.5" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg sm:text-2xl font-black text-slate-900 truncate">
                                {{ formatRupiah(todaySales) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Total Penjualan Hari Ini</p>
                        </div>
                    </div>

                    <!-- Transaksi Hari Ini -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-blue-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Transaksi</span>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <Receipt class="w-4.5 h-4.5" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg sm:text-2xl font-black text-slate-900">
                                {{ todayTransactionsCount }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Struk Selesai Terbit</p>
                        </div>
                    </div>

                    <!-- Item Terjual Hari Ini -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-amber-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Item Terjual</span>
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                <Package class="w-4.5 h-4.5" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg sm:text-2xl font-black text-slate-900">
                                {{ todayItemsCount }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Kuantiti Barang Listrik</p>
                        </div>
                    </div>

                    <!-- Rata-rata per Transaksi (AOV) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-purple-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Rata-rata</span>
                            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                                <TrendingUp class="w-4.5 h-4.5" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg sm:text-2xl font-black text-slate-900 truncate">
                                {{ formatRupiah(todayAverageOrder) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Per Struk Pembelian</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PERBANDINGAN BULANAN -->
            <div class="space-y-3">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span>Perbandingan Bulanan</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Pendapatan Bulan Ini -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-500">Pendapatan Bulan Ini</span>
                                <span 
                                    :class="salesGrowth >= 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black border flex items-center gap-0.5"
                                >
                                    <ArrowUpRight v-if="salesGrowth >= 0" class="w-3 h-3" />
                                    <ArrowDownRight v-else class="w-3 h-3" />
                                    <span>{{ salesGrowth >= 0 ? '+' : '' }}{{ salesGrowth }}%</span>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 mt-1">
                                {{ formatRupiah(monthSales) }}
                            </h3>
                            <p class="text-[10px] text-slate-400">
                                Bulan lalu: {{ formatRupiah(lastMonthSales) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black">
                            <Wallet class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- Transaksi Bulan Ini -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-500">Transaksi Bulan Ini</span>
                                <span 
                                    :class="trxGrowth >= 0 ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-black border flex items-center gap-0.5"
                                >
                                    <ArrowUpRight v-if="trxGrowth >= 0" class="w-3 h-3" />
                                    <ArrowDownRight v-else class="w-3 h-3" />
                                    <span>{{ trxGrowth >= 0 ? '+' : '' }}{{ trxGrowth }}%</span>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 mt-1">
                                {{ monthTransactionsCount }} <span class="text-sm font-bold text-slate-400">Transaksi</span>
                            </h3>
                            <p class="text-[10px] text-slate-400">
                                Akumulasi struk dari POS & Sales Order
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-800 flex items-center justify-center font-black">
                            <Receipt class="w-6 h-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: TREN PENJUALAN 7 HARI & TOP PRODUK TERLARIS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Tren Penjualan 7 Hari (Bar Chart) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Tren Penjualan (7 Hari Terakhir)</h3>
                            <p class="text-[11px] text-slate-500">Grafik omset harian kasir & proyek</p>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                            Live Sinkron
                        </span>
                    </div>

                    <!-- Visual Bar Chart -->
                    <div class="pt-4 pb-2">
                        <div class="flex items-end justify-between gap-2 sm:gap-3 h-48 px-2">
                            <div 
                                v-for="(day, idx) in sevenDaysTrend" 
                                :key="idx"
                                class="flex-1 flex flex-col items-center gap-2 group h-full justify-end"
                            >
                                <!-- Value Tooltip on top of bar -->
                                <div class="text-[9px] font-bold text-slate-600 text-center truncate max-w-full group-hover:text-emerald-700 transition">
                                    {{ day.total > 0 ? (day.total >= 1000000 ? (day.total / 1000000).toFixed(1) + 'M' : (day.total / 1000).toFixed(0) + 'k') : 'Rp 0' }}
                                </div>

                                <!-- Bar Element -->
                                <div class="w-full bg-slate-100 rounded-xl overflow-hidden flex flex-col justify-end h-32 relative">
                                    <div 
                                        :style="{ height: `${Math.max(8, (day.total / maxChartValue) * 100)}%` }"
                                        :class="day.total > 0 ? 'bg-emerald-600 group-hover:bg-emerald-500' : 'bg-slate-200'"
                                        class="w-full rounded-xl transition-all duration-500 relative"
                                    >
                                    </div>
                                </div>

                                <!-- Date Label -->
                                <span class="text-[10px] font-bold text-slate-500 group-hover:text-slate-900 transition">
                                    {{ day.date }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Produk Terlaris Bulan Ini -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Produk Terlaris Bulan Ini</h3>
                            <p class="text-[11px] text-slate-500">Ranking barang paling banyak terjual</p>
                        </div>
                        <Link href="/reports" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                            <span>Lihat Semua</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <!-- Ranking List -->
                    <div class="divide-y divide-slate-100 max-h-56 overflow-y-auto pr-1 space-y-1">
                        <div 
                            v-for="(prod, idx) in topProducts" 
                            :key="prod.id"
                            class="py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 rounded-2xl px-2 transition"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div 
                                    :class="idx === 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : (idx === 1 ? 'bg-slate-200 text-slate-800' : (idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-600'))"
                                    class="w-7 h-7 rounded-xl flex items-center justify-center font-black text-xs shrink-0 border"
                                >
                                    {{ idx + 1 }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 truncate">{{ prod.name }}</h4>
                                    <p class="text-[10px] text-slate-400">{{ prod.category_name || 'Alat Listrik' }} &bull; Terjual: <strong class="text-slate-700">{{ prod.total_qty_sold }} unit</strong></p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-emerald-700 font-mono">{{ formatRupiah(prod.total_revenue) }}</span>
                            </div>
                        </div>

                        <div v-if="topProducts.length === 0" class="py-8 text-center text-slate-400 text-xs">
                            Belum ada penjualan bulan ini.
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: QUICK OPERATIONAL STATUS & ALERTS -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Alert Stok Kritis -->
                <Link 
                    href="/products"
                    class="bg-white border border-slate-200 hover:border-amber-400 rounded-3xl p-4.5 shadow-xs flex items-center justify-between transition group cursor-pointer"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stok Kritis / Menipis</span>
                            <h4 class="text-base font-black text-slate-900">{{ criticalStockCount }} SKU Barang</h4>
                        </div>
                    </div>
                    <ArrowRight class="w-4 h-4 text-slate-400 group-hover:text-amber-600 group-hover:translate-x-1 transition" />
                </Link>

                <!-- Kasir POS Kantin -->
                <Link 
                    href="/pos"
                    class="bg-white border border-slate-200 hover:border-emerald-400 rounded-3xl p-4.5 shadow-xs flex items-center justify-between transition group cursor-pointer"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                            <ShoppingCart class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kasir Kantin</span>
                            <h4 class="text-base font-black text-slate-900">Buka Layanan POS</h4>
                        </div>
                    </div>
                    <ArrowRight class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-1 transition" />
                </Link>

                <!-- Total Piutang Aktif -->
                <Link 
                    href="/receivables"
                    class="bg-white border border-slate-200 hover:border-purple-400 rounded-3xl p-4.5 shadow-xs flex items-center justify-between transition group cursor-pointer"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold">
                            <Users class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bon & Piutang Karyawan</span>
                            <h4 class="text-base font-black text-slate-900">{{ formatRupiah(totalActiveDebts) }}</h4>
                        </div>
                    </div>
                    <ArrowRight class="w-4 h-4 text-slate-400 group-hover:text-purple-600 group-hover:translate-x-1 transition" />
                </Link>
            </div>
        </div>
    </MainLayout>
</template>
