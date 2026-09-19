<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Truck, Plus, Search, PackageCheck, Calendar, 
    FileText, User, ArrowDownRight, Printer, X, 
    Trash2, PlusCircle, CheckCircle2, ChevronDown, Check,
    Package, Building2, Phone, MapPin, Layers,
    Clock, XCircle, AlertCircle, ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
    receipts: Array,
    products: Array,
    suppliers: Array,
    locations: Array,
    pendingCount: Number,
    totalReceiptsThisMonth: Number,
    totalCostInboundThisMonth: Number,
    user: Object,
});

const activeTab = ref('receipts'); // 'receipts', 'suppliers'
const selectedStatusFilter = ref('all'); // 'all', 'pending', 'approved', 'rejected'
const searchQuery = ref('');
const isAddModalOpen = ref(false);
const isAddSupplierModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const isRejectModalOpen = ref(false);
const selectedReceipt = ref(null);

// Searchable Supplier Combobox State in Form
const isSupplierDropdownOpen = ref(false);
const supplierSearchQuery = ref('');

// Searchable Product Combobox per row state
const activeProductDropdownIndex = ref(null);
const productSearchQueries = ref({});

// Form Penerimaan Barang
const form = useForm({
    supplier_id: props.suppliers[0]?.id || null,
    supplier_name: props.suppliers[0]?.name || '',
    supplier_invoice_number: '',
    location_id: props.locations?.find(l => l.is_default)?.id || props.locations?.[0]?.id || null,
    receipt_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        {
            product_id: props.products[0]?.id || null,
            product_unit_id: props.products[0]?.units[0]?.id || null,
            qty_received: 10,
            cost_price_per_unit: props.products[0]?.units[0]?.cost_price || 0,
        }
    ]
});

// Form Tambah Master Supplier
const supplierForm = useForm({
    name: '',
    phone: '',
    contact_person: '',
    address: '',
});

// Form Penolakan
const rejectForm = useForm({
    reason: '',
});

// Format Rupiah
const formatRupiah = (number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(number || 0);
};

const formatDate = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const formatDateTime = (val) => {
    if (!val) return '-';
    return new Date(val).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Filtered Suppliers for Combobox
const filteredSuppliers = computed(() => {
    const q = supplierSearchQuery.value.toLowerCase().trim();
    if (!q) return props.suppliers;
    return props.suppliers.filter(s => 
        s.name.toLowerCase().includes(q) || 
        (s.contact_person && s.contact_person.toLowerCase().includes(q))
    );
});

const isSupplierExactMatch = computed(() => {
    const q = supplierSearchQuery.value.toLowerCase().trim();
    if (!q) return true;
    return props.suppliers.some(s => s.name.toLowerCase() === q);
});

const selectSupplier = (supplier) => {
    form.supplier_id = supplier.id;
    form.supplier_name = supplier.name;
    isSupplierDropdownOpen.value = false;
    supplierSearchQuery.value = '';
};

const createAndSelectSupplier = () => {
    const name = supplierSearchQuery.value.trim();
    if (!name) return;

    router.post('/suppliers', { name }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newlyCreated = page.props.suppliers?.find(s => s.name.toLowerCase() === name.toLowerCase());
            if (newlyCreated) {
                form.supplier_id = newlyCreated.id;
                form.supplier_name = newlyCreated.name;
            }
            isSupplierDropdownOpen.value = false;
            supplierSearchQuery.value = '';
        },
    });
};

const submitSupplierForm = () => {
    supplierForm.post('/suppliers', {
        onSuccess: () => {
            isAddSupplierModalOpen.value = false;
            supplierForm.reset();
        }
    });
};

// Row item helpers
const addItemRow = () => {
    const defaultProduct = props.products[0];
    const defaultUnit = defaultProduct?.units[0];

    form.items.push({
        product_id: defaultProduct?.id || null,
        product_unit_id: defaultUnit?.id || null,
        qty_received: 1,
        cost_price_per_unit: defaultUnit?.cost_price || 0,
    });
};

const removeItemRow = (idx) => {
    if (form.items.length > 1) {
        form.items.splice(idx, 1);
        delete productSearchQueries.value[idx];
    }
};

const getProductById = (id) => {
    return props.products.find(p => p.id === Number(id));
};

const getFilteredProductsForRow = (idx) => {
    const q = (productSearchQueries.value[idx] || '').toLowerCase().trim();
    if (!q) return props.products;
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        p.sku.toLowerCase().includes(q) || 
        (p.barcode && p.barcode.includes(q)) ||
        (p.brand && p.brand.name.toLowerCase().includes(q))
    );
};

const selectProductForRow = (idx, product) => {
    const item = form.items[idx];
    item.product_id = product.id;
    if (product.units && product.units.length > 0) {
        item.product_unit_id = product.units[0].id;
        item.cost_price_per_unit = product.units[0].cost_price || 0;
    }
    activeProductDropdownIndex.value = null;
    productSearchQueries.value[idx] = '';
};

const onUnitChange = (item, unitId) => {
    const prod = getProductById(item.product_id);
    if (prod) {
        const unit = prod.units.find(u => u.id === Number(unitId));
        if (unit) {
            item.cost_price_per_unit = unit.cost_price || 0;
        }
    }
};

const totalInboundEstimation = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.qty_received) * Number(item.cost_price_per_unit || 0)), 0);
});

const filteredReceipts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    return (props.receipts || []).filter(r => {
        const matchesStatus = selectedStatusFilter.value === 'all' || r.status === selectedStatusFilter.value;
        const matchesQuery = !q || 
            r.receipt_number.toLowerCase().includes(q) || 
            r.supplier_name.toLowerCase().includes(q) || 
            (r.supplier_invoice_number && r.supplier_invoice_number.toLowerCase().includes(q)) ||
            (r.receiver && r.receiver.name.toLowerCase().includes(q)) ||
            r.items.some(it => it.product?.name.toLowerCase().includes(q));

        return matchesStatus && matchesQuery;
    });
});

const submitReceipt = () => {
    form.post('/goods-receipts', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            form.reset();
            form.items = [
                {
                    product_id: props.products[0]?.id || null,
                    product_unit_id: props.products[0]?.units[0]?.id || null,
                    qty_received: 10,
                    cost_price_per_unit: props.products[0]?.units[0]?.cost_price || 0,
                }
            ];
            productSearchQueries.value = {};
            activeProductDropdownIndex.value = null;
        }
    });
};

const openDetail = (receipt) => {
    selectedReceipt.value = receipt;
    isDetailModalOpen.value = true;
};

// Approval Actions (Admin Only)
const approveReceipt = (receipt) => {
    if (!confirm(`Setujui penerimaan barang ${receipt.receipt_number}? Stok fisik produk akan otomatis bertambah ke inventaris.`)) {
        return;
    }
    router.post(`/goods-receipts/${receipt.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (isDetailModalOpen.value) {
                isDetailModalOpen.value = false;
            }
        }
    });
};

const openRejectModal = (receipt) => {
    selectedReceipt.value = receipt;
    rejectForm.reset();
    isRejectModalOpen.value = true;
};

const submitReject = () => {
    if (!selectedReceipt.value) return;
    rejectForm.post(`/goods-receipts/${selectedReceipt.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
            if (isDetailModalOpen.value) {
                isDetailModalOpen.value = false;
            }
        }
    });
};
</script>

<template>
    <MainLayout>
        <Head title="Penerimaan Barang & Belanja Stok" />
        <div class="p-6 w-full space-y-6">
            <!-- Header with Tabs -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <Truck class="w-6 h-6 text-amber-600" />
                        <span>Penerimaan Barang & Belanja Stok</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pencatatan belanja kulakan supplier oleh kasir, verifikasi persetujuan Admin, dan penambahan stok resmi.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Action Buttons -->
                    <button 
                        v-if="activeTab === 'receipts'"
                        @click="isAddModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer justify-center active:scale-95"
                    >
                        <Plus class="w-4 h-4 text-amber-400" />
                        <span>Catat Penerimaan Barang</span>
                    </button>

                    <button 
                        v-if="activeTab === 'suppliers'"
                        @click="isAddSupplierModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer justify-center active:scale-95"
                    >
                        <Building2 class="w-4 h-4 text-amber-400" />
                        <span>+ Tambah Supplier Baru</span>
                    </button>

                    <!-- Tabs Switcher -->
                    <div class="flex items-center gap-1 p-1 bg-slate-100 rounded-2xl">
                        <button 
                            @click="activeTab = 'receipts'"
                            :class="activeTab === 'receipts' ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Truck class="w-3.5 h-3.5" />
                            <span>Surat Penerimaan</span>
                            <span v-if="pendingCount > 0" class="px-1.5 py-0.2 bg-amber-500 text-white text-[9px] font-black rounded-full animate-pulse">
                                {{ pendingCount }}
                            </span>
                        </button>
                        <button 
                            @click="activeTab = 'suppliers'"
                            :class="activeTab === 'suppliers' ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Building2 class="w-3.5 h-3.5" />
                            <span>Master Supplier ({{ suppliers.length }})</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Selesai Disetujui</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ totalReceiptsThisMonth }} Faktur</h3>
                        <p class="text-[10px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                            <PackageCheck class="w-3 h-3" /> Bulan Berjalan
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <PackageCheck class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Menunggu Persetujuan</p>
                        <h3 class="text-xl font-black text-amber-600 mt-1">{{ pendingCount }} Dokumen</h3>
                        <p class="text-[10px] text-amber-700 font-bold mt-1 flex items-center gap-1">
                            <Clock class="w-3 h-3" /> Butuh Approval Admin
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <Clock class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nilai Belanja Disetujui</p>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 mt-1">{{ formatRupiah(totalCostInboundThisMonth) }}</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">HPP Belanja Terverifikasi</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <Calendar class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Database Supplier</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ suppliers.length }} Mitra</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Distributor & Toko Grosir</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                        <Building2 class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- TAB 1: RIWAYAT PENERIMAAN BARANG -->
            <div v-if="activeTab === 'receipts'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <button 
                            @click="selectedStatusFilter = 'all'"
                            :class="selectedStatusFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer shrink-0"
                        >
                            Semua Status
                        </button>
                        <button 
                            @click="selectedStatusFilter = 'pending'"
                            :class="selectedStatusFilter === 'pending' ? 'bg-amber-600 text-white font-bold' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1.5 shrink-0"
                        >
                            <Clock class="w-3 h-3" />
                            <span>Menunggu Persetujuan ({{ pendingCount }})</span>
                        </button>
                        <button 
                            @click="selectedStatusFilter = 'approved'"
                            :class="selectedStatusFilter === 'approved' ? 'bg-emerald-700 text-white font-bold' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1.5 shrink-0"
                        >
                            <CheckCircle2 class="w-3 h-3" />
                            <span>Disetujui</span>
                        </button>
                        <button 
                            @click="selectedStatusFilter = 'rejected'"
                            :class="selectedStatusFilter === 'rejected' ? 'bg-rose-700 text-white font-bold' : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100'"
                            class="px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1.5 shrink-0"
                        >
                            <XCircle class="w-3 h-3" />
                            <span>Ditolak</span>
                        </button>
                    </div>
                    
                    <div class="relative w-full sm:w-80">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari No GR, supplier, surat jalan..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4">No. Penerimaan & Tgl</th>
                                <th class="py-3.5 px-4">Distributor / Supplier</th>
                                <th class="py-3.5 px-4">Rincian Barang</th>
                                <th class="py-3.5 px-4">Status & Approval</th>
                                <th class="py-3.5 px-4 text-right">Nilai Belanja</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredReceipts.length === 0">
                                <td colspan="6" class="py-10 text-center text-slate-400">
                                    <Truck class="w-8 h-8 text-slate-300 mx-auto mb-2" />
                                    <p>Belum ada data penerimaan barang yang sesuai filter.</p>
                                </td>
                            </tr>
                            <tr v-for="rc in filteredReceipts" :key="rc.id" class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-mono font-bold text-slate-900 text-xs">{{ rc.receipt_number }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ formatDate(rc.receipt_date) }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ rc.supplier_name }}</div>
                                    <div v-if="rc.supplier_invoice_number" class="text-[10px] text-slate-500 font-mono">
                                        Faktur: {{ rc.supplier_invoice_number }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="space-y-1 max-w-xs">
                                        <div v-for="it in rc.items.slice(0, 3)" :key="it.id" class="text-[11px] text-slate-700 truncate">
                                            &bull; <strong class="text-slate-900">+{{ it.qty_received }} {{ it.unit?.unit_name || 'Pcs' }}</strong> {{ it.product?.name }}
                                        </div>
                                        <div v-if="rc.items.length > 3" class="text-[10px] text-slate-400 italic">
                                            +{{ rc.items.length - 3 }} barang lainnya...
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <!-- Status Badge -->
                                    <div class="space-y-1">
                                        <div>
                                            <span 
                                                v-if="rc.status === 'pending'"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-200"
                                            >
                                                <Clock class="w-3 h-3 text-amber-500" />
                                                <span>Menunggu Persetujuan</span>
                                            </span>
                                            <span 
                                                v-else-if="rc.status === 'approved'"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200"
                                            >
                                                <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                                <span>Disetujui Admin</span>
                                            </span>
                                            <span 
                                                v-else-if="rc.status === 'rejected'"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200"
                                            >
                                                <XCircle class="w-3 h-3 text-rose-600" />
                                                <span>Ditolak</span>
                                            </span>
                                        </div>

                                        <p class="text-[10px] text-slate-500">
                                            Petugas: <strong>{{ rc.receiver?.name || 'Kasir' }}</strong>
                                        </p>
                                        <p v-if="rc.approver" class="text-[9px] text-slate-400">
                                            Reviewer: {{ rc.approver?.name }}
                                        </p>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right font-black text-slate-900 text-xs whitespace-nowrap">
                                    {{ formatRupiah(rc.total_cost_amount) }}
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button 
                                            @click="openDetail(rc)"
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-[11px] transition cursor-pointer"
                                            title="Lihat Rincian Faktur"
                                        >
                                            Detail
                                        </button>

                                        <!-- Quick Admin Approve / Reject Buttons -->
                                        <template v-if="user?.role === 'admin' && rc.status === 'pending'">
                                            <button 
                                                @click="approveReceipt(rc)"
                                                class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-[11px] transition cursor-pointer shadow-xs active:scale-95 flex items-center gap-1"
                                                title="Setujui dan masukkan stok"
                                            >
                                                <Check class="w-3 h-3" />
                                                <span>Setujui</span>
                                            </button>
                                            <button 
                                                @click="openRejectModal(rc)"
                                                class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl text-[11px] transition cursor-pointer border border-rose-200"
                                                title="Tolak penerimaan barang ini"
                                            >
                                                <X class="w-3 h-3" />
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: MASTER DATA SUPPLIER -->
            <div v-if="activeTab === 'suppliers'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Daftar Supplier & Distributor Resmi Kantin</h3>
                    <button 
                        @click="isAddSupplierModalOpen = true"
                        class="px-3 py-1.5 bg-slate-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5 text-amber-400" />
                        <span>Tambah Supplier</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4">Nama Toko / Distributor</th>
                                <th class="py-3.5 px-4">Kontak Person (PIC)</th>
                                <th class="py-3.5 px-4">Telepon / WhatsApp</th>
                                <th class="py-3.5 px-4">Alamat Gudang / Kantor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="suppliers.length === 0">
                                <td colspan="4" class="py-8 text-center text-slate-400">
                                    Belum ada master data supplier rekanan.
                                </td>
                            </tr>
                            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-slate-50">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="w-4 h-4 text-amber-600 shrink-0" />
                                        <span>{{ s.name }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700 font-semibold">{{ s.contact_person || '-' }}</td>
                                <td class="py-3.5 px-4 font-mono text-slate-800">{{ s.phone || '-' }}</td>
                                <td class="py-3.5 px-4 text-slate-600">{{ s.address || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Form Catat Penerimaan Barang Masuk -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <Truck class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Catat Penerimaan Barang dari Supplier</h3>
                            <p class="text-xs text-slate-500">Entri belanja kulakan barang datang untuk diajukan ke Admin.</p>
                        </div>
                    </div>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Info Approval Notice -->
                <div class="bg-amber-50/80 border-b border-amber-200/80 px-6 py-2.5 flex items-center gap-2.5 text-[11px] text-amber-900">
                    <AlertCircle class="w-4 h-4 text-amber-600 shrink-0" />
                    <span>
                        <strong>Catatan Alur:</strong> Dokumen yang disimpan akan berstatus <strong>Menunggu Persetujuan</strong>. Stok fisik barang di toko akan otomatis bertambah setelah disetujui oleh Administrator.
                    </span>
                </div>

                <form @submit.prevent="submitReceipt" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <!-- Form Grid Header: Supplier, Lokasi Masuk, No SJ, Tanggal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Searchable Supplier Dropdown Combobox -->
                        <div class="relative">
                            <label class="block text-slate-700 font-bold mb-1">Supplier / Distributor *</label>
                            <button 
                                type="button"
                                @click="isSupplierDropdownOpen = !isSupplierDropdownOpen"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-left font-bold text-slate-900 flex items-center justify-between hover:border-amber-400 transition"
                            >
                                <span class="truncate">{{ form.supplier_name || 'Pilih Supplier...' }}</span>
                                <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                            </button>

                            <!-- Dropdown Panel -->
                            <div 
                                v-if="isSupplierDropdownOpen" 
                                class="absolute top-full left-0 right-0 mt-1 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl p-2.5 space-y-2 max-h-64 overflow-y-auto"
                            >
                                <div class="relative">
                                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                    <input 
                                        v-model="supplierSearchQuery" 
                                        type="text" 
                                        placeholder="Cari atau ketik nama supplier baru..."
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                    />
                                </div>

                                <div class="space-y-0.5 max-h-44 overflow-y-auto">
                                    <div 
                                        v-for="s in filteredSuppliers" 
                                        :key="s.id"
                                        @click="selectSupplier(s)"
                                        :class="[
                                            form.supplier_id === s.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50',
                                            'p-2 rounded-xl text-xs cursor-pointer flex items-center justify-between'
                                        ]"
                                    >
                                        <div>
                                            <p class="font-bold text-slate-900">{{ s.name }}</p>
                                            <p class="text-[10px] text-slate-400">{{ s.contact_person || 'Distributor' }} &bull; {{ s.phone || '-' }}</p>
                                        </div>
                                        <Check v-if="form.supplier_id === s.id" class="w-4 h-4 text-amber-600 shrink-0" />
                                    </div>

                                    <!-- Inline Quick Add New Supplier -->
                                    <div 
                                        v-if="!isSupplierExactMatch && supplierSearchQuery.trim()" 
                                        @click="createAndSelectSupplier"
                                        class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-xl font-bold text-xs cursor-pointer flex items-center gap-1.5 border border-amber-200 mt-1"
                                    >
                                        <PlusCircle class="w-4 h-4 text-amber-700" />
                                        <span>+ Daftarkan Supplier: "<strong>{{ supplierSearchQuery }}</strong>"</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Lokasi Tujuan Penerimaan -->
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Lokasi Masuk Barang *</label>
                            <select 
                                v-model="form.location_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                            >
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }} ({{ loc.code || 'Gudang' }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">No. Faktur / Nota Pembelian</label>
                            <input 
                                v-model="form.supplier_invoice_number" 
                                placeholder="Contoh: NOTA-10293"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono text-xs focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Tanggal Terima *</label>
                            <input 
                                v-model="form.receipt_date" 
                                type="date"
                                required 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- Multi-Item Inbound Section -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase text-slate-900">Daftar Barang yang Dibeli</h4>
                                <p class="text-[11px] text-slate-500">Pilih produk kantin, satuan masuk, dan jumlah kuantiti.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="addItemRow" 
                                class="px-3.5 py-1.5 rounded-xl bg-amber-100 text-amber-900 hover:bg-amber-200 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Baris Barang</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(it, idx) in form.items" 
                                :key="idx"
                                class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3"
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                    <!-- Searchable Combobox for Product -->
                                    <div class="sm:col-span-6 relative">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Produk Kantin (Cari Nama / Barcode)</label>
                                        <button 
                                            type="button"
                                            @click="activeProductDropdownIndex = (activeProductDropdownIndex === idx ? null : idx)"
                                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-left text-xs font-bold text-slate-900 flex items-center justify-between hover:border-amber-400 transition"
                                        >
                                            <span class="truncate">{{ getProductById(it.product_id)?.name || 'Pilih Produk Kantin...' }}</span>
                                            <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                                        </button>

                                        <!-- Dropdown Panel with Search Input -->
                                        <div 
                                            v-if="activeProductDropdownIndex === idx" 
                                            class="absolute top-full left-0 right-0 mt-1 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl p-2.5 space-y-2 max-h-64 overflow-y-auto"
                                        >
                                            <div class="relative">
                                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input 
                                                    v-model="productSearchQueries[idx]"
                                                    type="text" 
                                                    placeholder="Ketik nama makanan, minuman, snack, barang..."
                                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                                />
                                            </div>

                                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                                <div 
                                                    v-for="p in getFilteredProductsForRow(idx)" 
                                                    :key="p.id"
                                                    @click="selectProductForRow(idx, p)"
                                                    :class="[
                                                        it.product_id === p.id ? 'bg-amber-50 text-amber-900 font-bold border-amber-200' : 'text-slate-700 hover:bg-slate-50 border-transparent',
                                                        'p-2 rounded-xl text-xs cursor-pointer flex items-center justify-between border transition'
                                                    ]"
                                                >
                                                    <div>
                                                        <p class="font-bold text-slate-900">{{ p.name }}</p>
                                                        <p class="text-[10px] text-slate-400">{{ p.brand?.name || '-' }} &bull; Stok Sedia: {{ p.stock_physical }} {{ p.units[0]?.unit_name }}</p>
                                                    </div>
                                                    <Check v-if="it.product_id === p.id" class="w-4 h-4 text-amber-600 shrink-0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Satuan Pilihan -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Satuan Beli</label>
                                        <select 
                                            v-model="it.product_unit_id" 
                                            @change="onUnitChange(it, $event.target.value)"
                                            class="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900"
                                        >
                                            <option 
                                                v-for="u in getProductById(it.product_id)?.units" 
                                                :key="u.id" 
                                                :value="u.id"
                                            >
                                                {{ u.unit_name }} (x{{ u.conversion_ratio }})
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Qty Masuk -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-700 mb-1">Qty Beli (+)</label>
                                        <input 
                                            v-model.number="it.qty_received" 
                                            type="number" 
                                            step="0.1" 
                                            min="0.1" 
                                            required 
                                            class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-black text-slate-900 text-center"
                                        />
                                    </div>

                                    <!-- Harga Beli Modal Satuan (HPP) & Delete -->
                                    <div class="sm:col-span-2 flex items-center gap-1.5">
                                        <div class="w-full">
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Harga Beli (Rp)</label>
                                            <input 
                                                v-model.number="it.cost_price_per_unit" 
                                                type="number" 
                                                class="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900"
                                            />
                                        </div>
                                        <button 
                                            v-if="form.items.length > 1" 
                                            type="button" 
                                            @click="removeItemRow(idx)" 
                                            class="p-2 text-slate-400 hover:text-rose-600 transition self-end cursor-pointer"
                                            title="Hapus baris ini"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Catatan Penerimaan / Kondisi Barang</label>
                        <textarea 
                            v-model="form.notes" 
                            rows="2" 
                            placeholder="Contoh: Barang belanjaan dari pasar / distributor resmi dalam kondisi baik..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-500">Estimasi Total Belanja:</span>
                            <span class="text-sm font-black text-slate-900 ml-1.5">{{ formatRupiah(totalInboundEstimation) }}</span>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-xs active:scale-95">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan & Ajukan Persetujuan' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Tambah Master Supplier Baru -->
        <div v-if="isAddSupplierModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <Building2 class="w-5 h-5 text-amber-600" />
                        <h3 class="text-sm font-black text-slate-900">Tambah Data Supplier / Toko</h3>
                    </div>
                    <button @click="isAddSupplierModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSupplierForm" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Supplier / Toko Grosir *</label>
                        <input v-model="supplierForm.name" required placeholder="Contoh: Toko Grosir Barokah / PT Indofood" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Kontak Person (PIC)</label>
                            <input v-model="supplierForm.contact_person" placeholder="Nama sales/pemilik..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900" />
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">No. Telepon / WA</label>
                            <input v-model="supplierForm.phone" placeholder="0812xxxx" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat Gudang / Toko</label>
                        <textarea v-model="supplierForm.address" rows="2" placeholder="Alamat supplier..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="isAddSupplierModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl cursor-pointer">Batal</button>
                        <button type="submit" :disabled="supplierForm.processing" class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl cursor-pointer">Simpan Supplier</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Detail Dokumen Penerimaan Barang -->
        <div v-if="isDetailModalOpen && selectedReceipt" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                {{ selectedReceipt.receipt_number }}
                            </span>

                            <span 
                                v-if="selectedReceipt.status === 'pending'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-200"
                            >
                                <Clock class="w-3 h-3 text-amber-500" />
                                <span>Menunggu Persetujuan</span>
                            </span>
                            <span 
                                v-else-if="selectedReceipt.status === 'approved'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200"
                            >
                                <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                <span>Disetujui</span>
                            </span>
                            <span 
                                v-else-if="selectedReceipt.status === 'rejected'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200"
                            >
                                <XCircle class="w-3 h-3 text-rose-600" />
                                <span>Ditolak</span>
                            </span>
                        </div>
                        <h3 class="text-base font-black text-slate-900 mt-1">Bukti Penerimaan Barang Masuk</h3>
                        <p class="text-xs text-slate-500">Supplier: <strong>{{ selectedReceipt.supplier_name }}</strong> &bull; Faktur: {{ selectedReceipt.supplier_invoice_number || '-' }}</p>
                    </div>
                    <button @click="isDetailModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Status Callout -->
                <div v-if="selectedReceipt.status === 'pending'" class="p-3 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-2.5 text-xs text-amber-900">
                    <Clock class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Menunggu Persetujuan Admin</p>
                        <p class="text-[11px] text-amber-800">Dokumen belanja ini belum disetujui. Stok fisik barang belum bertambah ke inventaris toko.</p>
                    </div>
                </div>
                <div v-else-if="selectedReceipt.status === 'approved'" class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-start gap-2.5 text-xs text-emerald-900">
                    <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Telah Disetujui</p>
                        <p class="text-[11px] text-emerald-800">
                            Disetujui oleh <strong>{{ selectedReceipt.approver?.name || 'Admin' }}</strong> pada {{ formatDateTime(selectedReceipt.approved_at) }}. Stok fisik barang telah otomatis bertambah.
                        </p>
                    </div>
                </div>
                <div v-else-if="selectedReceipt.status === 'rejected'" class="p-3 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-2.5 text-xs text-rose-900">
                    <XCircle class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Dokumen Ditolak</p>
                        <p class="text-[11px] text-rose-800">
                            Ditolak oleh <strong>{{ selectedReceipt.approver?.name || 'Admin' }}</strong>. Alasan: {{ selectedReceipt.rejection_reason || '-' }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-3 bg-slate-50 p-3 rounded-2xl">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Tanggal Diterima:</span>
                            <span class="font-bold text-slate-800">{{ formatDate(selectedReceipt.receipt_date) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Petugas Input:</span>
                            <span class="font-bold text-slate-800">{{ selectedReceipt.receiver?.name || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Lokasi Gudang:</span>
                            <span class="font-bold text-slate-800">{{ selectedReceipt.location?.name || 'Kantin Utama' }}</span>
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-2xl overflow-hidden max-h-60 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-600 font-bold text-[10px] sticky top-0">
                                <tr>
                                    <th class="p-2.5">Nama Barang</th>
                                    <th class="p-2.5 text-center">Qty Diterima</th>
                                    <th class="p-2.5 text-right">Harga Beli (Modal)</th>
                                    <th class="p-2.5 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="it in selectedReceipt.items" :key="it.id">
                                    <td class="p-2.5 font-bold text-slate-900">{{ it.product?.name }}</td>
                                    <td class="p-2.5 text-center font-black text-emerald-700">+{{ it.qty_received }} {{ it.unit?.unit_name || 'Pcs' }}</td>
                                    <td class="p-2.5 text-right font-mono">{{ formatRupiah(it.cost_price_per_unit) }}</td>
                                    <td class="p-2.5 text-right font-mono font-bold">{{ formatRupiah(it.subtotal_cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="selectedReceipt.notes" class="text-slate-600 bg-slate-50 p-2.5 rounded-xl italic">
                        Catatan: "{{ selectedReceipt.notes }}"
                    </div>

                    <div class="flex justify-between items-center pt-2 border-t border-slate-100 font-bold">
                        <span class="text-slate-500">Total Nilai Pembelian:</span>
                        <span class="text-sm font-black text-slate-900">{{ formatRupiah(selectedReceipt.total_cost_amount) }}</span>
                    </div>
                </div>

                <!-- Footer with Admin Actions or Close -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <div>
                        <!-- Admin Approve / Reject inside modal -->
                        <div v-if="user?.role === 'admin' && selectedReceipt.status === 'pending'" class="flex items-center gap-2">
                            <button 
                                @click="approveReceipt(selectedReceipt)"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-95"
                            >
                                <Check class="w-4 h-4" />
                                <span>Setujui Dokumen Ini</span>
                            </button>
                            <button 
                                @click="openRejectModal(selectedReceipt)"
                                class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl text-xs cursor-pointer border border-rose-200"
                            >
                                Tolak
                            </button>
                        </div>
                    </div>

                    <button @click="isDetailModalOpen = false" class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Tolak Penerimaan Barang -->
        <div v-if="isRejectModalOpen && selectedReceipt" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <XCircle class="w-5 h-5 text-rose-600" />
                        <h3 class="text-sm font-black text-slate-900">Tolak Penerimaan Barang</h3>
                    </div>
                    <button @click="isRejectModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p class="text-xs text-slate-600">
                    Anda akan menolak dokumen <strong>{{ selectedReceipt.receipt_number }}</strong> dari supplier <strong>{{ selectedReceipt.supplier_name }}</strong>. Stok barang tidak akan bertambah.
                </p>

                <form @submit.prevent="submitReject" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alasan Penolakan</label>
                        <textarea 
                            v-model="rejectForm.reason" 
                            rows="3" 
                            required 
                            placeholder="Contoh: Barang fisik tidak sesuai faktur / harga tidak sesuai kesepakatan..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="isRejectModalOpen = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl cursor-pointer">Batal</button>
                        <button type="submit" :disabled="rejectForm.processing" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl cursor-pointer shadow-xs">Tolak Dokumen</button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
