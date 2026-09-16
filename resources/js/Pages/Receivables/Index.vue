<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    ClipboardList, PlusCircle, RefreshCw, Search, User, Check, ChevronDown, Wallet, UserCheck, 
    Clock, CheckCircle2, AlertCircle, History, X, ArrowRight,
    Building2, Receipt, Coins
} from 'lucide-vue-next';

const props = defineProps({
    receivables: Array,
    employees: Array,
    summary: Object,
});

const searchQuery = ref('');
const statusFilter = ref('all');
const employeeFilter = ref('all');

// Modals

const isSyncing = ref(false);
const syncEmployees = () => {
    isSyncing.value = true;
    router.post('/employees/sync', {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        }
    });
};

const isAddModalOpen = ref(false);
const isPayModalOpen = ref(false);
const isHistoryModalOpen = ref(false);
const selectedReceivable = ref(null);


const employeeSearchQuery = ref('');
const isEmployeeDropdownOpen = ref(false);
const selectedEmployeeObj = computed(() => {
    return (props.employees || []).find(e => e.id === addForm.employee_id) || null;
});
const filteredEmployees = computed(() => {
    const list = props.employees || [];
    const q = (employeeSearchQuery.value || '').toLowerCase().trim();
    if (!q) return list;
    return list.filter(e => 
        (e.name && e.name.toLowerCase().includes(q)) ||
        (e.department && e.department.toLowerCase().includes(q)) ||
        (e.nik && e.nik.toLowerCase().includes(q))
    );
});
const pickEmployee = (emp) => {
    addForm.employee_id = emp.id;
    isEmployeeDropdownOpen.value = false;
    employeeSearchQuery.value = '';
};
const clearEmployee = () => {
    addForm.employee_id = '';
    employeeSearchQuery.value = '';
};
const selectFirstEmployee = () => {
    if (filteredEmployees.value.length > 0) {
        pickEmployee(filteredEmployees.value[0]);
    }
};

const addForm = useForm({
    employee_id: '',
    amount: '',
    notes: '',
});

const payForm = useForm({
    amount: '',
    notes: '',
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const filteredReceivables = computed(() => {
    return (props.receivables || []).filter(item => {
        const empName = item.employee?.name || '';
        const dept = item.employee?.department || '';
        const invoice = item.transaction?.invoice_number || '';
        const notes = item.notes || '';

        const matchesSearch = 
            empName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            dept.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            invoice.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            notes.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesStatus = statusFilter.value === 'all' || item.status === statusFilter.value;
        const matchesEmployee = employeeFilter.value === 'all' || item.employee_id == employeeFilter.value;

        return matchesSearch && matchesStatus && matchesEmployee;
    });
});

const openAddModal = () => {
    addForm.reset();
    employeeSearchQuery.value = '';
    isEmployeeDropdownOpen.value = false;
    isAddModalOpen.value = true;
};

const submitAdd = () => {
    addForm.post('/receivables', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            addForm.reset();
        }
    });
};

const openPayModal = (item) => {
    selectedReceivable.value = item;
    payForm.amount = item.remaining;
    payForm.notes = '';
    isPayModalOpen.value = true;
};

const submitPay = () => {
    if (!selectedReceivable.value) return;
    payForm.post(`/receivables/${selectedReceivable.value.id}/pay`, {
        onSuccess: () => {
            isPayModalOpen.value = false;
            payForm.reset();
        }
    });
};

const openHistoryModal = (item) => {
    selectedReceivable.value = item;
    isHistoryModalOpen.value = true;
};
</script>

<template>
    <Head title="Piutang / Bon Karyawan RSIA" />

    <MainLayout>
        <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto">
            <!-- Header & Summary Cards -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5 tracking-tight">
                        <ClipboardList class="w-6 h-6 text-emerald-600" />
                        Piutang & Bon Karyawan
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
                        Monitoring bon belanja dan sisa kembalian yang belum diambil/dibayar karyawan RSIA.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        @click="syncEmployees"
                        :disabled="isSyncing"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-sm rounded-xl transition shadow-2xs hover:shadow active:scale-98 cursor-pointer disabled:opacity-50"
                        title="Tarik & perbarui data pegawai terbaru dari RSIA API"
                    >
                        <RefreshCw class="w-4 h-4 text-emerald-600" :class="{ 'animate-spin': isSyncing }" />
                        <span>Sinkron Pegawai RSIA</span>
                    </button>
                    <button 
                        @click="openAddModal"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition shadow-sm hover:shadow active:scale-98 cursor-pointer"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Catat Bon Manual
                    </button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Piutang Berjalan</div>
                        <div class="text-2xl font-black text-rose-600 mt-1">
                            {{ formatRupiah(summary?.total_unpaid || 0) }}
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                        <Wallet class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Karyawan Memiliki Bon</div>
                        <div class="text-2xl font-black text-amber-600 mt-1">
                            {{ summary?.total_employees_with_debt || 0 }} <span class="text-sm font-semibold text-slate-400">Orang</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                        <UserCheck class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between sm:col-span-2 lg:col-span-1">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Transaksi Bon</div>
                        <div class="text-2xl font-black text-emerald-600 mt-1">
                            {{ (receivables || []).length }} <span class="text-sm font-semibold text-slate-400">Data</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <Receipt class="w-6 h-6" />
                    </div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="relative">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari nama, invoice, catatan..." 
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none transition"
                    />
                </div>
                <select 
                    v-model="statusFilter"
                    class="py-2.5 px-3.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none transition cursor-pointer text-slate-700"
                >
                    <option value="all">Semua Status</option>
                    <option value="unpaid">Belum Lunas</option>
                    <option value="partial">Cicil / Sebagian</option>
                    <option value="paid">Sudah Lunas</option>
                </select>
                <select 
                    v-model="employeeFilter"
                    class="py-2.5 px-3.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none transition cursor-pointer text-slate-700"
                >
                    <option value="all">Semua Karyawan</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.department || 'Umum' }})</option>
                </select>
            </div>

            <!-- Receivables Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50/80 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3.5">Tanggal</th>
                                <th class="px-5 py-3.5">Karyawan</th>
                                <th class="px-5 py-3.5">Referensi / Keterangan</th>
                                <th class="px-5 py-3.5 text-right">Nominal Awal</th>
                                <th class="px-5 py-3.5 text-right">Sisa Piutang</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredReceivables.length === 0">
                                <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                    <ClipboardList class="w-8 h-8 mx-auto mb-2 opacity-40 text-slate-400" />
                                    <p class="font-medium">Tidak ada data piutang yang ditemukan.</p>
                                </td>
                            </tr>
                            <tr 
                                v-for="item in filteredReceivables" 
                                :key="item.id"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <td class="px-5 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                    {{ formatDate(item.created_at) }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ item.employee?.name || '-' }}</div>
                                    <div v-if="item.employee?.department" class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1 mt-0.5">
                                        <Building2 class="w-3 h-3" />
                                        {{ item.employee.department }}
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div v-if="item.transaction" class="inline-flex items-center gap-1 text-xs font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                                        <Receipt class="w-3 h-3 text-slate-400" />
                                        {{ item.transaction.invoice_number }}
                                    </div>
                                    <div class="text-xs text-slate-600 mt-1">{{ item.notes || '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-slate-700">
                                    {{ formatRupiah(item.amount) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <span 
                                        :class="item.remaining > 0 ? 'text-rose-600 font-black' : 'text-slate-400 font-medium'"
                                        class="text-sm"
                                    >
                                        {{ formatRupiah(item.remaining) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span 
                                        v-if="item.status === 'paid'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        <CheckCircle2 class="w-3 h-3" />
                                        Lunas
                                    </span>
                                    <span 
                                        v-else-if="item.status === 'partial'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800"
                                    >
                                        <Clock class="w-3 h-3" />
                                        Cicil
                                    </span>
                                    <span 
                                        v-else
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800"
                                    >
                                        <AlertCircle class="w-3 h-3" />
                                        Belum Lunas
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button 
                                            v-if="item.remaining > 0"
                                            @click="openPayModal(item)"
                                            class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-2xs cursor-pointer"
                                        >
                                            <Coins class="w-3.5 h-3.5" />
                                            Lunasi
                                        </button>
                                        <button 
                                            v-if="item.payments && item.payments.length > 0"
                                            @click="openHistoryModal(item)"
                                            class="p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition"
                                            title="Riwayat Pembayaran"
                                        >
                                            <History class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Bon Manual -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <PlusCircle class="w-5 h-5 text-emerald-600" />
                        Catat Bon / Piutang Baru
                    </h2>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="relative">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Pilih Karyawan RSIA *</label>
                        
                        <!-- Trigger Combobox -->
                        <div 
                            @click="isEmployeeDropdownOpen = !isEmployeeDropdownOpen"
                            class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-900 flex items-center justify-between cursor-pointer hover:border-emerald-500 transition shadow-2xs"
                            :class="{ 'border-emerald-500 ring-2 ring-emerald-500/20': isEmployeeDropdownOpen }"
                        >
                            <div class="flex items-center gap-2 truncate">
                                <User class="w-4 h-4 text-slate-400 shrink-0" />
                                <span v-if="selectedEmployeeObj" class="font-bold text-slate-900 truncate">
                                    {{ selectedEmployeeObj.name }} 
                                    <span class="text-[11px] font-normal text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded ml-1">
                                        {{ selectedEmployeeObj.department || '-' }}
                                    </span>
                                </span>
                                <span v-else class="text-slate-400">-- Cari Nama / Unit Pegawai RSIA --</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button 
                                    v-if="addForm.employee_id"
                                    type="button" 
                                    @click.stop="clearEmployee"
                                    class="text-slate-400 hover:text-rose-500 p-0.5 rounded cursor-pointer"
                                    title="Hapus pilihan"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                                <ChevronDown class="w-4 h-4 text-slate-400 transition" :class="{ 'rotate-180': isEmployeeDropdownOpen }" />
                            </div>
                        </div>

                        <!-- Dropdown Menu with Search Input -->
                        <div 
                            v-if="isEmployeeDropdownOpen"
                            class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
                        >
                            <div class="p-2 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2">
                                <Search class="w-4 h-4 text-slate-400 ml-1.5 shrink-0" />
                                <input 
                                    v-model="employeeSearchQuery"
                                    type="text"
                                    placeholder="Ketik nama atau unit..."
                                    class="w-full bg-transparent text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                    autocomplete="off"
                                    @keydown.esc="isEmployeeDropdownOpen = false"
                                    @keydown.enter.prevent="selectFirstEmployee"
                                />
                                <button 
                                    v-if="employeeSearchQuery" 
                                    type="button"
                                    @click="employeeSearchQuery = ''" 
                                    class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                                >
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div class="max-h-48 overflow-y-auto divide-y divide-slate-50 p-1">
                                <div 
                                    v-for="emp in filteredEmployees" 
                                    :key="emp.id"
                                    @click="pickEmployee(emp)"
                                    class="px-3 py-2 text-xs rounded-xl cursor-pointer transition flex items-center justify-between gap-2 hover:bg-emerald-50/80"
                                    :class="{ 'bg-emerald-50 font-bold text-emerald-900': emp.id === addForm.employee_id }"
                                >
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 truncate">{{ emp.name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono flex items-center gap-1.5 mt-0.5">
                                            <span class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{{ emp.department || 'Umum' }}</span>
                                            <span v-if="emp.nik" class="text-slate-400">NIP: {{ emp.nik }}</span>
                                        </div>
                                    </div>
                                    <Check v-if="emp.id === addForm.employee_id" class="w-4 h-4 text-emerald-600 shrink-0" />
                                </div>

                                <div v-if="filteredEmployees.length === 0" class="py-6 text-center text-xs text-slate-400">
                                    Tidak ada pegawai "{{ employeeSearchQuery }}"
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nominal Bon (Rp) *</label>
                        <input 
                            v-model="addForm.amount" 
                            type="number" 
                            min="1" 
                            required 
                            placeholder="Contoh: 15000"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-mono"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan / Alasan Bon</label>
                        <textarea 
                            v-model="addForm.notes" 
                            rows="2"
                            placeholder="Contoh: Titip beli makan siang / Kembalian kurang Rp 5.000"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="isAddModalOpen = false" 
                            class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="addForm.processing"
                            class="px-5 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-xs"
                        >
                            Simpan Bon
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Bayar / Lunasi Piutang -->
        <div v-if="isPayModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <Coins class="w-5 h-5 text-emerald-600" />
                        Pelunasan Piutang Karyawan
                    </h2>
                    <button @click="isPayModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div v-if="selectedReceivable" class="mb-4 bg-emerald-50 p-4 rounded-xl border border-emerald-100 text-sm">
                    <div class="font-bold text-slate-900">{{ selectedReceivable.employee?.name }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ selectedReceivable.employee?.department || 'Karyawan RSIA' }}</div>
                    <div class="flex items-center justify-between mt-3 pt-2 border-t border-emerald-200/60">
                        <span class="text-xs font-semibold text-slate-600">Sisa Tagihan:</span>
                        <span class="text-base font-black text-rose-600">{{ formatRupiah(selectedReceivable.remaining) }}</span>
                    </div>
                </div>
                <form @submit.prevent="submitPay" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jumlah Pembayaran (Rp) *</label>
                        <input 
                            v-model="payForm.amount" 
                            type="number" 
                            min="1" 
                            :max="selectedReceivable?.remaining"
                            required 
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-mono text-base font-bold text-slate-800"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Pembayaran</label>
                        <input 
                            v-model="payForm.notes" 
                            type="text" 
                            placeholder="Contoh: Dibayar tunai di kasir"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="isPayModalOpen = false" 
                            class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="payForm.processing"
                            class="px-5 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-xs"
                        >
                            Konfirmasi Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Riwayat Pembayaran -->
        <div v-if="isHistoryModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <History class="w-5 h-5 text-emerald-600" />
                        Riwayat Pembayaran
                    </h2>
                    <button @click="isHistoryModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-3">
                    <div v-for="pay in selectedReceivable?.payments" :key="pay.id" class="p-3 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-900">{{ formatRupiah(pay.amount) }}</div>
                            <div class="text-xs text-slate-500">{{ formatDate(pay.created_at) }} • Kasir: {{ pay.cashier?.name || 'Staff' }}</div>
                            <div v-if="pay.notes" class="text-xs text-slate-600 italic mt-0.5">{{ pay.notes }}</div>
                        </div>
                        <span class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">Diterima</span>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button 
                        @click="isHistoryModalOpen = false"
                        class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
