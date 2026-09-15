<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Wallet, Plus, ArrowDownLeft, ArrowUpRight, ArrowLeftRight, 
    Building2, PiggyBank, Receipt, DollarSign, TrendingUp,
    Calendar, User, FileText, CheckCircle2, X, PlusCircle,
    Layers, CreditCard, Sparkles, RefreshCw, Landmark, AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    cashboxes: Array,
    totalCashboxesCount: Number,
    totalCashBalance: Number,
    totalCashIn: Number,
    totalCashOut: Number,
    inventoryValuation: Number,
    totalDebts: Number,
    totalBusinessNetWorth: Number,
    transactions: Array,
    user: Object,
});

const isAddExpenseModalOpen = ref(false);
const isAddIncomeModalOpen = ref(false);
const isTransferModalOpen = ref(false);
const isAddCashboxModalOpen = ref(false);

const activeFilterCashbox = ref('all');
const activeFilterType = ref('all');

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Form Catat Pengeluaran Operasional
const expenseForm = useForm({
    cashbox_id: props.cashboxes[0]?.id || null,
    type: 'out',
    category: 'Biaya Operasional & Listrik/Air Kantin',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
});

// Form Catat Pemasukan Lain
const incomeForm = useForm({
    cashbox_id: props.cashboxes[0]?.id || null,
    type: 'in',
    category: 'Modal Awal / Tambahan',
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
});

// Form Transfer Kas
const transferForm = useForm({
    from_cashbox_id: props.cashboxes[0]?.id || null,
    to_cashbox_id: props.cashboxes[1]?.id || props.cashboxes[0]?.id || null,
    amount: '',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
});

// Form Tambah Cashbox Baru
const cashboxForm = useForm({
    name: '',
    type: 'bank',
    account_number: '',
    initial_balance: 0,
});

const submitExpense = () => {
    expenseForm.post('/cashboxes/transaction', {
        onSuccess: () => {
            isAddExpenseModalOpen.value = false;
            expenseForm.reset();
            expenseForm.cashbox_id = props.cashboxes[0]?.id || null;
            expenseForm.category = 'Biaya Operasional & Listrik/Air Kantin';
            expenseForm.transaction_date = new Date().toISOString().split('T')[0];
        }
    });
};

const submitIncome = () => {
    incomeForm.post('/cashboxes/transaction', {
        onSuccess: () => {
            isAddIncomeModalOpen.value = false;
            incomeForm.reset();
            incomeForm.cashbox_id = props.cashboxes[0]?.id || null;
            incomeForm.category = 'Modal Awal / Tambahan';
            incomeForm.transaction_date = new Date().toISOString().split('T')[0];
        }
    });
};

const submitTransfer = () => {
    if (transferForm.from_cashbox_id === transferForm.to_cashbox_id) {
        alert('Kas asal dan kas tujuan tidak boleh sama!');
        return;
    }
    transferForm.post('/cashboxes/transfer', {
        onSuccess: () => {
            isTransferModalOpen.value = false;
            transferForm.reset();
            transferForm.from_cashbox_id = props.cashboxes[0]?.id || null;
            transferForm.to_cashbox_id = props.cashboxes[1]?.id || null;
            transferForm.transaction_date = new Date().toISOString().split('T')[0];
        }
    });
};

const submitCashbox = () => {
    cashboxForm.post('/cashboxes', {
        onSuccess: () => {
            isAddCashboxModalOpen.value = false;
            cashboxForm.reset();
        }
    });
};

// Filtered Transactions
const filteredTransactions = computed(() => {
    return props.transactions.filter(t => {
        const matchCashbox = activeFilterCashbox.value === 'all' || t.cashbox_id === Number(activeFilterCashbox.value);
        const matchType = activeFilterType.value === 'all' || t.type === activeFilterType.value;
        return matchCashbox && matchType;
    });
});
</script>

<template>
    <MainLayout>
        <Head title="Buku Kas & Manajemen Cashbox" />

        <div class="p-6 w-full space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <Wallet class="w-6 h-6 text-emerald-600" />
                        <span>Buku Kas & Manajemen Cashbox</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kelola akun kas tunai & rekening bank, catat biaya pengeluaran operasional toko, dan pantau total nilai usaha.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <button 
                        @click="isAddExpenseModalOpen = true"
                        class="bg-rose-600 hover:bg-rose-700 text-white font-bold px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                    >
                        <ArrowUpRight class="w-4 h-4 text-rose-200" />
                        <span>Catat Pengeluaran Toko</span>
                    </button>

                    <button 
                        @click="isAddIncomeModalOpen = true"
                        class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                    >
                        <ArrowDownLeft class="w-4 h-4 text-emerald-200" />
                        <span>Catat Pemasukan Lain</span>
                    </button>

                    <button 
                        @click="isTransferModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                    >
                        <ArrowLeftRight class="w-4 h-4 text-amber-400" />
                        <span>Setor / Pindah Kas</span>
                    </button>
                </div>
            </div>

            <!-- SECTION 1: RINGKASAN KEUANGAN & NILAI USAHA (EXECUTIVE NET WORTH) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                        <Sparkles class="w-4 h-4 text-amber-500" />
                        <span>Ringkasan Keuangan & Valuasi Usaha (Neraca Real-time)</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 sm:gap-4">
                    <!-- 1. Saldo Keuangan (Kas/Bank) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-emerald-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Saldo Keuangan</span>
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <Wallet class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg font-black text-blue-600 truncate">
                                {{ formatRupiah(totalCashBalance) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Total Uang Tunai & Bank</p>
                        </div>
                    </div>

                    <!-- 2. Nilai Produk (HPP Persediaan Stok) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-amber-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Nilai Aset Produk</span>
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                <Layers class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg font-black text-amber-600 truncate">
                                {{ formatRupiah(inventoryValuation) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Modal Persediaan Barang</p>
                        </div>
                    </div>

                    <!-- 3. Utang Pembelian -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-rose-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Utang Pembelian</span>
                            <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                                <ArrowUpRight class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg font-black text-rose-600 truncate">
                                Rp 0
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Utang Supplier Tempo</p>
                        </div>
                    </div>

                    <!-- 4. Piutang Penjualan -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex flex-col justify-between hover:border-emerald-300 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">Piutang Penjualan</span>
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                <ArrowDownLeft class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg font-black text-emerald-600 truncate">
                                {{ formatRupiah(totalDebts) }}
                            </h3>
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Tagihan Pelanggan</p>
                        </div>
                    </div>

                    <!-- 5. Total Nilai Usaha (Net Worth) -->
                    <div class="bg-gradient-to-br from-purple-900 to-indigo-900 text-white rounded-3xl p-5 shadow-md flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black uppercase text-purple-200">Total Nilai Usaha</span>
                            <span class="text-xs">💎</span>
                        </div>
                        <div class="mt-3">
                            <h3 class="text-lg font-black text-white truncate">
                                {{ formatRupiah(totalBusinessNetWorth) }}
                            </h3>
                            <p class="text-[10px] text-purple-200 font-medium mt-0.5">Kas + Stok + Piutang</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: RINGKASAN CASHBOX & DAFTAR AKUN KAS -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left: Ringkasan Arus Kas Masuk & Keluar -->
                <div class="lg:col-span-4 bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Ringkasan Cashbox</h3>
                            <span class="text-[10px] font-bold text-slate-400">{{ totalCashboxesCount }} Akun Terdaftar</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Total Cashbox</span>
                                <h4 class="text-2xl font-black text-slate-900">{{ totalCashboxesCount }}</h4>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Total Saldo</span>
                                <h4 class="text-xl font-black text-emerald-600 truncate">{{ formatRupiah(totalCashBalance) }}</h4>
                            </div>

                            <div class="space-y-1 pt-2 border-t border-slate-100">
                                <span class="text-[10px] font-bold text-emerald-600 flex items-center gap-0.5">
                                    <ArrowDownLeft class="w-3 h-3" /> Pemasukan
                                </span>
                                <h4 class="text-base font-black text-slate-900 truncate">{{ formatRupiah(totalCashIn) }}</h4>
                            </div>

                            <div class="space-y-1 pt-2 border-t border-slate-100">
                                <span class="text-[10px] font-bold text-rose-600 flex items-center gap-0.5">
                                    <ArrowUpRight class="w-3 h-3" /> Pengeluaran
                                </span>
                                <h4 class="text-base font-black text-slate-900 truncate">{{ formatRupiah(totalCashOut) }}</h4>
                            </div>
                        </div>
                    </div>

                    <button 
                        @click="isAddCashboxModalOpen = true"
                        class="w-full py-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-2xl text-xs font-bold text-slate-800 flex items-center justify-center gap-1.5 transition cursor-pointer"
                    >
                        <Plus class="w-4 h-4 text-emerald-600" />
                        <span>Tambah Akun Kas / Bank Baru</span>
                    </button>
                </div>

                <!-- Right: Kartu Akun-Akun Kas & Bank -->
                <div class="lg:col-span-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Cashbox Active Card -->
                    <div 
                        v-for="cb in cashboxes" 
                        :key="cb.id"
                        class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4 relative hover:border-emerald-400 transition flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div 
                                        :class="cb.type === 'bank' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'"
                                        class="w-10 h-10 rounded-2xl flex items-center justify-center font-black"
                                    >
                                        <Landmark v-if="cb.type === 'bank'" class="w-5 h-5" />
                                        <Wallet v-else class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h4 class="font-black text-slate-900 text-sm">{{ cb.name }}</h4>
                                        <p class="text-[10px] font-mono text-slate-400 font-bold">
                                            {{ cb.account_number || (cb.type === 'cash' ? 'Kas Tunai Toko' : 'Rekening Bank') }}
                                        </p>
                                    </div>
                                </div>
                                <span 
                                    :class="cb.is_default ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600'"
                                    class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full border"
                                >
                                    {{ cb.is_default ? 'Kas Utama POS' : cb.type }}
                                </span>
                            </div>

                            <div class="pt-4 border-t border-slate-100 mt-4 flex items-baseline justify-between">
                                <div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Saldo Kas Berjalan</span>
                                    <h3 class="text-xl font-black text-emerald-700 font-mono mt-0.5">{{ formatRupiah(cb.balance) }}</h3>
                                </div>
                                <span class="text-[11px] text-slate-400 font-semibold bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-200">
                                    {{ cb.transactions_count }} mutasi
                                </span>
                            </div>
                        </div>

                        <!-- Direct Quick Actions on Card -->
                        <div class="pt-3 border-t border-slate-100 grid grid-cols-3 gap-1.5 text-xs">
                            <button 
                                @click="expenseForm.cashbox_id = cb.id; isAddExpenseModalOpen = true;"
                                class="py-1.5 px-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold text-[10px] flex items-center justify-center gap-1 transition cursor-pointer active:scale-95"
                                title="Catat Pengeluaran dari Kas Ini"
                            >
                                <ArrowUpRight class="w-3 h-3 text-rose-600" />
                                <span>Keluar</span>
                            </button>

                            <button 
                                @click="incomeForm.cashbox_id = cb.id; isAddIncomeModalOpen = true;"
                                class="py-1.5 px-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center justify-center gap-1 transition cursor-pointer active:scale-95"
                                title="Catat Pemasukan ke Kas Ini"
                            >
                                <ArrowDownLeft class="w-3 h-3 text-emerald-600" />
                                <span>Masuk</span>
                            </button>

                            <button 
                                @click="transferForm.from_cashbox_id = cb.id; isTransferModalOpen = true;"
                                class="py-1.5 px-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[10px] flex items-center justify-center gap-1 transition cursor-pointer active:scale-95"
                                title="Pindah Saldo / Setor Bank"
                            >
                                <ArrowLeftRight class="w-3 h-3 text-amber-600" />
                                <span>Pindah</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: TABEL RIWAYAT MUTASI BUKU KAS (CASH FLOW LOG) -->
            <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            Riwayat Mutasi Buku Kas & Arus Keuangan ({{ filteredTransactions.length }} Transaksi)
                        </h3>
                        <p class="text-[11px] text-slate-500">Catatan otomatis uang masuk kasir, setor bank, dan biaya operasional.</p>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <select 
                            v-model="activeFilterCashbox"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
                        >
                            <option value="all">Semua Akun Kas</option>
                            <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">{{ cb.name }}</option>
                        </select>

                        <select 
                            v-model="activeFilterType"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
                        >
                            <option value="all">Semua Jenis Arus</option>
                            <option value="in">Uang Masuk (+)</option>
                            <option value="out">Uang Keluar (-)</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 bg-slate-50/50">
                                <th class="py-3.5 px-4">Tanggal & Waktu</th>
                                <th class="py-3.5 px-4">Akun Cashbox</th>
                                <th class="py-3.5 px-4">Kategori Biaya / Arus</th>
                                <th class="py-3.5 px-4">Keterangan / Catatan</th>
                                <th class="py-3.5 px-4">Petugas</th>
                                <th class="py-3.5 px-4 text-right">Nominal Arus Kas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="trx in filteredTransactions" :key="trx.id" class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                                    {{ new Date(trx.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}
                                </td>

                                <td class="py-3 px-4 font-bold text-slate-900">
                                    {{ trx.cashbox?.name || 'Kas Utama' }}
                                </td>

                                <td class="py-3 px-4">
                                    <span 
                                        :class="trx.type === 'in' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold border inline-block"
                                    >
                                        {{ trx.category }}
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-slate-700">
                                    {{ trx.description || '-' }}
                                </td>

                                <td class="py-3 px-4 text-slate-600 font-semibold">
                                    {{ trx.user?.name || 'Kasir' }}
                                </td>

                                <td class="py-3 px-4 text-right font-black font-mono">
                                    <span :class="trx.type === 'in' ? 'text-emerald-700' : 'text-rose-600'">
                                        {{ trx.type === 'in' ? '+' : '-' }}{{ formatRupiah(trx.amount) }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="filteredTransactions.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    Belum ada catatan mutasi buku kas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL 1: CATAT PENGELUARAN TOKO (CASH OUT) -->
        <div v-if="isAddExpenseModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                            <ArrowUpRight class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Catat Pengeluaran Operasional Toko</h3>
                            <p class="text-[11px] text-slate-500">Saldo kas yang dipilih akan otomatis berkurang.</p>
                        </div>
                    </div>
                    <button @click="isAddExpenseModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitExpense" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Sumber Dana Kas *</label>
                        <select 
                            v-model="expenseForm.cashbox_id"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-rose-500"
                        >
                            <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">
                                {{ cb.name }} (Saldo: {{ formatRupiah(cb.balance) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Kategori Biaya Operasional *</label>
                        <select 
                            v-model="expenseForm.category"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-rose-500"
                        >
                            <option value="Biaya Operasional & Listrik/Air Kantin">Biaya Operasional & Listrik/Air Kantin</option>
                            <option value="Gaji & Upah Karyawan">Gaji & Upah Karyawan</option>
                            <option value="Biaya Operasional & Transport">Biaya Operasional & Bensin / Transport</option>
                            <option value="Konsumsi & Makan Toko">Konsumsi & Uang Makan Staf</option>
                            <option value="Perlengkapan Toko & ATK">Perlengkapan Toko, Lakban & ATK</option>
                            <option value="Sewa Tempat / Ruko">Sewa Tempat / Ruko</option>
                            <option value="Biaya Perbaikan / Maintenance">Biaya Perbaikan & Maintenance Toko</option>
                            <option value="Pengeluaran Lainnya">Pengeluaran Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Jumlah Pengeluaran (Rp) *</label>
                        <input 
                            v-model.number="expenseForm.amount"
                            type="number"
                            step="1000"
                            min="1"
                            placeholder="Contoh: 150000"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-sm focus:outline-none focus:border-rose-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Tanggal Transaksi *</label>
                        <input 
                            v-model="expenseForm.transaction_date"
                            type="date"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-rose-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Keterangan / Keperluan</label>
                        <input 
                            v-model="expenseForm.description"
                            placeholder="Contoh: Pembayaran token listrik / operasional kantin..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 text-xs focus:outline-none focus:border-rose-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isAddExpenseModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="expenseForm.processing"
                            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md disabled:opacity-50"
                        >
                            Simpan Pengeluaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: CATAT PEMASUKAN LAIN (CASH IN) -->
        <div v-if="isAddIncomeModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                            <ArrowDownLeft class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Catat Pemasukan Kas Lainnya</h3>
                            <p class="text-[11px] text-slate-500">Saldo kas yang dipilih akan otomatis bertambah.</p>
                        </div>
                    </div>
                    <button @click="isAddIncomeModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitIncome" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Masuk ke Akun Kas *</label>
                        <select 
                            v-model="incomeForm.cashbox_id"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        >
                            <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">
                                {{ cb.name }} (Saldo: {{ formatRupiah(cb.balance) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Kategori Pemasukan *</label>
                        <select 
                            v-model="incomeForm.category"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        >
                            <option value="Modal Awal / Tambahan">Suntikan Modal / Tambahan Kas</option>
                            <option value="Pendapatan Titip / Konsinyasi Kantin">Pendapatan Titip / Konsinyasi Kantin</option>
                            <option value="Pendapatan Sewa Alat">Pendapatan Sewa Alat / Genset</option>
                            <option value="Pemasukan Lain-lain">Pemasukan Lain-lain</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Jumlah Uang Masuk (Rp) *</label>
                        <input 
                            v-model.number="incomeForm.amount"
                            type="number"
                            step="1000"
                            min="1"
                            placeholder="Contoh: 500000"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-sm focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Tanggal Transaksi *</label>
                        <input 
                            v-model="incomeForm.transaction_date"
                            type="date"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Keterangan / Catatan</label>
                        <input 
                            v-model="incomeForm.description"
                            placeholder="Contoh: Uang kembalian proyek / modal tambahan..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isAddIncomeModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="incomeForm.processing"
                            class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md disabled:opacity-50"
                        >
                            Simpan Pemasukan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 3: TRANSFER ANTAR KAS / SETOR BANK -->
        <div v-if="isTransferModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <ArrowLeftRight class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Setor / Pindah Saldo Antar Kas</h3>
                            <p class="text-[11px] text-slate-500">Pindahkan uang dari kas toko ke rekening bank atau kas lain.</p>
                        </div>
                    </div>
                    <button @click="isTransferModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitTransfer" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Dari Akun Kas Asal *</label>
                        <select 
                            v-model="transferForm.from_cashbox_id"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                        >
                            <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">
                                {{ cb.name }} (Saldo: {{ formatRupiah(cb.balance) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Ke Akun Kas / Bank Tujuan *</label>
                        <select 
                            v-model="transferForm.to_cashbox_id"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                        >
                            <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">
                                {{ cb.name }} (Saldo: {{ formatRupiah(cb.balance) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nominal yang Dipindahkan (Rp) *</label>
                        <input 
                            v-model.number="transferForm.amount"
                            type="number"
                            step="1000"
                            min="1"
                            placeholder="Contoh: 500000"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-sm focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Tanggal Transfer *</label>
                        <input 
                            v-model="transferForm.transaction_date"
                            type="date"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Catatan</label>
                        <input 
                            v-model="transferForm.description"
                            placeholder="Contoh: Setoran hasil penjualan kasir ke rekening BCA..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isTransferModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="transferForm.processing"
                            class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md disabled:opacity-50"
                        >
                            Proses Pindah Kas
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 4: TAMBAH AKUN KAS / BANK BARU -->
        <div v-if="isAddCashboxModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900">Tambah Akun Kas / Bank Baru</h3>
                    <button @click="isAddCashboxModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCashbox" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Akun Kas *</label>
                        <input 
                            v-model="cashboxForm.name"
                            placeholder="Contoh: Rekening BCA Toko / Kas Kecil Toko"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Tipe Akun *</label>
                        <select 
                            v-model="cashboxForm.type"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        >
                            <option value="bank">Rekening Bank (BCA, Mandiri, BRI, BNI)</option>
                            <option value="cash">Kas Tunai (Cash di Laci / Brankas)</option>
                            <option value="other">E-Wallet / QRIS / Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nomor Rekening / No. Akun</label>
                        <input 
                            v-model="cashboxForm.account_number"
                            placeholder="Contoh: 123-456-7890"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Saldo Awal (Opsional)</label>
                        <input 
                            v-model.number="cashboxForm.initial_balance"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-emerald-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isAddCashboxModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="cashboxForm.processing"
                            class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md disabled:opacity-50"
                        >
                            Simpan Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
