<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Store, PlusCircle, Search, UserPlus, Phone, Calendar, Clock, 
    CheckCircle2, AlertCircle, Printer, Wallet, ArrowRight, X, 
    Trash2, Edit, Check, TrendingUp, PackageCheck, Receipt, 
    ChevronDown, Sparkles, Plus, AlertTriangle, UserCheck
} from 'lucide-vue-next';

const props = defineProps({
    activeBatches: Array,
    settledBatches: Array,
    consignors: Array,
    consignmentProducts: Array,
    allProducts: Array,
    cashboxes: Array,
    settings: Object,
    summary: Object,
});

const page = usePage();
const activeTab = ref('active'); // 'active' | 'history' | 'consignors'

// Helpers
const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { 
        day: '2-digit', month: 'short', year: 'numeric', 
        hour: '2-digit', minute: '2-digit' 
    });
};

// ==========================================
// 1. MODAL TERIMA TITIPAN (PAGI)
// ==========================================
const isNewBatchModalOpen = ref(false);
const batchForm = useForm({
    consignor_id: '',
    dropoff_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: [
        { product_id: '', custom_name: '', qty_dropped: 10, cost_price: 2000, selling_price: 2500 }
    ]
});

const openNewBatchModal = (preselectedConsignorId = null) => {
    batchForm.reset();
    batchForm.dropoff_date = new Date().toISOString().split('T')[0];
    batchForm.items = [
        { product_id: '', custom_name: '', qty_dropped: 10, cost_price: 2000, selling_price: 2500 }
    ];
    if (preselectedConsignorId) {
        batchForm.consignor_id = preselectedConsignorId;
    } else if (props.consignors && props.consignors.length > 0) {
        batchForm.consignor_id = props.consignors[0].id;
    }
    isNewBatchModalOpen.value = true;
};

const addBatchItemRow = () => {
    batchForm.items.push({
        product_id: '',
        custom_name: '',
        qty_dropped: 10,
        cost_price: 2000,
        selling_price: 2500
    });
};

const removeBatchItemRow = (idx) => {
    if (batchForm.items.length > 1) {
        batchForm.items.splice(idx, 1);
    }
};

const onProductSelect = (itemRow, productId) => {
    if (!productId) return;
    const prod = props.consignmentProducts.find(p => p.id === productId)
        || props.allProducts.find(p => p.id === productId);
    if (prod) {
        itemRow.product_id = prod.id;
        itemRow.custom_name = prod.name;
        if (prod.base_unit) {
            itemRow.cost_price = Number(prod.base_unit.cost_price) || 0;
            itemRow.selling_price = Number(prod.base_unit.selling_price) || 0;
        }
    }
};

const submitNewBatch = () => {
    batchForm.post('/consignments/batches', {
        preserveScroll: true,
        onSuccess: () => {
            isNewBatchModalOpen.value = false;
            batchForm.reset();
        }
    });
};

// ==========================================
// 2. MODAL HITUNG & BAYAR SORE (SETTLEMENT)
// ==========================================
const isSettleModalOpen = ref(false);
const selectedBatchForSettle = ref(null);
const settleForm = useForm({
    cashbox_id: '',
    notes: '',
    items: []
});

const openSettleModal = (batch) => {
    selectedBatchForSettle.value = batch;
    settleForm.notes = '';
    const defaultBox = (props.cashboxes || []).find(b => b.is_default) || (props.cashboxes || [])[0];
    settleForm.cashbox_id = defaultBox ? defaultBox.id : '';

    settleForm.items = batch.items.map(it => ({
        id: it.id,
        name: it.product ? it.product.name : 'Jajan',
        qty_dropped: Number(it.qty_dropped),
        qty_returned: 0, // default sisa 0 (laku semua)
        cost_price: Number(it.cost_price),
        selling_price: Number(it.selling_price),
        current_stock: it.current_stock ?? 0,
    }));

    isSettleModalOpen.value = true;
};

// Dynamic calculations during settlement
const settleCalculations = computed(() => {
    let totalSold = 0;
    let totalReturned = 0;
    let totalPayable = 0;
    let totalProfit = 0;

    const itemsDetail = settleForm.items.map(it => {
        const dropped = Number(it.qty_dropped) || 0;
        const returned = Math.max(0, Math.min(dropped, Number(it.qty_returned) || 0));
        const sold = Math.max(0, dropped - returned);
        const payable = sold * it.cost_price;
        const profit = sold * (it.selling_price - it.cost_price);

        totalSold += sold;
        totalReturned += returned;
        totalPayable += payable;
        totalProfit += profit;

        return {
            ...it,
            calculated_sold: sold,
            calculated_payable: payable,
            calculated_profit: profit,
        };
    });

    return {
        totalSold,
        totalReturned,
        totalPayable,
        totalProfit,
        itemsDetail,
    };
});

const submitSettle = () => {
    if (!selectedBatchForSettle.value) return;
    settleForm.post(`/consignments/batches/${selectedBatchForSettle.value.id}/settle`, {
        preserveScroll: true,
        onSuccess: (pageRes) => {
            isSettleModalOpen.value = false;
            // Jika ada flash settled_batch_id, otomatis buka modal cetak struk
            const flash = pageRes?.props?.flash;
            const settledId = flash?.settled_batch_id || selectedBatchForSettle.value.id;
            setTimeout(() => {
                const settled = (props.settledBatches || []).find(b => b.id === settledId);
                if (settled) {
                    openReceiptModal(settled);
                }
            }, 300);
        }
    });
};

// ==========================================
// 3. MODAL CETAK STRUK SERAH TERIMA
// ==========================================
const isReceiptModalOpen = ref(false);
const receiptBatch = ref(null);
const printPaperSize = ref('58mm'); // '58mm' | '80mm'

const openReceiptModal = (batch) => {
    receiptBatch.value = batch;
    isReceiptModalOpen.value = true;
};

const printReceipt = () => {
    if (!receiptBatch.value) return;
    const b = receiptBatch.value;
    const storeName = props.settings?.store_name || 'KANTIN RSIA PEKAJANGAN';
    const storeAddress = props.settings?.store_address || 'Jl. Raya Ambokembang No. 42 Pekalongan';
    const storePhone = props.settings?.store_phone || '';

    let iframe = document.getElementById('consignment-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'consignment-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
    }

    const width = printPaperSize.value === '80mm' ? '72mm' : '48mm';
    const fontSize = printPaperSize.value === '80mm' ? '12px' : '10px';

    let itemsHtml = '';
    (b.items || []).forEach(it => {
        const name = it.product ? it.product.name : 'Jajan';
        const cost = formatRupiah(it.cost_price);
        const subtotal = formatRupiah(it.subtotal_payable);
        itemsHtml += `
            <div style="margin-bottom: 4px; border-bottom: 1px dashed #ddd; padding-bottom: 3px;">
                <div style="font-weight: bold;">${name}</div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9em; color: #333;">
                    <span>Titip:${it.qty_dropped} | Laku:${it.qty_sold} | Sisa:${it.qty_returned}</span>
                    <span>@${cost}</span>
                </div>
                <div style="text-align: right; font-weight: bold;">Subtotal: ${subtotal}</div>
            </div>
        `;
    });

    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Struk Konsinyasi - ${b.batch_number}</title>
            <style>
                @page { margin: 0; }
                body {
                    width: ${width};
                    margin: 0 auto;
                    padding: 8px 4px;
                    font-family: 'Courier New', Courier, monospace;
                    font-size: ${fontSize};
                    line-height: 1.25;
                    color: #000;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .divider { border-top: 1px dashed #000; margin: 6px 0; }
                .flex-between { display: flex; justify-content: space-between; }
                .font-bold { font-weight: bold; }
                .title { font-size: 1.15em; font-weight: bold; margin-bottom: 2px; }
            </style>
        </head>
        <body>
            <div class="text-center">
                <div class="title">${storeName}</div>
                <div>${storeAddress}</div>
                ${storePhone ? `<div>Telp: ${storePhone}</div>` : ''}
            </div>
            <div class="divider"></div>
            <div class="text-center font-bold">BUKTI SETORAN & PELUNASAN JAJAN</div>
            <div class="divider"></div>
            <div class="flex-between"><span>No. Batch</span><span>${b.batch_number}</span></div>
            <div class="flex-between"><span>Penitip</span><span class="font-bold">${b.consignor ? b.consignor.name : '-'}</span></div>
            <div class="flex-between"><span>Tanggal Titip</span><span>${formatDate(b.dropoff_date)}</span></div>
            <div class="flex-between"><span>Tanggal Lunas</span><span>${formatDateTime(b.settlement_date)}</span></div>
            <div class="flex-between"><span>Kasir</span><span>${b.cashier ? b.cashier.name : '-'}</span></div>
            <div class="divider"></div>
            <div class="font-bold" style="margin-bottom: 4px;">RINCIAN JAJAN:</div>
            ${itemsHtml}
            <div class="divider"></div>
            <div class="flex-between font-bold">
                <span>Total Terjual:</span>
                <span>${b.total_qty_sold} pcs</span>
            </div>
            <div class="flex-between font-bold">
                <span>Total Retur/Sisa:</span>
                <span>${b.total_qty_returned} pcs</span>
            </div>
            <div class="divider"></div>
            <div class="flex-between font-bold" style="font-size: 1.15em;">
                <span>DIBAYAR KE PENITIP:</span>
                <span>${formatRupiah(b.total_payable)}</span>
            </div>
            <div class="divider"></div>
            <div style="display: flex; justify-content: space-between; margin-top: 16px; text-align: center;">
                <div style="width: 45%;">
                    <div>Penerima / Penitip</div>
                    <div style="height: 35px;"></div>
                    <div>( ${b.consignor ? b.consignor.name : 'Penitip'} )</div>
                </div>
                <div style="width: 45%;">
                    <div>Kasir Kantin</div>
                    <div style="height: 35px;"></div>
                    <div>( ${b.cashier ? b.cashier.name : 'Kasir'} )</div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="text-center" style="font-size: 0.85em; margin-top: 6px;">
                Terima kasih atas kerja samanya.
            </div>
        </body>
        </html>
    `;

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    setTimeout(() => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            console.error(e);
            window.print();
        }
    }, 250);
};

// ==========================================
// 4. MASTER PENITIP MODAL (CRUD)
// ==========================================
const isConsignorModalOpen = ref(false);
const editingConsignor = ref(null);
const consignorForm = useForm({
    name: '',
    phone: '',
    notes: '',
    is_active: true,
});

const openConsignorModal = (item = null) => {
    editingConsignor.value = item;
    if (item) {
        consignorForm.name = item.name;
        consignorForm.phone = item.phone || '';
        consignorForm.notes = item.notes || '';
        consignorForm.is_active = Boolean(item.is_active);
    } else {
        consignorForm.reset();
        consignorForm.is_active = true;
    }
    isConsignorModalOpen.value = true;
};

const submitConsignor = () => {
    if (editingConsignor.value) {
        consignorForm.put(`/consignments/consignors/${editingConsignor.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isConsignorModalOpen.value = false;
                consignorForm.reset();
            }
        });
    } else {
        consignorForm.post('/consignments/consignors', {
            preserveScroll: true,
            onSuccess: () => {
                isConsignorModalOpen.value = false;
                consignorForm.reset();
            }
        });
    }
};

const deleteConsignor = (c) => {
    if (confirm(`Yakin ingin menghapus atau menonaktifkan penitip ${c.name}?`)) {
        router.delete(`/consignments/consignors/${c.id}`, {
            preserveScroll: true,
        });
    }
};

// Filtered Lists
const historySearch = ref('');
const filteredSettledBatches = computed(() => {
    const q = (historySearch.value || '').toLowerCase().trim();
    if (!q) return props.settledBatches || [];
    return (props.settledBatches || []).filter(b => 
        (b.batch_number && b.batch_number.toLowerCase().includes(q)) ||
        (b.consignor && b.consignor.name.toLowerCase().includes(q))
    );
});
</script>

<template>
    <Head title="Titip Jual / Konsinyasi Jajan" />

    <MainLayout>
        <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2.5 tracking-tight">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200/60 flex items-center justify-center text-amber-600">
                            <Store class="w-5 h-5" />
                        </div>
                        Titip Jual / Konsinyasi Jajan
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
                        Penerimaan titipan jajan pagi, monitoring penjualan POS, dan rekonsiliasi bagi hasil serta retur sore.
                    </p>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <button 
                        @click="openConsignorModal()"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-sm rounded-xl transition shadow-2xs hover:shadow active:scale-98 cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4 text-slate-600" />
                        <span>Tambah Penitip</span>
                    </button>
                    <button 
                        @click="openNewBatchModal()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm rounded-xl transition shadow-sm hover:shadow active:scale-98 cursor-pointer"
                    >
                        <PlusCircle class="w-4 h-4" />
                        <span>Terima Titipan Pagi</span>
                    </button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Titipan Aktif Hari Ini</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">
                            {{ summary?.active_count || 0 }} <span class="text-xs font-semibold text-slate-500">Penitip/Batch</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                        <Clock class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Jajan Masuk Hari Ini</div>
                        <div class="text-2xl font-black text-blue-600 mt-1">
                            {{ summary?.total_dropped_today || 0 }} <span class="text-xs font-semibold text-slate-500">pcs</span>
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                        <PackageCheck class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Lunas ke Penitip</div>
                        <div class="text-2xl font-black text-emerald-600 mt-1">
                            {{ formatRupiah(summary?.total_paid_today || 0) }}
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                        <Wallet class="w-6 h-6" />
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Laba / Margin Kantin</div>
                        <div class="text-2xl font-black text-purple-600 mt-1">
                            {{ formatRupiah(summary?.total_profit_today || 0) }}
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600">
                        <TrendingUp class="w-6 h-6" />
                    </div>
                </div>
            </div>

            <!-- Tabs Header -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                <button 
                    @click="activeTab = 'active'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'active' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <Clock class="w-4 h-4" />
                    <span>Titipan Aktif Berjalan</span>
                    <span 
                        class="px-2 py-0.5 rounded-full text-xs font-black"
                        :class="activeTab === 'active' ? 'bg-amber-700 text-white' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ (activeBatches || []).length }}
                    </span>
                </button>

                <button 
                    @click="activeTab = 'history'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'history' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <CheckCircle2 class="w-4 h-4" />
                    <span>Riwayat Pelunasan Selesai</span>
                </button>

                <button 
                    @click="activeTab = 'consignors'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer"
                    :class="activeTab === 'consignors' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <UserCheck class="w-4 h-4" />
                    <span>Daftar Penitip ({{ (consignors || []).length }})</span>
                </button>
            </div>

            <!-- TAB 1: TITIPAN AKTIF -->
            <div v-if="activeTab === 'active'" class="space-y-4">
                <div v-if="(!activeBatches || activeBatches.length === 0)" class="bg-white p-12 text-center rounded-2xl border border-slate-200 shadow-sm">
                    <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-3">
                        <Store class="w-8 h-8" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Belum Ada Titipan Jajan Aktif Hari Ini</h3>
                    <p class="text-sm text-slate-500 max-w-md mx-auto mt-1 mb-5 font-medium">
                        Saat penitip datang di pagi hari membawa risol, donat, pastel, sari roti, dsb, klik tombol di bawah untuk mencatat titipan.
                    </p>
                    <button 
                        @click="openNewBatchModal()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm rounded-xl shadow-sm hover:shadow transition active:scale-98 cursor-pointer"
                    >
                        <PlusCircle class="w-4 h-4" />
                        Terima Titipan Baru
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 gap-5">
                    <div 
                        v-for="batch in activeBatches" 
                        :key="batch.id"
                        class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden transition hover:border-amber-300"
                    >
                        <!-- Batch Header -->
                        <div class="p-4 sm:p-5 bg-gradient-to-r from-slate-50 to-amber-50/30 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-black text-lg">
                                    {{ (batch.consignor?.name || 'P')[0] }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-black text-slate-900">{{ batch.consignor?.name }}</h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800 border border-amber-200">
                                            Aktif Berjalan
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 flex items-center gap-3 mt-1">
                                        <span>No: <strong class="text-slate-700 font-mono">{{ batch.batch_number }}</strong></span>
                                        <span>•</span>
                                        <span>Titip: {{ formatDate(batch.dropoff_date) }}</span>
                                        <span>•</span>
                                        <span>Kasir: {{ batch.cashier?.name || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    @click="openSettleModal(batch)"
                                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-xl shadow-sm hover:shadow transition active:scale-98 cursor-pointer"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                    <span>Hitung & Bayar Sore</span>
                                </button>
                            </div>
                        </div>

                        <!-- Table of items in this batch -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="bg-slate-50/80 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                        <th class="py-3 px-4">Nama Jajan</th>
                                        <th class="py-3 px-4 text-center">Dititip (Pagi)</th>
                                        <th class="py-3 px-4 text-center">Sisa Stok POS Saat Ini</th>
                                        <th class="py-3 px-4 text-right">Harga Setor (Hak Penitip)</th>
                                        <th class="py-3 px-4 text-right">Harga Jual POS</th>
                                        <th class="py-3 px-4 text-right">Margin Kantin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in batch.items" :key="item.id" class="hover:bg-slate-50/60">
                                        <td class="py-3 px-4 font-bold text-slate-900">
                                            {{ item.product?.name || 'Jajan' }}
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-block px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-extrabold text-xs">
                                                {{ item.qty_dropped }} pcs
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span 
                                                class="inline-block px-2.5 py-1 rounded-lg font-extrabold text-xs"
                                                :class="item.current_stock > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500'"
                                            >
                                                {{ item.current_stock }} pcs
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right font-medium text-slate-700">
                                            {{ formatRupiah(item.cost_price) }}
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-slate-900">
                                            {{ formatRupiah(item.selling_price) }}
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-emerald-600">
                                            +{{ formatRupiah(item.selling_price - item.cost_price) }} /pcs
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Notes Footer if any -->
                        <div v-if="batch.notes" class="px-5 py-2.5 bg-slate-50/50 border-t border-slate-100 text-xs text-slate-500">
                            <strong>Catatan:</strong> {{ batch.notes }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: RIWAYAT PELUNASAN -->
            <div v-if="activeTab === 'history'" class="space-y-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200">
                    <div class="relative w-full sm:w-80">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="historySearch"
                            type="text" 
                            placeholder="Cari No. Batch / Penitip..."
                            class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        />
                    </div>
                    <div class="text-xs text-slate-500 font-medium">
                        Menampilkan {{ filteredSettledBatches.length }} riwayat pelunasan terakhir
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                    <th class="py-3.5 px-4">No. Batch</th>
                                    <th class="py-3.5 px-4">Penitip</th>
                                    <th class="py-3.5 px-4">Waktu Selesai</th>
                                    <th class="py-3.5 px-4 text-center">Terjual / Retur</th>
                                    <th class="py-3.5 px-4 text-right">Dibayar ke Penitip</th>
                                    <th class="py-3.5 px-4 text-right">Laba Kantin</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="filteredSettledBatches.length === 0">
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-medium">
                                        Belum ada riwayat pelunasan titipan.
                                    </td>
                                </tr>
                                <tr v-for="b in filteredSettledBatches" :key="b.id" class="hover:bg-slate-50/60">
                                    <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                                        {{ b.batch_number }}
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-slate-900">
                                        {{ b.consignor?.name }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 text-xs">
                                        {{ formatDateTime(b.settlement_date) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5 text-xs font-bold">
                                            <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">{{ b.total_qty_sold }} laku</span>
                                            <span class="text-slate-400">/</span>
                                            <span class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded">{{ b.total_qty_returned }} retur</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                        {{ formatRupiah(b.total_payable) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-purple-600">
                                        +{{ formatRupiah(b.total_canteen_profit) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button 
                                            @click="openReceiptModal(b)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition active:scale-95 cursor-pointer"
                                            title="Cetak Ulang Struk"
                                        >
                                            <Printer class="w-3.5 h-3.5" />
                                            <span>Struk</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MASTER PENITIP -->
            <div v-if="activeTab === 'consignors'" class="space-y-4">
                <div class="flex justify-between items-center bg-white p-4 rounded-2xl border border-slate-200">
                    <div>
                        <h3 class="font-bold text-slate-900">Daftar Mitra Penitip Jajan</h3>
                        <p class="text-xs text-slate-500">Daftar orang atau UMKM yang menitipkan makanan/snack di kantin.</p>
                    </div>
                    <button 
                        @click="openConsignorModal()"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm rounded-xl transition shadow-sm active:scale-98 cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4" />
                        Tambah Penitip
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="c in consignors" 
                        :key="c.id"
                        class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-amber-300 transition"
                    >
                        <div>
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 font-black flex items-center justify-center text-base border border-amber-200/50">
                                        {{ c.name[0] }}
                                    </div>
                                    <div>
                                        <h4 class="font-black text-slate-900 text-base">{{ c.name }}</h4>
                                        <div class="text-xs text-slate-500 flex items-center gap-1.5 mt-0.5">
                                            <Phone class="w-3 h-3 text-slate-400" />
                                            <span>{{ c.phone || 'Tidak ada no. telp' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <span 
                                    class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                                    :class="c.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <p v-if="c.notes" class="text-xs text-slate-500 mt-3 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                {{ c.notes }}
                            </p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-500 font-medium">
                                Total {{ c.batches_count || 0 }}x titipan
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button 
                                    @click="openNewBatchModal(c.id)"
                                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold text-xs rounded-lg transition"
                                    title="Terima titipan baru untuk orang ini"
                                >
                                    + Titip Jajan
                                </button>
                                <button 
                                    @click="openConsignorModal(c)"
                                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition"
                                    title="Edit Data"
                                >
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button 
                                    @click="deleteConsignor(c)"
                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition"
                                    title="Hapus / Nonaktifkan"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: TERIMA TITIPAN BARU (PAGI) -->
        <!-- ============================================================= -->
        <div v-if="isNewBatchModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl border border-slate-200 overflow-hidden my-8">
                <!-- Header -->
                <div class="p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                            <PackageCheck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Terima Titipan Jajan Baru (Pagi)</h3>
                            <p class="text-xs text-slate-500 font-medium">Input jajan yang dititipkan pagi ini. Stok fisik POS akan langsung bertambah.</p>
                        </div>
                    </div>
                    <button @click="isNewBatchModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitNewBatch" class="p-6 space-y-5">
                    <!-- Top Form: Consignor & Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Pilih Penitip</label>
                            <select 
                                v-model="batchForm.consignor_id"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            >
                                <option value="" disabled>-- Pilih Nama Penitip --</option>
                                <option v-for="c in consignors" :key="c.id" :value="c.id">
                                    {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                                </option>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Belum terdaftar? <button type="button" @click="openConsignorModal()" class="text-amber-600 font-bold underline">Tambah penitip baru</button>
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Tanggal Titip</label>
                            <input 
                                v-model="batchForm.dropoff_date"
                                type="date"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Items Rows -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Daftar Jajan yang Dititipkan:
                            </label>
                            <button 
                                type="button"
                                @click="addBatchItemRow"
                                class="inline-flex items-center gap-1.5 text-xs font-black text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Tambah Jajan Lain</span>
                            </button>
                        </div>

                        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                            <div 
                                v-for="(row, idx) in batchForm.items" 
                                :key="idx"
                                class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3 relative group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-600">Jajan #{{ idx + 1 }}</span>
                                    <button 
                                        v-if="batchForm.items.length > 1"
                                        type="button"
                                        @click="removeBatchItemRow(idx)"
                                        class="text-rose-500 hover:text-rose-700 text-xs font-bold flex items-center gap-1"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                        Hapus
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <!-- Snack Name / Auto Select -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Pilih Produk atau Ketik Baru</label>
                                        <div class="space-y-1.5">
                                            <select 
                                                class="w-full px-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg text-slate-700 font-medium"
                                                @change="onProductSelect(row, $event.target.value)"
                                            >
                                                <option value="">-- Cari dari Produk Tersedia --</option>
                                                <option v-for="p in consignmentProducts" :key="p.id" :value="p.id">
                                                    {{ p.name }} ({{ formatRupiah(p.base_unit?.selling_price) }})
                                                </option>
                                            </select>
                                            <input 
                                                v-model="row.custom_name"
                                                type="text"
                                                required
                                                placeholder="Ketik nama jajan (misal: Risol Mayo)"
                                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                            />
                                        </div>
                                    </div>

                                    <!-- Qty Dropped -->
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Jumlah Dititip</label>
                                        <div class="relative">
                                            <input 
                                                v-model="row.qty_dropped"
                                                type="number"
                                                min="1"
                                                required
                                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm font-black text-slate-900 text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                                            />
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">pcs</span>
                                        </div>
                                    </div>

                                    <!-- Prices -->
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Harga Setor (Penitip)</label>
                                        <input 
                                            v-model="row.cost_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Setor"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-800 text-right focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        />
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-500">Harga Jual di POS:</span>
                                        <input 
                                            v-model="row.selling_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Jual"
                                            class="w-28 px-2 py-1 bg-white border border-slate-200 rounded-md text-xs font-black text-slate-900 text-right"
                                        />
                                    </div>
                                    <div class="font-bold">
                                        Margin Kantin: 
                                        <span class="text-emerald-600 font-black">
                                            {{ formatRupiah(row.selling_price - row.cost_price) }} /pcs
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Tambahan (Opsional)</label>
                        <input 
                            v-model="batchForm.notes"
                            type="text"
                            placeholder="Contoh: Titipan ditaruh di rak jajan basah depan"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isNewBatchModalOpen = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="batchForm.processing"
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-sm rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="batchForm.processing">Menyimpan...</span>
                            <span v-else>Simpan & Tambahkan ke Stok Kasir</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: HITUNG & BAYAR SORE (SETTLEMENT) -->
        <!-- ============================================================= -->
        <div v-if="isSettleModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl border border-slate-200 overflow-hidden my-8">
                <!-- Header -->
                <div class="p-5 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-emerald-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold">
                            <Wallet class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900">Hitung & Bayar Titipan (Pelunasan)</h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Penitip: <strong>{{ selectedBatchForSettle?.consignor?.name }}</strong> 
                                • No: <span class="font-mono">{{ selectedBatchForSettle?.batch_number }}</span>
                            </p>
                        </div>
                    </div>
                    <button @click="isSettleModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSettle" class="p-6 space-y-5">
                    <div class="bg-emerald-50/70 border border-emerald-200 p-3.5 rounded-xl text-xs text-emerald-900 flex items-center gap-2.5">
                        <Sparkles class="w-5 h-5 text-emerald-600 shrink-0" />
                        <div>
                            <strong>Instruksi Kasir:</strong> Cukup masukkan <strong>Sisa Fisik</strong> jajan yang tersisa sore ini dan dibawa pulang oleh penitip. Sistem akan otomatis menghitung jumlah terjual, uang hak penitip, dan laba kantin.
                        </div>
                    </div>

                    <!-- Items Calculation Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-600 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                    <th class="py-3 px-3.5">Nama Jajan</th>
                                    <th class="py-3 px-3 text-center">Dititip</th>
                                    <th class="py-3 px-3 text-center bg-amber-50 text-amber-900">Sisa Fisik (Retur)</th>
                                    <th class="py-3 px-3 text-center">Terjual</th>
                                    <th class="py-3 px-3 text-right">Harga Setor</th>
                                    <th class="py-3 px-3.5 text-right">Hak Penitip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(item, idx) in settleForm.items" :key="item.id" class="hover:bg-slate-50/50">
                                    <td class="py-3 px-3.5 font-bold text-slate-900">
                                        {{ item.name }}
                                        <div class="text-[11px] text-slate-400 font-normal">
                                            Jual @{{ formatRupiah(item.selling_price) }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold text-slate-700">
                                        {{ item.qty_dropped }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center bg-amber-50/50">
                                        <div class="flex items-center justify-center">
                                            <input 
                                                v-model="item.qty_returned"
                                                type="number"
                                                min="0"
                                                :max="item.qty_dropped"
                                                required
                                                class="w-16 px-2 py-1.5 bg-white border border-amber-300 rounded-lg text-center font-black text-amber-900 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none shadow-xs"
                                            />
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-1 rounded-md bg-emerald-50 text-emerald-800 font-black text-xs">
                                            {{ Math.max(0, item.qty_dropped - (item.qty_returned || 0)) }} pcs
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right text-xs font-medium text-slate-600">
                                        {{ formatRupiah(item.cost_price) }}
                                    </td>
                                    <td class="py-3 px-3.5 text-right font-black text-slate-900">
                                        {{ formatRupiah(Math.max(0, item.qty_dropped - (item.qty_returned || 0)) * item.cost_price) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Card -->
                    <div class="p-4 bg-slate-900 text-white rounded-2xl space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center border-b border-slate-800 pb-3">
                            <div>
                                <div class="text-[11px] text-slate-400 font-bold uppercase">Total Dititip</div>
                                <div class="text-base font-black text-white mt-0.5">
                                    {{ settleForm.items.reduce((acc, i) => acc + Number(i.qty_dropped), 0) }} pcs
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400 font-bold uppercase">Total Terjual</div>
                                <div class="text-base font-black text-emerald-400 mt-0.5">
                                    {{ settleCalculations.totalSold }} pcs
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400 font-bold uppercase">Total Retur (Sisa)</div>
                                <div class="text-base font-black text-amber-400 mt-0.5">
                                    {{ settleCalculations.totalReturned }} pcs
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-slate-400 font-bold uppercase">Laba Kantin</div>
                                <div class="text-base font-black text-purple-300 mt-0.5">
                                    +{{ formatRupiah(settleCalculations.totalProfit) }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-1">
                            <span class="text-sm font-bold text-slate-300 uppercase tracking-wider">
                                Total Uang Dibayar ke Penitip:
                            </span>
                            <span class="text-2xl sm:text-3xl font-black text-emerald-400">
                                {{ formatRupiah(settleCalculations.totalPayable) }}
                            </span>
                        </div>
                    </div>

                    <!-- Cashbox & Notes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Potong Saldo Kasir / Cashbox</label>
                            <select 
                                v-model="settleForm.cashbox_id"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            >
                                <option v-for="cb in cashboxes" :key="cb.id" :value="cb.id">
                                    {{ cb.name }} (Saldo: {{ formatRupiah(cb.balance) }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Catatan Pelunasan</label>
                            <input 
                                v-model="settleForm.notes"
                                type="text"
                                placeholder="Contoh: Sudah lunas tunai sore ini"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isSettleModalOpen = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="settleForm.processing"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-xl shadow-md transition disabled:opacity-50 active:scale-98 cursor-pointer"
                        >
                            <Check class="w-4 h-4" />
                            <span v-if="settleForm.processing">Memproses...</span>
                            <span v-else>Konfirmasi Bayar & Cetak Struk</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: CETAK STRUK SERAH TERIMA (THERMAL) -->
        <!-- ============================================================= -->
        <div v-if="isReceiptModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden my-8">
                <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Printer class="w-5 h-5 text-amber-400" />
                        <h3 class="font-black text-sm">Struk Serah Terima Jajan</h3>
                    </div>
                    <button @click="isReceiptModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Preview Area -->
                <div class="p-6 bg-slate-100 flex justify-center">
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-slate-200 w-full font-mono text-xs text-slate-900 space-y-2">
                        <div class="text-center border-b border-dashed border-slate-300 pb-2">
                            <div class="font-black text-sm">{{ settings?.store_name || 'KANTIN RSIA PEKAJANGAN' }}</div>
                            <div class="text-[10px] text-slate-500">{{ settings?.store_address || 'Pekalongan' }}</div>
                            <div class="text-[10px] font-bold mt-1 uppercase">Struk Pelunasan Konsinyasi</div>
                        </div>

                        <div class="space-y-1 text-[11px] border-b border-dashed border-slate-300 pb-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">No. Batch:</span>
                                <span class="font-bold">{{ receiptBatch?.batch_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Penitip:</span>
                                <span class="font-bold">{{ receiptBatch?.consignor?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Waktu:</span>
                                <span>{{ formatDateTime(receiptBatch?.settlement_date) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kasir:</span>
                                <span>{{ receiptBatch?.cashier?.name || '-' }}</span>
                            </div>
                        </div>

                        <!-- Item list -->
                        <div class="space-y-2 border-b border-dashed border-slate-300 pb-2">
                            <div v-for="it in (receiptBatch?.items || [])" :key="it.id" class="text-[11px]">
                                <div class="font-bold text-slate-900">{{ it.product?.name || 'Jajan' }}</div>
                                <div class="flex justify-between text-slate-500 text-[10px]">
                                    <span>Titip: {{ it.qty_dropped }} | Laku: {{ it.qty_sold }} | Retur: {{ it.qty_returned }}</span>
                                    <span>@{{ formatRupiah(it.cost_price) }}</span>
                                </div>
                                <div class="text-right font-black text-slate-800">
                                    {{ formatRupiah(it.subtotal_payable) }}
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-1 text-[11px] pt-1 border-b border-dashed border-slate-300 pb-2">
                            <div class="flex justify-between font-bold">
                                <span>Total Terjual:</span>
                                <span>{{ receiptBatch?.total_qty_sold }} pcs</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Total Retur (Sisa):</span>
                                <span>{{ receiptBatch?.total_qty_returned }} pcs</span>
                            </div>
                            <div class="flex justify-between font-black text-sm pt-1">
                                <span>TOTAL DIBAYAR:</span>
                                <span class="text-emerald-700">{{ formatRupiah(receiptBatch?.total_payable) }}</span>
                            </div>
                        </div>

                        <!-- Signature mockup -->
                        <div class="grid grid-cols-2 text-center text-[10px] pt-3 pb-1">
                            <div>
                                <div>Penitip</div>
                                <div class="h-8"></div>
                                <div class="font-bold">({{ receiptBatch?.consignor?.name || 'Penitip' }})</div>
                            </div>
                            <div>
                                <div>Kasir</div>
                                <div class="h-8"></div>
                                <div class="font-bold">({{ receiptBatch?.cashier?.name || 'Kasir' }})</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Print options -->
                <div class="p-4 bg-white border-t border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600">Kertas:</label>
                        <select v-model="printPaperSize" class="text-xs font-bold bg-slate-100 border border-slate-200 rounded-lg px-2 py-1">
                            <option value="58mm">58mm</option>
                            <option value="80mm">80mm</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            @click="isReceiptModalOpen = false" 
                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl"
                        >
                            Tutup
                        </button>
                        <button 
                            @click="printReceipt" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-sm cursor-pointer"
                        >
                            <Printer class="w-3.5 h-3.5 text-amber-400" />
                            <span>Cetak Struk Thermal</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: TAMBAH / EDIT PENITIP -->
        <!-- ============================================================= -->
        <div v-if="isConsignorModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                <div class="p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                            <UserPlus class="w-4 h-4" />
                        </div>
                        <h3 class="font-black text-slate-900">
                            {{ editingConsignor ? 'Edit Data Penitip' : 'Daftarkan Penitip Baru' }}
                        </h3>
                    </div>
                    <button @click="isConsignorModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitConsignor" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Penitip / Vendor</label>
                        <input 
                            v-model="consignorForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Bu Anita (Donat & Risol)"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp / HP</label>
                        <input 
                            v-model="consignorForm.phone"
                            type="text"
                            placeholder="Contoh: 08123456789"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan / Alamat / Jadwal</label>
                        <textarea 
                            v-model="consignorForm.notes"
                            rows="2"
                            placeholder="Contoh: Datang jam 06.30 pagi, ambil sisa jam 16.00 sore"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div v-if="editingConsignor" class="flex items-center gap-2">
                        <input 
                            v-model="consignorForm.is_active"
                            type="checkbox"
                            id="consignor_active"
                            class="w-4 h-4 rounded text-amber-600"
                        />
                        <label for="consignor_active" class="text-xs font-bold text-slate-700">Status Aktif</label>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isConsignorModalOpen = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="consignorForm.processing"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-sm disabled:opacity-50 cursor-pointer"
                        >
                            Simpan Data Penitip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>
