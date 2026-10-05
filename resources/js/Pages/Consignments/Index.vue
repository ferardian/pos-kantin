<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useForm, router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import SearchableProductSelect from '@/Components/SearchableProductSelect.vue';
import { 
    Store, PlusCircle, Search, UserPlus, Phone, Calendar, Clock, 
    CheckCircle2, AlertCircle, Printer, Wallet, ArrowRight, X, 
    Trash2, Edit, Check, TrendingUp, PackageCheck, Receipt, 
    ChevronDown, Sparkles, Plus, AlertTriangle, UserCheck,
    Layers, GitMerge, Tag, Utensils, Filter, ArrowUpRight
} from 'lucide-vue-next';

const props = defineProps({
    activeBatches: Array,
    settledBatches: Array,
    consignors: Array,
    consignmentProducts: Array,
    allProducts: Array,
    categories: Array,
    cashboxes: Array,
    settings: Object,
    summary: Object,
});

const page = usePage();
const activeTab = ref('active'); // 'active' | 'catalog' | 'history' | 'consignors'

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
        { product_id: '', custom_name: '', qty_dropped: '', cost_price: '', selling_price: '', price_employee: '' }
    ]
});

const openNewBatchModal = (preselectedConsignorId = null) => {
    batchForm.reset();
    batchForm.dropoff_date = new Date().toISOString().split('T')[0];
    batchForm.items = [
        { product_id: '', custom_name: '', qty_dropped: '', cost_price: '', selling_price: '', price_employee: '' }
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
        qty_dropped: '', cost_price: '', selling_price: '', price_employee: ''
    });
};

const removeBatchItemRow = (idx) => {
    if (batchForm.items.length > 1) {
        batchForm.items.splice(idx, 1);
    }
};

const onProductSelect = (itemRow, productId) => {
    if (!productId) {
        itemRow.product_id = "";
        return;
    }
    const targetId = Number(productId);
    const prod = (props.consignmentProducts || []).find(p => p.id == targetId)
        || (props.allProducts || []).find(p => p.id == targetId);
    if (prod) {
        itemRow.product_id = prod.id;
        itemRow.custom_name = prod.name;
        if (prod.base_unit) {
            itemRow.cost_price = Number(prod.base_unit.cost_price) || "";
            itemRow.selling_price = Number(prod.base_unit.price_retail || prod.base_unit.selling_price) || "";
            itemRow.price_employee = Number(prod.base_unit.price_employee) || "";
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
// 2. MODAL TAMBAH TITIPAN SUSULAN
// ==========================================
const isAddItemsModalOpen = ref(false);
const targetBatchForAdd = ref(null);
const addItemsForm = useForm({
    items: [
        { product_id: '', custom_name: '', qty_dropped: '', cost_price: '', selling_price: '', price_employee: '' }
    ]
});

const openAddItemsModal = (batch) => {
    targetBatchForAdd.value = batch;
    addItemsForm.reset();
    addItemsForm.items = [
        { product_id: '', custom_name: '', qty_dropped: '', cost_price: '', selling_price: '', price_employee: '' }
    ];
    isAddItemsModalOpen.value = true;
};

const addItemsRow = () => {
    addItemsForm.items.push({
        product_id: '',
        custom_name: '',
        qty_dropped: '', cost_price: '', selling_price: '', price_employee: ''
    });
};

const removeItemsRow = (idx) => {
    if (addItemsForm.items.length > 1) {
        addItemsForm.items.splice(idx, 1);
    }
};

const onAddItemsProductSelect = (itemRow, productId) => {
    if (!productId) {
        itemRow.product_id = "";
        return;
    }
    const targetId = Number(productId);
    const prod = (props.consignmentProducts || []).find(p => p.id == targetId)
        || (props.allProducts || []).find(p => p.id == targetId);
    if (prod) {
        itemRow.product_id = prod.id;
        itemRow.custom_name = prod.name;
        if (prod.base_unit) {
            itemRow.cost_price = Number(prod.base_unit.cost_price) || "";
            itemRow.selling_price = Number(prod.base_unit.price_retail || prod.base_unit.selling_price) || "";
            itemRow.price_employee = Number(prod.base_unit.price_employee) || "";
        }
    }
};

const submitAddItems = () => {
    if (!targetBatchForAdd.value) return;
    addItemsForm.post(`/consignments/batches/${targetBatchForAdd.value.id}/add-items`, {
        preserveScroll: true,
        onSuccess: () => {
            isAddItemsModalOpen.value = false;
            addItemsForm.reset();
            targetBatchForAdd.value = null;
        }
    });
};

// ==========================================
// 3. EDIT & HAPUS SATUAN ITEM DARI BATCH AKTIF
// ==========================================
// Hapus item
const isDeleteItemModalOpen = ref(false);
const selectedBatchForItemDelete = ref(null);
const selectedItemForDelete = ref(null);
const isDeletingItem = ref(false);

const openDeleteItemModal = (batch, item) => {
    selectedBatchForItemDelete.value = batch;
    selectedItemForDelete.value = item;
    isDeleteItemModalOpen.value = true;
};

const confirmDeleteItem = () => {
    if (!selectedBatchForItemDelete.value || !selectedItemForDelete.value) return;
    isDeletingItem.value = true;
    router.delete(`/consignments/batches/${selectedBatchForItemDelete.value.id}/items/${selectedItemForDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingItem.value = false;
            isDeleteItemModalOpen.value = false;
            selectedBatchForItemDelete.value = null;
            selectedItemForDelete.value = null;
        }
    });
};

// Edit item
const isEditItemModalOpen = ref(false);
const selectedBatchForItemEdit = ref(null);
const selectedItemForEdit = ref(null);
const editItemForm = useForm({
    qty_dropped: '',
    cost_price: '',
    selling_price: '',
    price_employee: '',
});

const openEditItemModal = (batch, item) => {
    selectedBatchForItemEdit.value = batch;
    selectedItemForEdit.value = item;
    editItemForm.qty_dropped = item.qty_dropped;
    editItemForm.cost_price = Number(item.cost_price);
    editItemForm.selling_price = Number(item.selling_price);
    editItemForm.price_employee = Number(item.price_employee || 0);
    isEditItemModalOpen.value = true;
};

const submitEditItem = () => {
    if (!selectedBatchForItemEdit.value || !selectedItemForEdit.value) return;
    editItemForm.put(`/consignments/batches/${selectedBatchForItemEdit.value.id}/items/${selectedItemForEdit.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditItemModalOpen.value = false;
            selectedBatchForItemEdit.value = null;
            selectedItemForEdit.value = null;
            editItemForm.reset();
        }
    });
};

// ==========================================
// 4. FITUR PENGGABUNGAN BATCH (MERGE BATCHES)
// ==========================================
const duplicateConsignorBatches = computed(() => {
    const groups = {};
    (props.activeBatches || []).forEach(b => {
        const cid = b.consignor_id;
        if (!groups[cid]) {
            groups[cid] = {
                consignor: b.consignor,
                batches: []
            };
        }
        groups[cid].batches.push(b);
    });
    return Object.values(groups).filter(g => g.batches.length > 1);
});

const isMergeModalOpen = ref(false);
const mergeTargetBatch = ref(null);
const mergeSourceBatch = ref(null);
const isMerging = ref(false);

const openMergeModal = (group) => {
    if (!group || group.batches.length < 2) return;
    mergeTargetBatch.value = group.batches[0];
    mergeSourceBatch.value = group.batches[1];
    isMergeModalOpen.value = true;
};

const confirmMergeBatches = () => {
    if (!mergeTargetBatch.value || !mergeSourceBatch.value) return;
    isMerging.value = true;
    router.post('/consignments/batches/merge', {
        target_batch_id: mergeTargetBatch.value.id,
        source_batch_id: mergeSourceBatch.value.id,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isMerging.value = false;
            isMergeModalOpen.value = false;
            mergeTargetBatch.value = null;
            mergeSourceBatch.value = null;
        }
    });
};

// ==========================================
// 5. KATALOG JAJAN KONSINYASI (CRUD PRODUK)
// ==========================================
const catalogSearch = ref('');
const catalogCategoryFilter = ref('');
const catalogConsignorFilter = ref('');

const filteredConsignmentProducts = computed(() => {
    let list = props.consignmentProducts || [];
    const q = (catalogSearch.value || '').toLowerCase().trim();
    if (q) {
        list = list.filter(p => 
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.barcode && p.barcode.toLowerCase().includes(q)) ||
            (p.consignor?.name && p.consignor.name.toLowerCase().includes(q))
        );
    }
    if (catalogCategoryFilter.value) {
        list = list.filter(p => p.category_id == catalogCategoryFilter.value);
    }
    if (catalogConsignorFilter.value) {
        if (catalogConsignorFilter.value === 'none') {
            list = list.filter(p => !p.consignor_id);
        } else {
            list = list.filter(p => p.consignor_id == catalogConsignorFilter.value);
        }
    }
    return list;
});

const isProductModalOpen = ref(false);
const editingProduct = ref(null);
const productForm = useForm({
    name: '',
    category_id: '',
    consignor_id: '',
    cost_price: '',
    price_retail: '',
    price_employee: '',
    barcode: '',
});

const openConsignmentProductModal = (prod = null) => {
    editingProduct.value = prod;
    if (prod) {
        productForm.name = prod.name;
        productForm.category_id = prod.category_id || '';
        productForm.consignor_id = prod.consignor_id || '';
        productForm.barcode = prod.barcode || '';
        const unit = prod.base_unit || (prod.units && prod.units[0]);
        productForm.cost_price = unit ? Number(unit.cost_price) : '';
        productForm.price_retail = unit ? Number(unit.price_retail || unit.selling_price) : '';
        productForm.price_employee = unit ? Number(unit.price_employee || 0) : '';
    } else {
        productForm.reset();
        const defaultCat = (props.categories || []).find(c => 
            c.name.toLowerCase().includes('snack') || c.name.toLowerCase().includes('makan')
        ) || (props.categories || [])[0];
        if (defaultCat) productForm.category_id = defaultCat.id;
    }
    isProductModalOpen.value = true;
};

const submitConsignmentProduct = () => {
    if (editingProduct.value) {
        productForm.put(`/consignments/products/${editingProduct.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isProductModalOpen.value = false;
                productForm.reset();
                editingProduct.value = null;
            }
        });
    } else {
        productForm.post('/consignments/products', {
            preserveScroll: true,
            onSuccess: () => {
                isProductModalOpen.value = false;
                productForm.reset();
                editingProduct.value = null;
            }
        });
    }
};

const isDeleteProductModalOpen = ref(false);
const productToDelete = ref(null);
const isDeletingProduct = ref(false);

const openDeleteProductModal = (prod) => {
    productToDelete.value = prod;
    isDeleteProductModalOpen.value = true;
};

const confirmDeleteProduct = () => {
    if (!productToDelete.value) return;
    isDeletingProduct.value = true;
    router.delete(`/consignments/products/${productToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingProduct.value = false;
            isDeleteProductModalOpen.value = false;
            productToDelete.value = null;
        }
    });
};

// ==========================================
// 6. MODAL HITUNG & BAYAR SORE (SETTLEMENT)
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

    settleForm.items = batch.items.map(it => {
        const dropped = Number(it.qty_dropped) || 0;
        const currentStock = Math.max(0, Number(it.current_stock ?? 0));
        // Default otomatis: sisa fisik = sisa stok fisik di kasir POS (tidak melebihi jumlah dititip)
        const autoReturned = Math.min(dropped, currentStock);

        return {
            id: it.id,
            name: it.product ? it.product.name : 'Jajan',
            qty_dropped: dropped,
            qty_returned: autoReturned,
            cost_price: Number(it.cost_price),
            selling_price: Number(it.selling_price),
            current_stock: it.current_stock ?? 0,
        };
    });

    isSettleModalOpen.value = true;
};

const applyPosStockToAll = () => {
    if (!settleForm.items) return;
    settleForm.items.forEach(it => {
        const dropped = Number(it.qty_dropped) || 0;
        const currentStock = Math.max(0, Number(it.current_stock ?? 0));
        it.qty_returned = Math.min(dropped, currentStock);
    });
};

const setAllSoldOut = () => {
    if (!settleForm.items) return;
    settleForm.items.forEach(it => {
        it.qty_returned = 0;
    });
};

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
// 7. MODAL HAPUS / BATALKAN TITIPAN BATCH
// ==========================================
const isDeleteModalOpen = ref(false);
const selectedBatchForDelete = ref(null);
const isDeleting = ref(false);

const openDeleteModal = (batch) => {
    selectedBatchForDelete.value = batch;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    selectedBatchForDelete.value = null;
};

const confirmDeleteBatch = () => {
    if (!selectedBatchForDelete.value) return;
    isDeleting.value = true;
    router.delete(`/consignments/batches/${selectedBatchForDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            selectedBatchForDelete.value = null;
        }
    });
};

// ==========================================
// 8. CETAK STRUK SERAH TERIMA
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
// 9. MASTER PENITIP MODAL (CRUD)
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

// History search
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
                        Penerimaan titipan jajan pagi, monitoring penjualan POS, katalog jajan konsinyasi, dan bagi hasil serta retur sore.
                    </p>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <button 
                        @click="openConsignmentProductModal()"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-sm rounded-xl transition shadow-2xs hover:shadow active:scale-98 cursor-pointer"
                    >
                        <Utensils class="w-4 h-4 text-amber-600" />
                        <span>+ Menu Jajan Baru</span>
                    </button>
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

            <!-- Tabs Navigation -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                <button 
                    @click="activeTab = 'active'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer shrink-0"
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
                    @click="activeTab = 'catalog'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer shrink-0"
                    :class="activeTab === 'catalog' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <Utensils class="w-4 h-4" />
                    <span>Katalog Jajan Konsinyasi</span>
                    <span 
                        class="px-2 py-0.5 rounded-full text-xs font-black"
                        :class="activeTab === 'catalog' ? 'bg-amber-700 text-white' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ (consignmentProducts || []).length }}
                    </span>
                </button>

                <button 
                    @click="activeTab = 'history'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer shrink-0"
                    :class="activeTab === 'history' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <Receipt class="w-4 h-4" />
                    <span>Riwayat Pelunasan</span>
                    <span 
                        class="px-2 py-0.5 rounded-full text-xs font-black"
                        :class="activeTab === 'history' ? 'bg-amber-700 text-white' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ (settledBatches || []).length }}
                    </span>
                </button>

                <button 
                    @click="activeTab = 'consignors'"
                    class="px-4 py-2 rounded-xl font-bold text-sm transition flex items-center gap-2 cursor-pointer shrink-0"
                    :class="activeTab === 'consignors' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'"
                >
                    <Store class="w-4 h-4" />
                    <span>Mitra Penitip</span>
                    <span 
                        class="px-2 py-0.5 rounded-full text-xs font-black"
                        :class="activeTab === 'consignors' ? 'bg-amber-700 text-white' : 'bg-slate-200 text-slate-700'"
                    >
                        {{ (consignors || []).length }}
                    </span>
                </button>
            </div>

            <!-- ============================================================= -->
            <!-- TAB 1: TITIPAN AKTIF BERJALAN -->
            <!-- ============================================================= -->
            <div v-if="activeTab === 'active'" class="space-y-4">
                <!-- Alert Gabung Batch jika ada penitip dengan > 1 batch aktif -->
                <div 
                    v-if="duplicateConsignorBatches.length > 0" 
                    class="p-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-300 rounded-2xl shadow-xs space-y-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <GitMerge class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-black text-amber-950 flex items-center gap-2">
                                <span>Terdeteksi Sesi Titipan Terpisah untuk Penitip yang Sama</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-200 text-amber-900 uppercase">Perlu Perhatian</span>
                            </h4>
                            <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                                Terdapat penitip yang memiliki lebih dari satu kartu titipan aktif berjalan hari ini. Agar struk dan pelunasan sore nanti menjadi satu, Anda dapat menggabungkan kartu-kartu tersebut.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 pt-1">
                        <div 
                            v-for="grp in duplicateConsignorBatches" 
                            :key="grp.consignor?.id" 
                            class="bg-white p-3 rounded-xl border border-amber-200/90 flex items-center justify-between gap-3 shadow-2xs"
                        >
                            <div>
                                <div class="font-black text-slate-900 text-xs">{{ grp.consignor?.name }}</div>
                                <div class="text-[11px] text-slate-500">
                                    Memiliki <strong>{{ grp.batches.length }} kartu aktif</strong> ({{ grp.batches.map(b => b.batch_number).join(', ') }})
                                </div>
                            </div>
                            <button 
                                @click="openMergeModal(grp)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl transition shadow-2xs cursor-pointer active:scale-98 shrink-0"
                            >
                                <GitMerge class="w-3.5 h-3.5" />
                                <span>Gabung Jadi 1</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="(activeBatches || []).length === 0" class="bg-white p-12 text-center rounded-2xl border border-slate-200">
                    <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-4">
                        <Clock class="w-8 h-8" />
                    </div>
                    <h3 class="text-lg font-black text-slate-900">Belum Ada Titipan Aktif Hari Ini</h3>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1 mb-5">
                        Klik tombol di bawah ini saat ada mitra penitip jajan yang datang menitipkan dagangannya pagi ini.
                    </p>
                    <button 
                        @click="openNewBatchModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-sm rounded-xl transition shadow-sm active:scale-98 cursor-pointer"
                    >
                        <PlusCircle class="w-4 h-4" />
                        <span>Terima Titipan Jajan Pagi</span>
                    </button>
                </div>

                <!-- Active Batches Grid -->
                <div v-else class="space-y-4">
                    <div 
                        v-for="batch in activeBatches" 
                        :key="batch.id"
                        class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden hover:border-amber-300 transition"
                    >
                        <!-- Batch Header -->
                        <div class="p-4 sm:p-5 bg-gradient-to-r from-slate-50 to-amber-50/30 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-base shadow-xs shrink-0">
                                    {{ batch.consignor?.name ? batch.consignor.name[0] : 'P' }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-base font-black text-slate-900">{{ batch.consignor?.name || 'Penitip Tanpa Nama' }}</h3>
                                        <span class="px-2 py-0.5 rounded-md text-[11px] font-black bg-amber-100 text-amber-800 font-mono">
                                            {{ batch.batch_number }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-500 mt-1 flex-wrap font-medium">
                                        <span class="flex items-center gap-1">
                                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                            {{ formatDate(batch.dropoff_date) }}
                                        </span>
                                        <span class="text-slate-300">•</span>
                                        <span>Kasir Penerima: <strong class="text-slate-700">{{ batch.cashier?.name || '-' }}</strong></span>
                                        <span class="text-slate-300">•</span>
                                        <span>Total: <strong class="text-blue-700 font-bold">{{ batch.total_qty_dropped }} pcs dititipkan</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons on Batch Card -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Tombol Tambah Susulan -->
                                <button 
                                    @click="openAddItemsModal(batch)"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs rounded-xl transition shadow-2xs hover:shadow cursor-pointer active:scale-98"
                                    title="Tambah barang jajan susulan ke batch ini"
                                >
                                    <Plus class="w-4 h-4 text-amber-700" />
                                    <span>+ Tambah Susulan</span>
                                </button>

                                <!-- Tombol Pelunasan Sore -->
                                <button 
                                    @click="openSettleModal(batch)"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl transition shadow-sm hover:shadow active:scale-98 cursor-pointer"
                                >
                                    <Wallet class="w-4 h-4" />
                                    <span>Hitung & Bayar Sore</span>
                                </button>

                                <!-- Tombol Hapus Sesi Titipan Keseluruhan -->
                                <button 
                                    @click="openDeleteModal(batch)"
                                    class="inline-flex items-center gap-1 px-2.5 py-2 bg-white hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200 hover:border-rose-200 font-bold text-xs rounded-xl transition shadow-2xs cursor-pointer active:scale-98"
                                    title="Batalkan & hapus seluruh sesi titipan ini"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Table of items in this batch -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="bg-slate-50/80 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                        <th class="py-3 px-4">Nama Jajan</th>
                                        <th class="py-3 px-4 text-center">Dititip (Pagi + Susulan)</th>
                                        <th class="py-3 px-4 text-center">Sisa Stok POS Saat Ini</th>
                                        <th class="py-3 px-4 text-right">Harga Setor (Hak Penitip)</th>
                                        <th class="py-3 px-4 text-right">Harga Jual POS</th>
                                        <th class="py-3 px-4 text-right">Margin Kantin</th>
                                        <th class="py-3 px-4 text-center">Aksi Item</th>
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
                                            <div>{{ formatRupiah(item.selling_price) }}</div>
                                            <div v-if="Number(item.price_employee) > 0" class="text-[10px] text-amber-700 font-extrabold">
                                                Karyawan: {{ formatRupiah(item.price_employee) }}
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right font-bold text-emerald-600">
                                            +{{ formatRupiah(item.selling_price - item.cost_price) }} /pcs
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button 
                                                    @click="openEditItemModal(batch, item)"
                                                    class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition cursor-pointer"
                                                    title="Edit Jumlah atau Harga Jajan Ini"
                                                >
                                                    <Edit class="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                    @click="openDeleteItemModal(batch, item)"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                                                    title="Hapus baris jajan ini dari titipan"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
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

            <!-- ============================================================= -->
            <!-- TAB 2: KATALOG JAJAN KONSINYASI -->
            <!-- ============================================================= -->
            <div v-if="activeTab === 'catalog'" class="space-y-4">
                <!-- Toolbar: Search & Filters -->
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="flex items-center gap-3 flex-1 flex-wrap">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="catalogSearch"
                                type="text" 
                                placeholder="Cari nama jajan / SKU / barcode..."
                                class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 focus:ring-amber-500"
                            />
                        </div>

                        <!-- Filter Kategori -->
                        <select 
                            v-model="catalogCategoryFilter"
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            <option value="">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>

                        <!-- Filter Penitip -->
                        <select 
                            v-model="catalogConsignorFilter"
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            <option value="">Semua Penitip / Bebas</option>
                            <option value="none">Bebas / Siapa Saja (Umum)</option>
                            <option v-for="c in consignors" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 justify-end">
                        <button 
                            @click="openConsignmentProductModal()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-xs transition active:scale-98 cursor-pointer"
                        >
                            <Plus class="w-4 h-4" />
                            <span>Tambah Jajan Baru</span>
                        </button>
                    </div>
                </div>

                <!-- Table of Consignment Catalog -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                    <th class="py-3.5 px-4">Nama Jajan & Kode</th>
                                    <th class="py-3.5 px-4">Kategori</th>
                                    <th class="py-3.5 px-4">Penitip Langganan</th>
                                    <th class="py-3.5 px-4 text-right">Harga Setor (Kulakan)</th>
                                    <th class="py-3.5 px-4 text-right">Harga Jual POS</th>
                                    <th class="py-3.5 px-4 text-right">Margin Kantin</th>
                                    <th class="py-3.5 px-4 text-center">Stok Kasir</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="filteredConsignmentProducts.length === 0">
                                    <td colspan="8" class="py-12 text-center text-slate-400 font-medium">
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                            <Utensils class="w-6 h-6" />
                                        </div>
                                        Tidak ada menu jajan konsinyasi yang sesuai pencarian / filter.
                                    </td>
                                </tr>
                                <tr v-for="prod in filteredConsignmentProducts" :key="prod.id" class="hover:bg-slate-50/70">
                                    <td class="py-3.5 px-4">
                                        <div class="font-black text-slate-900 text-sm">{{ prod.name }}</div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                                                {{ prod.sku }}
                                            </span>
                                            <span v-if="prod.barcode" class="text-[10px] font-mono text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                                Barcode: {{ prod.barcode }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200/60">
                                            {{ prod.category?.name || 'Snack / Makanan' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span 
                                            v-if="prod.consignor"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200/60"
                                        >
                                            <Store class="w-3 h-3" />
                                            {{ prod.consignor.name }}
                                        </span>
                                        <span 
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500"
                                        >
                                            Bebas / Siapa Saja
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-semibold text-slate-700">
                                        {{ formatRupiah(prod.base_unit?.cost_price || 0) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                        <div>{{ formatRupiah(prod.base_unit?.price_retail || prod.base_unit?.selling_price || 0) }}</div>
                                        <div v-if="Number(prod.base_unit?.price_employee) > 0" class="text-[10px] text-amber-700 font-extrabold">
                                            Karyawan: {{ formatRupiah(prod.base_unit?.price_employee) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-emerald-600">
                                        +{{ formatRupiah((prod.base_unit?.price_retail || 0) - (prod.base_unit?.cost_price || 0)) }}
                                        <span class="text-[10px] font-bold text-emerald-700 block">
                                            ({{ Number(prod.base_unit?.price_retail || 0) > 0 ? Math.round(((prod.base_unit?.price_retail - prod.base_unit?.cost_price) / prod.base_unit?.price_retail) * 100) : 0 }}%)
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span 
                                            class="inline-block px-2.5 py-1 rounded-lg font-black text-xs"
                                            :class="Number(prod.stock_physical) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'"
                                        >
                                            {{ Number(prod.stock_physical) || 0 }} pcs
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button 
                                                @click="openConsignmentProductModal(prod)"
                                                class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition cursor-pointer"
                                                title="Edit Jajan"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button 
                                                @click="openDeleteProductModal(prod)"
                                                class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                                                title="Hapus Jajan"
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

            <!-- ============================================================= -->
            <!-- TAB 3: RIWAYAT PELUNASAN -->
            <!-- ============================================================= -->
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
                                    <td class="py-3.5 px-4 text-right font-black text-purple-700">
                                        {{ formatRupiah(b.total_canteen_profit) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button 
                                            @click="openReceiptModal(b)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer"
                                        >
                                            <Printer class="w-3.5 h-3.5" />
                                            <span>Cetak Struk</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- TAB 4: MITRA PENITIP -->
            <!-- ============================================================= -->
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
                                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold text-xs rounded-lg transition cursor-pointer"
                                    title="Terima titipan baru untuk orang ini"
                                >
                                    + Titip Jajan
                                </button>
                                <button 
                                    @click="openConsignorModal(c)"
                                    class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                                    title="Edit Data"
                                >
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button 
                                    @click="deleteConsignor(c)"
                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer"
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
        <div v-if="isNewBatchModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh] my-auto">
                <!-- Header -->
                <div class="p-4 sm:p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold shrink-0">
                            <PackageCheck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900">Terima Titipan Jajan Baru (Pagi)</h3>
                            <p class="text-xs text-slate-500 font-medium">Input jajan yang dititipkan pagi ini. Stok fisik POS akan langsung bertambah.</p>
                        </div>
                    </div>
                    <button @click="isNewBatchModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitNewBatch" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                    <div class="p-4 sm:p-6 space-y-5 overflow-y-auto flex-1 min-h-0">
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
                                Belum terdaftar? <button type="button" @click="openConsignorModal()" class="text-amber-600 font-bold underline cursor-pointer">Tambah penitip baru</button>
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Tanggal Titip</label>
                            <input 
                                v-model="batchForm.dropoff_date"
                                type="date"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Items Repeater -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-black text-slate-700 uppercase tracking-wider">
                                Daftar Jajan yang Dititipkan Pagi Ini:
                            </label>
                            <button 
                                type="button" 
                                @click="addBatchItemRow"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                Tambah Baris Jajan
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(row, idx) in batchForm.items" 
                                :key="idx"
                                class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3 relative"
                                :style="{ zIndex: batchForm.items.length - idx + 10 }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <!-- Pilih Jajan dari Katalog (Searchable) -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                                Pilih dari Katalog Jajan Konsinyasi:
                                            </label>
                                            <SearchableProductSelect 
                                                v-model="row.product_id"
                                                :products="consignmentProducts"
                                                :preferred-consignor-id="batchForm.consignor_id"
                                                placeholder="-- Ketik / Cari Jajan dari Katalog --"
                                                @select="(prod) => onProductSelect(row, prod ? prod.id : '')"
                                            />
                                        </div>

                                        <!-- Atau Ketik Nama Baru -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                                Atau Ketik Nama Jajan (Jika belum ada):
                                            </label>
                                            <input 
                                                v-model="row.custom_name"
                                                type="text"
                                                placeholder="Contoh: Risol Mayo, Lemper Ayam..."
                                                :required="!row.product_id"
                                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <button 
                                        v-if="batchForm.items.length > 1"
                                        type="button" 
                                        @click="removeBatchItemRow(idx)"
                                        class="p-2 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer mt-5"
                                        title="Hapus baris"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-200/60">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Jumlah (Pcs)</label>
                                        <input 
                                            v-model="row.qty_dropped"
                                            type="number"
                                            min="1"
                                            required
                                            placeholder="Jml pcs"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-blue-700 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Setor (Hak)</label>
                                        <input 
                                            v-model="row.cost_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Setor"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Jual POS</label>
                                        <input 
                                            v-model="row.selling_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Jual Umum"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Karyawan (Opsional)</label>
                                        <input 
                                            v-model="row.price_employee"
                                            type="number"
                                            min="0"
                                            placeholder="Rp Karyawan"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-amber-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 font-bold text-[11px] pt-1">
                                    <div>
                                        Margin Umum: 
                                        <span class="text-emerald-600 font-black">
                                            {{ formatRupiah((row.selling_price || 0) - (row.cost_price || 0)) }} /pcs
                                        </span>
                                    </div>
                                    <div v-if="Number(row.price_employee) > 0">
                                        Margin Karyawan: 
                                        <span class="text-amber-700 font-black">
                                            {{ formatRupiah((row.price_employee || 0) - (row.cost_price || 0)) }} /pcs
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
                    </div>

                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                        <button 
                            type="button" 
                            @click="isNewBatchModalOpen = false"
                            class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-sm rounded-xl transition cursor-pointer"
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
        <!-- MODAL: TAMBAH TITIPAN SUSULAN -->
        <!-- ============================================================= -->
        <div v-if="isAddItemsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl border border-amber-200 overflow-hidden flex flex-col max-h-[92vh] my-auto animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="p-4 sm:p-5 bg-gradient-to-r from-amber-50 via-orange-50 to-amber-100/60 border-b border-amber-200 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center shadow-xs shrink-0">
                            <PlusCircle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Tambah Jajan Susulan Hari Ini</h3>
                            <p class="text-xs text-amber-800 font-semibold">
                                Penitip: <strong class="text-slate-900">{{ targetBatchForAdd?.consignor?.name }}</strong> 
                                • Batch: <span class="font-mono font-bold">{{ targetBatchForAdd?.batch_number }}</span>
                            </p>
                        </div>
                    </div>
                    <button @click="isAddItemsModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddItems" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                    <div class="p-4 sm:p-6 space-y-5 overflow-y-auto flex-1 min-h-0">
                    <!-- Informative Banner -->
                    <div class="p-3.5 bg-amber-50/90 border border-amber-200 rounded-xl flex items-start gap-2.5">
                        <Sparkles class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div class="text-xs text-amber-900 leading-relaxed">
                            <strong>Otomatis Jadi Satu:</strong> Jajan susulan ini akan ditambahkan langsung ke sesi titipan <strong>{{ targetBatchForAdd?.consignor?.name }}</strong> dan otomatis menambah stok fisik kasir POS. Saat sore pelunasan, hitungan dan struk tetap menjadi <strong>satu struk utuh</strong>!
                        </div>
                    </div>

                    <!-- Items Repeater for Susulan -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-black text-slate-700 uppercase tracking-wider">
                                Daftar Jajan Susulan yang Dibawa:
                            </label>
                            <button 
                                type="button" 
                                @click="addItemsRow"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition cursor-pointer"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                Tambah Baris Susulan
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div 
                                v-for="(row, idx) in addItemsForm.items" 
                                :key="idx"
                                class="p-4 bg-slate-50/80 rounded-xl border border-slate-200 space-y-3 relative"
                                :style="{ zIndex: addItemsForm.items.length - idx + 10 }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <!-- Pilih Jajan dari Katalog (Searchable) -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                                Pilih dari Katalog Jajan Konsinyasi:
                                            </label>
                                            <SearchableProductSelect 
                                                v-model="row.product_id"
                                                :products="consignmentProducts"
                                                :preferred-consignor-id="targetBatchForAdd?.consignor_id"
                                                placeholder="-- Ketik / Cari Jajan dari Katalog --"
                                                @select="(prod) => onAddItemsProductSelect(row, prod ? prod.id : '')"
                                            />
                                        </div>

                                        <!-- Atau Ketik Nama Baru -->
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                                Atau Ketik Nama Jajan Baru:
                                            </label>
                                            <input 
                                                v-model="row.custom_name"
                                                type="text"
                                                placeholder="Contoh: Risol Mayo, Onde-onde..."
                                                :required="!row.product_id"
                                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <button 
                                        v-if="addItemsForm.items.length > 1"
                                        type="button" 
                                        @click="removeItemsRow(idx)"
                                        class="p-2 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer mt-5"
                                        title="Hapus baris"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-200/60">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Jumlah Susulan (Pcs)</label>
                                        <input 
                                            v-model="row.qty_dropped"
                                            type="number"
                                            min="1"
                                            required
                                            placeholder="Jml pcs"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-blue-700 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Setor (Hak)</label>
                                        <input 
                                            v-model="row.cost_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Setor"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Jual POS</label>
                                        <input 
                                            v-model="row.selling_price"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="Rp Jual Umum"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Karyawan</label>
                                        <input 
                                            v-model="row.price_employee"
                                            type="number"
                                            min="0"
                                            placeholder="Rp Karyawan"
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-amber-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                    <!-- Modal Actions -->
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                        <button 
                            type="button" 
                            @click="isAddItemsModalOpen = false"
                            class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-sm rounded-xl transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="addItemsForm.processing"
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-sm rounded-xl shadow-md transition disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="addItemsForm.processing">Menyimpan...</span>
                            <span v-else>Simpan Barang Susulan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: KONFIRMASI HAPUS SATU ITEM JAJAN DARI BATCH -->
        <!-- ============================================================= -->
        <div v-if="isDeleteItemModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl border border-rose-100 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 bg-gradient-to-r from-rose-50 to-orange-50 border-b border-rose-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-xs">
                            <Trash2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Hapus Jajan dari Titipan</h3>
                            <p class="text-xs text-rose-700 font-medium">
                                Penitip: <strong>{{ selectedBatchForItemDelete?.consignor?.name }}</strong>
                            </p>
                        </div>
                    </div>
                    <button @click="isDeleteItemModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <p class="text-sm text-slate-700">
                        Apakah Anda yakin ingin menghapus jajan <strong>{{ selectedItemForDelete?.product?.name || 'Jajan' }}</strong> dari sesi titipan ini?
                    </p>

                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Jumlah yang ditarik:</span>
                            <span class="font-black text-rose-600">{{ selectedItemForDelete?.qty_dropped }} pcs</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Harga Setor:</span>
                            <span class="font-bold text-slate-800">{{ formatRupiah(selectedItemForDelete?.cost_price) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Harga Jual POS:</span>
                            <span class="font-bold text-slate-800">{{ formatRupiah(selectedItemForDelete?.selling_price) }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <span>Stok fisik jajan ini di POS kasir akan otomatis dikurangi kembali sebanyak <strong>{{ selectedItemForDelete?.qty_dropped }} pcs</strong>.</span>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="isDeleteItemModalOpen = false"
                        :disabled="isDeletingItem"
                        class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                    >
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="confirmDeleteItem"
                        :disabled="isDeletingItem"
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-xs disabled:opacity-50 cursor-pointer active:scale-98"
                    >
                        <span v-if="isDeletingItem">Menghapus...</span>
                        <span v-else>Ya, Hapus Jajan Ini</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: EDIT SATU ITEM JAJAN DI BATCH -->
        <!-- ============================================================= -->
        <div v-if="isEditItemModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-slate-200 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                            <Edit class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Edit Jajan Titipan</h3>
                            <p class="text-xs text-slate-500 font-medium">
                                {{ selectedItemForEdit?.product?.name || 'Jajan' }} • Penitip: {{ selectedBatchForItemEdit?.consignor?.name }}
                            </p>
                        </div>
                    </div>
                    <button @click="isEditItemModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditItem" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Jumlah Dititipkan (Pcs)</label>
                            <input 
                                v-model="editItemForm.qty_dropped"
                                type="number"
                                min="1"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-blue-700 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Harga Setor (Hak Penitip)</label>
                            <input 
                                v-model="editItemForm.cost_price"
                                type="number"
                                min="0"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Harga Jual POS (Umum)</label>
                            <input 
                                v-model="editItemForm.selling_price"
                                type="number"
                                min="0"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Harga Karyawan (Opsional)</label>
                            <input 
                                v-model="editItemForm.price_employee"
                                type="number"
                                min="0"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-amber-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Margin preview -->
                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-between text-xs font-bold">
                        <span class="text-emerald-800">Margin Laba Kantin:</span>
                        <span class="text-emerald-700 font-black text-sm">
                            +{{ formatRupiah((editItemForm.selling_price || 0) - (editItemForm.cost_price || 0)) }} /pcs
                        </span>
                    </div>

                    <p class="text-[11px] text-slate-400">
                        * Perubahan jumlah pcs akan otomatis menyesuaikan stok fisik jajan di kasir POS.
                    </p>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isEditItemModalOpen = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="editItemForm.processing"
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="editItemForm.processing">Menyimpan...</span>
                            <span v-else>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: GABUNG BATCH (MERGE DUPLICATE BATCHES) -->
        <!-- ============================================================= -->
        <div v-if="isMergeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-amber-200 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="p-5 bg-gradient-to-r from-amber-500 to-orange-500 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                            <GitMerge class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black">Gabungkan Sesi Titipan Jajan</h3>
                            <p class="text-xs text-amber-100 font-medium">Jadikan satu kartu untuk kemudahan struk & pelunasan sore.</p>
                        </div>
                    </div>
                    <button @click="isMergeModalOpen = false" class="text-white/80 hover:text-white p-1.5 rounded-lg hover:bg-white/20 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Penitip:</span>
                            <span class="font-black text-slate-900">{{ mergeTargetBatch?.consignor?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kartu Utama (Dipertahankan):</span>
                            <span class="font-mono font-bold text-slate-800">{{ mergeTargetBatch?.batch_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kartu Kedua (Digabungkan):</span>
                            <span class="font-mono font-bold text-amber-700">{{ mergeSourceBatch?.batch_number }}</span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Semua menu jajan dari kartu <strong>{{ mergeSourceBatch?.batch_number }}</strong> akan dipindahkan ke kartu utama <strong>{{ mergeTargetBatch?.batch_number }}</strong>. Jumlah pcs akan otomatis diakumulasikan, dan kartu kedua akan ditutup.
                    </p>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-center gap-2">
                        <CheckCircle2 class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Stok fisik di POS tetap aman dan tidak akan berubah ganda.</span>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="isMergeModalOpen = false"
                        :disabled="isMerging"
                        class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="confirmMergeBatches"
                        :disabled="isMerging"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-xs transition active:scale-98 cursor-pointer disabled:opacity-50"
                    >
                        <GitMerge class="w-4 h-4" />
                        <span v-if="isMerging">Menggabungkan...</span>
                        <span v-else>Ya, Gabungkan Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: TAMBAH / EDIT JAJAN KONSINYASI (KATALOG) -->
        <!-- ============================================================= -->
        <div v-if="isProductModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-slate-200 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold">
                            <Utensils class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">
                                {{ editingProduct ? 'Edit Menu Jajan Konsinyasi' : 'Daftarkan Jajan Konsinyasi Baru' }}
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Data ini tersimpan di katalog kasir tanpa harus menunggu ada titipan masuk.
                            </p>
                        </div>
                    </div>
                    <button @click="isProductModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitConsignmentProduct" class="p-6 space-y-4">
                    <!-- Nama Jajan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Jajan / Makanan <span class="text-rose-500">*</span></label>
                        <input 
                            v-model="productForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Risol Mayo Rogout, Arem-arem..."
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Kategori & Penitip Default -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Kategori</label>
                            <select 
                                v-model="productForm.category_id"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            >
                                <option value="">-- Pilih Kategori --</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Penitip Utama (Opsional)</label>
                            <select 
                                v-model="productForm.consignor_id"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            >
                                <option value="">-- Bebas / Siapa Saja --</option>
                                <option v-for="c in consignors" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Barcode / SKU Opsional -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Barcode POS (Opsional)</label>
                        <input 
                            v-model="productForm.barcode"
                            type="text"
                            placeholder="Scan atau ketik kode barcode jika ada"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Pricing Grid -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                        <span class="text-xs font-black text-slate-700 uppercase tracking-wider block">Harga & Margin Kantin:</span>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Setor (Kulakan) <span class="text-rose-500">*</span></label>
                                <input 
                                    v-model="productForm.cost_price"
                                    type="number"
                                    min="0"
                                    required
                                    placeholder="Rp"
                                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Jual POS <span class="text-rose-500">*</span></label>
                                <input 
                                    v-model="productForm.price_retail"
                                    type="number"
                                    min="0"
                                    required
                                    placeholder="Rp"
                                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-black text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Harga Karyawan</label>
                                <input 
                                    v-model="productForm.price_employee"
                                    type="number"
                                    min="0"
                                    placeholder="Rp"
                                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-amber-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Real-time Margin Calculation -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200 text-xs font-bold">
                            <span class="text-slate-600">Laba Bersih Kantin:</span>
                            <div class="text-right">
                                <span class="text-emerald-600 font-black text-sm">
                                    {{ formatRupiah((productForm.price_retail || 0) - (productForm.cost_price || 0)) }}
                                </span>
                                <span class="text-[11px] text-slate-400 ml-1">
                                    ({{ Number(productForm.price_retail || 0) > 0 ? Math.round(((productForm.price_retail - productForm.cost_price) / productForm.price_retail) * 100) : 0 }}%)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isProductModalOpen = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit"
                            :disabled="productForm.processing"
                            class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-xs disabled:opacity-50 cursor-pointer"
                        >
                            <span v-if="productForm.processing">Menyimpan...</span>
                            <span v-else>{{ editingProduct ? 'Simpan Perubahan' : 'Daftarkan Jajan' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: HAPUS JAJAN DARI KATALOG -->
        <!-- ============================================================= -->
        <div v-if="isDeleteProductModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl border border-rose-100 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 bg-gradient-to-r from-rose-50 to-orange-50 border-b border-rose-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-xs">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Hapus Jajan Konsinyasi</h3>
                            <p class="text-xs text-rose-700 font-medium">Konfirmasi penghapusan dari katalog.</p>
                        </div>
                    </div>
                    <button @click="isDeleteProductModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-3">
                    <p class="text-sm text-slate-700">
                        Apakah Anda yakin ingin menghapus jajan <strong>{{ productToDelete?.name }}</strong> dari katalog konsinyasi?
                    </p>
                    <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 border border-slate-200">
                        Catatan: Jika jajan ini sudah memiliki riwayat transaksi penjualan di POS, sistem akan secara otomatis menonaktifkan status konsinyasinya untuk menjaga integritas laporan keuangan.
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="isDeleteProductModalOpen = false"
                        :disabled="isDeletingProduct"
                        class="px-4 py-2 bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                    >
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="confirmDeleteProduct"
                        :disabled="isDeletingProduct"
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-xs disabled:opacity-50 cursor-pointer"
                    >
                        <span v-if="isDeletingProduct">Menghapus...</span>
                        <span v-else>Ya, Hapus Jajan</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: HITUNG & BAYAR SORE (SETTLEMENT) -->
        <!-- ============================================================= -->
        <div v-if="isSettleModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[92vh] my-auto">
                <!-- Header (Sticky top) -->
                <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-emerald-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                            <Wallet class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900">Hitung & Bayar Titipan (Pelunasan Sore)</h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Penitip: <strong class="text-slate-900">{{ selectedBatchForSettle?.consignor?.name }}</strong> 
                                • No: <span class="font-mono text-emerald-800 font-bold">{{ selectedBatchForSettle?.batch_number }}</span>
                            </p>
                        </div>
                    </div>
                    <button @click="isSettleModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 cursor-pointer transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitSettle" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                    <!-- Scrollable Body -->
                    <div class="p-4 sm:p-6 space-y-5 overflow-y-auto flex-1 min-h-0">
                    <div class="bg-emerald-50/90 border border-emerald-200 p-3.5 rounded-xl text-xs text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-start sm:items-center gap-2.5">
                            <Sparkles class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5 sm:mt-0" />
                            <div>
                                <strong class="text-emerald-900">Otomatis Terhitung dari Penjualan POS:</strong>
                                <span class="text-slate-600 block sm:inline sm:ml-1">Sisa jajan sudah otomatis diisi dari sisa stok kasir hari ini. Silakan cocokkan dengan fisik kue di etalase.</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto">
                            <button 
                                type="button" 
                                @click="applyPosStockToAll"
                                class="px-2.5 py-1.5 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-[11px] rounded-lg transition cursor-pointer shadow-2xs"
                                title="Reset sisa fisik sesuai stok yang tersisa di POS kasir"
                            >
                                Sesuai Stok POS
                            </button>
                            <button 
                                type="button" 
                                @click="setAllSoldOut"
                                class="px-2.5 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 font-bold text-[11px] rounded-lg transition cursor-pointer shadow-2xs"
                                title="Set semua sisa jajan menjadi 0 (terjual habis)"
                            >
                                Habis Semua (Sisa 0)
                            </button>
                        </div>
                    </div>

                    <!-- Items Calculation Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <div class="max-h-[340px] overflow-y-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="sticky top-0 z-10">
                                    <tr class="bg-slate-100 text-slate-700 font-bold text-xs uppercase tracking-wider border-b border-slate-200">
                                        <th class="py-3 px-3.5 bg-slate-100">Nama Jajan</th>
                                        <th class="py-3 px-3 text-center bg-slate-100">Dititip</th>
                                        <th class="py-3 px-3 text-center bg-amber-100 text-amber-950 font-black">Sisa Fisik (Retur)</th>
                                        <th class="py-3 px-3 text-center bg-slate-100">Terjual</th>
                                        <th class="py-3 px-3 text-right bg-slate-100">Harga Setor</th>
                                        <th class="py-3 px-3.5 text-right bg-slate-100">Hak Penitip</th>
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
                                        <div class="text-[10px] text-slate-500 mt-0.5 flex items-center justify-center gap-1">
                                            <span>Stok POS: <strong class="text-slate-700">{{ item.current_stock }}</strong></span>
                                            <button 
                                                v-if="item.qty_returned !== Math.min(item.qty_dropped, Math.max(0, Number(item.current_stock || 0)))"
                                                type="button"
                                                @click="item.qty_returned = Math.min(item.qty_dropped, Math.max(0, Number(item.current_stock || 0)))"
                                                class="text-[9px] text-emerald-700 underline hover:text-emerald-900 cursor-pointer font-bold"
                                                title="Samakan dengan sisa stok POS"
                                            >
                                                (reset)
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center font-black text-blue-700">
                                        {{ Math.max(0, item.qty_dropped - Math.min(item.qty_dropped, (Number(item.qty_returned) || 0))) }} pcs
                                    </td>
                                    <td class="py-3 px-3 text-right font-medium text-slate-600">
                                        {{ formatRupiah(item.cost_price) }}
                                    </td>
                                    <td class="py-3 px-3.5 text-right font-black text-emerald-700">
                                        {{ formatRupiah(Math.max(0, item.qty_dropped - Math.min(item.qty_dropped, (Number(item.qty_returned) || 0))) * item.cost_price) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <!-- Settle Summary Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-center">
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase">Total Terjual</div>
                            <div class="text-xl font-black text-blue-600 mt-0.5">{{ settleCalculations.totalSold }} pcs</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase">Total Retur / Sisa</div>
                            <div class="text-xl font-black text-amber-700 mt-0.5">{{ settleCalculations.totalReturned }} pcs</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase">Uang Dibayar ke Penitip</div>
                            <div class="text-xl font-black text-emerald-600 mt-0.5">{{ formatRupiah(settleCalculations.totalPayable) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-slate-500 uppercase">Laba Margin Kantin</div>
                            <div class="text-xl font-black text-purple-600 mt-0.5">{{ formatRupiah(settleCalculations.totalProfit) }}</div>
                        </div>
                    </div>

                    <!-- Payment Source Box & Notes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Ambil Kas Dari Kotak Uang (Cashbox)</label>
                            <select 
                                v-model="settleForm.cashbox_id"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            >
                                <option v-for="box in cashboxes" :key="box.id" :value="box.id">
                                    {{ box.name }} (Saldo: {{ formatRupiah(box.balance) }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Pelunasan</label>
                            <input 
                                v-model="settleForm.notes"
                                type="text"
                                placeholder="Contoh: Sudah lunas tunai sore ini"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    </div>

                    <!-- Sticky Footer Actions -->
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                        <button 
                            type="button" 
                            @click="isSettleModalOpen = false"
                            class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-sm rounded-xl transition cursor-pointer"
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
                    <button @click="isReceiptModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer">
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
                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button 
                            @click="printReceipt"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer active:scale-98"
                        >
                            <Printer class="w-4 h-4" />
                            <span>Cetak Struk Sekarang</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: MASTER PENITIP (TAMBAH / EDIT) -->
        <!-- ============================================================= -->
        <div v-if="isConsignorModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 overflow-hidden my-8">
                <div class="p-5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <UserPlus class="w-5 h-5 text-amber-600" />
                        <h3 class="font-black text-slate-900 text-base">
                            {{ editingConsignor ? 'Edit Data Penitip' : 'Tambah Mitra Penitip Baru' }}
                        </h3>
                    </div>
                    <button @click="isConsignorModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitConsignor" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Nama Penitip / Toko <span class="text-rose-500">*</span></label>
                        <input 
                            v-model="consignorForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Bu Umi, Snack Barokah..."
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">No. WhatsApp / HP</label>
                        <input 
                            v-model="consignorForm.phone"
                            type="text"
                            placeholder="Contoh: 08123456789"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Catatan Tambahan</label>
                        <textarea 
                            v-model="consignorForm.notes"
                            rows="2"
                            placeholder="Alamat, rekening transfer bank, atau info lainnya"
                            class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <div v-if="editingConsignor" class="flex items-center gap-2 pt-2">
                        <input 
                            type="checkbox" 
                            id="is_active_consignor" 
                            v-model="consignorForm.is_active"
                            class="w-4 h-4 text-amber-600 rounded"
                        />
                        <label for="is_active_consignor" class="text-xs font-bold text-slate-700">Status Penitip Aktif</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                        <button 
                            type="button" 
                            @click="isConsignorModalOpen = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
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
    
        <!-- ============================================================= -->
        <!-- MODAL: KONFIRMASI HAPUS / BATALKAN TITIPAN KESELURUHAN BATCH -->
        <!-- ============================================================= -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-rose-100 overflow-hidden my-8 animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="p-5 bg-gradient-to-r from-rose-50 via-rose-100/60 to-amber-50 border-b border-rose-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-sm">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Batalkan & Hapus Seluruh Sesi Titipan</h3>
                            <p class="text-xs text-rose-700 font-semibold">
                                No. Batch: <span class="font-mono font-bold">{{ selectedBatchForDelete?.batch_number }}</span>
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="closeDeleteModal" 
                        class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-white/60 transition cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <!-- Info Penitip -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Penitip Jajan</span>
                            <span class="text-sm font-black text-slate-900">{{ selectedBatchForDelete?.consignor?.name }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Waktu Dititip</span>
                            <span class="text-xs font-bold text-slate-700">{{ formatDate(selectedBatchForDelete?.dropoff_date) }}</span>
                        </div>
                    </div>

                    <!-- Detail Daftar Jajan & Dampak Stok -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Daftar Jajan yang Akan Ditarik:</span>
                            <span class="text-[11px] font-bold text-slate-400">{{ selectedBatchForDelete?.items?.length || 0 }} Menu Jajan</span>
                        </div>
                        <div class="bg-slate-50 rounded-xl border border-slate-200/70 divide-y divide-slate-200/60 max-h-48 overflow-y-auto">
                            <div 
                                v-for="item in (selectedBatchForDelete?.items || [])" 
                                :key="item.id" 
                                class="p-3 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <div class="font-black text-slate-900">{{ item.product?.name || item.name || "Jajan" }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        Dititip: <span class="font-bold text-slate-700">{{ item.qty_dropped }} pcs</span>
                                        • Harga Setor: {{ formatRupiah(item.cost_price) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-black bg-rose-100 text-rose-700 border border-rose-200">
                                        -{{ item.qty_dropped }} pcs dari POS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Notice Box -->
                    <div class="p-3.5 bg-amber-50/90 border border-amber-200 rounded-xl flex items-start gap-2.5">
                        <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div class="text-xs text-amber-900 leading-relaxed">
                            <strong>Perhatian:</strong> Seluruh sesi titipan ini akan dihapus permanen dari sistem, dan stok fisik seluruh jajan di atas akan ditarik kembali dari kasir POS.
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="p-4 bg-slate-50 border-t border-slate-200/80 flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="closeDeleteModal"
                        :disabled="isDeleting"
                        class="px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl shadow-xs transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="confirmDeleteBatch"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-sm hover:shadow transition disabled:opacity-50 cursor-pointer active:scale-98"
                    >
                        <Trash2 class="w-4 h-4" />
                        <span v-if="isDeleting">Menghapus & Mengembalikan Stok...</span>
                        <span v-else>Ya, Hapus & Tarik Stok</span>
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
