<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    ClipboardCheck, Search, Calendar, User, FileText, CheckCircle2, 
    AlertTriangle, ArrowUpDown, Plus, Printer, Eye, X, History, 
    Check, Filter, ArrowUpRight, ArrowDownRight, Sparkles, RefreshCw,
    Layers, AlertCircle, TrendingDown, TrendingUp, DollarSign, PackageCheck
} from 'lucide-vue-next';

const props = defineProps({
    products: Array,
    categories: Array,
    opnames: Array,
    stockLogs: Array,
    autoOpnameNumber: String,
    currentUser: Object,
});

const page = usePage();
const activeTab = ref('audit'); // 'audit', 'history', 'logs'
const selectedCategoryFilter = ref('all');
const searchQuery = ref('');
const filterDiffOnly = ref(false);

// Form Sesi Stok Opname
const form = useForm({
    opname_number: props.autoOpnameNumber,
    opname_date: new Date().toISOString().split('T')[0],
    category_id: null,
    notes: '',
    items: [],
});

// State input fisik: productId -> { physical, notes }
const auditInputs = ref({});

// Inisialisasi input fisik dari props.products
const initAuditInputs = (matchWithSystem = false) => {
    props.products.forEach(p => {
        const sys = Number(p.stock_physical || 0);
        auditInputs.value[p.id] = {
            physical: matchWithSystem ? sys : sys,
            notes: '',
        };
    });
};
initAuditInputs(true);

// Sinkronisasi ulang jika produk berubah
watch(() => props.products, () => {
    initAuditInputs(true);
}, { deep: true });

// Filter Produk untuk Lembar Kerja Audit
const filteredProducts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const cat = selectedCategoryFilter.value;

    return props.products.filter(p => {
        const matchCat = (cat === 'all' || p.category_id === Number(cat));
        const matchSearch = (!q || 
            p.name.toLowerCase().includes(q) || 
            (p.sku && p.sku.toLowerCase().includes(q)) || 
            (p.barcode && p.barcode.includes(q))
        );
        
        if (filterDiffOnly.value) {
            const inputVal = auditInputs.value[p.id]?.physical;
            const sysVal = Number(p.stock_physical || 0);
            const diff = (inputVal !== undefined && inputVal !== null && inputVal !== '') ? (Number(inputVal) - sysVal) : 0;
            return matchCat && matchSearch && Math.abs(diff) > 0.0001;
        }

        return matchCat && matchSearch;
    });
});

// Helper kalkulasi selisih per produk
const getProductDiff = (product) => {
    const inputVal = auditInputs.value[product.id]?.physical;
    const sysVal = Number(product.stock_physical || 0);
    if (inputVal === undefined || inputVal === null || inputVal === '') return 0;
    return Number(inputVal) - sysVal;
};

// Ringkasan Statistik Audit Lembar Kerja
const auditSummary = computed(() => {
    let totalChecked = 0;
    let matchCount = 0;
    let surplusCount = 0;
    let lossCount = 0;
    let totalQtyDiff = 0;
    let totalCostDiff = 0;

    props.products.forEach(p => {
        totalChecked++;
        const diff = getProductDiff(p);
        const cost = Number(p.units?.[0]?.cost_price || 0);

        totalQtyDiff += diff;
        totalCostDiff += (diff * cost);

        if (Math.abs(diff) < 0.0001) {
            matchCount++;
        } else if (diff > 0) {
            surplusCount++;
        } else {
            lossCount++;
        }
    });

    return {
        totalChecked,
        matchCount,
        surplusCount,
        lossCount,
        diffCount: surplusCount + lossCount,
        totalQtyDiff,
        totalCostDiff,
    };
});

// Aksi Cepat Samakan Fisik = Sistem untuk produk yang sedang difilter
const matchFilteredToSystem = () => {
    filteredProducts.value.forEach(p => {
        if (!auditInputs.value[p.id]) auditInputs.value[p.id] = { physical: 0, notes: '' };
        auditInputs.value[p.id].physical = Number(p.stock_physical || 0);
    });
};

// Modal Konfirmasi Simpan SO
const isConfirmModalOpen = ref(false);

const openConfirmModal = () => {
    isConfirmModalOpen.value = true;
};

// Submit Sesi Stok Opname
const submitStockOpname = () => {
    form.category_id = selectedCategoryFilter.value === 'all' ? null : Number(selectedCategoryFilter.value);
    
    // Siapkan items payload
    form.items = props.products.map(p => {
        const sys = Number(p.stock_physical || 0);
        const phys = Number(auditInputs.value[p.id]?.physical ?? sys);
        const diff = phys - sys;
        const cost = Number(p.units?.[0]?.cost_price || 0);
        const notes = auditInputs.value[p.id]?.notes || '';

        return {
            product_id: p.id,
            unit_name: p.units?.[0]?.unit_name || 'Pcs',
            qty_system: sys,
            qty_physical: phys,
            qty_difference: diff,
            cost_price: cost,
            notes: notes,
        };
    });

    form.post('/stock-opnames', {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            activeTab.value = 'history';
        },
    });
};

// Detail Modal State & Cetak BAP
const isDetailModalOpen = ref(false);
const selectedOpname = ref(null);

const openDetailModal = (opname) => {
    selectedOpname.value = opname;
    isDetailModalOpen.value = true;
};

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

// Fungsi Cetak Berita Acara Stok Opname (BAP)
const printBap = (opname) => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Izinkan pop-up browser untuk mencetak Berita Acara.');
        return;
    }

    const diffItems = opname.items.filter(it => Math.abs(Number(it.qty_difference)) > 0.0001);

    const rowsHtml = diffItems.length > 0 
        ? diffItems.map((it, idx) => `
            <tr>
                <td style="text-align: center; border: 1px solid #cbd5e1; padding: 6px;">${idx + 1}</td>
                <td style="border: 1px solid #cbd5e1; padding: 6px; font-weight: bold;">${it.product?.name || '-'}</td>
                <td style="text-align: center; border: 1px solid #cbd5e1; padding: 6px;">${it.product?.sku || '-'}</td>
                <td style="text-align: center; border: 1px solid #cbd5e1; padding: 6px;">${it.qty_system} ${it.unit_name || 'Pcs'}</td>
                <td style="text-align: center; border: 1px solid #cbd5e1; padding: 6px; font-weight: bold;">${it.qty_physical} ${it.unit_name || 'Pcs'}</td>
                <td style="text-align: center; border: 1px solid #cbd5e1; padding: 6px; font-weight: bold; color: ${it.qty_difference < 0 ? '#b91c1c' : '#15803d'};">
                    ${it.qty_difference > 0 ? '+' : ''}${it.qty_difference}
                </td>
                <td style="text-align: right; border: 1px solid #cbd5e1; padding: 6px;">${formatRupiah(it.cost_price_per_unit)}</td>
                <td style="text-align: right; border: 1px solid #cbd5e1; padding: 6px; font-weight: bold;">${formatRupiah(it.subtotal_cost_diff)}</td>
                <td style="border: 1px solid #cbd5e1; padding: 6px; font-style: italic;">${it.notes || '-'}</td>
            </tr>
        `).join('')
        : `<tr><td colspan="9" style="text-align:center; padding: 15px; border: 1px solid #cbd5e1; font-style: italic;">Seluruh fisik barang 100% cocok dengan stok sistem (Tidak ada selisih).</td></tr>`;

    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Berita Acara Stok Opname - ${opname.opname_number}</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #0f172a; margin: 25px; }
                .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 15px; }
                .header h2 { margin: 0; font-size: 16pt; font-weight: 900; }
                .header p { margin: 3px 0 0 0; font-size: 10pt; color: #475569; }
                .meta { width: 100%; margin-bottom: 15px; border-collapse: collapse; font-size: 10pt; }
                .meta td { padding: 4px 6px; }
                table.data { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9.5pt; }
                th { background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px; font-weight: bold; }
                .signatures { margin-top: 40px; width: 100%; text-align: center; font-size: 10pt; }
                .sig-box { width: 33%; vertical-align: top; display: inline-block; }
                @media print {
                    body { margin: 10mm; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>KOPERASI RSIA AISYIYAH PEKAJANGAN</h2>
                <p>BERITA ACARA AUDIT HASIL STOK OPNAME KANTIN</p>
            </div>

            <table class="meta">
                <tr>
                    <td style="width: 18%; font-weight: bold;">No. Dokumen</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 30%; font-family: monospace; font-weight: bold;">${opname.opname_number}</td>
                    <td style="width: 18%; font-weight: bold;">Tanggal Audit</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 30%;">${formatDate(opname.opname_date)}</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Petugas Auditor</td>
                    <td>:</td>
                    <td>${opname.user?.name || 'Admin Toko'} (${opname.user?.role || '-'})</td>
                    <td style="font-weight: bold;">Total Item Selisih</td>
                    <td>:</td>
                    <td style="font-weight: bold; color: ${opname.total_items_diff > 0 ? '#b91c1c' : '#15803d'};">
                        ${opname.total_items_diff} dari ${opname.total_items_checked} Barang
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Total Selisih Nilai (HPP)</td>
                    <td>:</td>
                    <td style="font-weight: bold; color: ${opname.total_cost_diff < 0 ? '#b91c1c' : '#15803d'};">
                        ${formatRupiah(opname.total_cost_diff)}
                    </td>
                    <td style="font-weight: bold;">Catatan / Keterangan</td>
                    <td>:</td>
                    <td>${opname.notes || '-'}</td>
                </tr>
            </table>

            <h4 style="margin: 15px 0 5px 0; font-size: 11pt;">Daftar Rincian Barang Selisih:</h4>
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th>Nama Produk</th>
                        <th style="width: 80px;">SKU</th>
                        <th style="width: 75px;">Stok Sistem</th>
                        <th style="width: 75px;">Fisik Nyata</th>
                        <th style="width: 65px;">Selisih</th>
                        <th style="width: 90px;">HPP Satuan</th>
                        <th style="width: 100px;">Total Nilai (+/-)</th>
                        <th>Keterangan / Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>

            <table class="signatures" style="width: 100%; margin-top: 50px;">
                <tr>
                    <td style="width: 33%;">
                        Petugas Auditor (Pemeriksa),<br><br><br><br>
                        <strong>( ${opname.user?.name || '....................'} )</strong>
                    </td>
                    <td style="width: 33%;">
                        Staf Gudang / Kasir,<br><br><br><br>
                        <strong>( .................................... )</strong>
                    </td>
                    <td style="width: 33%;">
                        Mengetahui,<br>Pengurus Koperasi RSIA,<br><br><br><br>
                        <strong>( .................................... )</strong>
                    </td>
                </tr>
            </table>

        </body>
        </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
    }, 400);
};
</script>

<template>
    <Head title="Stok Opname Toko" />
    <MainLayout>
        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            
            <!-- Header Halaman Stok Opname -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white border border-slate-200 rounded-3xl p-6 shadow-xs">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-black shadow-md shadow-amber-500/20 shrink-0">
                        <ClipboardCheck class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black tracking-tight text-slate-900">Stok Opname & Audit Fisik</h2>
                            <span class="px-2.5 py-0.5 rounded-full bg-slate-900 text-amber-400 font-black text-[10px] uppercase tracking-wider">
                                Khusus Admin & Gudang
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Hitung fisik aktual barang kantin, hitung selisih kuantitas, estimasi selisih rupiah HPP, dan catat Berita Acara Opname.
                        </p>
                    </div>
                </div>

                <!-- Navigasi Tab Internal -->
                <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100 rounded-2xl">
                    <button 
                        @click="activeTab = 'audit'"
                        :class="activeTab === 'audit' ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <PackageCheck class="w-4 h-4 text-amber-500" />
                        <span>Lembar Kerja Opname</span>
                    </button>
                    <button 
                        @click="activeTab = 'history'"
                        :class="activeTab === 'history' ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <FileText class="w-4 h-4 text-sky-600" />
                        <span>Riwayat Dokumen SO</span>
                        <span class="ml-1 px-1.5 py-0.2 rounded-md bg-slate-200 text-slate-800 text-[10px]">
                            {{ opnames.length }}
                        </span>
                    </button>
                    <button 
                        @click="activeTab = 'logs'"
                        :class="activeTab === 'logs' ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold'"
                        class="px-4 py-2 rounded-xl text-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <History class="w-4 h-4 text-slate-500" />
                        <span>Log Mutasi Stok</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: LEMBAR KERJA AUDIT STOK OPNAME -->
            <div v-if="activeTab === 'audit'" class="space-y-4">
                
                <!-- Summary Card Baris Statistik Selisih -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
                        <span class="text-[11px] font-bold text-slate-400 block mb-1">Total Barang Dicek</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black text-slate-900">{{ auditSummary.totalChecked }}</span>
                            <span class="text-xs text-slate-500 font-medium">Item Produk</span>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
                        <span class="text-[11px] font-bold text-emerald-600 block mb-1 flex items-center gap-1">
                            <CheckCircle2 class="w-3.5 h-3.5" /> Fisik Sesuai (Cocok)
                        </span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black text-emerald-700">{{ auditSummary.matchCount }}</span>
                            <span class="text-xs text-slate-500 font-medium">Item</span>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
                        <span class="text-[11px] font-bold text-rose-600 block mb-1 flex items-center gap-1">
                            <AlertTriangle class="w-3.5 h-3.5" /> Barang Selisih (+/-)
                        </span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black" :class="auditSummary.diffCount > 0 ? 'text-rose-600' : 'text-slate-900'">
                                {{ auditSummary.diffCount }}
                            </span>
                            <span class="text-xs text-slate-500 font-medium">
                                ({{ auditSummary.lossCount }} Kurang / {{ auditSummary.surplusCount }} Lebih)
                            </span>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
                        <span class="text-[11px] font-bold text-slate-400 block mb-1">Nilai Selisih HPP (Modal)</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-lg font-black" :class="auditSummary.totalCostDiff < 0 ? 'text-rose-600' : (auditSummary.totalCostDiff > 0 ? 'text-emerald-600' : 'text-slate-900')">
                                {{ formatRupiah(auditSummary.totalCostDiff) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Control Bar: Input No. Dokumen, Tanggal, Filter, Aksi Cepat & Tombol Simpan -->
                <div class="bg-amber-50/70 border border-amber-200 rounded-3xl p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 text-xs">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Nomor Dokumen SO</label>
                            <input 
                                v-model="form.opname_number" 
                                required
                                class="w-full bg-white border border-amber-300 rounded-xl px-3 py-2 text-slate-900 font-mono font-bold focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Tanggal Pelaksanaan Audit *</label>
                            <input 
                                v-model="form.opname_date" 
                                type="date"
                                required
                                class="w-full bg-white border border-amber-300 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Filter Kategori Produk</label>
                            <select 
                                v-model="selectedCategoryFilter"
                                class="w-full bg-white border border-amber-300 rounded-xl px-3 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500"
                            >
                                <option value="all">Semua Kategori</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Petugas Auditor</label>
                            <div class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 font-bold truncate">
                                {{ currentUser?.name }} ({{ currentUser?.role }})
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Search, Quick Actions & Submit Button -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-amber-200/70">
                        <div class="flex items-center gap-2 flex-1 max-w-md">
                            <div class="relative flex-1">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input 
                                    v-model="searchQuery" 
                                    type="text" 
                                    placeholder="Ketik nama makanan, minuman, SKU, atau scan barcode..."
                                    class="w-full bg-white border border-amber-300 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                                />
                            </div>

                            <button 
                                type="button" 
                                @click="filterDiffOnly = !filterDiffOnly"
                                :class="filterDiffOnly ? 'bg-amber-600 text-white font-bold' : 'bg-white text-slate-700 border border-amber-300 hover:bg-amber-100'"
                                class="px-3 py-2 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shrink-0"
                                title="Hanya tampilkan produk yang selisih"
                            >
                                <Filter class="w-3.5 h-3.5" />
                                <span>Selisih Saja</span>
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                @click="matchFilteredToSystem"
                                class="px-3.5 py-2 rounded-xl bg-white border border-amber-300 hover:bg-amber-100 text-slate-800 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                                title="Isi otomatis hitungan fisik sama dengan stok sistem untuk barang yang ditampilkan"
                            >
                                <RefreshCw class="w-3.5 h-3.5 text-amber-700" />
                                <span>Samakan Fisik = Sistem</span>
                            </button>

                            <button 
                                type="button" 
                                @click="openConfirmModal"
                                class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-black text-xs flex items-center gap-2 transition shadow-md cursor-pointer active:scale-95"
                            >
                                <PackageCheck class="w-4 h-4 text-amber-400" />
                                <span>Terapkan Hasil Opname</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Lembar Kerja Hitung Fisik -->
                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                    <div class="max-h-[calc(100vh-320px)] overflow-y-auto overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 shadow-xs">
                                <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                    <th class="py-3.5 px-3.5 w-12 text-center bg-slate-50">No</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Nama Produk / Menu</th>
                                    <th class="py-3.5 px-4 bg-slate-50 text-center w-28">Stok Sistem</th>
                                    <th class="py-3.5 px-4 bg-slate-50 text-center w-36">Hitungan Fisik Nyata</th>
                                    <th class="py-3.5 px-4 bg-slate-50 text-center w-28">Selisih (+/-)</th>
                                    <th class="py-3.5 px-4 bg-slate-50 text-right w-32">Estimasi Nilai (Rp)</th>
                                    <th class="py-3.5 px-4 bg-slate-50">Alasan / Catatan Penyesuaian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr 
                                    v-for="(product, idx) in filteredProducts" 
                                    :key="product.id"
                                    class="hover:bg-slate-50 transition"
                                    :class="Math.abs(getProductDiff(product)) > 0.0001 ? 'bg-amber-50/30' : ''"
                                >
                                    <td class="py-3.5 px-3.5 text-center text-slate-400 font-mono text-[11px] font-bold">
                                        {{ idx + 1 }}
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-xs">{{ product.name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            SKU: {{ product.sku || '-' }} &bull; Barcode: {{ product.barcode || '-' }} &bull; {{ product.category?.name || 'Kantin' }}
                                        </div>
                                    </td>

                                    <!-- Stok Sistem Saat Ini -->
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-700">
                                        <div class="inline-flex flex-col items-center">
                                            <span class="px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 font-black text-xs font-mono">
                                                {{ product.stock_physical }} {{ product.units[0]?.unit_name || 'Pcs' }}
                                            </span>
                                            <span v-if="product.stock_booked > 0" class="text-[9px] text-amber-700 font-bold mt-0.5">
                                                (Di-booking SO: {{ product.stock_booked }})
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Input Hitungan Fisik Nyata -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <input 
                                                v-if="auditInputs[product.id]"
                                                v-model.number="auditInputs[product.id].physical" 
                                                type="number" 
                                                step="0.1" 
                                                min="0"
                                                class="w-24 text-center font-black text-xs py-1.5 px-2 bg-white border-2 rounded-xl focus:outline-none transition shadow-xs"
                                                :class="[
                                                    getProductDiff(product) === 0 ? 'border-slate-200 text-slate-900 focus:border-amber-500' :
                                                    (getProductDiff(product) > 0 ? 'border-emerald-500 text-emerald-800 bg-emerald-50/50' : 'border-rose-500 text-rose-800 bg-rose-50/50')
                                                ]"
                                            />
                                            <span class="text-[11px] text-slate-500 font-semibold">
                                                {{ product.units[0]?.unit_name || 'Pcs' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Selisih (+/-) -->
                                    <td class="py-3.5 px-4 text-center font-mono font-black">
                                        <span 
                                            class="inline-flex items-center gap-0.5 px-2.5 py-1 rounded-xl text-xs"
                                            :class="[
                                                getProductDiff(product) === 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                (getProductDiff(product) > 0 ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-rose-50 text-rose-700 border border-rose-200')
                                            ]"
                                        >
                                            <span v-if="getProductDiff(product) === 0">&bull; Cocok (0)</span>
                                            <span v-else>{{ getProductDiff(product) > 0 ? '+' : '' }}{{ getProductDiff(product) }}</span>
                                        </span>
                                    </td>

                                    <!-- Estimasi Nilai Rupiah Selisih HPP -->
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-xs">
                                        <span :class="getProductDiff(product) < 0 ? 'text-rose-600' : (getProductDiff(product) > 0 ? 'text-emerald-600' : 'text-slate-400')">
                                            {{ formatRupiah(getProductDiff(product) * Number(product.units?.[0]?.cost_price || 0)) }}
                                        </span>
                                    </td>

                                    <!-- Catatan Per Barang -->
                                    <td class="py-3.5 px-4">
                                        <input 
                                            v-if="auditInputs[product.id]"
                                            v-model="auditInputs[product.id].notes"
                                            placeholder="Alasan selisih (rusak / kedaluwarsa / salah hitung)..."
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                        />
                                    </td>
                                </tr>

                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Tidak ada produk yang cocok dengan pencarian atau filter kategori saat ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT DOKUMEN STOK OPNAME -->
            <div v-if="activeTab === 'history'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Riwayat Dokumen Stok Opname</h3>
                            <p class="text-xs text-slate-500">Daftar seluruh pelaksanaan audit fisik stok yang telah tersimpan di sistem.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="p-3.5">No. Dokumen</th>
                                    <th class="p-3.5">Tanggal Audit</th>
                                    <th class="p-3.5">Petugas Auditor</th>
                                    <th class="p-3.5 text-center">Total Dicek</th>
                                    <th class="p-3.5 text-center">Item Selisih</th>
                                    <th class="p-3.5 text-right">Nilai Selisih HPP</th>
                                    <th class="p-3.5">Catatan Berita Acara</th>
                                    <th class="p-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="op in opnames" :key="op.id" class="hover:bg-slate-50 transition">
                                    <td class="p-3.5 font-mono font-bold text-slate-900">{{ op.opname_number }}</td>
                                    <td class="p-3.5 font-bold text-slate-700">{{ formatDate(op.opname_date) }}</td>
                                    <td class="p-3.5">
                                        <div class="font-bold text-slate-900">{{ op.user?.name || '-' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ op.user?.role || '-' }}</div>
                                    </td>
                                    <td class="p-3.5 text-center font-bold font-mono">{{ op.total_items_checked }} Item</td>
                                    <td class="p-3.5 text-center font-bold font-mono">
                                        <span 
                                            class="px-2 py-0.5 rounded-full text-xs"
                                            :class="op.total_items_diff > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                        >
                                            {{ op.total_items_diff }} Selisih
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-right font-mono font-bold" :class="op.total_cost_diff < 0 ? 'text-rose-600' : (op.total_cost_diff > 0 ? 'text-emerald-600' : 'text-slate-700')">
                                        {{ formatRupiah(op.total_cost_diff) }}
                                    </td>
                                    <td class="p-3.5 text-slate-600 italic max-w-xs truncate">{{ op.notes || '-' }}</td>
                                    <td class="p-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button 
                                                @click="openDetailModal(op)"
                                                class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs flex items-center gap-1 transition cursor-pointer"
                                                title="Lihat Rincian Barang"
                                            >
                                                <Eye class="w-3.5 h-3.5 text-slate-600" />
                                                <span>Detail</span>
                                            </button>
                                            <button 
                                                @click="printBap(op)"
                                                class="px-3 py-1.5 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs flex items-center gap-1 transition cursor-pointer"
                                                title="Cetak Berita Acara Opname"
                                            >
                                                <Printer class="w-3.5 h-3.5 text-amber-700" />
                                                <span>Cetak BAP</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="opnames.length === 0">
                                    <td colspan="8" class="p-12 text-center text-slate-400">
                                        Belum ada riwayat pelaksanaan Stok Opname yang tersimpan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LOG MUTASI AUDIT STOK -->
            <div v-if="activeTab === 'logs'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Riwayat Penyesuaian & Audit Mutasi Stok Fisik</h3>
                            <p class="text-xs text-slate-500">Merekam 50 penyesuaian stok terbaru yang dilakukan sistem maupun staf audit.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="p-3">Waktu</th>
                                    <th class="p-3">Produk</th>
                                    <th class="p-3">Tipe</th>
                                    <th class="p-3 text-center">Selisih Qty</th>
                                    <th class="p-3">Alasan / Referensi</th>
                                    <th class="p-3">Petugas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="log in stockLogs" :key="log.id" class="hover:bg-slate-50 transition">
                                    <td class="p-3 font-mono text-slate-600">{{ formatDateTime(log.created_at) }}</td>
                                    <td class="p-3 font-bold text-slate-900">{{ log.product?.name || '-' }}</td>
                                    <td class="p-3 font-bold">
                                        <span 
                                            class="px-2 py-0.5 rounded-full text-[10px] uppercase font-black tracking-wider"
                                            :class="[
                                                log.type === 'in' ? 'bg-emerald-100 text-emerald-800' : 
                                                (log.type === 'out' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')
                                            ]"
                                        >
                                            {{ log.type }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center font-mono font-black" :class="log.qty_change > 0 ? 'text-emerald-700' : 'text-rose-700'">
                                        {{ log.qty_change > 0 ? '+' : '' }}{{ log.qty_change }}
                                    </td>
                                    <td class="p-3 text-slate-600">{{ log.reason || '-' }}</td>
                                    <td class="p-3 font-semibold text-slate-800">{{ log.user?.name || '-' }}</td>
                                </tr>

                                <tr v-if="stockLogs.length === 0">
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        Belum ada riwayat mutasi stok.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MODAL: Konfirmasi Selesai & Terapkan Hasil Stok Opname -->
            <div v-if="isConfirmModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black">
                                <ClipboardCheck class="w-5 h-5" />
                            </div>
                            <h3 class="text-sm font-black text-slate-900">Konfirmasi Simpan Hasil Stok Opname</h3>
                        </div>
                        <button @click="isConfirmModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Anda akan menerapkan hasil hitung fisik ke stok sistem dan membuat dokumen Berita Acara <strong>{{ form.opname_number }}</strong>.
                    </p>

                    <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-2xl text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Total Barang Dicek:</span>
                            <span class="font-bold text-slate-800">{{ auditSummary.totalChecked }} Produk</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Barang Sesuai:</span>
                            <span class="font-bold text-emerald-700">{{ auditSummary.matchCount }} Produk</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Barang Selisih:</span>
                            <span class="font-black text-rose-700">{{ auditSummary.diffCount }} Produk</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold">Total Nilai Selisih HPP:</span>
                            <span class="font-black" :class="auditSummary.totalCostDiff < 0 ? 'text-rose-600' : 'text-slate-800'">
                                {{ formatRupiah(auditSummary.totalCostDiff) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-700 font-bold mb-1 text-xs">Catatan Kesimpulan Berita Acara (Opsional)</label>
                        <textarea 
                            v-model="form.notes"
                            rows="2.5"
                            placeholder="Contoh: Stok opname rutin akhir bulan September 2026. Selisih disebabkan barang rusak dan human error kasir..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="isConfirmModalOpen = false" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            @click="submitStockOpname" 
                            :disabled="form.processing"
                            class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-md flex items-center gap-1.5"
                        >
                            <span v-if="form.processing">Memproses...</span>
                            <span v-else>Ya, Terapkan & Simpan SO</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL: Rincian Detail Sesi SO & Cetak -->
            <div v-if="isDetailModalOpen && selectedOpname" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-sky-500 text-white flex items-center justify-center font-black">
                                <FileText class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Rincian Dokumen {{ selectedOpname.opname_number }}</h3>
                                <p class="text-xs text-slate-500">Tanggal: {{ formatDate(selectedOpname.opname_date) }} &bull; Auditor: {{ selectedOpname.user?.name }}</p>
                            </div>
                        </div>
                        <button @click="isDetailModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 space-y-4 overflow-y-auto flex-1 text-xs">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3.5 rounded-2xl">
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold">Total Barang:</span>
                                <span class="font-bold text-slate-800">{{ selectedOpname.total_items_checked }} Produk</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold">Item Selisih:</span>
                                <span class="font-black text-rose-700">{{ selectedOpname.total_items_diff }} Produk</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold">Total Kuantitas Selisih:</span>
                                <span class="font-black text-slate-800">{{ selectedOpname.total_qty_diff > 0 ? '+' : '' }}{{ selectedOpname.total_qty_diff }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold">Total Selisih HPP:</span>
                                <span class="font-black" :class="selectedOpname.total_cost_diff < 0 ? 'text-rose-600' : 'text-slate-800'">
                                    {{ formatRupiah(selectedOpname.total_cost_diff) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="selectedOpname.notes" class="bg-amber-50 border border-amber-200 rounded-2xl p-3 text-amber-900 text-xs italic">
                            Catatan Berita Acara: "{{ selectedOpname.notes }}"
                        </div>

                        <div class="border border-slate-200 rounded-2xl overflow-hidden">
                            <div class="max-h-72 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 text-slate-600 font-bold text-[10px] sticky top-0">
                                        <tr>
                                            <th class="p-2.5 text-center w-10">No</th>
                                            <th class="p-2.5">Nama Produk</th>
                                            <th class="p-2.5 text-center w-24">Stok Sistem</th>
                                            <th class="p-2.5 text-center w-24">Fisik Nyata</th>
                                            <th class="p-2.5 text-center w-24">Selisih</th>
                                            <th class="p-2.5 text-right w-28">Subtotal HPP</th>
                                            <th class="p-2.5">Alasan / Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr 
                                            v-for="(it, idx) in selectedOpname.items" 
                                            :key="it.id"
                                            :class="Math.abs(Number(it.qty_difference)) > 0.0001 ? 'bg-amber-50/40' : ''"
                                        >
                                            <td class="p-2.5 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                            <td class="p-2.5 font-bold text-slate-900">{{ it.product?.name || '-' }}</td>
                                            <td class="p-2.5 text-center font-mono">{{ it.qty_system }} {{ it.unit_name }}</td>
                                            <td class="p-2.5 text-center font-mono font-bold">{{ it.qty_physical }} {{ it.unit_name }}</td>
                                            <td class="p-2.5 text-center font-mono font-black">
                                                <span :class="Number(it.qty_difference) < 0 ? 'text-rose-600' : (Number(it.qty_difference) > 0 ? 'text-emerald-600' : 'text-slate-400')">
                                                    {{ Number(it.qty_difference) > 0 ? '+' : '' }}{{ it.qty_difference }}
                                                </span>
                                            </td>
                                            <td class="p-2.5 text-right font-mono font-bold" :class="Number(it.subtotal_cost_diff) < 0 ? 'text-rose-600' : 'text-slate-800'">
                                                {{ formatRupiah(it.subtotal_cost_diff) }}
                                            </td>
                                            <td class="p-2.5 text-slate-600 italic">{{ it.notes || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
                        <button 
                            type="button" 
                            @click="printBap(selectedOpname)"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-xs"
                        >
                            <Printer class="w-4 h-4" />
                            <span>Cetak Berita Acara Opname</span>
                        </button>

                        <button 
                            type="button" 
                            @click="isDetailModalOpen = false"
                            class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs cursor-pointer"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
