<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Users, User, UserPlus, UserCog, ShieldCheck, ShoppingCart, 
    Smartphone, Truck, Mail, Phone, Lock, Key, Edit3, 
    Trash2, CheckCircle2, XCircle, Search, Eye, EyeOff,
    Sparkles, Shield, AlertTriangle, X
} from 'lucide-vue-next';

const props = defineProps({
    users: Array,
    authUserId: Number,
});

// Search & Filters
const searchQuery = ref('');
const selectedRoleFilter = ref('all');
const selectedStatusFilter = ref('all');

// Modals
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isResetPasswordModalOpen = ref(false);
const selectedUser = ref(null);

const showPassword = ref(false);
const showResetPassword = ref(false);

// Forms
const addForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'kasir',
    password: '',
    is_active: true,
});

const editForm = useForm({
    id: null,
    name: '',
    email: '',
    phone: '',
    role: 'kasir',
    is_active: true,
});

const resetPasswordForm = useForm({
    new_password: '',
    confirm_password: '',
});

// Roles info & styling
const rolesList = [
    { id: 'kasir', label: 'Kasir Toko (POS)', desc: 'Melayani transaksi penjualan kasir, kas, dan pembayaran' },
    { id: 'sales', label: 'Sales Lapangan (SO)', desc: 'Membuat pesanan sales order lewat aplikasi mobile & kanvaser' },
    { id: 'gudang', label: 'Gudang & Logistik', desc: 'Penerimaan barang dari supplier, transfer stok, dan cek fisik' },
    { id: 'admin', label: 'Administrator / Owner', desc: 'Akses penuh ke seluruh menu, laporan keuangan, dan pengaturan' },
];

const getRoleLabel = (role) => {
    switch (role) {
        case 'admin': return 'Administrator';
        case 'kasir': return 'Kasir Toko';
        case 'sales': return 'Sales Lapangan';
        case 'gudang': return 'Gudang & Logistik';
        default: return role;
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin': return 'bg-rose-100 text-rose-800 border-rose-300';
        case 'kasir': return 'bg-emerald-100 text-emerald-800 border-emerald-300';
        case 'sales': return 'bg-amber-100 text-amber-900 border-amber-300';
        case 'gudang': return 'bg-indigo-100 text-indigo-800 border-indigo-300';
        default: return 'bg-slate-100 text-slate-800 border-slate-200';
    }
};

const getRoleAvatarBg = (role) => {
    switch (role) {
        case 'admin': return 'bg-rose-600 text-white';
        case 'kasir': return 'bg-emerald-600 text-white';
        case 'sales': return 'bg-amber-600 text-white';
        case 'gudang': return 'bg-indigo-600 text-white';
        default: return 'bg-slate-700 text-white';
    }
};

// Filtered Users
const filteredUsers = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    return (props.users || []).filter(u => {
        const matchesQuery = !q || 
            (u.name && u.name.toLowerCase().includes(q)) || 
            (u.email && u.email.toLowerCase().includes(q)) || 
            (u.phone && u.phone.toLowerCase().includes(q));

        const matchesRole = selectedRoleFilter.value === 'all' || u.role === selectedRoleFilter.value;
        const matchesStatus = selectedStatusFilter.value === 'all' || 
            (selectedStatusFilter.value === 'active' && u.is_active) || 
            (selectedStatusFilter.value === 'inactive' && !u.is_active);

        return matchesQuery && matchesRole && matchesStatus;
    });
});

// Summary Counts
const totalCount = computed(() => props.users?.length || 0);
const kasirCount = computed(() => (props.users || []).filter(u => u.role === 'kasir').length);
const salesCount = computed(() => (props.users || []).filter(u => u.role === 'sales').length);
const gudangCount = computed(() => (props.users || []).filter(u => u.role === 'gudang').length);
const adminCount = computed(() => (props.users || []).filter(u => u.role === 'admin').length);

// Modal Handlers
const openAddModal = () => {
    addForm.reset();
    addForm.role = 'kasir';
    addForm.is_active = true;
    showPassword.value = false;
    isAddModalOpen.value = true;
};

const openEditModal = (user) => {
    selectedUser.value = user;
    editForm.id = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.phone = user.phone || '';
    editForm.role = user.role;
    editForm.is_active = Boolean(user.is_active);
    isEditModalOpen.value = true;
};

const openResetPasswordModal = (user) => {
    selectedUser.value = user;
    resetPasswordForm.reset();
    showResetPassword.value = false;
    isResetPasswordModalOpen.value = true;
};

// Submissions
const submitAdd = () => {
    addForm.post('/users', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            addForm.reset();
        },
    });
};

const submitEdit = () => {
    editForm.put(`/users/${editForm.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
    });
};

const submitResetPassword = () => {
    if (resetPasswordForm.new_password !== resetPasswordForm.confirm_password) {
        alert('Konfirmasi kata sandi tidak cocok. Mohon periksa kembali.');
        return;
    }
    resetPasswordForm.post(`/users/${selectedUser.value.id}/reset-password`, {
        onSuccess: () => {
            isResetPasswordModalOpen.value = false;
            resetPasswordForm.reset();
        },
    });
};

const toggleUserStatus = (user) => {
    if (user.id === props.authUserId) {
        alert('Anda tidak dapat menonaktifkan akun sendiri.');
        return;
    }
    router.post(`/users/${user.id}/toggle-status`);
};

const deleteUser = (user) => {
    if (user.id === props.authUserId) {
        alert('Anda tidak dapat menghapus akun sendiri yang sedang aktif digunakan.');
        return;
    }
    if (confirm(`Apakah Anda yakin ingin menghapus akun staff "${user.name}" (${getRoleLabel(user.role)})?`)) {
        router.delete(`/users/${user.id}`);
    }
};
</script>

<template>
    <Head title="Kelola Pengguna & Staff" />
    <MainLayout>
        <div class="p-6 w-full space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <UserCog class="w-6 h-6 text-amber-600" />
                        <span>Kelola Pengguna & Hak Akses Staff</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Manajemen akun karyawan, penugasan divisi (Kasir, Sales Lapangan, Gudang, Admin), nomor kontak, dan keamanan kata sandi.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        @click="openAddModal"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                    >
                        <UserPlus class="w-4 h-4 text-amber-400" />
                        <span>Tambah Pengguna Baru</span>
                    </button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold">
                        <Users class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Total Staff</p>
                        <p class="text-base font-black text-slate-900">{{ totalCount }}</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <ShoppingCart class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Kasir POS</p>
                        <p class="text-base font-black text-emerald-700">{{ kasirCount }}</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                        <Smartphone class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Sales (SO)</p>
                        <p class="text-base font-black text-amber-700">{{ salesCount }}</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                        <Truck class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Gudang</p>
                        <p class="text-base font-black text-indigo-700">{{ gudangCount }}</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Admin</p>
                        <p class="text-base font-black text-rose-700">{{ adminCount }}</p>
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
                        placeholder="Cari nama, email, atau nomor HP..." 
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                    />
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
                    <!-- Role Filter -->
                    <select 
                        v-model="selectedRoleFilter" 
                        class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">Semua Hak Akses (Role)</option>
                        <option value="admin">Administrator</option>
                        <option value="kasir">Kasir Toko</option>
                        <option value="sales">Sales Lapangan</option>
                        <option value="gudang">Gudang & Logistik</option>
                    </select>

                    <!-- Status Filter -->
                    <select 
                        v-model="selectedStatusFilter" 
                        class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500"
                    >
                        <option value="all">Semua Status Akun</option>
                        <option value="active">Akun Aktif</option>
                        <option value="inactive">Akun Nonaktif</option>
                    </select>
                </div>
            </div>

            <!-- Users Grid Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div 
                    v-for="u in filteredUsers" 
                    :key="u.id"
                    class="bg-white border border-slate-200 hover:border-slate-300 rounded-3xl p-5 shadow-xs flex flex-col justify-between transition group relative"
                    :class="!u.is_active ? 'opacity-70 bg-slate-50/50' : ''"
                >
                    <div class="space-y-4">
                        <!-- Top Header: Avatar, Name, Role Badge -->
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <div 
                                    :class="[getRoleAvatarBg(u.role), 'w-11 h-11 rounded-2xl flex items-center justify-center font-black text-sm shadow-xs shrink-0']"
                                >
                                    {{ u.name ? u.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <h3 class="text-sm font-black text-slate-900 truncate">{{ u.name }}</h3>
                                        <span v-if="u.id === authUserId" class="px-1.5 py-0.2 bg-amber-100 text-amber-800 text-[9px] font-black rounded uppercase">Anda</span>
                                    </div>
                                    <span :class="[getRoleBadgeClass(u.role), 'inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md border mt-1']">
                                        {{ getRoleLabel(u.role) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Status Badge & Quick Toggle -->
                            <button 
                                @click="toggleUserStatus(u)"
                                :disabled="u.id === authUserId"
                                :title="u.is_active ? 'Klik untuk nonaktifkan akun' : 'Klik untuk aktifkan akun'"
                                class="shrink-0 flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase transition cursor-pointer"
                                :class="u.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100'"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="u.is_active ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                                <span>{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </button>
                        </div>

                        <!-- Contact Details -->
                        <div class="text-xs text-slate-600 space-y-2 bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80">
                            <p class="flex items-center gap-2 truncate">
                                <Mail class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <span class="truncate font-medium text-slate-700">{{ u.email }}</span>
                            </p>
                            <p class="flex items-center gap-2">
                                <Phone class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <span class="font-medium text-slate-700">{{ u.phone || '-' }}</span>
                            </p>
                        </div>

                        <!-- Performance Activity Metrics -->
                        <div class="grid grid-cols-2 gap-2 text-center text-xs">
                            <div class="p-2 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Transaksi Kasir</p>
                                <p class="text-xs font-black text-slate-800">{{ u.transactions_count || 0 }} Struk</p>
                            </div>
                            <div class="p-2 bg-slate-50 rounded-xl border border-slate-100">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Pesanan Sales</p>
                                <p class="text-xs font-black text-slate-800">{{ u.sales_orders_count || 0 }} Order</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons -->
                    <div class="pt-3.5 border-t border-slate-100 flex items-center gap-2 mt-4">
                        <button 
                            @click="openEditModal(u)"
                            class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-[11px] flex items-center justify-center gap-1.5 transition cursor-pointer"
                        >
                            <Edit3 class="w-3.5 h-3.5 text-slate-600" />
                            <span>Edit Profil</span>
                        </button>
                        <button 
                            @click="openResetPasswordModal(u)"
                            title="Ganti Password Akun"
                            class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 font-bold rounded-xl transition cursor-pointer"
                        >
                            <Key class="w-3.5 h-3.5 text-amber-700" />
                        </button>
                        <button 
                            v-if="u.id !== authUserId"
                            @click="deleteUser(u)"
                            title="Hapus Akun Pengguna"
                            class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold rounded-xl transition cursor-pointer"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredUsers.length === 0" class="bg-white rounded-3xl p-12 border border-slate-200 text-center space-y-3">
                <Users class="w-12 h-12 text-slate-300 mx-auto" />
                <h3 class="text-sm font-bold text-slate-700">Tidak ada data pengguna yang sesuai</h3>
                <p class="text-xs text-slate-400">Silakan ubah kata kunci pencarian atau reset filter role.</p>
            </div>
        </div>

        <!-- ================= MODAL: TAMBAH PENGGUNA BARU ================= -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Tambah Pengguna / Staff Baru</h3>
                        <p class="text-[11px] text-slate-400">Buat akun login untuk kasir, sales lapangan, gudang, atau admin.</p>
                    </div>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAdd" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Lengkap Karyawan</label>
                        <div class="relative">
                            <User class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="addForm.name" 
                                required 
                                placeholder="Contoh: Budi Santoso" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat Email (Username Login)</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="addForm.email" 
                                type="email" 
                                required 
                                placeholder="staff@rsiaaisyiyah.com" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">No. WhatsApp / HP</label>
                        <div class="relative">
                            <Phone class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="addForm.phone" 
                                placeholder="081234567890" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Divisi & Hak Akses (Role)</label>
                        <select 
                            v-model="addForm.role" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 font-bold focus:outline-none focus:border-amber-500"
                        >
                            <option value="kasir">Kasir Toko (POS & Pembayaran)</option>
                            <option value="sales">Sales Lapangan (Mobile SO & Kanvaser)</option>
                            <option value="gudang">Gudang & Logistik (Stok & Penerimaan)</option>
                            <option value="admin">Administrator / Owner (Akses Penuh)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Kata Sandi Awal (Password)</label>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                :type="showPassword ? 'text' : 'password'" 
                                v-model="addForm.password" 
                                required 
                                minlength="6"
                                placeholder="Minimal 6 karakter" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-11 py-2.5 text-slate-900 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword" 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1"
                            >
                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            id="add_is_active" 
                            type="checkbox" 
                            v-model="addForm.is_active" 
                            class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-4 w-4"
                        />
                        <label for="add_is_active" class="text-slate-700 font-bold text-xs cursor-pointer">
                            Akun langsung aktif dan dapat digunakan untuk login
                        </label>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="addForm.processing" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md active:scale-95"
                    >
                        Simpan Pengguna Baru
                    </button>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: EDIT DATA PENGGUNA ================= -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Edit Data Pengguna</h3>
                        <p class="text-[11px] text-slate-400">{{ selectedUser?.name }}</p>
                    </div>
                    <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Lengkap</label>
                        <div class="relative">
                            <User class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="editForm.name" 
                                required 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat Email (Login)</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="editForm.email" 
                                type="email" 
                                required 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">No. WhatsApp / HP</label>
                        <div class="relative">
                            <Phone class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                v-model="editForm.phone" 
                                placeholder="081234567890" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Divisi & Hak Akses (Role)</label>
                        <select 
                            v-model="editForm.role" 
                            :disabled="selectedUser?.id === authUserId"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 font-bold focus:outline-none focus:border-amber-500 disabled:opacity-60"
                        >
                            <option value="kasir">Kasir Toko (POS & Pembayaran)</option>
                            <option value="sales">Sales Lapangan (Mobile SO & Kanvaser)</option>
                            <option value="gudang">Gudang & Logistik (Stok & Penerimaan)</option>
                            <option value="admin">Administrator / Owner (Akses Penuh)</option>
                        </select>
                        <p v-if="selectedUser?.id === authUserId" class="text-[10px] text-amber-600 mt-1 font-medium">
                            Role akun administrator utama Anda tidak dapat diubah di sini.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            id="edit_is_active" 
                            type="checkbox" 
                            v-model="editForm.is_active" 
                            :disabled="selectedUser?.id === authUserId"
                            class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-4 w-4 disabled:opacity-50"
                        />
                        <label for="edit_is_active" class="text-slate-700 font-bold text-xs cursor-pointer">
                            Status Akun Aktif
                        </label>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="editForm.processing" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md active:scale-95"
                    >
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: RESET PASSWORD ================= -->
        <div v-if="isResetPasswordModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Ubah Kata Sandi (Password)</h3>
                        <p class="text-[11px] text-slate-400">Pengguna: {{ selectedUser?.name }} ({{ selectedUser?.email }})</p>
                    </div>
                    <button @click="isResetPasswordModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitResetPassword" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Kata Sandi Baru</label>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                :type="showResetPassword ? 'text' : 'password'" 
                                v-model="resetPasswordForm.new_password" 
                                required 
                                minlength="6"
                                placeholder="Minimal 6 karakter" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-11 py-2.5 text-slate-900 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                            <button 
                                type="button" 
                                @click="showResetPassword = !showResetPassword" 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1"
                            >
                                <Eye v-if="!showResetPassword" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Konfirmasi Kata Sandi Baru</label>
                        <div class="relative">
                            <Key class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input 
                                :type="showResetPassword ? 'text' : 'password'" 
                                v-model="resetPasswordForm.confirm_password" 
                                required 
                                minlength="6"
                                placeholder="Ulangi kata sandi baru" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-slate-900 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition" 
                            />
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="resetPasswordForm.processing" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition text-xs mt-2 cursor-pointer shadow-md active:scale-95"
                    >
                        Simpan Password Baru
                    </button>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
