<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Truck, Plus, Search, PackageCheck, Calendar, 
    FileText, User, ArrowDownRight, Printer, X, 
    Trash2, PlusCircle, CheckCircle2, ChevronDown, Check,
    Package, Building2, Phone, MapPin, Layers
} from 'lucide-vue-next';

const props = defineProps({
    receipts: Array,
    products: Array,
    suppliers: Array,
    locations: Array,
    totalReceiptsThisMonth: Number,
    totalCostInboundThisMonth: Number,
    user: Object,
});

const activeTab = ref('receipts'); // 'receipts', 'suppliers'
const searchQuery = ref('');
const isAddModalOpen = ref(false);
const isAddSupplierModalOpen = ref(false);
const isDetailModalOpen = ref(false);
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

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Supplier Helpers
const selectedSupplierObj = computed(() => {
    return props.suppliers.find(s => s.id === form.supplier_id);
});

const filteredSuppliers = computed(() => {
    const q = supplierSearchQuery.value.toLowerCase().trim();
    if (!q) return props.suppliers;
    return props.suppliers.filter(s => 
        s.name.toLowerCase().includes(q) || 
        (s.contact_person && s.contact_person.toLowerCase().includes(q)) ||
        (s.phone && s.phone.includes(q))
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
    if (!q) return props.receipts;
    return props.receipts.filter(r => 
        r.receipt_number.toLowerCase().includes(q) || 
        r.supplier_name.toLowerCase().includes(q) || 
        (r.supplier_invoice_number && r.supplier_invoice_number.toLowerCase().includes(q)) ||
        r.items.some(it => it.product?.name.toLowerCase().includes(q))
    );
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
</script>

<template>
    <MainLayout>
        <Head title="Penerimaan Barang & Master Supplier" />
        <div class="p-6 w-full space-y-6">
            <!-- Header with Tabs -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <Truck class="w-6 h-6 text-amber-600" />
                        <span>Penerimaan Barang & Master Supplier</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pencatatan faktur & surat jalan distributor resmi, penambahan stok gudang otomatis, dan database supplier.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Action Buttons -->
                    <button 
                        v-if="activeTab === 'receipts'"
                        @click="isAddModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer justify-center"
                    >
                        <Plus class="w-4 h-4 text-amber-400" />
                        <span>Catat Penerimaan Barang</span>
                    </button>

                    <button 
                        v-if="activeTab === 'suppliers'"
                        @click="isAddSupplierModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer justify-center"
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
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Dokumen Masuk</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ totalReceiptsThisMonth }} Penerimaan</h3>
                        <p class="text-[10px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                            <PackageCheck class="w-3 h-3" /> Bulan Berjalan
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <Truck class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Nilai Pembelian (HPP)</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ formatRupiah(totalCostInboundThisMonth) }}</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Pembelian dari Distributor</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <Calendar class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Database Supplier Rekanan</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ suppliers.length }} Distributor</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Pabrikan & Mitra Toko</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <Building2 class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- TAB 1: RIWAYAT PENERIMAAN BARANG -->
            <div v-if="activeTab === 'receipts'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Riwayat Surat Penerimaan Barang Gudang</h3>
                    
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
                                <th class="py-3.5 px-4">Rincian Barang yang Diterima</th>
                                <th class="py-3.5 px-4">Penerima Gudang</th>
                                <th class="py-3.5 px-4 text-right">Nilai Masuk (Rp)</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredReceipts.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-400">
                                    Belum ada data penerimaan barang masuk dari supplier.
                                </td>
                            </tr>
                            <tr v-for="rc in filteredReceipts" :key="rc.id" class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-mono font-bold text-slate-900 text-xs">{{ rc.receipt_number }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ new Date(rc.receipt_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ rc.supplier_name }}</div>
                                    <div v-if="rc.supplier_invoice_number" class="text-[10px] text-slate-500 font-mono">
                                        No. SJ: {{ rc.supplier_invoice_number }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="space-y-1">
                                        <div v-for="it in rc.items" :key="it.id" class="text-[11px] text-slate-700">
                                            &bull; <strong class="text-slate-900">+{{ it.qty_received }} {{ it.unit?.unit_name || 'Pcs' }}</strong> {{ it.product?.name }}
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-800">{{ rc.receiver?.name }}</div>
                                    <span class="text-[9px] uppercase px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-black">
                                        {{ rc.receiver?.role }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-right font-black text-slate-900 text-xs whitespace-nowrap">
                                    {{ formatRupiah(rc.total_cost_amount) }}
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <button 
                                        @click="openDetail(rc)"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-[11px] transition cursor-pointer"
                                    >
                                        Detail Dokumen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: MASTER DATA SUPPLIER -->
            <div v-if="activeTab === 'suppliers'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Daftar Supplier & Distributor Resmi Toko</h3>
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
                                <th class="py-3.5 px-4">Nama Perusahaan / Supplier</th>
                                <th class="py-3.5 px-4">Kontak Person</th>
                                <th class="py-3.5 px-4">Telepon / WhatsApp</th>
                                <th class="py-3.5 px-4">Alamat Gudang / Kantor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
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

        <!-- MODAL: Form Catat Penerimaan Barang Masuk (Dengan Master Supplier Dinamis) -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <Truck class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Catat Penerimaan Barang dari Supplier</h3>
                            <p class="text-xs text-slate-500">Stok fisik barang di toko/gudang akan bertambah otomatis.</p>
                        </div>
                    </div>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitReceipt" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <!-- Form Grid Header: Supplier, Lokasi Masuk, No SJ, Tanggal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Searchable Supplier Dropdown Combobox -->
                        <div class="relative">
                            <label class="block text-slate-700 font-bold mb-1">Supplier / Distributor Rekanan *</label>
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
                                    {{ loc.name }} ({{ loc.code }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">No. SJ / Faktur Supplier</label>
                            <input 
                                v-model="form.supplier_invoice_number" 
                                placeholder="Contoh: SJ-DIST-8891"
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
                                <h4 class="text-xs font-black uppercase text-slate-900">Daftar Barang yang Diterima</h4>
                                <p class="text-[11px] text-slate-500">Pilih barang listrik, satuan masuk, dan jumlah kuantiti.</p>
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
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Barang Listrik (Bisa Diketik & Dicari)</label>
                                        <button 
                                            type="button"
                                            @click="activeProductDropdownIndex = (activeProductDropdownIndex === idx ? null : idx)"
                                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-left text-xs font-bold text-slate-900 flex items-center justify-between hover:border-amber-400 transition"
                                        >
                                            <span class="truncate">{{ getProductById(it.product_id)?.name || 'Pilih Produk Listrik...' }}</span>
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
                                                    placeholder="Ketik nama, merk, kabel, lampu, saklar..."
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
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Satuan</label>
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
                                        <label class="block text-[10px] font-bold text-slate-700 mb-1">Qty Masuk (+)</label>
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
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Harga Beli</label>
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
                            placeholder="Contoh: Barang diterima lengkap dalam kondisi kardus segel baik..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-500">Estimasi Total Pembelian:</span>
                            <span class="text-sm font-black text-slate-900 ml-1.5">{{ formatRupiah(totalInboundEstimation) }}</span>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-xs">
                                Simpan & Tambah Stok
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
                        <h3 class="text-sm font-black text-slate-900">Tambah Data Supplier Baru</h3>
                    </div>
                    <button @click="isAddSupplierModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSupplierForm" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Supplier / PT / CV *</label>
                        <input v-model="supplierForm.name" required placeholder="Contoh: PT Kabelindo Mandiri" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Kontak Person (PIC)</label>
                            <input v-model="supplierForm.contact_person" placeholder="Nama sales/PIC..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900" />
                        </div>
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">No. Telepon / WA</label>
                            <input v-model="supplierForm.phone" placeholder="0812xxxx" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat Kantor / Gudang Supplier</label>
                        <textarea v-model="supplierForm.address" rows="2" placeholder="Alamat distributor..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-slate-900"></textarea>
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
                        <span class="font-mono text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                            {{ selectedReceipt.receipt_number }}
                        </span>
                        <h3 class="text-base font-black text-slate-900 mt-1">Bukti Penerimaan Barang Masuk</h3>
                        <p class="text-xs text-slate-500">Supplier: <strong>{{ selectedReceipt.supplier_name }}</strong> &bull; No SJ: {{ selectedReceipt.supplier_invoice_number || '-' }}</p>
                    </div>
                    <button @click="isDetailModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-2xl">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Tanggal Diterima:</span>
                            <span class="font-bold text-slate-800">{{ new Date(selectedReceipt.receipt_date).toLocaleDateString('id-ID') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Petugas Gudang:</span>
                            <span class="font-bold text-slate-800">{{ selectedReceipt.receiver?.name }}</span>
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-600 font-bold text-[10px]">
                                <tr>
                                    <th class="p-2.5">Nama Barang</th>
                                    <th class="p-2.5 text-center">Qty Diterima</th>
                                    <th class="p-2.5 text-right">Harga Beli Satuan</th>
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

                <div class="flex justify-end pt-2">
                    <button @click="isDetailModalOpen = false" class="px-4 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
