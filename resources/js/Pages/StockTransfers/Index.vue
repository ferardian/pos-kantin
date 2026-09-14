<script setup>
import { ref, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    ArrowLeftRight, Plus, Search, Building2, Warehouse, Store, 
    Calendar, FileText, User, ArrowRight, Printer, X, 
    Trash2, PlusCircle, CheckCircle2, ChevronDown, Check,
    Package, Layers, History, MapPin, Eye, AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    transfers: Array,
    locations: Array,
    products: Array,
    user: Object,
    settings: Object,
});

const activeTab = ref('transfers'); // 'transfers', 'stock_matrix', 'locations'
const searchQuery = ref('');
const matrixSearchQuery = ref('');
const isAddTransferModalOpen = ref(false);
const isAddLocationModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const selectedTransfer = ref(null);

// Searchable Product Combobox per row state
const activeProductDropdownIndex = ref(null);
const productSearchQueries = ref({});

// Form Transfer Mutasi Stok
const defaultFromLoc = props.locations.find(l => l.type === 'warehouse') || props.locations[0];
const defaultToLoc = props.locations.find(l => l.type === 'store') || props.locations[1] || props.locations[0];

const transferForm = useForm({
    from_location_id: defaultFromLoc?.id || null,
    to_location_id: defaultToLoc?.id || null,
    transfer_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        {
            product_id: props.products[0]?.id || null,
            product_unit_id: props.products[0]?.units[0]?.id || null,
            qty: 1,
        }
    ]
});

// Form Tambah Lokasi Baru
const locationForm = useForm({
    name: '',
    code: '',
    type: 'warehouse',
    address: '',
    is_default: false,
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Item Row Management for Transfer Form
const addItemRow = () => {
    transferForm.items.push({
        product_id: props.products[0]?.id || null,
        product_unit_id: props.products[0]?.units[0]?.id || null,
        qty: 1,
    });
};

const removeItemRow = (index) => {
    if (transferForm.items.length > 1) {
        transferForm.items.splice(index, 1);
    }
};

const getProductById = (id) => {
    return props.products.find(p => p.id === id);
};

const getFilteredProductsForRow = (idx) => {
    const q = (productSearchQueries.value[idx] || '').toLowerCase().trim();
    if (!q) return props.products;
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        p.sku.toLowerCase().includes(q) || 
        (p.brand?.name && p.brand.name.toLowerCase().includes(q))
    );
};

const selectProductForRow = (idx, product) => {
    transferForm.items[idx].product_id = product.id;
    transferForm.items[idx].product_unit_id = product.units[0]?.id || null;
    activeProductDropdownIndex.value = null;
    productSearchQueries.value[idx] = '';
};

// Stock at From Location Helper
const getProductStockAtFromLoc = (productId) => {
    const prod = getProductById(productId);
    if (!prod || !prod.product_locations) return 0;
    const pl = prod.product_locations.find(l => l.location_id === transferForm.from_location_id);
    return pl ? Number(pl.stock_physical) : 0;
};

const getProductStockAtLocation = (product, locationId) => {
    if (!product || !product.product_locations) return 0;
    const pl = product.product_locations.find(l => l.location_id === locationId);
    return pl ? Number(pl.stock_physical) : 0;
};

// Submit Transfer
const submitTransfer = () => {
    if (transferForm.from_location_id === transferForm.to_location_id) {
        alert('Lokasi asal dan lokasi tujuan tidak boleh sama!');
        return;
    }

    transferForm.post('/stock-transfers', {
        onSuccess: () => {
            isAddTransferModalOpen.value = false;
            transferForm.reset();
            transferForm.from_location_id = defaultFromLoc?.id || null;
            transferForm.to_location_id = defaultToLoc?.id || null;
            transferForm.items = [
                {
                    product_id: props.products[0]?.id || null,
                    product_unit_id: props.products[0]?.units[0]?.id || null,
                    qty: 1,
                }
            ];
        }
    });
};

// Submit Location
const submitLocation = () => {
    locationForm.post('/locations', {
        onSuccess: () => {
            isAddLocationModalOpen.value = false;
            locationForm.reset();
        }
    });
};

const openDetailModal = (transfer) => {
    selectedTransfer.value = transfer;
    isDetailModalOpen.value = true;
};

// Filtered Transfers
const filteredTransfers = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return props.transfers;
    return props.transfers.filter(t => 
        t.transfer_number.toLowerCase().includes(q) || 
        (t.from_location?.name && t.from_location.name.toLowerCase().includes(q)) || 
        (t.to_location?.name && t.to_location.name.toLowerCase().includes(q)) || 
        (t.user?.name && t.user.name.toLowerCase().includes(q)) || 
        (t.notes && t.notes.toLowerCase().includes(q))
    );
});

// Filtered Matrix Products
const filteredMatrixProducts = computed(() => {
    const q = matrixSearchQuery.value.toLowerCase().trim();
    if (!q) return props.products;
    return props.products.filter(p => 
        p.name.toLowerCase().includes(q) || 
        p.sku.toLowerCase().includes(q) || 
        (p.brand?.name && p.brand.name.toLowerCase().includes(q)) || 
        (p.category?.name && p.category.name.toLowerCase().includes(q))
    );
});

// Standalone Print Engine for Surat Jalan Mutasi Internal
const printTransferSlip = (transfer) => {
    if (!transfer) return;

    const rawDate = transfer.transfer_date ? new Date(transfer.transfer_date).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID');
    const items = transfer.items || [];
    
    let rowsHtml = '';
    items.forEach((it, idx) => {
        rowsHtml += `
            <tr style="border-bottom: 1px solid #cbd5e1;">
                <td style="padding: 8px 6px; text-align: center; border-right: 1px solid #cbd5e1; font-weight: bold;">${idx + 1}</td>
                <td style="padding: 8px 10px; border-right: 1px solid #cbd5e1;">
                    <div style="font-weight: 900; font-size: 11px; color: #0f172a;">${it.product?.name || 'Produk Listrik'}</div>
                    <div style="font-size: 9px; font-family: monospace; color: #64748b; margin-top: 2px;">SKU: ${it.product?.sku || '-'}</div>
                </td>
                <td style="padding: 8px 6px; text-align: center; border-right: 1px solid #cbd5e1; font-weight: 900; font-size: 12px; color: #0f172a;">
                    ${it.qty} ${it.unit?.unit_name || 'Pcs'}
                </td>
                <td style="padding: 8px 6px; text-align: center; font-size: 10px; color: #475569; font-weight: 600;">
                    Baik & Lengkap
                </td>
            </tr>
        `;
    });

    const html = `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Surat Jalan Mutasi ${transfer.transfer_number}</title>
            <style>
                @page { margin: 8mm; size: A4 portrait; }
                * { box-sizing: border-box; }
                body {
                    margin: 0; padding: 12px;
                    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    font-size: 11px; color: #0f172a; background: #fff; line-height: 1.4;
                }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f1f5f9; border-top: 1px solid #cbd5e1; border-bottom: 2px solid #94a3b8; padding: 8px 6px; text-align: left; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #334155; }
            </style>
        </head>
        <body>
            <table style="width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 14px;">
                <tr>
                    <td style="width: 58%; vertical-align: middle;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <img src="${props.settings?.store_logo || '/images/logo.png'}" alt="Logo" style="width: 52px; height: 52px; object-fit: contain;" />
                            <div>
                                <h1 style="margin: 0; font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">${props.settings?.store_name || 'TRISNA JAYA LISTRIK'}</h1>
                                <div style="font-size: 10px; font-weight: bold; color: #d97706; text-transform: uppercase;">Logistik & Mutasi Stok Internal</div>
                                <div style="font-size: 10px; color: #475569; margin-top: 1px;">${props.settings?.store_address || 'Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890'}</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <span style="display: inline-block; background: #78350f; color: #fff; font-weight: 900; font-size: 11px; padding: 4px 14px; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
                            SURAT JALAN MUTASI STOK
                        </span>
                        <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 6px;">No: <strong>${transfer.transfer_number}</strong></div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">Tanggal: ${rawDate}</div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; font-size: 11px;">
                <tr>
                    <td style="width: 50%; padding: 10px 14px; vertical-align: top;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #b45309;">DARI LOKASI ASAL:</div>
                        <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${transfer.from_location?.name} (${transfer.from_location?.code})</div>
                        <div style="font-size: 10px; color: #475569; margin-top: 1px;">${transfer.from_location?.address || 'Lokasi Toko'}</div>
                    </td>
                    <td style="width: 50%; padding: 10px 14px; vertical-align: top; border-left: 1px solid #e2e8f0;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #047857;">TUJUAN MUTASI:</div>
                        <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${transfer.to_location?.name} (${transfer.to_location?.code})</div>
                        <div style="font-size: 10px; color: #475569; margin-top: 1px;">${transfer.to_location?.address || 'Lokasi Toko'}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 8px 14px; border-top: 1px solid #e2e8f0; background: #fff;">
                        <span style="font-size: 10px; color: #64748b;">Petugas Otorisasi: <strong>${transfer.user?.name}</strong></span>
                        ${transfer.notes ? `<span style="font-size: 10px; color: #92400e; font-style: italic; margin-left: 16px;">Catatan: "${transfer.notes}"</span>` : ''}
                    </td>
                </tr>
            </table>

            <div style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center; border-right: 1px solid #cbd5e1;">NO</th>
                            <th style="border-right: 1px solid #cbd5e1;">NAMA BARANG & SPESIFIKASI</th>
                            <th style="width: 140px; text-align: center; border-right: 1px solid #cbd5e1;">JUMLAH / QTY</th>
                            <th style="width: 120px; text-align: center;">KONDISI FISIK</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>

            <table style="width: 100%; margin-top: 30px; text-align: center; font-size: 11px;">
                <tr>
                    <td style="width: 33.3%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Pengirim (Lokasi Asal),</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( ${transfer.user?.name} )
                        </div>
                    </td>
                    <td style="width: 33.3%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Supir / Kurir Pengangkut,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( .................................... )
                        </div>
                    </td>
                    <td style="width: 33.3%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Penerima (Lokasi Tujuan),</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( .................................... )
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    `;

    let iframe = document.getElementById('transfer-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'transfer-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.left = '-9999px';
        iframe.style.top = '-9999px';
        iframe.style.width = '1000px';
        iframe.style.height = '1000px';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        document.body.appendChild(iframe);
    }

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    setTimeout(() => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (err) {
            console.error('Print iframe error:', err);
            const win = window.open('', '_blank');
            if (win) {
                win.document.write(html);
                win.document.close();
                win.focus();
                win.print();
            }
        }
    }, 250);
};
</script>

<template>
    <MainLayout>
        <Head title="Mutasi & Transfer Stok Antar Lokasi" />
        
        <div class="p-6 w-full space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <ArrowLeftRight class="w-6 h-6 text-amber-600" />
                        <span>Mutasi & Transfer Stok Antar Lokasi</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Pindahkan persediaan barang dari Gudang Utama ke Toko Display, antar cabang, atau antar gudang dengan surat jalan resmi.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <!-- Tabs Switcher -->
                    <div class="flex items-center gap-1 p-1 bg-white border border-slate-200 rounded-2xl shadow-xs">
                        <button 
                            @click="activeTab = 'transfers'"
                            :class="activeTab === 'transfers' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <History class="w-3.5 h-3.5" />
                            <span>Riwayat Mutasi</span>
                        </button>
                        <button 
                            @click="activeTab = 'stock_matrix'"
                            :class="activeTab === 'stock_matrix' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Layers class="w-3.5 h-3.5 text-amber-500" />
                            <span>Stok per Lokasi</span>
                        </button>
                        <button 
                            @click="activeTab = 'locations'"
                            :class="activeTab === 'locations' ? 'bg-slate-900 text-white font-black' : 'text-slate-600 hover:text-slate-900'"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <Building2 class="w-3.5 h-3.5" />
                            <span>Master Lokasi ({{ locations.length }})</span>
                        </button>
                    </div>

                    <button 
                        @click="isAddTransferModalOpen = true"
                        class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-2xl text-xs flex items-center gap-2 transition shadow-md cursor-pointer shrink-0"
                    >
                        <Plus class="w-4 h-4 text-amber-400" />
                        <span>Buat Transfer Mutasi</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Mutasi Tercatat</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ transfers.length }} Dokumen</h3>
                        <p class="text-[10px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
                            <CheckCircle2 class="w-3 h-3" /> Status Selesai
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <ArrowLeftRight class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Master Titik Lokasi</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ locations.length }} Lokasi Aktif</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Gudang & Toko Display</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <Warehouse class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Master Item Produk</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ products.length }} SKU Barang</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Tersinkronisasi Multi-Lokasi</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <Package class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- TAB 1: RIWAYAT MUTASI DOKUMEN -->
            <div v-if="activeTab === 'transfers'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Daftar Dokumen Mutasi & Transfer Stok</h3>
                    
                    <div class="relative w-full sm:w-80">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari No. Transfer, Lokasi, Petugas..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 bg-slate-50/50">
                                <th class="py-3 px-4">No. Dokumen & Tgl</th>
                                <th class="py-3 px-4">Alur Perpindahan</th>
                                <th class="py-3 px-4">Jumlah Barang</th>
                                <th class="py-3 px-4">Petugas Otorisasi</th>
                                <th class="py-3 px-4">Catatan</th>
                                <th class="py-3 px-4 text-center">Aksi Dokumen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in filteredTransfers" :key="t.id" class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4">
                                    <div class="font-black text-slate-900 font-mono">{{ t.transfer_number }}</div>
                                    <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                        <Calendar class="w-3 h-3" />
                                        <span>{{ new Date(t.transfer_date).toLocaleDateString('id-ID', { dateStyle: 'medium' }) }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-900 font-bold rounded-lg text-[10px]">
                                            {{ t.from_location?.name }}
                                        </span>
                                        <ArrowRight class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-900 font-bold rounded-lg text-[10px]">
                                            {{ t.to_location?.name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-black text-slate-900">{{ t.items?.length || 0 }} Macam Barang</span>
                                    <p class="text-[10px] text-slate-400 truncate max-w-xs">
                                        {{ t.items?.map(i => i.product?.name).join(', ') }}
                                    </p>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">{{ t.user?.name }}</div>
                                    <div class="text-[10px] text-slate-400 capitalize">{{ t.user?.role }}</div>
                                </td>
                                <td class="py-3 px-4 text-slate-600 italic max-w-xs truncate">
                                    {{ t.notes || '-' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button 
                                            @click="openDetailModal(t)"
                                            class="p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                                            title="Lihat Rincian"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button 
                                            @click="printTransferSlip(t)"
                                            class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-[10px] flex items-center gap-1 transition cursor-pointer shadow-xs"
                                            title="Cetak Surat Jalan Mutasi"
                                        >
                                            <Printer class="w-3.5 h-3.5 text-amber-400" />
                                            <span>Cetak SJ</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredTransfers.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400 space-y-2">
                                    <ArrowLeftRight class="w-8 h-8 mx-auto text-slate-300" />
                                    <p>Belum ada riwayat mutasi stok antar lokasi.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: MATRIKS SALDO STOK PER LOKASI (Gudang Utama vs Toko) -->
            <div v-if="activeTab === 'stock_matrix'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Perbandingan Saldo Stok per Lokasi (Real-time Matrix)</h3>
                        <p class="text-[11px] text-slate-500">Lihat ketersediaan barang di rak display toko vs gudang pusat secara berdampingan.</p>
                    </div>
                    
                    <div class="relative w-full sm:w-80">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="matrixSearchQuery"
                            type="text" 
                            placeholder="Cari Nama Barang, SKU, Merk..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase tracking-wider text-[10px] border-b border-slate-100 bg-slate-50/50">
                                <th class="py-3 px-4">Nama Produk & SKU</th>
                                <th class="py-3 px-4">Kategori / Merk</th>
                                <th v-for="loc in locations" :key="loc.id" class="py-3 px-4 text-center">
                                    <span class="font-black text-slate-800">{{ loc.name }}</span>
                                    <span class="block text-[9px] text-slate-400 font-mono">({{ loc.code }})</span>
                                </th>
                                <th class="py-3 px-4 text-center bg-slate-100 font-black text-slate-900">TOTAL FISIK</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="p in filteredMatrixProducts" :key="p.id" class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ p.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ p.sku }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-700">{{ p.category?.name || '-' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ p.brand?.name || '-' }}</div>
                                </td>
                                
                                <!-- Columns for each location -->
                                <td v-for="loc in locations" :key="loc.id" class="py-3 px-4 text-center">
                                    <span 
                                        :class="getProductStockAtLocation(p, loc.id) > 0 ? 'text-slate-900 font-black' : 'text-slate-300 font-semibold'"
                                        class="text-xs"
                                    >
                                        {{ getProductStockAtLocation(p, loc.id) }} {{ p.units?.[0]?.unit_name || 'Pcs' }}
                                    </span>
                                </td>

                                <!-- Total Aggregate Stock -->
                                <td class="py-3 px-4 text-center bg-slate-50/80 font-black text-xs text-amber-600">
                                    {{ p.stock_physical }} {{ p.units?.[0]?.unit_name || 'Pcs' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: MASTER LOKASI (Gudang & Toko) -->
            <div v-if="activeTab === 'locations'" class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs space-y-4">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Master Lokasi Gudang & Toko Cabang</h3>
                        <p class="text-[11px] text-slate-500">Kelola titik gudang penyimpanan, toko display kasir, dan pos proyek.</p>
                    </div>
                    <button 
                        @click="isAddLocationModalOpen = true"
                        class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-black px-3.5 py-2 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Tambah Lokasi Baru</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                    <div 
                        v-for="loc in locations" 
                        :key="loc.id"
                        class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-2.5 relative hover:border-amber-400 transition"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div 
                                    :class="loc.type === 'warehouse' ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-900'"
                                    class="w-9 h-9 rounded-xl flex items-center justify-center font-bold"
                                >
                                    <Warehouse v-if="loc.type === 'warehouse'" class="w-4 h-4" />
                                    <Store v-else class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="font-black text-slate-900 text-xs">{{ loc.name }}</h4>
                                    <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ loc.code }}</span>
                                </div>
                            </div>
                            <span 
                                :class="loc.is_default ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-slate-200 text-slate-700'"
                                class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md border"
                            >
                                {{ loc.is_default ? 'Default Gudang' : loc.type }}
                            </span>
                        </div>

                        <p class="text-[11px] text-slate-600 flex items-center gap-1">
                            <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>{{ loc.address || 'Alamat belum diatur' }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 1: FORM BUAT TRANSFER MUTASI STOK -->
        <div v-if="isAddTransferModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <ArrowLeftRight class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Buat Surat Mutasi & Transfer Stok</h3>
                            <p class="text-xs text-slate-500">Pindahkan stok dari lokasi asal ke lokasi tujuan secara otomatis.</p>
                        </div>
                    </div>
                    <button @click="isAddTransferModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitTransfer" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <!-- Form Header Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Dari Lokasi Asal *</label>
                            <select 
                                v-model="transferForm.from_location_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                            >
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }} ({{ loc.code }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Ke Lokasi Tujuan *</label>
                            <select 
                                v-model="transferForm.to_location_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                            >
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.name }} ({{ loc.code }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Tanggal Mutasi *</label>
                            <input 
                                v-model="transferForm.transfer_date" 
                                type="date"
                                required
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase text-slate-900">Daftar Barang yang Dimutasi</h4>
                                <p class="text-[11px] text-slate-500">Pilih produk listrik dan jumlah kuantiti yang akan dipindahkan.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="addItemRow" 
                                class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-900 hover:bg-amber-200 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Baris</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(it, idx) in transferForm.items" 
                                :key="idx"
                                class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2"
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                    <!-- Searchable Combobox for Product -->
                                    <div class="sm:col-span-6 relative">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">
                                            Pilih Produk (Stok di Asal: <strong>{{ getProductStockAtFromLoc(it.product_id) }}</strong>)
                                        </label>
                                        <button 
                                            type="button"
                                            @click="activeProductDropdownIndex = (activeProductDropdownIndex === idx ? null : idx)"
                                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-left text-xs font-bold text-slate-900 flex items-center justify-between hover:border-amber-400 transition"
                                        >
                                            <span class="truncate">{{ getProductById(it.product_id)?.name || 'Pilih Produk...' }}</span>
                                            <ChevronDown class="w-4 h-4 text-slate-400 shrink-0" />
                                        </button>

                                        <!-- Dropdown Panel -->
                                        <div 
                                            v-if="activeProductDropdownIndex === idx" 
                                            class="absolute top-full left-0 right-0 mt-1 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl p-2.5 space-y-2 max-h-60 overflow-y-auto"
                                        >
                                            <div class="relative">
                                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input 
                                                    v-model="productSearchQueries[idx]"
                                                    type="text" 
                                                    placeholder="Ketik nama atau SKU..."
                                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                                                />
                                            </div>

                                            <div class="space-y-0.5 max-h-40 overflow-y-auto">
                                                <div 
                                                    v-for="p in getFilteredProductsForRow(idx)" 
                                                    :key="p.id"
                                                    @click="selectProductForRow(idx, p)"
                                                    :class="[
                                                        it.product_id === p.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50',
                                                        'p-2 rounded-xl text-xs cursor-pointer flex items-center justify-between'
                                                    ]"
                                                >
                                                    <div>
                                                        <p class="font-bold text-slate-900">{{ p.name }}</p>
                                                        <p class="text-[10px] text-slate-400 font-mono">SKU: {{ p.sku }} &bull; Sisa di Asal: {{ getProductStockAtFromLoc(p.id) }}</p>
                                                    </div>
                                                    <Check v-if="it.product_id === p.id" class="w-4 h-4 text-amber-600 shrink-0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Satuan Pilihan -->
                                    <div class="sm:col-span-3">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Satuan</label>
                                        <select 
                                            v-model="it.product_unit_id" 
                                            class="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900"
                                        >
                                            <option 
                                                v-for="u in getProductById(it.product_id)?.units || []" 
                                                :key="u.id" 
                                                :value="u.id"
                                            >
                                                {{ u.unit_name }} (x{{ u.conversion_ratio }})
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Jumlah Qty -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Qty Transfer</label>
                                        <input 
                                            v-model.number="it.qty" 
                                            type="number" 
                                            step="0.1" 
                                            min="0.1" 
                                            required
                                            class="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-black text-slate-900 text-center"
                                        />
                                    </div>

                                    <!-- Delete Row Button -->
                                    <div class="sm:col-span-1 flex justify-end">
                                        <button 
                                            type="button" 
                                            @click="removeItemRow(idx)" 
                                            :disabled="transferForm.items.length === 1"
                                            class="p-2 text-slate-400 hover:text-rose-600 disabled:opacity-30 transition cursor-pointer"
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
                        <label class="block text-slate-700 font-bold mb-1">Catatan / Keterangan Transfer (Opsional)</label>
                        <input 
                            v-model="transferForm.notes" 
                            placeholder="Contoh: Pengisian rak display toko / pesanan customer ambil di toko..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <!-- Footer Buttons -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isAddTransferModalOpen = false" 
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="transferForm.processing"
                            class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md flex items-center gap-2 disabled:opacity-50"
                        >
                            <CheckCircle2 class="w-4 h-4 text-amber-400" />
                            <span>{{ transferForm.processing ? 'Memproses...' : 'Proses Transfer Stok' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: DETAIL TRANSAKSI MUTASI -->
        <div v-if="isDetailModalOpen && selectedTransfer" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-start border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Rincian Dokumen Mutasi</h3>
                        <p class="text-xs font-mono font-bold text-amber-700">{{ selectedTransfer.transfer_number }}</p>
                    </div>
                    <button @click="isDetailModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-2xl text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Asal $\rightarrow$ Tujuan:</span>
                        <span class="font-bold text-slate-900">{{ selectedTransfer.from_location?.name }} $\rightarrow$ {{ selectedTransfer.to_location?.name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">Tanggal & Petugas:</span>
                        <span class="font-bold text-slate-900">{{ new Date(selectedTransfer.transfer_date).toLocaleDateString('id-ID') }} ({{ selectedTransfer.user?.name }})</span>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden text-xs">
                    <table class="w-full text-left">
                        <thead class="bg-slate-100 font-black text-[10px] uppercase text-slate-700">
                            <tr>
                                <th class="p-2.5">No</th>
                                <th class="p-2.5">Barang</th>
                                <th class="p-2.5 text-center">Qty Transfer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(it, i) in selectedTransfer.items" :key="i">
                                <td class="p-2.5 font-bold">{{ i + 1 }}</td>
                                <td class="p-2.5">
                                    <div class="font-bold text-slate-900">{{ it.product?.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ it.product?.sku }}</div>
                                </td>
                                <td class="p-2.5 text-center font-black text-slate-900">
                                    {{ it.qty }} {{ it.unit?.unit_name }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button 
                        @click="printTransferSlip(selectedTransfer)"
                        class="px-4 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-xs"
                    >
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Surat Jalan Mutasi</span>
                    </button>
                    <button 
                        @click="isDetailModalOpen = false"
                        class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: TAMBAH MASTER LOKASI -->
        <div v-if="isAddLocationModalOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900">Tambah Lokasi Gudang / Toko Baru</h3>
                    <button @click="isAddLocationModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitLocation" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Nama Lokasi *</label>
                        <input 
                            v-model="locationForm.name" 
                            placeholder="Contoh: Gudang Proyek Barat / Toko Cabang 2"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Kode Lokasi (Unik) *</label>
                        <input 
                            v-model="locationForm.code" 
                            placeholder="Contoh: GDG-02 / TKO-02"
                            required
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono text-xs focus:outline-none focus:border-amber-500 uppercase"
                        />
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Tipe Titik Lokasi *</label>
                        <select 
                            v-model="locationForm.type"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs focus:outline-none focus:border-amber-500"
                        >
                            <option value="warehouse">Gudang Penyimpanan (Warehouse)</option>
                            <option value="store">Toko Retail / Display (Store)</option>
                            <option value="other">Pos Proyek / Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alamat Fisik Lokasi</label>
                        <input 
                            v-model="locationForm.address" 
                            placeholder="Contoh: Jl. Industri No. 45"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 text-xs focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            type="button" 
                            @click="isAddLocationModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            :disabled="locationForm.processing"
                            class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs transition cursor-pointer shadow-md disabled:opacity-50"
                        >
                            Simpan Lokasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
