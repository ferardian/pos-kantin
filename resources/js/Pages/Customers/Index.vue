<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Users, Plus, Phone, MapPin, CreditCard, DollarSign, 
    Clock, CheckCircle, X, AlertCircle, Sparkles, UserPlus,
    Search, Edit3, Trash2, ShieldAlert, ArrowUpDown, History,
    Check, Wallet, AlertTriangle, Info, CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({
    customers: Array,
    user: Object,
});

// Search and Filter state
const searchQuery = ref('');
const selectedTierFilter = ref('all');
const selectedDebtFilter = ref('all');

// Modals
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAdjustDebtModalOpen = ref(false);
const isPayDebtModalOpen = ref(false);

const selectedCustomer = ref(null);
const selectedDebt = ref(null);

// Toast Notification State
const toast = ref({
    show: false,
    type: 'success', // 'success' | 'error' | 'info'
    title: '',
    message: '',
});
let toastTimeout = null;
const showToast = (type, title, message) => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toast.value = { show: true, type, title, message };
    toastTimeout = setTimeout(() => {
        toast.value.show = false;
    }, 4500);
};

// Listen to Inertia session flash messages
const page = usePage();
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        showToast('success', 'Berhasil', flash.success);
    } else if (flash?.error) {
        showToast('error', 'Perhatian', flash.error);
    }
}, { deep: true, immediate: true });

// Generic Confirmation Modal State
const confirmModal = ref({
    isOpen: false,
    type: 'danger', // 'danger' | 'warning' | 'info'
    title: '',
    subtitle: '',
    message: '',
    customer: null,
    confirmText: 'Ya, Lanjutkan',
    actionType: 'delete', // 'delete' | 'open_debt' | 'close'
});

// Forms
const addCustomerForm = useForm({
    name: '',
    phone: '',
    address: '',
    tier: 'kontraktor',
    credit_limit: 10000000,
});

const editCustomerForm = useForm({
    id: null,
    name: '',
    phone: '',
    address: '',
    tier: 'kontraktor',
    credit_limit: 0,
});

const adjustDebtForm = useForm({
    type: 'add', // 'add', 'deduct', 'set'
    amount: 0,
    due_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    notes: '',
});

const payDebtForm = useForm({
    amount: 0,
    payment_method: 'cash',
    notes: '',
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const getTierLabel = (tier) => {
    switch (tier) {
        case 'eceran': return 'Retail';
        case 'tukang': return 'Bronze';
        case 'kontraktor': return 'Gold';
        case 'grosir': return 'Diamond';
        default: return tier;
    }
};

const getTierBadgeClass = (tier) => {
    switch (tier) {
        case 'eceran': return 'bg-slate-100 text-slate-700 border-slate-300';
        case 'tukang': return 'bg-amber-800 text-white border-amber-900 shadow-2xs font-black';
        case 'kontraktor': return 'bg-amber-300 text-amber-950 border-amber-500 shadow-2xs font-black';
        case 'grosir': return 'bg-sky-600 text-white border-sky-700 shadow-2xs font-black';
        default: return 'bg-slate-100 text-slate-700 border-slate-300';
    }
};

// Filtered Customers
const filteredCustomers = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    return (props.customers || []).filter(c => {
        const matchesQuery = !q || 
            (c.name && c.name.toLowerCase().includes(q)) || 
            (c.phone && c.phone.toLowerCase().includes(q)) || 
            (c.address && c.address.toLowerCase().includes(q));

        const matchesTier = selectedTierFilter.value === 'all' || c.tier === selectedTierFilter.value;

        const matchesDebt = selectedDebtFilter.value === 'all' || 
            (selectedDebtFilter.value === 'has_debt' && Number(c.current_debt) > 0) || 
            (selectedDebtFilter.value === 'no_debt' && Number(c.current_debt) <= 0);

        return matchesQuery && matchesTier && matchesDebt;
    });
});

// Summary Stats
const totalCustomersCount = computed(() => props.customers?.length || 0);
const totalOutstandingDebt = computed(() => {
    return (props.customers || []).reduce((acc, c) => acc + Number(c.current_debt || 0), 0);
});
const customersWithDebtCount = computed(() => {
    return (props.customers || []).filter(c => Number(c.current_debt) > 0).length;
});

// Open Modals
const openEditCustomer = (customer) => {
    selectedCustomer.value = customer;
    editCustomerForm.clearErrors();
    editCustomerForm.id = customer.id;
    editCustomerForm.name = customer.name;
    editCustomerForm.phone = customer.phone || '';
    editCustomerForm.address = customer.address || '';
    editCustomerForm.tier = customer.tier || 'eceran';
    editCustomerForm.credit_limit = Number(customer.credit_limit || 0);
    isEditModalOpen.value = true;
};

const openAdjustDebt = (customer) => {
    selectedCustomer.value = customer;
    adjustDebtForm.reset();
    adjustDebtForm.type = 'add';
    adjustDebtForm.amount = 0;
    adjustDebtForm.due_date = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    adjustDebtForm.notes = '';
    isAdjustDebtModalOpen.value = true;
};

const openPayDebt = (customer, debt) => {
    selectedCustomer.value = customer;
    selectedDebt.value = debt;
    payDebtForm.amount = debt.remaining_debt;
    payDebtForm.payment_method = 'cash';
    payDebtForm.notes = '';
    isPayDebtModalOpen.value = true;
};

// Submissions
const submitAddCustomer = () => {
    addCustomerForm.post('/customers', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            addCustomerForm.reset();
            showToast('success', 'Pelanggan Baru', 'Pelanggan baru berhasil ditambahkan.');
        },
        onError: () => {
            showToast('error', 'Validasi Gagal', 'Harap periksa kembali isian form yang ditandai merah.');
        }
    });
};

const submitEditCustomer = () => {
    editCustomerForm.put(`/customers/${editCustomerForm.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
            showToast('success', 'Data Disimpan', `Perubahan data pelanggan "${editCustomerForm.name}" berhasil diperbarui.`);
        },
        onError: () => {
            showToast('error', 'Gagal Menyimpan', 'Harap periksa isian data yang ditandai merah.');
        }
    });
};

const submitAdjustDebt = () => {
    adjustDebtForm.post(`/customers/${selectedCustomer.value.id}/adjust-debt`, {
        onSuccess: () => {
            isAdjustDebtModalOpen.value = false;
            showToast('success', 'Mutasi Piutang', 'Penyesuaian saldo piutang berhasil dicatat.');
        },
        onError: () => {
            showToast('error', 'Gagal Mutasi', 'Harap periksa nominal mutasi piutang.');
        }
    });
};

const submitPayDebt = () => {
    payDebtForm.post(`/debts/${selectedDebt.value.id}/pay`, {
        onSuccess: () => {
            isPayDebtModalOpen.value = false;
            showToast('success', 'Pembayaran Piutang', 'Pembayaran cicilan piutang berhasil dicatat ke buku kas.');
        },
        onError: () => {
            showToast('error', 'Gagal Bayar', 'Periksa nominal pembayaran.');
        }
    });
};

// Delete Customer Handling
const openDeleteConfirm = (customer) => {
    if (customer.name === 'Pelanggan Umum') {
        confirmModal.value = {
            isOpen: true,
            type: 'warning',
            title: 'Akun Sistem Kasir',
            subtitle: 'Pelanggan Utama Toko',
            message: 'Data "Pelanggan Umum" adalah akun sistem kasir untuk melayani pembeli eceran langsung tanpa member, sehingga tidak dapat dihapus.',
            customer: customer,
            confirmText: 'Saya Mengerti',
            actionType: 'close',
        };
        return;
    }

    if (Number(customer.current_debt) > 0) {
        confirmModal.value = {
            isOpen: true,
            type: 'warning',
            title: 'Pelanggan Masih Memiliki Piutang Aktif',
            subtitle: `Sisa Piutang Berjalan: ${formatRupiah(customer.current_debt)}`,
            message: `Data pelanggan "${customer.name}" belum dapat dihapus karena masih tercatat memiliki kewajiban piutang sebesar ${formatRupiah(customer.current_debt)}. Untuk menghapus data sample/pelanggan ini, silakan sesuaikan atau potong piutang menjadi Rp 0 terlebih dahulu di menu Kelola Piutang.`,
            customer: customer,
            confirmText: 'Buka Kelola Piutang',
            actionType: 'open_debt',
        };
        return;
    }

    confirmModal.value = {
        isOpen: true,
        type: 'danger',
        title: 'Hapus Data Pelanggan?',
        subtitle: `Pelanggan: ${customer.name}`,
        message: `Apakah Anda yakin ingin menghapus data pelanggan "${customer.name}"? Data kontak, alamat, dan profil pelanggan ini akan dihapus permanen dari sistem.`,
        customer: customer,
        confirmText: 'Ya, Hapus Pelanggan',
        actionType: 'delete',
    };
};

const handleConfirmAction = () => {
    const modal = confirmModal.value;
    if (modal.actionType === 'delete' && modal.customer) {
        const customer = modal.customer;
        confirmModal.value.isOpen = false;
        router.delete(`/customers/${customer.id}`, {
            onSuccess: () => {
                showToast('success', 'Pelanggan Dihapus', `Data pelanggan "${customer.name}" berhasil dihapus dari sistem.`);
            },
            onError: (errs) => {
                showToast('error', 'Gagal Menghapus', 'Terjadi kendala saat menghapus data pelanggan.');
            }
        });
    } else if (modal.actionType === 'open_debt' && modal.customer) {
        const customer = modal.customer;
        confirmModal.value.isOpen = false;
        openAdjustDebt(customer);
    } else {
        confirmModal.value.isOpen = false;
    }
};
</script>

<template>
    <Head title="Pelanggan & Buku Piutang" />
    <MainLayout>
        <div class="p-6 w-full space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <Users class="w-6 h-6 text-amber-600" />
                        <span>Pelanggan & Buku Piutang Usaha</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kelola data pelanggan, nomor kontak, alamat, batas kredit (Credit Limit), dan mutasi piutang usaha.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        @click="isAddModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4 text-amber-400" />
                        <span>Tambah Pelanggan</span>
                    </button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold">
                        <Users class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500 font-medium">Total Pelanggan Terdaftar</p>
                        <p class="text-lg font-black text-slate-900">{{ totalCustomersCount }} <span class="text-xs font-normal text-slate-500">Kontak</span></p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                        <CreditCard class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500 font-medium">Total Sisa Piutang Berjalan</p>
                        <p class="text-lg font-black text-rose-600">{{ formatRupiah(totalOutstandingDebt) }}</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                        <Clock class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500 font-medium">Pelanggan Memiliki Piutang Aktif</p>
                        <p class="text-lg font-black text-amber-700">{{ customersWithDebtCount }} <span class="text-xs font-normal text-slate-500">Pelanggan</span></p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="relative w-full md:w-80">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari nama, nomor HP, atau alamat..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                    />
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
                    <!-- Tier Filter -->
                    <select 
                        v-model="selectedTierFilter" 
                        class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">Semua Kategori (Tier)</option>
                        <option value="eceran">1. Retail</option>
                        <option value="tukang">2. Bronze</option>
                        <option value="kontraktor">3. Gold</option>
                        <option value="grosir">4. Diamond</option>
                    </select>

                    <!-- Debt Status Filter -->
                    <select 
                        v-model="selectedDebtFilter" 
                        class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">Semua Status Piutang</option>
                        <option value="has_debt">Ada Piutang (Belum Lunas)</option>
                        <option value="no_debt">Lunas / Tidak Ada Piutang</option>
                    </select>
                </div>
            </div>

            <!-- Customers Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div 
                    v-for="customer in filteredCustomers" 
                    :key="customer.id"
                    class="bg-white border border-slate-200 hover:border-slate-300 rounded-3xl p-5 shadow-xs flex flex-col justify-between transition group"
                >
                    <div class="space-y-3.5">
                        <!-- Top Row: Name, Tier Badge & Action Menu -->
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-900 leading-snug">{{ customer.name }}</h3>
                                <span :class="[getTierBadgeClass(customer.tier), 'inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md border mt-1']">
                                    {{ getTierLabel(customer.tier) }}
                                </span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1">
                                <button 
                                    @click="openEditCustomer(customer)"
                                    title="Edit Data Pelanggan"
                                    class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition cursor-pointer"
                                >
                                    <Edit3 class="w-4 h-4" />
                                </button>
                                <button 
                                    @click="openAdjustDebt(customer)"
                                    title="Kelola / Mutasi Piutang"
                                    class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition cursor-pointer"
                                >
                                    <Wallet class="w-4 h-4" />
                                </button>
                                <button 
                                    @click="openDeleteConfirm(customer)"
                                    :title="customer.name === 'Pelanggan Umum' ? 'Akun sistem kasir tidak dapat dihapus' : (Number(customer.current_debt) > 0 ? 'Pelanggan masih memiliki sisa piutang aktif' : 'Hapus Pelanggan')"
                                    :class="customer.name === 'Pelanggan Umum' ? 'text-slate-300 hover:text-slate-500 hover:bg-slate-100' : (Number(customer.current_debt) > 0 ? 'text-amber-500 hover:text-amber-700 hover:bg-amber-50' : 'text-slate-400 hover:text-rose-600 hover:bg-rose-50')"
                                    class="p-1.5 rounded-lg transition cursor-pointer"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Contact & Address -->
                        <div class="text-xs text-slate-600 space-y-1.5 bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <p class="flex items-center gap-2">
                                <Phone class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <span class="font-medium text-slate-800">{{ customer.phone || '-' }}</span>
                            </p>
                            <p class="flex items-start gap-2">
                                <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                                <span class="line-clamp-2">{{ customer.address || 'Langsung di Toko' }}</span>
                            </p>
                        </div>

                        <!-- Credit Limit & Current Debt -->
                        <div class="space-y-2 pt-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-500 font-medium">Batas Plafon Kredit:</span>
                                <span class="text-slate-800 font-bold">{{ formatRupiah(customer.credit_limit) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-xs p-2 rounded-xl" :class="customer.current_debt > 0 ? 'bg-rose-50 border border-rose-200' : 'bg-slate-50'">
                                <span class="text-slate-600 font-medium">Sisa Piutang Berjalan:</span>
                                <span :class="customer.current_debt > 0 ? 'text-rose-700 font-black text-sm' : 'text-emerald-700 font-bold'">
                                    {{ formatRupiah(customer.current_debt) }}
                                </span>
                            </div>
                        </div>

                        <!-- Active Debts List -->
                        <div v-if="customer.debts?.length > 0" class="pt-2 border-t border-slate-100 space-y-2">
                            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Faktur Piutang Belum Lunas:</div>
                            <div 
                                v-for="debt in customer.debts" 
                                :key="debt.id"
                                class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <p class="font-black text-rose-600">{{ formatRupiah(debt.remaining_debt) }}</p>
                                    <p class="text-[10px] text-slate-500">Jatuh Tempo: {{ debt.due_date || '-' }}</p>
                                </div>
                                <button 
                                    @click="openPayDebt(customer, debt)"
                                    class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-900 border border-slate-200 rounded-lg text-[10px] font-black transition shadow-2xs cursor-pointer"
                                >
                                    Bayar Cicilan
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="pt-3 border-t border-slate-100 flex items-center gap-2 mt-3">
                        <button 
                            @click="openEditCustomer(customer)"
                            class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-[11px] flex items-center justify-center gap-1.5 transition cursor-pointer"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                            <span>Edit Pelanggan</span>
                        </button>
                        <button 
                            @click="openAdjustDebt(customer)"
                            class="flex-1 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 font-bold rounded-xl text-[11px] flex items-center justify-center gap-1.5 transition cursor-pointer"
                        >
                            <Wallet class="w-3.5 h-3.5 text-amber-700" />
                            <span>Kelola Piutang</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredCustomers.length === 0" class="bg-white rounded-3xl p-12 border border-slate-200 text-center space-y-3">
                <Users class="w-12 h-12 text-slate-300 mx-auto" />
                <h3 class="text-sm font-bold text-slate-700">Tidak ada pelanggan yang cocok dengan pencarian</h3>
                <p class="text-xs text-slate-400">Coba ubah kata kunci pencarian atau reset filter kategori.</p>
            </div>
        </div>

        <!-- ================= MODAL: ADD CUSTOMER ================= -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900">Tambah Pelanggan Baru</h3>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddCustomer" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Pelanggan / Toko / Proyek <span class="text-rose-500">*</span></label>
                        <input v-model="addCustomerForm.name" required placeholder="Contoh: Toko Berkah Listrik" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="addCustomerForm.errors.name" class="text-rose-600 text-[10px] font-bold mt-1">{{ addCustomerForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">No. WhatsApp / HP</label>
                        <input v-model="addCustomerForm.phone" placeholder="081234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="addCustomerForm.errors.phone" class="text-rose-600 text-[10px] font-bold mt-1">{{ addCustomerForm.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat / Lokasi</label>
                        <input v-model="addCustomerForm.address" placeholder="Jl. Raya Utama No. 12" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="addCustomerForm.errors.address" class="text-rose-600 text-[10px] font-bold mt-1">{{ addCustomerForm.errors.address }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Klasifikasi Harga (Tier Pelanggan)</label>
                        <select v-model="addCustomerForm.tier" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500">
                            <option value="eceran">1. Retail</option>
                            <option value="tukang">2. Bronze</option>
                            <option value="kontraktor">3. Gold</option>
                            <option value="grosir">4. Diamond</option>
                        </select>
                        <p v-if="addCustomerForm.errors.tier" class="text-rose-600 text-[10px] font-bold mt-1">{{ addCustomerForm.errors.tier }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Batas Plafon Kredit Piutang (Rp)</label>
                        <input v-model.number="addCustomerForm.credit_limit" type="number" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="addCustomerForm.errors.credit_limit" class="text-rose-600 text-[10px] font-bold mt-1">{{ addCustomerForm.errors.credit_limit }}</p>
                    </div>

                    <button type="submit" :disabled="addCustomerForm.processing" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md flex items-center justify-center gap-2">
                        <span>{{ addCustomerForm.processing ? 'Menyimpan...' : 'Simpan Pelanggan' }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: EDIT CUSTOMER ================= -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Edit Data Pelanggan</h3>
                        <p class="text-[11px] text-slate-500">{{ selectedCustomer?.name }}</p>
                    </div>
                    <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditCustomer" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Pelanggan / Toko <span class="text-rose-500">*</span></label>
                        <input v-model="editCustomerForm.name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition font-bold" />
                        <p v-if="editCustomerForm.errors.name" class="text-rose-600 text-[10px] font-bold mt-1">{{ editCustomerForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">No. WhatsApp / HP</label>
                        <input v-model="editCustomerForm.phone" placeholder="081234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="editCustomerForm.errors.phone" class="text-rose-600 text-[10px] font-bold mt-1">{{ editCustomerForm.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat / Lokasi</label>
                        <input v-model="editCustomerForm.address" placeholder="Jl. Raya Utama No. 12" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="editCustomerForm.errors.address" class="text-rose-600 text-[10px] font-bold mt-1">{{ editCustomerForm.errors.address }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Klasifikasi Harga (Tier Pelanggan)</label>
                        <select v-model="editCustomerForm.tier" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500">
                            <option value="eceran">1. Retail</option>
                            <option value="tukang">2. Bronze</option>
                            <option value="kontraktor">3. Gold</option>
                            <option value="grosir">4. Diamond</option>
                        </select>
                        <p v-if="editCustomerForm.errors.tier" class="text-rose-600 text-[10px] font-bold mt-1">{{ editCustomerForm.errors.tier }}</p>
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Batas Plafon Kredit Piutang (Rp)</label>
                        <input v-model.number="editCustomerForm.credit_limit" type="number" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                        <p v-if="editCustomerForm.errors.credit_limit" class="text-rose-600 text-[10px] font-bold mt-1">{{ editCustomerForm.errors.credit_limit }}</p>
                    </div>

                    <!-- Status Piutang Berjalan (Read-Only Informative Card) -->
                    <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/90 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-600">Sisa Piutang Berjalan Saat Ini:</span>
                            <span :class="Number(selectedCustomer?.current_debt) > 0 ? 'text-rose-600 font-black text-xs' : 'text-emerald-700 font-black text-xs'">
                                {{ formatRupiah(selectedCustomer?.current_debt) }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-500 leading-normal">
                            * Anda dapat mengedit data profil pelanggan kapan saja. Untuk mutasi, penambahan saldo, atau cicilan piutang, gunakan tombol <span class="font-bold text-slate-700">Kelola Piutang</span>.
                        </p>
                    </div>

                    <button type="submit" :disabled="editCustomerForm.processing" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md flex items-center justify-center gap-2">
                        <span>{{ editCustomerForm.processing ? 'Menyimpan Perubahan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: KELOLA / MUTASI PIUTANG ================= -->
        <div v-if="isAdjustDebtModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Kelola Piutang Pelanggan</h3>
                        <p class="text-xs text-slate-500">{{ selectedCustomer?.name }}</p>
                    </div>
                    <button @click="isAdjustDebtModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Current Balance Display -->
                <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 flex justify-between items-center text-xs">
                    <span class="text-slate-600 font-medium">Sisa Piutang Saat Ini:</span>
                    <span class="text-rose-600 font-black text-sm">{{ formatRupiah(selectedCustomer?.current_debt) }}</span>
                </div>

                <!-- Action Type Selector -->
                <div class="space-y-1.5">
                    <label class="block text-slate-700 font-bold text-xs">Pilih Jenis Mutasi Piutang:</label>
                    <div class="grid grid-cols-3 gap-2 text-[11px]">
                        <button 
                            type="button" 
                            @click="adjustDebtForm.type = 'add'"
                            :class="adjustDebtForm.type === 'add' ? 'bg-rose-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="py-2 rounded-xl transition cursor-pointer font-bold"
                        >
                            + Tambah Piutang
                        </button>
                        <button 
                            type="button" 
                            @click="adjustDebtForm.type = 'deduct'"
                            :class="adjustDebtForm.type === 'deduct' ? 'bg-emerald-700 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="py-2 rounded-xl transition cursor-pointer font-bold"
                        >
                            - Koreksi / Potongan
                        </button>
                        <button 
                            type="button" 
                            @click="adjustDebtForm.type = 'set'"
                            :class="adjustDebtForm.type === 'set' ? 'bg-amber-600 text-white font-black shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="py-2 rounded-xl transition cursor-pointer font-bold"
                        >
                            = Sesuaikan Saldo
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submitAdjustDebt" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">
                            {{ adjustDebtForm.type === 'add' ? 'Nominal Piutang Baru (Rp)' : adjustDebtForm.type === 'deduct' ? 'Nominal Pengurangan / Potongan (Rp)' : 'Total Saldo Piutang Baru (Rp)' }}
                        </label>
                        <input 
                            v-model.number="adjustDebtForm.amount" 
                            type="number" 
                            min="0" 
                            required 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                        />
                    </div>

                    <div v-if="adjustDebtForm.type === 'add'">
                        <label class="block text-slate-700 font-bold mb-1">Tanggal Jatuh Tempo</label>
                        <input 
                            v-model="adjustDebtForm.due_date" 
                            type="date" 
                            required 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500" 
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Keterangan / Catatan Mutasi</label>
                        <input 
                            v-model="adjustDebtForm.notes" 
                            placeholder="Contoh: Piutang tambahan kabel proyek Bojong" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                        />
                    </div>

                    <button 
                        type="submit" 
                        :disabled="adjustDebtForm.processing" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md"
                    >
                        Simpan Mutasi Piutang
                    </button>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: PAY DEBT ================= -->
        <div v-if="isPayDebtModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Pembayaran Cicilan Piutang</h3>
                        <p class="text-xs text-slate-500">{{ selectedCustomer?.name }}</p>
                    </div>
                    <button @click="isPayDebtModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 flex justify-between items-center">
                        <span class="text-slate-600 font-medium">Sisa Piutang Tagihan Ini:</span>
                        <span class="text-rose-600 font-black text-sm">{{ formatRupiah(selectedDebt?.remaining_debt) }}</span>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Jumlah Pembayaran (Rp)</label>
                        <input v-model.number="payDebtForm.amount" type="number" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-black text-sm focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Metode Pembayaran</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                v-for="m in ['cash', 'transfer']" 
                                :key="m"
                                type="button"
                                @click="payDebtForm.payment_method = m"
                                :class="payDebtForm.payment_method === m ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'"
                                class="py-2 text-[11px] rounded-xl uppercase transition cursor-pointer"
                            >
                                {{ m }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Catatan Pembayaran (Opsional)</label>
                        <input v-model="payDebtForm.notes" placeholder="Contoh: Cicilan tahap 1 via transfer BCA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" />
                    </div>

                    <button @click="submitPayDebt" :disabled="payDebtForm.processing" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md">
                        Catat Pembayaran Piutang
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= TELEPORT: CONFIRMATION & WARNING MODAL ================= -->
        <Teleport to="body">
            <div 
                v-if="confirmModal.isOpen" 
                class="fixed inset-0 z-[100] bg-slate-950/75 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-150"
            >
                <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full overflow-hidden flex flex-col animate-in zoom-in-95 duration-150">
                    <div class="p-6 text-center space-y-4">
                        <!-- Icon Circle -->
                        <div 
                            :class="[
                                confirmModal.type === 'danger' ? 'bg-rose-100 text-rose-600 shadow-rose-500/20' : 
                                (confirmModal.type === 'warning' ? 'bg-amber-100 text-amber-700 shadow-amber-500/20' : 'bg-sky-100 text-sky-600 shadow-sky-500/20'),
                                'w-14 h-14 rounded-2xl flex items-center justify-center mx-auto shadow-lg'
                            ]"
                        >
                            <Trash2 v-if="confirmModal.type === 'danger'" class="w-7 h-7 stroke-[2.5]" />
                            <AlertTriangle v-else-if="confirmModal.type === 'warning'" class="w-7 h-7 stroke-[2.5]" />
                            <Info v-else class="w-7 h-7 stroke-[2.5]" />
                        </div>

                        <!-- Title & Subtitle -->
                        <div>
                            <h3 class="text-base font-black text-slate-900">{{ confirmModal.title }}</h3>
                            <p v-if="confirmModal.subtitle" class="text-xs font-bold text-amber-800 mt-1">{{ confirmModal.subtitle }}</p>
                        </div>

                        <!-- Highlight Customer Card -->
                        <div v-if="confirmModal.customer" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-left">
                            <div class="flex items-center justify-between">
                                <p class="font-black text-xs text-slate-900 truncate">{{ confirmModal.customer.name }}</p>
                                <span :class="[getTierBadgeClass(confirmModal.customer.tier), 'text-[9px] font-black uppercase px-2 py-0.5 rounded border']">
                                    {{ getTierLabel(confirmModal.customer.tier) }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ confirmModal.customer.address || 'Langsung di Toko' }}</p>
                            <div class="flex justify-between items-center text-[11px] mt-2 pt-2 border-t border-slate-200/80">
                                <span class="text-slate-500 font-medium">Sisa Piutang:</span>
                                <span :class="Number(confirmModal.customer.current_debt) > 0 ? 'text-rose-600 font-black' : 'text-emerald-700 font-bold'">
                                    {{ formatRupiah(confirmModal.customer.current_debt) }}
                                </span>
                            </div>
                        </div>

                        <!-- Message -->
                        <p class="text-xs text-slate-600 leading-relaxed px-2">
                            {{ confirmModal.message }}
                        </p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button 
                            type="button"
                            @click="confirmModal.isOpen = false" 
                            class="px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 cursor-pointer transition active:scale-95"
                        >
                            {{ confirmModal.actionType === 'close' ? 'Tutup' : 'Batal' }}
                        </button>
                        <button 
                            v-if="confirmModal.actionType !== 'close'"
                            type="button"
                            @click="handleConfirmAction" 
                            :class="[
                                confirmModal.actionType === 'open_debt' ? 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/25' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/25',
                                'px-5 py-2.5 text-white font-black rounded-xl text-xs transition cursor-pointer active:scale-95 shadow-md flex items-center gap-1.5'
                            ]"
                        >
                            <Wallet v-if="confirmModal.actionType === 'open_debt'" class="w-3.5 h-3.5" />
                            <Trash2 v-else class="w-3.5 h-3.5" />
                            <span>{{ confirmModal.confirmText }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ================= FLOATING TOAST NOTIFICATION ================= -->
        <Teleport to="body">
            <div 
                v-if="toast.show"
                class="fixed bottom-6 right-6 z-[110] flex items-start gap-3 p-4 rounded-2xl shadow-2xl border transition-all duration-300 max-w-sm"
                :class="[
                    toast.type === 'success' ? 'bg-emerald-900/95 border-emerald-500 text-white shadow-emerald-950/40' : 
                    (toast.type === 'error' ? 'bg-rose-950/95 border-rose-500 text-white shadow-rose-950/40' : 'bg-slate-900/95 border-slate-700 text-white shadow-slate-950/40')
                ]"
            >
                <div class="shrink-0 mt-0.5">
                    <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-400" />
                    <AlertTriangle v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-400" />
                    <Info v-else class="w-5 h-5 text-sky-400" />
                </div>
                <div class="flex-1 min-w-0 text-left pr-2">
                    <p class="text-xs font-black tracking-wide">{{ toast.title }}</p>
                    <p class="text-[11px] text-slate-200 mt-0.5 leading-snug">{{ toast.message }}</p>
                </div>
                <button @click="toast.show = false" class="text-slate-400 hover:text-white transition p-0.5 cursor-pointer">
                    <X class="w-4 h-4" />
                </button>
            </div>
        </Teleport>
    </MainLayout>
</template>
