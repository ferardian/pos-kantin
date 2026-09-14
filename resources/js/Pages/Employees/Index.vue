<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Users, UserPlus, Phone, Edit3, Trash2, CheckCircle2, 
    XCircle, Search, Sparkles, Building2, Wallet, X
} from 'lucide-vue-next';

const props = defineProps({
    employees: Array,
});

const searchQuery = ref('');
const selectedDeptFilter = ref('all');
const selectedStatusFilter = ref('all');

const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedEmployee = ref(null);

const addForm = useForm({
    name: '',
    department: '',
    phone: '',
    is_active: true,
});

const editForm = useForm({
    id: null,
    name: '',
    department: '',
    phone: '',
    is_active: true,
});

// Unique departments for filter
const departments = computed(() => {
    const list = (props.employees || [])
        .map(e => e.department)
        .filter(d => Boolean(d));
    return [...new Set(list)].sort();
});

const filteredEmployees = computed(() => {
    return (props.employees || []).filter(emp => {
        const matchesSearch = 
            emp.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (emp.department && emp.department.toLowerCase().includes(searchQuery.value.toLowerCase())) ||
            (emp.phone && emp.phone.includes(searchQuery.value));
        
        const matchesDept = selectedDeptFilter.value === 'all' || emp.department === selectedDeptFilter.value;
        const matchesStatus = selectedStatusFilter.value === 'all' || 
            (selectedStatusFilter.value === 'active' && emp.is_active) ||
            (selectedStatusFilter.value === 'inactive' && !emp.is_active);

        return matchesSearch && matchesDept && matchesStatus;
    });
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const openAddModal = () => {
    addForm.reset();
    isAddModalOpen.value = true;
};

const submitAdd = () => {
    addForm.post('/employees', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            addForm.reset();
        }
    });
};

const openEditModal = (emp) => {
    selectedEmployee.value = emp;
    editForm.id = emp.id;
    editForm.name = emp.name;
    editForm.department = emp.department || '';
    editForm.phone = emp.phone || '';
    editForm.is_active = Boolean(emp.is_active);
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    editForm.put(`/employees/${editForm.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
            editForm.reset();
        }
    });
};

const confirmDelete = (emp) => {
    if (emp.total_remaining > 0) {
        alert('Karyawan ini masih memiliki tanggungan piutang yang belum lunas sebesar ' + formatRupiah(emp.total_remaining));
        return;
    }
    if (confirm(`Yakin ingin menghapus data karyawan "${emp.name}"?`)) {
        router.delete(`/employees/${emp.id}`);
    }
};
</script>

<template>
    <Head title="Data Karyawan RSIA" />

    <MainLayout>
        <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5 tracking-tight">
                        <Users class="w-6 h-6 text-emerald-600" />
                        Data Karyawan RSIA
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
                        Kelola data staf & karyawan RSIA Aisyiyah Pekajangan untuk pencatatan bon/piutang kantin.
                    </p>
                </div>
                <button 
                    @click="openAddModal"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition shadow-sm hover:shadow active:scale-98 cursor-pointer"
                >
                    <UserPlus class="w-4 h-4" />
                    Tambah Karyawan
                </button>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="relative">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                        v-model="searchQuery" 
                        type="text" 
                        placeholder="Cari nama, unit/bagian, HP..." 
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                    />
                </div>
                <select 
                    v-model="selectedDeptFilter"
                    class="py-2.5 px-3.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition cursor-pointer text-slate-700"
                >
                    <option value="all">Semua Unit / Departemen</option>
                    <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                </select>
                <select 
                    v-model="selectedStatusFilter"
                    class="py-2.5 px-3.5 text-sm bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition cursor-pointer text-slate-700"
                >
                    <option value="all">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Non-Aktif</option>
                </select>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50/80 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3.5">Nama Karyawan</th>
                                <th class="px-5 py-3.5">Unit / Departemen</th>
                                <th class="px-5 py-3.5">No. Telepon / WA</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Bon / Piutang Berjalan</th>
                                <th class="px-5 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredEmployees.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                    <Users class="w-8 h-8 mx-auto mb-2 opacity-40 text-slate-400" />
                                    <p class="font-medium">Belum ada data karyawan yang cocok.</p>
                                </td>
                            </tr>
                            <tr 
                                v-for="emp in filteredEmployees" 
                                :key="emp.id"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-slate-900">{{ emp.name }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="emp.department" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        <Building2 class="w-3 h-3" />
                                        {{ emp.department }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-xs">-</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span v-if="emp.phone" class="inline-flex items-center gap-1 text-xs text-slate-700 font-mono">
                                        <Phone class="w-3 h-3 text-slate-400" />
                                        {{ emp.phone }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-xs">-</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span 
                                        :class="emp.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                    >
                                        <CheckCircle2 v-if="emp.is_active" class="w-3 h-3" />
                                        <XCircle v-else class="w-3 h-3" />
                                        {{ emp.is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <span 
                                        v-if="emp.total_remaining > 0"
                                        class="inline-flex items-center gap-1 font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-100 text-xs"
                                    >
                                        <Wallet class="w-3 h-3" />
                                        {{ formatRupiah(emp.total_remaining) }}
                                    </span>
                                    <span v-else class="text-xs font-medium text-slate-400">
                                        Rp 0 (Lunas)
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <button 
                                            @click="openEditModal(emp)"
                                            class="p-1.5 text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                            title="Ubah Data"
                                        >
                                            <Edit3 class="w-4 h-4" />
                                        </button>
                                        <button 
                                            @click="confirmDelete(emp)"
                                            class="p-1.5 text-slate-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Karyawan"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <UserPlus class="w-5 h-5 text-emerald-600" />
                        Tambah Karyawan Baru
                    </h2>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap *</label>
                        <input 
                            v-model="addForm.name" 
                            type="text" 
                            required 
                            placeholder="Contoh: Siti Aisyah, Amd.Kep"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unit / Departemen</label>
                        <input 
                            v-model="addForm.department" 
                            type="text" 
                            placeholder="Contoh: Poli Anak, IGD, Rawat Inap, Farmasi"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp / HP</label>
                        <input 
                            v-model="addForm.phone" 
                            type="text" 
                            placeholder="Contoh: 08123456789"
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            v-model="addForm.is_active" 
                            type="checkbox" 
                            id="add_active"
                            class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
                        />
                        <label for="add_active" class="text-sm font-semibold text-slate-700 cursor-pointer">Status Aktif</label>
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
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <Edit3 class="w-5 h-5 text-emerald-600" />
                        Ubah Data Karyawan
                    </h2>
                    <button @click="isEditModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Lengkap *</label>
                        <input 
                            v-model="editForm.name" 
                            type="text" 
                            required 
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unit / Departemen</label>
                        <input 
                            v-model="editForm.department" 
                            type="text" 
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp / HP</label>
                        <input 
                            v-model="editForm.phone" 
                            type="text" 
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none"
                        />
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            v-model="editForm.is_active" 
                            type="checkbox" 
                            id="edit_active"
                            class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
                        />
                        <label for="edit_active" class="text-sm font-semibold text-slate-700 cursor-pointer">Status Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="isEditModalOpen = false" 
                            class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="editForm.processing"
                            class="px-5 py-2 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-xs"
                        >
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
