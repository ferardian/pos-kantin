<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    ClipboardList, CheckCircle2, Truck, Package, Clock, 
    Printer, ArrowRight, User, Phone, MapPin, Calendar, 
    AlertCircle, FileText, Check, Sparkles, Receipt, Eye, X,
    Trash2, RotateCcw, Ban, AlertTriangle, HelpCircle,
    Edit3, Plus, Minus, Search
} from 'lucide-vue-next';

const props = defineProps({
    orders: Array,
    products: {
        type: Array,
        default: () => [],
    },
    customers: {
        type: Array,
        default: () => [],
    },
    user: Object,
    settings: Object,
});

const activeStatus = ref('all');
const isPickingModalOpen = ref(false);
const isSuratJalanModalOpen = ref(false);
const isInvoiceModalOpen = ref(false);
const selectedOrder = ref(null);
const selectedInvoicePrintFormat = ref('dot_matrix'); // 'dot_matrix' or 'a4'

// Custom Modern Confirmation Modals State
const isPickingConfirmModalOpen = ref(false);
const uncheckedPickingItems = ref([]);
const checkedPickingIds = ref([]);

const isGenericConfirmModalOpen = ref(false);
const genericConfirmData = ref({
    title: '',
    subtitle: '',
    message: '',
    itemName: '',
    itemDetail: '',
    confirmText: 'Lanjutkan',
    type: 'warning',
    onConfirm: () => {}
});

const isWarningToastOpen = ref(false);
const warningToastMessage = ref('');

const showToastWarning = (msg) => {
    warningToastMessage.value = msg;
    isWarningToastOpen.value = true;
    setTimeout(() => {
        isWarningToastOpen.value = false;
    }, 3500);
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'pending': return '1. Perlu Konfirmasi';
        case 'confirmed':
        case 'processing': return '2. Disiapkan Gudang';
        case 'packed':
        case 'ready': return '3. Siap Kirim (Packing Selesai)';
        case 'delivered': return '4. Dalam Pengiriman';
        case 'completed': return 'Selesai (Faktur Terbit)';
        case 'cancelled': return 'Dibatalkan';
        default: return status || 'Status Tidak Diketahui';
    }
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'pending': return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'confirmed':
        case 'processing': return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'packed':
        case 'ready': return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'delivered': return 'bg-indigo-50 text-indigo-800 border-indigo-200';
        case 'completed': return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'cancelled': return 'bg-rose-50 text-rose-800 border-rose-200';
        default: return 'bg-slate-50 text-slate-800 border-slate-200';
    }
};

const filteredOrders = computed(() => {
    if (activeStatus.value === 'all') return props.orders;
    if (activeStatus.value === 'confirmed') return props.orders.filter(o => o.status === 'confirmed' || o.status === 'processing');
    if (activeStatus.value === 'packed') return props.orders.filter(o => o.status === 'packed' || o.status === 'ready');
    return props.orders.filter(o => o.status === activeStatus.value);
});

// Edit Order Modal State & Handlers
const isEditOrderModalOpen = ref(false);
const editingOrder = ref(null);
const editOrderForm = useForm({
    customer_id: null,
    delivery_date: '',
    payment_type: 'tempo',
    notes: '',
    items: [],
});

const addProductSearch = ref('');
const isAddProductDropdownOpen = ref(false);
const selectedProductToAdd = ref(null);
const selectedUnitToAdd = ref(null);
const addQty = ref(1);

const getUnitPrice = (unit, tier) => {
    if (!unit) return 0;
    if (tier === 'tukang') return Number(unit.price_technician || unit.price_retail || 0);
    if (tier === 'kontraktor') return Number(unit.price_contractor || unit.price_retail || 0);
    if (tier === 'grosir') return Number(unit.price_wholesale || unit.price_retail || 0);
    return Number(unit.price_retail || 0);
};

const getTierLabel = (tier) => {
    switch (tier) {
        case 'eceran': return 'Retail';
        case 'tukang': return 'Bronze';
        case 'kontraktor': return 'Gold';
        case 'grosir': return 'Diamond';
        default: return tier || 'Retail';
    }
};

const getTierBadgeClass = (tier) => {
    switch (tier) {
        case 'eceran': return 'bg-slate-100 text-slate-800 border border-slate-200';
        case 'tukang': return 'bg-amber-100 text-amber-900 border border-amber-300';
        case 'kontraktor': return 'bg-amber-400/20 text-amber-950 border border-amber-400';
        case 'grosir': return 'bg-sky-100 text-sky-900 border border-sky-300';
        default: return 'bg-slate-100 text-slate-800 border border-slate-200';
    }
};

const openEditOrderModal = (order) => {
    editingOrder.value = order;
    editOrderForm.customer_id = order.customer_id;
    editOrderForm.delivery_date = order.delivery_date ? order.delivery_date.split('T')[0] : '';
    editOrderForm.payment_type = order.payment_type || 'tempo';
    editOrderForm.notes = order.notes || '';

    const cust = (props.customers || []).find(c => c.id === order.customer_id) || order.customer;
    const custTier = cust?.tier || 'eceran';

    editOrderForm.items = (order.items || []).map(it => {
        const prod = (props.products || []).find(p => p.id === it.product_id) || it.product || { id: it.product_id, name: 'Barang', units: [] };
        const unit = (prod.units || []).find(u => u.id === it.product_unit_id) || it.unit || { id: it.product_unit_id, unit_name: 'Pcs' };

        return {
            id: it.id,
            product_id: it.product_id,
            product: prod,
            product_unit_id: it.product_unit_id,
            unit: unit,
            qty: Number(it.qty),
            unit_price: Number(it.unit_price),
            subtotal: Number(it.subtotal),
            status: it.status || 'fulfilled',
        };
    });

    addProductSearch.value = '';
    selectedProductToAdd.value = null;
    selectedUnitToAdd.value = null;
    addQty.value = 1;
    isAddProductDropdownOpen.value = false;
    isEditOrderModalOpen.value = true;
};

const updateEditItemQty = (index, delta) => {
    const item = editOrderForm.items[index];
    if (!item) return;
    const newQty = Number((item.qty + delta).toFixed(2));
    if (newQty <= 0) {
        removeEditItem(index);
    } else {
        item.qty = newQty;
        item.subtotal = Math.round(item.qty * item.unit_price);
    }
};

const onEditItemQtyInput = (index) => {
    const item = editOrderForm.items[index];
    if (!item) return;
    if (item.qty < 0) item.qty = 0;
    item.subtotal = Math.round((item.qty || 0) * item.unit_price);
};

const onEditItemPriceInput = (index) => {
    const item = editOrderForm.items[index];
    if (!item) return;
    if (item.unit_price < 0) item.unit_price = 0;
    item.subtotal = Math.round((item.qty || 0) * item.unit_price);
};

const changeEditItemUnit = (index, newUnitId) => {
    const item = editOrderForm.items[index];
    if (!item || !item.product) return;
    const newUnit = (item.product.units || []).find(u => u.id === Number(newUnitId));
    if (newUnit) {
        item.unit = newUnit;
        item.product_unit_id = newUnit.id;
        const cust = (props.customers || []).find(c => c.id === editOrderForm.customer_id) || editingOrder.value?.customer;
        const tier = cust?.tier || 'eceran';
        item.unit_price = getUnitPrice(newUnit, tier);
        item.subtotal = Math.round(item.qty * item.unit_price);
    }
};

const removeEditItem = (index) => {
    editOrderForm.items.splice(index, 1);
};

const filteredProductsForAdd = computed(() => {
    const q = addProductSearch.value.toLowerCase().trim();
    if (!q) return [];
    return (props.products || []).filter(p => {
        return (p.name && p.name.toLowerCase().includes(q)) ||
               (p.barcode && p.barcode.toLowerCase().includes(q)) ||
               (p.sku && p.sku.toLowerCase().includes(q));
    }).slice(0, 10);
});

const selectProductToAdd = (product) => {
    selectedProductToAdd.value = product;
    selectedUnitToAdd.value = (product.units || []).find(u => u.is_base_unit) || (product.units || [])[0];
    addProductSearch.value = product.name;
    isAddProductDropdownOpen.value = false;
};

const confirmAddProductToOrder = () => {
    if (!selectedProductToAdd.value || !selectedUnitToAdd.value) return;
    const cust = (props.customers || []).find(c => c.id === editOrderForm.customer_id) || editingOrder.value?.customer;
    const tier = cust?.tier || 'eceran';
    const price = getUnitPrice(selectedUnitToAdd.value, tier);
    const qty = Number(addQty.value) || 1;

    const existing = editOrderForm.items.find(it => it.product_id === selectedProductToAdd.value.id && it.product_unit_id === selectedUnitToAdd.value.id);
    if (existing) {
        existing.qty = Number((existing.qty + qty).toFixed(2));
        existing.subtotal = Math.round(existing.qty * existing.unit_price);
    } else {
        editOrderForm.items.push({
            product_id: selectedProductToAdd.value.id,
            product: selectedProductToAdd.value,
            product_unit_id: selectedUnitToAdd.value.id,
            unit: selectedUnitToAdd.value,
            qty: qty,
            unit_price: price,
            subtotal: Math.round(qty * price),
            status: 'fulfilled',
        });
    }

    selectedProductToAdd.value = null;
    selectedUnitToAdd.value = null;
    addProductSearch.value = '';
    addQty.value = 1;
};

const editOrderTotalAmount = computed(() => {
    return editOrderForm.items.reduce((sum, it) => sum + (Number(it.subtotal) || 0), 0);
});

const submitEditOrder = () => {
    if (!editingOrder.value) return;
    if (editOrderForm.items.length === 0) {
        showToastWarning('Pesanan harus memiliki minimal 1 barang!');
        return;
    }

    editOrderForm.items = editOrderForm.items.map(it => ({
        product_id: it.product_id,
        product_unit_id: it.product_unit_id,
        qty: Number(it.qty),
        unit_price: Number(it.unit_price),
        subtotal: Math.round(Number(it.qty) * Number(it.unit_price)),
    }));

    editOrderForm.put(`/sales-orders/${editingOrder.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditOrderModalOpen.value = false;
            editingOrder.value = null;
            showToastWarning('Pesanan berhasil diperbarui!');
        },
        onError: () => {
            showToastWarning('Gagal memperbarui pesanan. Pastikan data lengkap.');
        }
    });
};

const confirmOrder = (orderId) => {
    router.post(`/sales-orders/${orderId}/confirm`, {}, {
        preserveScroll: true,
    });
};

const updateStatus = (orderId, status) => {
    router.post(`/sales-orders/${orderId}/status`, { status }, {
        preserveScroll: true,
    });
};

const checkedItems = ref({});

const activeOrderItems = computed(() => {
    return (selectedOrder.value?.items || []).filter(it => it.status !== 'out_of_stock');
});

const isAllItemsChecked = computed(() => {
    const items = activeOrderItems.value;
    if (!items.length) return false;
    return items.every(it => checkedItems.value[it.id]);
});

const checkedItemsCount = computed(() => {
    if (!selectedOrder.value?.items) return 0;
    return selectedOrder.value.items.filter(it => checkedItems.value[it.id]).length;
});

const openPickingModal = (order) => {
    selectedOrder.value = order;
    checkedItems.value = {};
    order.items?.forEach(it => {
        if (it.status === 'out_of_stock') {
            checkedItems.value[it.id] = false;
        } else {
            checkedItems.value[it.id] = true;
        }
    });
    isPickingModalOpen.value = true;
};

const toggleCheckItem = (itemId) => {
    checkedItems.value[itemId] = !checkedItems.value[itemId];
};

const checkAllItems = () => {
    if (selectedOrder.value?.items) {
        selectedOrder.value.items.forEach(it => {
            if (it.status !== 'out_of_stock') {
                checkedItems.value[it.id] = true;
            }
        });
    }
};

const uncheckAllItems = () => {
    checkedItems.value = {};
};

const originalOrderTotal = computed(() => {
    return (selectedOrder.value?.items || []).filter(it => it.status !== 'out_of_stock').reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
});

const uncheckedItemsTotal = computed(() => {
    return uncheckedPickingItems.value.reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
});

const newEstimatedOrderTotal = computed(() => {
    const checked = (selectedOrder.value?.items || []).filter(it => checkedPickingIds.value.includes(it.id));
    return checked.reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
});

const confirmFinishPicking = () => {
    if (!selectedOrder.value) return;

    const allItems = selectedOrder.value.items || [];
    const checkedIds = allItems.filter(it => checkedItems.value[it.id]).map(it => it.id);
    const unchecked = allItems.filter(it => !checkedItems.value[it.id]);

    if (checkedIds.length === 0) {
        showToastWarning('Minimal harus ada 1 barang yang dicentang siap kirim!');
        return;
    }

    if (unchecked.length > 0) {
        uncheckedPickingItems.value = unchecked;
        checkedPickingIds.value = checkedIds;
        isPickingConfirmModalOpen.value = true;
        return;
    }

    executePickingFinish(checkedIds);
};

const executePickingFinish = (checkedIds = null) => {
    const ids = checkedIds || checkedPickingIds.value;
    router.post(`/sales-orders/${selectedOrder.value.id}/status`, {
        status: 'packed',
        checked_item_ids: ids
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isPickingConfirmModalOpen.value = false;
            isPickingModalOpen.value = false;
        }
    });
};

const openGenericConfirm = (data) => {
    genericConfirmData.value = {
        title: data.title || 'Konfirmasi',
        subtitle: data.subtitle || '',
        message: data.message || '',
        itemName: data.itemName || '',
        itemDetail: data.itemDetail || '',
        confirmText: data.confirmText || 'Lanjutkan',
        type: data.type || 'warning',
        onConfirm: data.onConfirm || (() => {})
    };
    isGenericConfirmModalOpen.value = true;
};

const toggleItemStatus = (orderId, item) => {
    const isCurrentlyOOS = item.status === 'out_of_stock';
    
    openGenericConfirm({
        title: isCurrentlyOOS ? 'Pulihkan Barang ke Pesanan' : 'Tandai Stok Fisik Kosong',
        subtitle: isCurrentlyOOS ? 'Kembalikan barang ini ke pesanan & nota' : 'Keluarkan sementara dari tagihan nota',
        type: isCurrentlyOOS ? 'success' : 'warning',
        itemName: item.product?.name,
        itemDetail: `${item.qty} ${item.unit?.unit_name || 'Pcs'} • ${formatRupiah(item.subtotal)}`,
        message: isCurrentlyOOS 
            ? 'Barang ini akan dimasukkan kembali ke pesanan, stok dibooking ulang, dan total tagihan nota bertambah.' 
            : 'Barang ini akan ditandai stok kosong, stok booking dilepas, dan otomatis TIDAK ditagihkan di nota.',
        confirmText: isCurrentlyOOS ? 'Ya, Pulihkan Barang' : 'Ya, Tandai Kosong',
        onConfirm: () => {
            router.post(`/sales-orders/${orderId}/items/${item.id}/toggle-status`, {
                status: isCurrentlyOOS ? 'fulfilled' : 'out_of_stock'
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    if (isCurrentlyOOS) {
                        checkedItems.value[item.id] = true;
                    } else {
                        checkedItems.value[item.id] = false;
                    }
                    isGenericConfirmModalOpen.value = false;
                }
            });
        }
    });
};

const removeItemFromOrder = (orderId, item) => {
    openGenericConfirm({
        title: 'Hapus Barang dari Pesanan',
        subtitle: 'Tindakan ini menghapus baris barang',
        type: 'danger',
        itemName: item.product?.name,
        itemDetail: `${item.qty} ${item.unit?.unit_name || 'Pcs'} • ${formatRupiah(item.subtotal)}`,
        message: 'Apakah Anda yakin ingin menghapus barang ini secara permanen dari pesanan? Total tagihan nota akan disesuaikan.',
        confirmText: 'Ya, Hapus Barang',
        onConfirm: () => {
            router.delete(`/sales-orders/${orderId}/items/${item.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (checkedItems.value[item.id] !== undefined) {
                        delete checkedItems.value[item.id];
                    }
                    isGenericConfirmModalOpen.value = false;
                }
            });
        }
    });
};

watch(() => props.orders, (newOrders) => {
    if (selectedOrder.value && newOrders) {
        const updated = newOrders.find(o => o.id === selectedOrder.value.id);
        if (updated) {
            selectedOrder.value = updated;
        }
    }
}, { deep: true });

const openSuratJalanModal = (order) => {
    selectedOrder.value = order;
    isSuratJalanModalOpen.value = true;
};

const openInvoiceModal = (order) => {
    selectedOrder.value = order;
    isInvoiceModalOpen.value = true;
};

const formatTextWithBreaks = (text) => {
    if (!text) return '';
    return String(text)
        .replace(/\\r\\n/g, '<br>')
        .replace(/\\n/g, '<br>')
        .replace(/\r\n/g, '<br>')
        .replace(/\n/g, '<br>');
};

const buildPickingSlipHtml = (order) => {
    const rawDate = order.order_date ? new Date(order.order_date).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID');
    const items = order.items || [];
    const storeLogo = props.settings?.store_logo || '/pos-kantin/images/logo.png';
    const storeName = props.settings?.store_name || 'TRISNA JAYA LISTRIK';
    const storeAddress = props.settings?.store_address || 'Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890';
    
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
                <td style="padding: 8px 6px; text-align: center;">
                    <div style="width: 20px; height: 20px; border: 2px solid #64748b; border-radius: 4px; margin: 0 auto;"></div>
                </td>
            </tr>
        `;
    });

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Picking List SO ${order.so_number}</title>
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
                            <img src="${storeLogo}" alt="Logo" style="width: 52px; height: 52px; object-fit: contain;" />
                            <div>
                                <h1 style="margin: 0; font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">${storeName}</h1>
                                <div style="font-size: 10px; font-weight: bold; color: #b45309; text-transform: uppercase;">Gudang & Logistik Distribusi</div>
                                <div style="font-size: 10px; color: #475569; margin-top: 1px;">${storeAddress}</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <span style="display: inline-block; background: #0f172a; color: #fff; font-weight: 900; font-size: 10px; padding: 4px 12px; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
                            PICKING LIST GUDANG
                        </span>
                        <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 6px;">No. SO: <strong>${order.so_number}</strong></div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">Tanggal: ${rawDate}</div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; font-size: 11px;">
                <tr>
                    <td style="width: 55%; padding: 10px 14px; vertical-align: top;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Pelanggan / Proyek:</div>
                        <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${order.customer?.name || 'Pelanggan Proyek'}</div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">${order.customer?.address || '-'}</div>
                    </td>
                    <td style="width: 45%; padding: 10px 14px; vertical-align: top; border-left: 1px solid #e2e8f0;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Sales Representative:</div>
                        <div style="font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 2px;">${order.sales?.name || '-'}</div>
                        ${order.notes ? `<div style="font-size: 10px; font-style: italic; color: #92400e; margin-top: 4px;">Catatan: "${formatTextWithBreaks(order.notes)}"</div>` : ''}
                    </td>
                </tr>
            </table>

            <div style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center; border-right: 1px solid #cbd5e1;">NO</th>
                            <th style="border-right: 1px solid #cbd5e1;">NAMA BARANG & SKU</th>
                            <th style="width: 130px; text-align: center; border-right: 1px solid #cbd5e1;">KUANTITI / SATUAN</th>
                            <th style="width: 90px; text-align: center;">CEK FISIK</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>

            <table style="width: 100%; margin-top: 30px; text-align: center; font-size: 11px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Petugas Gudang,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 20px; padding-right: 20px;">
                            ( .................................... )
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Kurir / Driver,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 20px; padding-right: 20px;">
                            ( .................................... )
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    `;
};

const buildSuratJalanHtml = (order) => {
    const rawDate = order.delivery_date ? new Date(order.delivery_date).toLocaleDateString('id-ID') : (order.order_date ? new Date(order.order_date).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID'));
    const items = (order.items || []).filter(it => it.status !== 'out_of_stock');
    const custName = order.customer?.name || 'Pelanggan Proyek';
    const storeLogo = props.settings?.store_logo || '/pos-kantin/images/logo.png';
    const storeName = props.settings?.store_name || 'TRISNA JAYA LISTRIK';
    const storeAddress = props.settings?.store_address || 'Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890';

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
                    Kondisi Baik
                </td>
            </tr>
        `;
    });

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Surat Jalan SJ-${order.so_number}</title>
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
                            <img src="${storeLogo}" alt="Logo" style="width: 52px; height: 52px; object-fit: contain;" />
                            <div>
                                <h1 style="margin: 0; font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">${storeName}</h1>
                                <div style="font-size: 10px; font-weight: bold; color: #2563eb;">Distributor & Suplier Peralatan Listrik Gedung / Proyek</div>
                                <div style="font-size: 10px; color: #475569; margin-top: 1px;">${storeAddress}</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <span style="display: inline-block; background: #1e3a8a; color: #fff; font-weight: 900; font-size: 11px; padding: 4px 14px; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
                            SURAT JALAN
                        </span>
                        <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 6px;">No: <strong>SJ-${order.so_number}</strong></div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">Tanggal: ${rawDate}</div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; font-size: 11px;">
                <tr>
                    <td style="width: 55%; padding: 10px 14px; vertical-align: top;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Kepada Yth:</div>
                        <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${custName}</div>
                        <div style="font-size: 10px; color: #334155; font-weight: 600; margin-top: 2px;">Alamat Tujuan: ${order.customer?.address || '-'}</div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 1px;">No. Telepon / PIC: ${order.customer?.phone || '-'}</div>
                    </td>
                    <td style="width: 45%; padding: 10px 14px; vertical-align: top; border-left: 1px solid #e2e8f0;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Keterangan Pengiriman:</div>
                        <div style="font-size: 11px; color: #334155; margin-top: 2px;">Sales: <strong>${order.sales?.name || '-'}</strong></div>
                        <div style="font-size: 11px; color: #334155; margin-top: 2px;">Tipe Order: <strong>Pesanan Proyek (SO)</strong></div>
                        ${order.notes ? `<div style="font-size: 10px; font-style: italic; color: #92400e; background: #fef3c7; border: 1px solid #fde68a; padding: 4px 8px; border-radius: 6px; margin-top: 4px;">Catatan: "${formatTextWithBreaks(order.notes)}"</div>` : ''}
                    </td>
                </tr>
            </table>

            <div style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center; border-right: 1px solid #cbd5e1;">NO</th>
                            <th style="border-right: 1px solid #cbd5e1;">DESKRIPSI BARANG & SPESIFIKASI</th>
                            <th style="width: 140px; text-align: center; border-right: 1px solid #cbd5e1;">JUMLAH / QTY</th>
                            <th style="width: 120px; text-align: center;">KETERANGAN</th>
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
                        <div style="font-weight: bold; color: #334155;">Kepala Gudang,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( .................................... )
                        </div>
                    </td>
                    <td style="width: 33.3%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Supir / Pengirim,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( .................................... )
                        </div>
                    </td>
                    <td style="width: 33.3%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Penerima / Proyek,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 12px; padding-right: 12px;">
                            ( ${custName} )
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    `;
};

const buildSalesOrderInvoiceHtml = (order) => {
    const rawDate = order.order_date ? new Date(order.order_date).toLocaleDateString('id-ID') : new Date().toLocaleDateString('id-ID');
    const items = (order.items || []).filter(it => it.status !== 'out_of_stock');
    const custName = order.customer?.name || 'Pelanggan Proyek';
    const storeLogo = props.settings?.store_logo || '/pos-kantin/images/logo.png';
    const storeName = props.settings?.store_name || 'TRISNA JAYA LISTRIK';
    const storeAddress = props.settings?.store_address || 'Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890';

    let rowsHtml = '';
    items.forEach((it, idx) => {
        rowsHtml += `
            <tr style="border-bottom: 1px solid #cbd5e1;">
                <td style="padding: 8px 6px; text-align: center; border-right: 1px solid #cbd5e1; font-weight: bold;">${idx + 1}</td>
                <td style="padding: 8px 10px; border-right: 1px solid #cbd5e1;">
                    <div style="font-weight: 900; font-size: 11px; color: #0f172a;">${it.product?.name || 'Produk Listrik'}</div>
                    <div style="font-size: 9px; font-family: monospace; color: #64748b; margin-top: 2px;">SKU: ${it.product?.sku || '-'}</div>
                </td>
                <td style="padding: 8px 6px; text-align: center; border-right: 1px solid #cbd5e1; font-weight: bold; font-size: 11px;">
                    ${it.qty} ${it.unit?.unit_name || 'Pcs'}
                </td>
                <td style="padding: 8px 8px; text-align: right; border-right: 1px solid #cbd5e1; font-family: monospace; font-size: 11px;">
                    ${formatRupiah(it.unit_price)}
                </td>
                <td style="padding: 8px 8px; text-align: right; font-family: monospace; font-weight: 900; font-size: 11px; color: #0f172a;">
                    ${formatRupiah(it.subtotal)}
                </td>
            </tr>
        `;
    });

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Faktur INV-${order.so_number}</title>
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
                            <img src="${storeLogo}" alt="Logo" style="width: 52px; height: 52px; object-fit: contain;" />
                            <div>
                                <h1 style="margin: 0; font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">${storeName}</h1>
                                <div style="font-size: 10px; font-weight: bold; color: #047857;">Distributor & Perlengkapan Listrik Proyek</div>
                                <div style="font-size: 10px; color: #475569; margin-top: 1px;">${storeAddress}</div>
                            </div>
                        </div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <span style="display: inline-block; background: #065f46; color: #fff; font-weight: 900; font-size: 11px; padding: 4px 14px; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
                            FAKTUR PENJUALAN
                        </span>
                        <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 6px;">No: <strong>INV-${order.so_number}</strong></div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">Tanggal: ${rawDate}</div>
                        <div style="margin-top: 4px;">
                            <span style="font-size: 9px; font-weight: 900; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; border: 1px solid #fde68a;">
                                PEMBAYARAN: ${order.payment_type || 'TEMPO'}
                            </span>
                        </div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; font-size: 11px;">
                <tr>
                    <td style="width: 55%; padding: 10px 14px; vertical-align: top;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Ditagihkan Kepada:</div>
                        <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${custName}</div>
                        <div style="font-size: 10px; color: #475569; margin-top: 2px;">Alamat: ${order.customer?.address || '-'}</div>
                        ${order.customer?.phone ? `<div style="font-size: 10px; color: #64748b; margin-top: 1px;">Telp: ${order.customer.phone}</div>` : ''}
                    </td>
                    <td style="width: 45%; padding: 10px 14px; vertical-align: top; border-left: 1px solid #e2e8f0;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b;">Keterangan Tagihan:</div>
                        <div style="font-size: 11px; color: #334155; margin-top: 2px;">Sales Representative: <strong>${order.sales?.name || '-'}</strong></div>
                        <div style="font-size: 11px; color: #059669; font-weight: bold; margin-top: 2px;">Status Faktur: Resmi Terbit</div>
                        ${order.notes ? `<div style="font-size: 10px; font-style: italic; color: #475569; margin-top: 4px;">Catatan: "${formatTextWithBreaks(order.notes)}"</div>` : ''}
                    </td>
                </tr>
            </table>

            <div style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 16px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center; border-right: 1px solid #cbd5e1;">NO</th>
                            <th style="border-right: 1px solid #cbd5e1;">DESKRIPSI BARANG LISTRIK</th>
                            <th style="width: 100px; text-align: center; border-right: 1px solid #cbd5e1;">KUANTITI</th>
                            <th style="width: 120px; text-align: right; border-right: 1px solid #cbd5e1;">HARGA SATUAN</th>
                            <th style="width: 130px; text-align: right;">SUBTOTAL (RP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>

            <table style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; font-size: 11px;">
                <tr>
                    <td style="width: 60%; padding: 12px 14px; vertical-align: top;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;">Rekening Pembayaran Resmi Toko:</div>
                        <div style="font-size: 11px; color: #334155; margin-top: 4px;">&bull; Bank BCA: <strong>8830-123-456</strong> a.n. TRISNA JAYA LISTRIK</div>
                        <div style="font-size: 11px; color: #334155; margin-top: 2px;">&bull; Bank Mandiri: <strong>137-00-9876543-2</strong> a.n. TRISNA JAYA LISTRIK</div>
                    </td>
                    <td style="width: 40%; padding: 12px 14px; vertical-align: middle; text-align: right;">
                        <div style="font-size: 9px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">TOTAL TAGIHAN:</div>
                        <div style="font-size: 18px; font-weight: 900; color: #0f172a; margin-top: 2px; font-family: monospace;">
                            ${formatRupiah(order.total_amount)}
                        </div>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-top: 30px; text-align: center; font-size: 11px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Penerima Tagihan,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 20px; padding-right: 20px;">
                            ( ${custName} )
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-weight: bold; color: #334155;">Hormat Kami,</div>
                        <div style="height: 55px;"></div>
                        <div style="font-weight: bold; border-top: 1px solid #64748b; display: inline-block; padding-top: 4px; padding-left: 20px; padding-right: 20px;">
                            ( TRISNA JAYA LISTRIK )
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    `;
};

const numberToWords = (num) => {
    if (!num || num === 0) return 'Nol Rupiah';
    const units = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    
    function terbilang(n) {
        n = Math.floor(n);
        if (n < 12) return units[n];
        if (n < 20) return terbilang(n - 10) + ' Belas';
        if (n < 100) return terbilang(Math.floor(n / 10)) + ' Puluh ' + terbilang(n % 10);
        if (n < 200) return 'Seratus ' + terbilang(n - 100);
        if (n < 1000) return terbilang(Math.floor(n / 100)) + ' Ratus ' + terbilang(n % 100);
        if (n < 2000) return 'Seribu ' + terbilang(n - 1000);
        if (n < 1000000) return terbilang(Math.floor(n / 1000)) + ' Ribu ' + terbilang(n % 1000);
        if (n < 1000000000) return terbilang(Math.floor(n / 1000000)) + ' Juta ' + terbilang(n % 1000000);
        if (n < 1000000000000) return terbilang(Math.floor(n / 1000000000)) + ' Miliar ' + terbilang(n % 1000000000);
        return terbilang(Math.floor(n / 1000000000000)) + ' Triliun ' + terbilang(n % 1000000000000);
    }
    
    return (terbilang(num).trim().replace(/\s+/g, ' ') + ' Rupiah');
};

const buildSalesOrderDotMatrixHtml = (order) => {
    const formatNumberClean = (num) => {
        return new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
    };

    const formatCleanDate = (dStr) => {
        if (!dStr) return new Date().toLocaleDateString('id-ID');
        const d = new Date(dStr);
        if (isNaN(d.getTime())) return dStr;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return `${d.getDate()}/${months[d.getMonth()]}/${d.getFullYear()}`;
    };

    const storeName = props.settings?.store_name || 'TRISNA JAYA LISTRIK';
    const storeAddress = props.settings?.store_address || 'Jl. Raya Pantura No. 99, Pekalongan';
    const storePhone = props.settings?.store_phone || '+62 815 7345 5951';
    const storeEmail = props.settings?.store_email || 'trisnajaya050@gmail.com';
    const storeLogo = props.settings?.store_logo || '/pos-kantin/images/logo.png';
    const bankInfo = props.settings?.bank_info || 'Bank : BCA\nNo. Rekening : 2501294511\nAtas Nama : YUNIAR DWI RAHMAWATI';

    const storeNameDisplay = (storeName && storeName.trim() !== '') ? storeName : 'TRISNA JAYA LISTRIK';
    const storeAddressDisplay = storeAddress || 'Jl. Raya Karanganyar, Kebonsari, Karangsari, Kab. Pekalongan';
    const storePhoneDisplay = storePhone || '+62 815-7345-5951';
    const storeEmailDisplay = storeEmail || 'trisnajaya050@gmail.com';

    const custName = order.customer?.name || 'Pelanggan Proyek';
    const custAddress = order.customer?.address || '-';
    const custPhone = order.customer?.phone || '-';
    const custEmail = order.customer?.email || '-';
    const salesName = order.sales?.name || props.user?.name || 'Sales Lapangan';
    const formattedDate = formatCleanDate(order.order_date || order.created_at || new Date());
    const invoiceNum = `INV-${order.so_number}`;
    const terbilangText = numberToWords(order.total_amount);

    let payMethodLabel = (order.payment_type || 'Tunai').toUpperCase();
    if (order.payment_type === 'tempo') payMethodLabel = `TEMPO ${order.due_date ? '(Jatuh Tempo: ' + order.due_date + ')' : ''}`;

    const items = (order.items || []).filter(it => it.status !== 'out_of_stock');
    const dotMatrixItemsHtml = items.map((it) => {
        const qtyStr = `${it.qty} ${it.unit?.unit_name || 'Pcs'}`;
        const priceStr = formatNumberClean(it.unit_price);
        const itemDiscount = (it.discount_amount || it.discount || 0);
        const discountStr = itemDiscount > 0 ? formatNumberClean(itemDiscount) : '-';
        const subtotalStr = formatNumberClean(it.subtotal);

        return `
            <tr style="border-bottom: 1px dashed #000; font-size: 9.5pt; line-height: 1.25;">
                <td style="padding: 3px 4px; text-align: left; font-weight: bold; font-family: 'Courier New', monospace;">${it.product?.name || 'Item'}</td>
                <td style="padding: 3px 4px; text-align: center; white-space: nowrap; font-weight: bold;">${qtyStr}</td>
                <td style="padding: 3px 4px; text-align: right; white-space: nowrap; font-weight: bold;">${priceStr}</td>
                <td style="padding: 3px 4px; text-align: right; white-space: nowrap; font-weight: bold;">${discountStr}</td>
                <td style="padding: 3px 4px; text-align: right; font-weight: 900; white-space: nowrap;">${subtotalStr}</td>
            </tr>
        `;
    }).join('');

    const bankInfoFormatted = bankInfo
        .replace(/\\n/g, '<br>')
        .replace(/\n/g, '<br>');

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Faktur ${invoiceNum}</title>
            <style>
                @page {
                    size: 215mm 139mm landscape;
                    margin: 0;
                }
                @media print {
                    html, body {
                        width: 100% !important;
                        max-width: 185mm !important;
                        margin: 0 auto !important;
                        padding: 4mm 0 0 0 !important;
                        overflow: visible !important;
                    }
                    tr {
                        page-break-inside: avoid !important;
                        break-inside: avoid !important;
                    }
                    thead {
                        display: table-row-group !important;
                    }
                }
                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body {
                    width: 100%;
                    max-width: 185mm;
                    margin: 0 auto;
                    padding: 4mm 0 0 0;
                    font-family: 'Courier New', Courier, 'Lucida Console', Monaco, monospace;
                    font-size: 9pt;
                    font-weight: 900;
                    color: #000000;
                    background: #ffffff;
                    line-height: 1.3;
                    -webkit-font-smoothing: none;
                    font-smooth: never;
                    text-rendering: optimizeSpeed;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }
                tr {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                }
                thead {
                    display: table-row-group !important;
                }
                td, th {
                    word-break: break-word;
                    overflow-wrap: break-word;
                    color: #000000;
                }
            </style>
        </head>
        <body>
            <!-- HEADER TOP -->
            <table style="width: 100%; table-layout: fixed; margin-bottom: 2px;">
                <tr>
                    <td style="width: 58%; vertical-align: top; padding-right: 8px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                ${storeLogo ? `
                                <td style="width: 48px; vertical-align: top; padding-right: 8px;">
                                    <img src="${storeLogo}" alt="Logo" style="width: 42px; height: 42px; object-fit: contain; filter: grayscale(100%) contrast(140%); display: block;" />
                                </td>
                                ` : ''}
                                <td style="vertical-align: top;">
                                    <div style="font-size: 12.5pt; font-weight: 900; letter-spacing: 0.5px; line-height: 1.1; text-transform: uppercase; color: #000;">
                                        ${storeNameDisplay}
                                    </div>
                                    <div style="font-size: 8pt; font-weight: bold; color: #000; margin-top: 2px; line-height: 1.2;">
                                        ${storeAddressDisplay}
                                    </div>
                                    <div style="font-size: 8pt; font-weight: bold; color: #000; line-height: 1.2;">
                                        Telp/HP: ${storePhoneDisplay} | Email: ${storeEmailDisplay}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 42%; vertical-align: top; text-align: right;">
                        <div style="font-size: 14pt; font-weight: 900; letter-spacing: 1.5px; line-height: 1; text-transform: uppercase; margin-bottom: 3px; color: #000;">
                            FAKTUR PENJUALAN
                        </div>
                        <table style="font-size: 9pt; font-weight: bold; margin-left: auto; width: 100%; table-layout: fixed; line-height: 1.25;">
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000; width: 45%;">No. Faktur :</td>
                                <td style="font-weight: 900; text-align: left; width: 55%; font-family: 'Courier New', monospace;">${invoiceNum}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Tanggal :</td>
                                <td style="font-weight: bold; text-align: left;">${formattedDate}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Pembayaran :</td>
                                <td style="font-weight: bold; text-align: left;">${payMethodLabel}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Sales :</td>
                                <td style="font-weight: bold; text-align: left; text-transform: uppercase;">${salesName}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- DIVIDER LINE -->
            <div style="border-bottom: 2px solid #000; margin: 3px 0;"></div>

            <!-- SUB-HEADER: CUSTOMER & BIG TOTAL -->
            <table style="width: 100%; table-layout: fixed; margin-bottom: 3px;">
                <tr>
                    <td style="width: 58%; vertical-align: top; font-size: 9pt; line-height: 1.3; padding-right: 6px;">
                        <div style="color: #000; font-size: 8.5pt;">Kepada Yth:</div>
                        <div style="font-size: 11pt; font-weight: 900; text-transform: uppercase; color: #000;">${custName}</div>
                        <div>Alamat    : ${custAddress}</div>
                        <div>No. HP/WA : ${custPhone}</div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <div style="font-size: 8.5pt; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #000;">
                            TOTAL TAGIHAN :
                        </div>
                        <div style="font-size: 16pt; font-weight: 900; color: #000; letter-spacing: 0.5px; margin-top: 1px; font-family: 'Courier New', monospace;">
                            Rp${formatNumberClean(order.total_amount)}
                        </div>
                    </td>
                </tr>
            </table>

            <!-- ITEMS TABLE -->
            <table style="width: 100%; table-layout: fixed; font-size: 9.5pt;">
                <thead>
                    <tr style="border-top: 2px solid #000; border-bottom: 2px solid #000; background: transparent;">
                        <th style="padding: 3px 4px; text-align: left; font-weight: 900; width: 44%;">NAMA BARANG</th>
                        <th style="padding: 3px 4px; text-align: center; font-weight: 900; width: 14%;">QTY</th>
                        <th style="padding: 3px 4px; text-align: right; font-weight: 900; width: 14%;">HARGA</th>
                        <th style="padding: 3px 4px; text-align: right; font-weight: 900; width: 10%;">DISKON</th>
                        <th style="padding: 3px 4px; text-align: right; font-weight: 900; width: 18%;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    ${dotMatrixItemsHtml}
                </tbody>
            </table>

            <!-- FOOTER SUMMARY & SIGNATURES -->
            <div style="border-top: 2px solid #000; margin-top: 3px; padding-top: 3px;">
                <table style="width: 100%; table-layout: fixed; font-size: 8.5pt; line-height: 1.25;">
                    <tr>
                        <td style="width: 58%; vertical-align: top; padding-right: 8px;">
                            <div style="font-style: italic; font-size: 9pt; font-weight: 900; margin-bottom: 3px;">
                                Terbilang: ${terbilangText}
                            </div>
                            <div style="font-weight: bold; margin-top: 2px;">Keterangan / Pembayaran:</div>
                            <div style="line-height: 1.25; font-weight: bold;">${bankInfoFormatted}</div>
                            <div style="font-size: 7.5pt; margin-top: 3px; color: #000;">* Barang yang sudah dibeli tidak dapat ditukar/dikembalikan tanpa nota resmi.</div>
                        </td>
                        <td style="width: 42%; vertical-align: top;">
                            <table style="width: 100%; table-layout: fixed; font-size: 9pt; line-height: 1.25;">
                                <tr>
                                    <td style="text-align: right; padding-right: 6px; width: 55%;">Subtotal :</td>
                                    <td style="text-align: right; font-weight: bold; width: 45%;">${formatNumberClean(order.total_amount)}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: right; padding-right: 6px;">Diskon :</td>
                                    <td style="text-align: right; font-weight: bold;">-</td>
                                </tr>
                                <tr style="border-top: 1.5px solid #000; font-weight: 900; font-size: 11pt;">
                                    <td style="text-align: right; padding-right: 6px; padding-top: 2px;">TOTAL :</td>
                                    <td style="text-align: right; padding-top: 2px; font-family: 'Courier New', monospace;">Rp${formatNumberClean(order.total_amount)}</td>
                                </tr>
                            </table>

                            <table style="width: 100%; table-layout: fixed; margin-top: 6px; text-align: center; font-size: 8.5pt;">
                                <tr>
                                    <td style="width: 50%; vertical-align: top;">
                                        <div>Hormat Kami,</div>
                                        <div style="height: 26px;"></div>
                                        <div style="font-weight: 900;">( ${storeNameDisplay} )</div>
                                    </td>
                                    <td style="width: 50%; vertical-align: top;">
                                        <div>Penerima,</div>
                                        <div style="height: 26px;"></div>
                                        <div style="font-weight: 900;">( ${custName} )</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
        </html>
    `;
};

const printDocument = (type = 'invoice') => {
    if (!selectedOrder.value) return;

    let html = '';
    if (type === 'printable-picking' || type === 'picking') {
        html = buildPickingSlipHtml(selectedOrder.value);
    } else if (type === 'printable-suratjalan' || type === 'suratjalan') {
        html = buildSuratJalanHtml(selectedOrder.value);
    } else {
        if (selectedInvoicePrintFormat.value === 'dot_matrix') {
            html = buildSalesOrderDotMatrixHtml(selectedOrder.value);
        } else {
            html = buildSalesOrderInvoiceHtml(selectedOrder.value);
        }
    }

    let iframe = document.getElementById('so-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'so-print-iframe';
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

    const triggerPrint = () => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (err) {
            console.error('Iframe print error, falling back to window:', err);
            const win = window.open('', '_blank');
            if (win) {
                win.document.write(html);
                win.document.close();
                win.focus();
                win.print();
            }
        }
    };

    const images = iframe.contentDocument ? iframe.contentDocument.images : [];
    if (images && images.length > 0) {
        let loadedCount = 0;
        const totalImages = images.length;
        let fired = false;

        const checkAllLoaded = () => {
            if (fired) return;
            loadedCount++;
            if (loadedCount >= totalImages) {
                fired = true;
                setTimeout(triggerPrint, 150);
            }
        };

        for (let i = 0; i < totalImages; i++) {
            if (images[i].complete) {
                loadedCount++;
            } else {
                images[i].addEventListener('load', checkAllLoaded);
                images[i].addEventListener('error', checkAllLoaded);
            }
        }

        if (loadedCount >= totalImages) {
            fired = true;
            setTimeout(triggerPrint, 200);
        } else {
            setTimeout(() => {
                if (!fired) {
                    fired = true;
                    triggerPrint();
                }
            }, 1000);
        }
    } else {
        setTimeout(triggerPrint, 250);
    }
};
</script>

<template>
    <MainLayout>
        <Head title="Antrean Pesanan Sales (SO)" />
        <div class="p-6 w-full space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 flex items-center gap-2.5">
                        <ClipboardList class="w-6 h-6 text-amber-600" />
                        <span>Antrean Pesanan Masuk (Sales Order)</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kelola pesanan dari sales di luar toko: konfirmasi booking stok, instruksi gudang, surat jalan, dan faktur penagihan.
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3.5 py-2 rounded-2xl shadow-xs">
                        Total: <strong class="text-slate-900">{{ orders.length }} Pesanan</strong>
                    </span>
                </div>
            </div>

            <!-- Status Filter Tabs (Full Width Clean Layout) -->
            <div class="flex items-center gap-1.5 p-1.5 bg-white border border-slate-200 rounded-2xl shadow-xs overflow-x-auto">
                <button 
                    v-for="st in [
                        { id: 'all', label: 'Semua Pesanan', count: (orders || []).length },
                        { id: 'pending', label: 'Perlu Konfirmasi', count: (orders || []).filter(o => o.status === 'pending').length },
                        { id: 'confirmed', label: 'Disiapkan Gudang', count: (orders || []).filter(o => o.status === 'confirmed' || o.status === 'processing').length },
                        { id: 'packed', label: 'Siap Kirim', count: (orders || []).filter(o => o.status === 'packed' || o.status === 'ready').length },
                        { id: 'delivered', label: 'Dalam Pengiriman', count: (orders || []).filter(o => o.status === 'delivered').length },
                        { id: 'completed', label: 'Selesai (Faktur)', count: (orders || []).filter(o => o.status === 'completed').length }
                    ]" 
                    :key="st.id"
                    @click="activeStatus = st.id"
                    :class="activeStatus === st.id ? 'bg-slate-900 text-white font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold'"
                    class="px-3.5 py-2 rounded-xl text-xs transition cursor-pointer flex items-center gap-2 shrink-0"
                >
                    <span>{{ st.label }}</span>
                    <span 
                        v-if="st.count > 0"
                        :class="activeStatus === st.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
                        class="px-1.5 py-0.5 rounded-md text-[10px] font-mono font-bold"
                    >
                        {{ st.count }}
                    </span>
                </button>
            </div>

            <!-- Orders Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div 
                    v-for="order in filteredOrders" 
                    :key="order.id"
                    class="bg-white border border-slate-200 rounded-3xl p-5 space-y-4 shadow-xs hover:border-slate-300 transition flex flex-col justify-between"
                >
                    <div class="space-y-3">
                        <!-- Card Header -->
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm font-black text-slate-900">{{ order.so_number }}</span>
                                    <span 
                                        :class="getStatusBadgeClass(order.status)"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase border"
                                    >
                                        {{ getStatusLabel(order.status) }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-medium mt-0.5 flex items-center gap-1.5">
                                    <User class="w-3.5 h-3.5 text-slate-400" />
                                    <span>Sales: <strong class="text-slate-800">{{ order.sales?.name }}</strong></span>
                                </p>
                            </div>

                            <div class="text-right">
                                <span class="text-base font-black text-slate-900">{{ formatRupiah(order.total_amount) }}</span>
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">{{ order.payment_type }}</p>
                            </div>
                        </div>

                        <!-- Customer Info Box -->
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-slate-900">{{ order.customer?.name }}</p>
                                <span class="text-[9px] uppercase px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded font-black">
                                    {{ order.customer?.tier }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 flex items-center gap-1">
                                <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                                <span class="truncate">{{ order.customer?.address }}</span>
                            </p>
                            <p v-if="order.notes" class="text-[11px] text-amber-700 bg-amber-50/80 p-1.5 rounded-lg italic">
                                "{{ order.notes }}"
                            </p>
                        </div>

                        <!-- Items Breakdown -->
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Rincian Barang Dipesan:</p>
                            <div class="space-y-1">
                                <div 
                                    v-for="it in order.items" 
                                    :key="it.id"
                                    class="flex items-center justify-between text-xs text-slate-700 group py-0.5"
                                    :class="it.status === 'out_of_stock' ? 'opacity-60' : ''"
                                >
                                    <div class="flex items-center gap-1.5 truncate">
                                        <span :class="it.status === 'out_of_stock' ? 'line-through text-slate-400 font-medium' : 'font-bold text-slate-900'">{{ it.qty }} {{ it.unit?.unit_name }}</span>
                                        <span class="text-slate-400">&bull;</span>
                                        <span :class="it.status === 'out_of_stock' ? 'line-through text-slate-400' : 'text-slate-800'" class="truncate">{{ it.product?.name }}</span>
                                        <span v-if="it.status === 'out_of_stock'" class="text-[9px] font-black uppercase px-1.5 py-0.2 bg-rose-100 text-rose-700 rounded border border-rose-200 shrink-0">Kosong</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span :class="it.status === 'out_of_stock' ? 'line-through text-slate-400' : 'font-semibold text-slate-900'">{{ formatRupiah(it.subtotal) }}</span>
                                        
                                        <!-- If out_of_stock, allow quick restore if not completed -->
                                        <button 
                                            v-if="it.status === 'out_of_stock' && !['completed', 'cancelled'].includes(order.status)"
                                            type="button"
                                            @click.stop="toggleItemStatus(order.id, it)"
                                            title="Barang ini ditandai kosong. Klik untuk PULIHKAN jika stok ada"
                                            class="p-0.5 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded transition cursor-pointer"
                                        >
                                            <RotateCcw class="w-3.5 h-3.5" />
                                        </button>
                                        
                                        <!-- If fulfilled, allow marking out_of_stock on hover if still in preparation -->
                                        <button 
                                            v-else-if="['pending', 'confirmed', 'processing', 'packed'].includes(order.status)"
                                            type="button"
                                            @click.stop="toggleItemStatus(order.id, it)"
                                            title="Tandai barang ini kosong / batal"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition cursor-pointer"
                                        >
                                            <Ban class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Quick Edit Item from Order -->
                                        <button 
                                            v-if="!['completed', 'cancelled'].includes(order.status)"
                                            type="button"
                                            @click.stop="openEditOrderModal(order)"
                                            title="Edit rincian barang pesanan ini"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition cursor-pointer"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Actions & Documents Buttons -->
                    <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                        <!-- Document Output Buttons Available for this order -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <!-- Tombol Edit Pesanan (Kurang, Tambah, Hapus Barang) -->
                            <button 
                                v-if="!['completed', 'cancelled'].includes(order.status)"
                                @click="openEditOrderModal(order)"
                                class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold rounded-xl text-[11px] flex items-center gap-1 border border-amber-300/80 cursor-pointer transition active:scale-95 shadow-2xs"
                                title="Edit pesanan: tambah, kurangi, atau hapus barang"
                            >
                                <Edit3 class="w-3.5 h-3.5 text-amber-600" />
                                <span>Edit Pesanan</span>
                            </button>

                            <button 
                                @click="openPickingModal(order)"
                                class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-[11px] flex items-center gap-1 cursor-pointer transition"
                                title="Lihat / Cetak Daftar Ambil Barang Gudang"
                            >
                                <ClipboardList class="w-3.5 h-3.5 text-slate-600" />
                                <span>Picking List</span>
                            </button>

                            <button 
                                v-if="['confirmed', 'processing', 'packed', 'ready', 'delivered', 'completed'].includes(order.status)"
                                @click="openSuratJalanModal(order)"
                                class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold rounded-xl text-[11px] flex items-center gap-1 border border-blue-200 cursor-pointer transition"
                                title="Lihat / Cetak Dokumen Surat Jalan Resmi Pengiriman"
                            >
                                <Truck class="w-3.5 h-3.5 text-blue-600" />
                                <span>Surat Jalan</span>
                            </button>

                            <button 
                                v-if="['confirmed', 'processing', 'packed', 'ready', 'delivered', 'completed'].includes(order.status)"
                                @click="openInvoiceModal(order)"
                                class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 font-bold rounded-xl text-[11px] flex items-center gap-1 border border-emerald-200 cursor-pointer transition"
                                title="Lihat / Cetak Faktur Penagihan Resmi Proyek"
                            >
                                <Receipt class="w-3.5 h-3.5 text-emerald-700" />
                                <span>Faktur (Invoice)</span>
                            </button>
                        </div>

                        <!-- State Transition Action Buttons (Linear Step-by-Step) -->
                        <div class="flex items-center gap-2">
                            <!-- STEP 1: Konfirmasi -->
                            <template v-if="order.status === 'pending'">
                                <button 
                                    @click="confirmOrder(order.id)"
                                    class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
                                >
                                    <Check class="w-3.5 h-3.5 text-amber-400" />
                                    <span>1. Konfirmasi & Kunci Stok</span>
                                </button>
                            </template>

                            <!-- STEP 2: Disiapkan Gudang -> Selesai Packing -->
                            <template v-else-if="order.status === 'confirmed' || order.status === 'processing'">
                                <button 
                                    @click="updateStatus(order.id, 'packed')"
                                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
                                >
                                    <Package class="w-3.5 h-3.5" />
                                    <span>2. Selesai Packing (Siap Kirim)</span>
                                </button>
                            </template>

                            <!-- STEP 3: Siap Kirim -> Kirim Pesanan (dengan opsi Diambil di Toko) -->
                            <template v-else-if="order.status === 'packed' || order.status === 'ready'">
                                <button 
                                    @click="updateStatus(order.id, 'delivered')"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
                                >
                                    <Truck class="w-3.5 h-3.5" />
                                    <span>3. Kirim Pesanan</span>
                                </button>

                                <button 
                                    @click="updateStatus(order.id, 'completed')"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-[11px] flex items-center gap-1 transition cursor-pointer"
                                    title="Klik jika barang diambil sendiri oleh pembeli di toko tanpa diantar kurir"
                                >
                                    <CheckCircle2 class="w-3 h-3 text-slate-500" />
                                    <span>Diambil di Toko</span>
                                </button>
                            </template>

                            <!-- STEP 4: Dalam Pengiriman -> Selesaikan & Terbitkan Faktur -->
                            <template v-else-if="order.status === 'delivered'">
                                <button 
                                    @click="updateStatus(order.id, 'completed')"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs active:scale-95"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-200" />
                                    <span>4. Selesai & Terbitkan Faktur</span>
                                </button>
                            </template>

                            <!-- STATUS SELESAI -->
                            <template v-else-if="order.status === 'completed'">
                                <span class="px-3 py-1.5 text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-xl font-bold text-xs flex items-center gap-1.5">
                                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Faktur Telah Terbit</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL EDIT PESANAN (Sales Order) -->
        <div v-if="isEditOrderModalOpen && editingOrder" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white text-slate-900 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh] border border-slate-200">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/80">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-xs">
                            <Edit3 class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm sm:text-base font-black text-slate-900">Edit Pesanan: {{ editingOrder.so_number }}</h3>
                                <span :class="[getStatusBadgeClass(editingOrder.status), 'text-[10px] font-black uppercase px-2 py-0.5 rounded-full border']">
                                    {{ getStatusLabel(editingOrder.status) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Sales: <span class="font-bold text-slate-700">{{ editingOrder.sales?.name || 'Sales' }}</span> &bull; 
                                Pelanggan: <span class="font-bold text-slate-700">{{ editingOrder.customer?.name || 'Toko' }}</span>
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="isEditOrderModalOpen = false" 
                        class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 rounded-xl transition cursor-pointer"
                        title="Tutup Modal"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5">
                    <!-- Ringkasan Info Header Order -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Pelanggan & Strata</span>
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-900 truncate">{{ editingOrder.customer?.name }}</span>
                                <span :class="[getTierBadgeClass(editingOrder.customer?.tier), 'text-[9px] font-black uppercase px-1.5 py-0.5 rounded border']">
                                    {{ getTierLabel(editingOrder.customer?.tier) }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Tanggal Pengiriman</span>
                            <input 
                                type="date" 
                                v-model="editOrderForm.delivery_date"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Tipe Pembayaran</span>
                            <select 
                                v-model="editOrderForm.payment_type"
                                class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-semibold text-slate-800 focus:outline-none focus:border-amber-500"
                            >
                                <option value="tempo">Tempo (Kredit)</option>
                                <option value="cash">Cash (Tunai)</option>
                                <option value="transfer">Transfer Bank</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3 pt-2 border-t border-slate-200/60">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Catatan Pesanan</span>
                            <input 
                                type="text" 
                                v-model="editOrderForm.notes"
                                placeholder="Tulis instruksi khusus (misal: kirim pagi, packing kardus aman)..."
                                class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-amber-500"
                            />
                        </div>
                    </div>

                    <!-- Tambah Barang Baru ke Pesanan -->
                    <div class="p-3.5 bg-amber-50/50 border border-amber-200/80 rounded-2xl space-y-2.5">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-amber-900">
                            <Plus class="w-4 h-4 text-amber-600" />
                            <span>Tambah Barang Baru ke Pesanan:</span>
                        </div>

                        <!-- Search Bar with Live Suggestions Dropdown -->
                        <div class="relative">
                            <div class="relative">
                                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input 
                                    v-model="addProductSearch"
                                    @focus="isAddProductDropdownOpen = true"
                                    type="text"
                                    placeholder="Ketik nama produk / barcode / SKU untuk mencari..."
                                    class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition font-medium"
                                />
                            </div>

                            <!-- Suggestion List -->
                            <div 
                                v-if="isAddProductDropdownOpen && filteredProductsForAdd.length > 0"
                                class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-2xl shadow-xl z-20 max-h-56 overflow-y-auto divide-y divide-slate-100"
                            >
                                <button 
                                    v-for="p in filteredProductsForAdd"
                                    :key="p.id"
                                    type="button"
                                    @click="selectProductToAdd(p)"
                                    class="w-full p-2.5 text-left hover:bg-amber-50/60 flex items-center justify-between transition cursor-pointer"
                                >
                                    <div class="min-w-0 pr-2">
                                        <p class="text-xs font-bold text-slate-900 truncate">{{ p.name }}</p>
                                        <p class="text-[10px] text-slate-400">Barcode: {{ p.barcode || '-' }} &bull; Sedia: {{ p.stock_actual }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-black text-amber-700">
                                            {{ formatRupiah(getUnitPrice((p.units || [])[0], editingOrder?.customer?.tier || 'eceran')) }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Selected Item Ready to Add Configuration -->
                        <div v-if="selectedProductToAdd" class="p-3 bg-white border border-amber-300 rounded-xl flex flex-wrap items-center justify-between gap-3 shadow-2xs">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-900 truncate">{{ selectedProductToAdd.name }}</p>
                                <p class="text-[11px] font-semibold text-amber-700">
                                    Harga: {{ formatRupiah(getUnitPrice(selectedUnitToAdd, editingOrder?.customer?.tier || 'eceran')) }} / {{ selectedUnitToAdd?.unit_name }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Unit Selector -->
                                <select 
                                    v-if="(selectedProductToAdd.units || []).length > 1"
                                    v-model="selectedUnitToAdd"
                                    class="text-xs font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-slate-50 focus:outline-none focus:border-amber-500"
                                >
                                    <option v-for="u in selectedProductToAdd.units" :key="u.id" :value="u">
                                        {{ u.unit_name }} ({{ formatRupiah(getUnitPrice(u, editingOrder?.customer?.tier || 'eceran')) }})
                                    </option>
                                </select>
                                <span v-else class="text-xs font-bold text-slate-600 px-2 py-1 bg-slate-100 rounded-lg">
                                    {{ selectedUnitToAdd?.unit_name }}
                                </span>

                                <!-- Qty Selector -->
                                <div class="flex items-center border border-slate-200 rounded-lg bg-slate-50 overflow-hidden">
                                    <button 
                                        @click="addQty = Math.max(1, addQty - 1)" 
                                        type="button"
                                        class="px-2 py-1 hover:bg-slate-200 text-slate-700 font-bold transition cursor-pointer"
                                    >
                                        <Minus class="w-3 h-3" />
                                    </button>
                                    <input 
                                        v-model.number="addQty" 
                                        type="number" 
                                        min="1" 
                                        class="w-12 text-center text-xs font-bold bg-white py-1 focus:outline-none" 
                                    />
                                    <button 
                                        @click="addQty += 1" 
                                        type="button"
                                        class="px-2 py-1 hover:bg-slate-200 text-slate-700 font-bold transition cursor-pointer"
                                    >
                                        <Plus class="w-3 h-3" />
                                    </button>
                                </div>

                                <!-- Add Button -->
                                <button 
                                    @click="confirmAddProductToOrder"
                                    type="button"
                                    class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg flex items-center gap-1 shadow-xs transition cursor-pointer active:scale-95"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Tambah</span>
                                </button>

                                <button 
                                    @click="selectedProductToAdd = null; addProductSearch = '';" 
                                    type="button" 
                                    class="p-1.5 text-slate-400 hover:text-slate-600 cursor-pointer"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Barang Dipesan (Kurang, Tambah, Hapus) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Rincian Barang Dipesan ({{ editOrderForm.items.length }} Item):
                            </h4>
                            <span class="text-[11px] text-slate-400">Gunakan tombol - / + untuk ubah jumlah, atau ikon tong sampah untuk hapus barang</span>
                        </div>

                        <div v-if="editOrderForm.items.length === 0" class="p-8 text-center bg-slate-50 border border-dashed border-slate-200 rounded-2xl text-slate-400 text-xs">
                            Tidak ada barang dalam pesanan ini. Silakan cari & tambah barang di atas.
                        </div>

                        <div v-else class="space-y-2">
                            <div 
                                v-for="(it, idx) in editOrderForm.items" 
                                :key="it.id || idx"
                                class="p-3 bg-white border border-slate-200 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 shadow-2xs hover:border-slate-300 transition"
                            >
                                <!-- Info Barang -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900 truncate">{{ it.product?.name || 'Barang' }}</span>
                                        <span v-if="it.status === 'out_of_stock'" class="text-[9px] font-black uppercase px-1.5 py-0.2 bg-rose-100 text-rose-700 rounded border border-rose-200 shrink-0">
                                            Kosong
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                                        <!-- Unit Dropdown jika multi-satuan -->
                                        <select 
                                            v-if="(it.product?.units || []).length > 1"
                                            :value="it.product_unit_id"
                                            @change="changeEditItemUnit(idx, $event.target.value)"
                                            class="font-bold text-slate-700 bg-slate-100 border border-slate-200 rounded px-1.5 py-0.5 text-[10px] focus:outline-none"
                                        >
                                            <option v-for="u in it.product.units" :key="u.id" :value="u.id">
                                                {{ u.unit_name }}
                                            </option>
                                        </select>
                                        <span v-else class="font-bold text-slate-700">{{ it.unit?.unit_name || 'Pcs' }}</span>
                                        <span>&bull;</span>
                                        <span>Harga:</span>
                                        <input 
                                            v-model.number="it.unit_price"
                                            @input="onEditItemPriceInput(idx)"
                                            type="number"
                                            min="0"
                                            class="w-20 font-semibold text-slate-800 bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5 text-[10px] text-right focus:outline-none focus:border-amber-500"
                                        />
                                    </div>
                                </div>

                                <!-- Pengatur Qty & Subtotal -->
                                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                    <!-- Minus & Plus Button Controls -->
                                    <div class="flex items-center border border-slate-200 rounded-xl bg-slate-50 overflow-hidden shadow-2xs">
                                        <button 
                                            @click="updateEditItemQty(idx, -1)"
                                            type="button"
                                            class="w-7 h-7 flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-200 transition cursor-pointer font-black"
                                            title="Kurangi Qty"
                                        >
                                            <Minus class="w-3.5 h-3.5" />
                                        </button>
                                        <input 
                                            v-model.number="it.qty"
                                            @input="onEditItemQtyInput(idx)"
                                            type="number"
                                            min="0.01"
                                            step="any"
                                            class="w-14 text-center text-xs font-black text-slate-900 bg-white py-1 focus:outline-none"
                                        />
                                        <button 
                                            @click="updateEditItemQty(idx, 1)"
                                            type="button"
                                            class="w-7 h-7 flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-200 transition cursor-pointer font-black"
                                            title="Tambah Qty"
                                        >
                                            <Plus class="w-3.5 h-3.5" />
                                        </button>
                                    </div>

                                    <!-- Subtotal -->
                                    <div class="text-right min-w-[85px]">
                                        <span class="text-xs font-black text-slate-900">{{ formatRupiah(it.subtotal) }}</span>
                                    </div>

                                    <!-- Tombol Hapus Baris Barang -->
                                    <button 
                                        @click="removeEditItem(idx)"
                                        type="button"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer"
                                        title="Hapus barang ini dari pesanan"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 sm:p-5 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="text-left">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Tagihan Pesanan</span>
                            <span class="text-base sm:text-lg font-black text-amber-600">{{ formatRupiah(editOrderTotalAmount) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button 
                            @click="isEditOrderModalOpen = false"
                            type="button"
                            class="flex-1 sm:flex-none px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold text-xs rounded-xl hover:bg-slate-100 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            @click="submitEditOrder"
                            :disabled="editOrderForm.processing || editOrderForm.items.length === 0"
                            type="button"
                            class="flex-1 sm:flex-none px-5 py-2.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-slate-950 font-black text-xs rounded-xl shadow-xs transition cursor-pointer flex items-center justify-center gap-1.5 active:scale-95"
                        >
                            <Check class="w-4 h-4" />
                            <span>{{ editOrderForm.processing ? 'Menyimpan...' : 'Simpan Perubahan Pesanan' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DOKUMEN 1: Picking List Gudang (Daftar Ambil Barang - Interactive Checklist & Output) -->
        <div v-if="isPickingModalOpen && selectedOrder" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white text-slate-900 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-4 border-b flex justify-between items-center no-print bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="w-5 h-5 text-amber-600" />
                        <div>
                            <h3 class="text-sm font-black uppercase text-slate-900">Petunjuk Ambil Barang (Picking Slip)</h3>
                            <p class="text-[10px] text-slate-500">Cek fisik barang di rak gudang sebelum dipacking & dikirim</p>
                        </div>
                    </div>
                    <button @click="isPickingModalOpen = false" class="text-slate-400 text-xs font-bold cursor-pointer hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Interactive Checklist Progress Banner for Warehouse Staff -->
                <div class="px-6 py-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between no-print text-xs">
                    <div class="flex items-center gap-2">
                        <span 
                            :class="isAllItemsChecked ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-slate-950'"
                            class="px-2 py-0.5 rounded-full font-black text-[10px] uppercase shadow-xs flex items-center gap-1"
                        >
                            <CheckCircle2 v-if="isAllItemsChecked" class="w-3 h-3" />
                            <Clock v-else class="w-3 h-3" />
                            <span>{{ checkedItemsCount }} / {{ selectedOrder?.items?.length || 0 }} Siap</span>
                        </span>
                        <span class="text-slate-700 font-semibold text-[11px]">
                            {{ isAllItemsChecked ? 'Semua barang telah dicek fisik & ready!' : 'Centang kotak pada barang yang sudah diambil.' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isAllItemsChecked"
                            @click="checkAllItems" 
                            type="button"
                            class="text-[11px] font-bold text-amber-700 hover:text-amber-900 underline cursor-pointer"
                        >
                            Centang Semua
                        </button>
                        <button 
                            v-else
                            @click="uncheckAllItems" 
                            type="button"
                            class="text-[11px] font-bold text-slate-500 hover:text-slate-700 underline cursor-pointer"
                        >
                            Reset Centang
                        </button>
                    </div>
                </div>

                <div id="printable-picking" class="p-8 space-y-6 flex-1 overflow-y-auto bg-white">
                    <div class="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-14 h-14 object-contain" />
                            <div>
                                <h2 class="text-lg font-black tracking-tight text-slate-950 uppercase">TRISNA JAYA LISTRIK</h2>
                                <p class="text-xs text-slate-600">Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890</p>
                                <p class="text-[11px] text-amber-700 font-bold uppercase">Gudang & Logistik Distribusi</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs uppercase tracking-widest px-3 py-1 bg-slate-900 text-white font-black rounded-lg">
                                PICKING LIST GUDANG
                            </span>
                            <p class="text-xs text-slate-700 font-mono font-bold mt-2">No. SO: <strong>{{ selectedOrder?.so_number }}</strong></p>
                            <p class="text-xs text-slate-600">Tgl: {{ new Date(selectedOrder?.order_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs">
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Pelanggan / Proyek:</p>
                            <p class="text-sm font-black text-slate-900">{{ selectedOrder?.customer?.name }}</p>
                            <p class="text-slate-600 mt-0.5">{{ selectedOrder?.customer?.address }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Sales Representative:</p>
                            <p class="text-sm font-bold text-slate-900">{{ selectedOrder?.sales?.name }}</p>
                            <p v-if="selectedOrder?.notes" class="text-amber-800 font-medium italic mt-1">"{{ selectedOrder?.notes }}"</p>
                        </div>
                    </div>

                    <table class="w-full text-xs text-left border-collapse border border-slate-300">
                        <thead>
                            <tr class="bg-slate-100 font-black border-b border-slate-300 text-[11px]">
                                <th class="py-2.5 px-3 border-r border-slate-300 w-10 text-center">No</th>
                                <th class="py-2.5 px-3 border-r border-slate-300">Nama Barang & SKU</th>
                                <th class="py-2.5 px-3 text-center border-r border-slate-300 w-32">Kuantiti / Satuan</th>
                                <th class="py-2.5 px-3 text-center w-28">Cek Fisik</th>
                                <th v-if="['pending', 'confirmed', 'processing', 'packed'].includes(selectedOrder?.status)" class="py-2.5 px-3 text-center w-28 no-print">Status Gudang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr 
                                v-for="(it, i) in selectedOrder?.items" 
                                :key="i"
                                @click="toggleCheckItem(it.id)"
                                :class="it.status === 'out_of_stock' ? 'bg-rose-50/60' : (checkedItems[it.id] ? 'bg-emerald-50/50' : 'hover:bg-slate-50')"
                                class="transition cursor-pointer select-none"
                            >
                                <td class="py-3 px-3 border-r border-slate-300 text-center font-bold">{{ i + 1 }}</td>
                                <td class="py-3 px-3 border-r border-slate-300">
                                    <div class="flex items-center gap-1.5">
                                        <span :class="it.status === 'out_of_stock' ? 'line-through text-slate-400 font-medium' : 'text-slate-950 font-bold'" class="text-xs">{{ it.product?.name }}</span>
                                        <span v-if="it.status === 'out_of_stock'" class="text-[9px] font-black uppercase px-1.5 py-0.2 bg-rose-100 text-rose-700 rounded border border-rose-200 shrink-0">Kosong</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-0.5">SKU: {{ it.product?.sku }}</div>
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-300 font-black text-sm" :class="it.status === 'out_of_stock' ? 'line-through text-slate-400' : 'text-slate-950'">
                                    {{ it.qty }} {{ it.unit?.unit_name }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button 
                                        type="button"
                                        :class="checkedItems[it.id] ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-300 text-transparent'"
                                        class="w-6 h-6 border-2 rounded-lg inline-flex items-center justify-center transition cursor-pointer shadow-xs mx-auto"
                                    >
                                        <Check class="w-4 h-4 stroke-[3]" />
                                    </button>
                                </td>
                                <td v-if="['pending', 'confirmed', 'processing', 'packed'].includes(selectedOrder?.status)" class="py-3 px-3 text-center no-print" @click.stop>
                                    <button 
                                        v-if="it.status === 'out_of_stock'"
                                        type="button"
                                        @click.stop="toggleItemStatus(selectedOrder.id, it)"
                                        title="Barang ini ditandai kosong. Klik untuk PULIHKAN jika stok ditemukan"
                                        class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-black inline-flex items-center gap-1 cursor-pointer transition shadow-xs"
                                    >
                                        <RotateCcw class="w-3.5 h-3.5" />
                                        <span>Pulihkan</span>
                                    </button>
                                    <button 
                                        v-else
                                        type="button"
                                        @click.stop="toggleItemStatus(selectedOrder.id, it)"
                                        title="Tandai stok fisik kosong"
                                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 border border-slate-200 hover:border-rose-200 rounded-lg text-[10px] font-bold inline-flex items-center gap-1 cursor-pointer transition"
                                    >
                                        <Ban class="w-3.5 h-3.5" />
                                        <span>Kosong</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pt-8 border-t text-xs grid grid-cols-2 text-center text-slate-800">
                        <div>
                            <p class="font-bold">Petugas Gudang,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-8">( .................................... )</p>
                        </div>
                        <div>
                            <p class="font-bold">Kurir / Driver,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-8">( .................................... )</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t flex flex-wrap items-center justify-between gap-2 no-print">
                    <button 
                        v-if="['pending', 'confirmed', 'processing'].includes(selectedOrder?.status)"
                        @click="confirmFinishPicking" 
                        type="button"
                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-xs flex items-center gap-1.5 cursor-pointer shadow-md transition active:scale-[0.99]"
                    >
                        <CheckCircle2 class="w-4 h-4" />
                        <span>Konfirmasi Selesai Disiapkan (Siap Kirim)</span>
                    </button>
                    <div v-else class="flex items-center gap-1.5 text-emerald-700 font-bold text-xs bg-emerald-100/70 px-3 py-1.5 rounded-xl border border-emerald-300">
                        <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                        <span>Barang Sudah Selesai Disiapkan</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="printDocument('printable-picking')" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md">
                            <Printer class="w-4 h-4 text-amber-400" />
                            <span>Cetak Picking List</span>
                        </button>
                        <button @click="isPickingModalOpen = false" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DOKUMEN 2: Dokumen Surat Jalan Resmi (Full A4 Output) -->
        <div v-if="isSuratJalanModalOpen && selectedOrder" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white text-slate-900 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-4 border-b flex justify-between items-center no-print">
                    <h3 class="text-sm font-black uppercase text-slate-900">Dokumen Surat Jalan Pengiriman Barang</h3>
                    <button @click="isSuratJalanModalOpen = false" class="text-slate-400 text-xs font-bold cursor-pointer hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div id="printable-suratjalan" class="p-8 space-y-6 flex-1 overflow-y-auto bg-white">
                    <div class="border-b-2 border-slate-900 pb-4 flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-14 h-14 object-contain" />
                            <div>
                                <h2 class="text-xl font-black tracking-tight text-slate-950 uppercase">TRISNA JAYA LISTRIK</h2>
                                <p class="text-xs text-slate-600 font-medium">Distributor & Suplier Peralatan Listrik Gedung / Proyek</p>
                                <p class="text-xs text-slate-600">Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm uppercase tracking-widest px-3.5 py-1 bg-blue-900 text-white font-black rounded-lg inline-block">
                                SURAT JALAN
                            </span>
                            <p class="text-xs text-slate-700 font-mono font-bold mt-2">No: <strong>SJ-{{ selectedOrder?.so_number }}</strong></p>
                            <p class="text-xs text-slate-600">Tanggal: {{ selectedOrder?.delivery_date ? new Date(selectedOrder.delivery_date).toLocaleDateString('id-ID') : new Date(selectedOrder?.order_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs">
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Kepada Yth:</p>
                            <p class="text-sm font-black text-slate-950">{{ selectedOrder?.customer?.name }}</p>
                            <p class="text-slate-700 font-semibold mt-1">Alamat Tujuan: {{ selectedOrder?.customer?.address }}</p>
                            <p class="text-slate-600">No. Telepon / PIC: {{ selectedOrder?.customer?.phone || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Keterangan Pengiriman:</p>
                            <p class="text-slate-700">Sales: <strong>{{ selectedOrder?.sales?.name }}</strong></p>
                            <p class="text-slate-700">Tipe Order: <strong>Pesanan Proyek (SO)</strong></p>
                            <p v-if="selectedOrder?.notes" class="text-amber-900 font-bold italic mt-1 bg-amber-50 p-2 rounded-xl border border-amber-200">
                                Catatan: "{{ selectedOrder?.notes }}"
                            </p>
                        </div>
                    </div>

                    <table class="w-full text-xs text-left border-collapse border border-slate-300">
                        <thead>
                            <tr class="bg-slate-100 font-black border-b border-slate-300 text-[11px]">
                                <th class="py-2.5 px-3 border-r border-slate-300 w-10 text-center">No</th>
                                <th class="py-2.5 px-3 border-r border-slate-300">Deskripsi Barang & Spesifikasi</th>
                                <th class="py-2.5 px-3 text-center border-r border-slate-300 w-36">Jumlah / Qty</th>
                                <th class="py-2.5 px-3 text-center w-36">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(it, i) in selectedOrder?.items" :key="i">
                                <td class="py-3 px-3 border-r border-slate-300 text-center font-bold">{{ i + 1 }}</td>
                                <td class="py-3 px-3 border-r border-slate-300">
                                    <div class="font-bold text-slate-950 text-xs">{{ it.product?.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ it.product?.sku }}</div>
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-300 font-black text-slate-950 text-sm">
                                    {{ it.qty }} {{ it.unit?.unit_name }}
                                </td>
                                <td class="py-3 px-3 text-center text-slate-500 font-medium">Kondisi Baik</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pt-8 border-t text-xs grid grid-cols-3 text-center text-slate-800">
                        <div>
                            <p class="font-bold">Kepala Gudang,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-6">( .................................... )</p>
                        </div>
                        <div>
                            <p class="font-bold">Supir / Pengirim,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-6">( .................................... )</p>
                        </div>
                        <div>
                            <p class="font-bold">Penerima / Proyek,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-6">( {{ selectedOrder?.customer?.name }} )</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2 no-print">
                    <button @click="printDocument('printable-suratjalan')" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md">
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Surat Jalan</span>
                    </button>
                    <button @click="isSuratJalanModalOpen = false" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer">Tutup</button>
                </div>
            </div>
        </div>

        <!-- MODAL DOKUMEN 3: Faktur Penjualan (Commercial Invoice - Dot Matrix & A4 Support) -->
        <div v-if="isInvoiceModalOpen && selectedOrder" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white text-slate-900 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-4 border-b flex flex-wrap justify-between items-center gap-3 no-print bg-slate-50">
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-900">Faktur Penjualan (Invoice SO)</h3>
                        <p class="text-[11px] text-slate-500">Pilih format cetak sesuai jenis printer Anda</p>
                    </div>

                    <!-- Print Format Selector Tabs -->
                    <div class="flex items-center gap-1.5 bg-white p-1 rounded-2xl border border-slate-200 shadow-2xs">
                        <button 
                            @click="selectedInvoicePrintFormat = 'dot_matrix'"
                            :class="selectedInvoicePrintFormat === 'dot_matrix' ? 'bg-slate-900 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <span>📄 Print Dot Matrix</span>
                        </button>
                        <button 
                            @click="selectedInvoicePrintFormat = 'a4'"
                            :class="selectedInvoicePrintFormat === 'a4' ? 'bg-slate-900 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <span>📑 Standar HVS A4</span>
                        </button>
                    </div>

                    <button @click="isInvoiceModalOpen = false" class="text-slate-400 text-xs font-bold cursor-pointer hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- PREVIEW FORMAT 1: KERTAS 2 PLY CONTINUOUS FORM (9.5" x 11"/2) -->
                <div v-if="selectedInvoicePrintFormat === 'dot_matrix'" class="p-6 font-sans text-slate-900 bg-white border border-slate-300 rounded-2xl leading-normal space-y-3 print:border-none print:p-0 select-text max-w-3xl mx-auto shadow-sm overflow-y-auto flex-1">
                    <!-- Header Section -->
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-2.5">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-10 h-10 object-contain shrink-0 mt-0.5" />
                            <div>
                                <h2 class="font-black text-base uppercase tracking-tight text-slate-950">{{ settings?.store_name || 'TRISNA JAYA LISTRIK' }}</h2>
                                <p class="text-[11px] text-slate-600 mt-0.5">Alamat : {{ settings?.store_address || 'Jl. Raya Pantura No. 99, Pekalongan' }}</p>
                                <p class="text-[11px] text-slate-600">Telepon/HP : {{ settings?.store_phone || '+62 815 7345 5951' }}</p>
                                <p class="text-[11px] text-slate-600">Email : {{ settings?.store_email || 'trisnajaya050@gmail.com' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <h3 class="font-black text-xl uppercase tracking-wider text-slate-950">INVOICE</h3>
                            <table class="text-xs ml-auto mt-1 leading-tight">
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">No. Invoice :</td>
                                    <td class="font-black font-mono text-slate-950">INV-{{ selectedOrder?.so_number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Tanggal :</td>
                                    <td class="font-bold text-slate-900">{{ new Date(selectedOrder?.order_date || selectedOrder?.created_at).toLocaleDateString('id-ID') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Pembayaran :</td>
                                    <td class="font-bold text-slate-900 uppercase">
                                        {{ selectedOrder?.payment_type }} {{ selectedOrder?.due_date ? `(Jatuh Tempo: ${selectedOrder?.due_date})` : '' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Sales :</td>
                                    <td class="font-bold text-slate-900 uppercase">{{ selectedOrder?.sales?.name || user?.name }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="border-b-2 border-slate-950"></div>

                    <!-- Customer & Big Total Header -->
                    <div class="flex justify-between items-center text-xs">
                        <div>
                            <p class="text-slate-500 text-[11px]">Kepada Yth.</p>
                            <h4 class="font-black text-sm uppercase text-slate-950">{{ selectedOrder?.customer?.name || 'Pelanggan Proyek' }}</h4>
                            <p class="text-slate-600 text-[11px]">Alamat : {{ selectedOrder?.customer?.address || '-' }}</p>
                            <p class="text-slate-600 text-[11px]">No. HP / WA : {{ selectedOrder?.customer?.phone || '-' }}</p>
                            <p class="text-slate-600 text-[11px]">Email : {{ selectedOrder?.customer?.email || '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">JUMLAH YANG HARUS DIBAYAR</p>
                            <p class="text-2xl font-black text-slate-950 tracking-tight mt-0.5">{{ formatRupiah(selectedOrder?.total_amount) }}</p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table class="w-full border-collapse text-xs my-2">
                        <thead>
                            <tr class="border-y-2 border-slate-950 bg-slate-100 text-slate-900 font-black text-left">
                                <th class="py-1.5 px-2">NAMA BARANG</th>
                                <th class="py-1.5 px-2 text-center w-20">QTY</th>
                                <th class="py-1.5 px-2 text-right w-24">HARGA</th>
                                <th class="py-1.5 px-2 text-right w-20">DISKON</th>
                                <th class="py-1.5 px-2 text-right w-28">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(it, idx) in (selectedOrder?.items || []).filter(it => it.status !== 'out_of_stock')" :key="idx">
                                <td class="py-1 px-2 font-medium text-slate-900">{{ it.product?.name }}</td>
                                <td class="py-1 px-2 text-center text-slate-800">{{ it.qty }} {{ it.unit?.unit_name }}</td>
                                <td class="py-1 px-2 text-right text-slate-800">{{ formatRupiah(it.unit_price) }}</td>
                                <td class="py-1 px-2 text-right text-slate-600">{{ it.discount > 0 ? formatRupiah(it.discount) : '-' }}</td>
                                <td class="py-1 px-2 text-right font-black text-slate-950">{{ formatRupiah(it.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Footer Section -->
                    <div class="border-t border-slate-950 pt-2 grid grid-cols-2 gap-4 text-xs">
                        <div class="space-y-2 text-[11px]">
                            <p class="italic text-slate-700">Terbilang: <span class="font-bold">{{ numberToWords(selectedOrder?.total_amount) }}</span></p>
                            <div>
                                <p class="font-bold text-slate-900">Keterangan:</p>
                                <p class="text-slate-600">Terima kasih atas kepercayaan Anda.</p>
                                <p class="text-slate-600">Mohon simpan dokumen ini sebagai bukti transaksi.</p>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900">Metode Pembayaran:</p>
                                <p class="text-slate-700 whitespace-pre-line leading-tight">{{ formatTextWithBreaks(settings?.bank_info || 'Bank : BCA\nNo. Rekening : 2501294511\nAtas Nama : YUNIAR DWI RAHMAWATI') }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <table class="w-full text-xs leading-tight">
                                <tr>
                                    <td class="text-right pr-2 text-slate-600">Subtotal :</td>
                                    <td class="text-right font-bold text-slate-900 w-28">{{ formatRupiah(selectedOrder?.total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-600">Diskon :</td>
                                    <td class="text-right font-bold text-slate-900">-</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-600">Total Sblm Pajak :</td>
                                    <td class="text-right font-bold text-slate-900">{{ formatRupiah(selectedOrder?.total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-600">Pajak 000% :</td>
                                    <td class="text-right font-bold text-slate-900">-</td>
                                </tr>
                                <tr class="border-t border-slate-950 font-black text-sm">
                                    <td class="text-right pr-2 pt-1 text-slate-950">Total :</td>
                                    <td class="text-right pt-1 text-slate-950">{{ formatRupiah(selectedOrder?.total_amount) }}</td>
                                </tr>
                            </table>

                            <!-- Signatures Block -->
                            <div class="grid grid-cols-2 gap-4 text-center pt-2">
                                <div>
                                    <p class="text-slate-600 font-medium">Hormat Kami,</p>
                                    <div class="h-4"></div>
                                    <p class="font-bold text-slate-900">( TJL )</p>
                                </div>
                                <div>
                                    <p class="text-slate-600 font-medium">Diterima Oleh,</p>
                                    <div class="h-4"></div>
                                    <p class="font-bold text-slate-900">( {{ selectedOrder?.customer?.name }} )</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PREVIEW FORMAT 2: STANDAR HVS A4 -->
                <div v-else id="printable-invoice" class="p-8 space-y-6 flex-1 overflow-y-auto bg-white">
                    <div class="border-b-2 border-slate-900 pb-4 flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo Trisna Jaya" class="w-14 h-14 object-contain" />
                            <div>
                                <h2 class="text-xl font-black tracking-tight text-slate-950 uppercase">{{ settings?.store_name || 'TRISNA JAYA LISTRIK' }}</h2>
                                <p class="text-xs text-slate-600 font-medium">Distributor & Perlengkapan Listrik Proyek</p>
                                <p class="text-xs text-slate-600">Jl. Raya Utama No. 88 &bull; Telp/WA: 0812-3456-7890</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm uppercase tracking-widest px-3.5 py-1 bg-emerald-900 text-white font-black rounded-lg inline-block">
                                FAKTUR PENJUALAN
                            </span>
                            <p class="text-xs text-slate-700 font-mono font-bold mt-2">No: <strong>INV-{{ selectedOrder?.so_number }}</strong></p>
                            <p class="text-xs text-slate-600">Tanggal: {{ new Date(selectedOrder?.order_date).toLocaleDateString('id-ID') }}</p>
                            <div class="mt-1">
                                <span class="text-[10px] uppercase px-2 py-0.5 bg-amber-100 text-amber-900 font-black rounded">
                                    PEMBAYARAN: {{ selectedOrder?.payment_type }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs">
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Ditagihkan Kepada:</p>
                            <p class="text-sm font-black text-slate-950">{{ selectedOrder?.customer?.name }}</p>
                            <p class="text-slate-600 mt-1">Alamat: {{ selectedOrder?.customer?.address }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Keterangan Tagihan:</p>
                            <p class="text-slate-700">Sales Representative: <strong>{{ selectedOrder?.sales?.name }}</strong></p>
                            <p class="text-slate-700">Status Faktur: <strong class="text-emerald-700">Resmi Terbit</strong></p>
                            <p v-if="selectedOrder?.notes" class="text-slate-600 italic mt-1">Catatan: "{{ selectedOrder?.notes }}"</p>
                        </div>
                    </div>

                    <table class="w-full text-xs text-left border-collapse border border-slate-300">
                        <thead>
                            <tr class="bg-slate-100 font-black border-b border-slate-300 text-[11px]">
                                <th class="py-2.5 px-3 border-r border-slate-300 w-10 text-center">No</th>
                                <th class="py-2.5 px-3 border-r border-slate-300">Deskripsi Barang Listrik</th>
                                <th class="py-2.5 px-3 text-center border-r border-slate-300 w-28">Kuantiti</th>
                                <th class="py-2.5 px-3 text-right border-r border-slate-300 w-36">Harga Satuan</th>
                                <th class="py-2.5 px-3 text-right w-36">Subtotal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(it, i) in (selectedOrder?.items || []).filter(it => it.status !== 'out_of_stock')" :key="i">
                                <td class="py-3 px-3 border-r border-slate-300 text-center font-bold">{{ i + 1 }}</td>
                                <td class="py-3 px-3 border-r border-slate-300">
                                    <div class="font-bold text-slate-950 text-xs">{{ it.product?.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ it.product?.sku }}</div>
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-300 font-semibold">{{ it.qty }} {{ it.unit?.unit_name }}</td>
                                <td class="py-3 px-3 text-right border-r border-slate-300 font-mono">{{ formatRupiah(it.unit_price) }}</td>
                                <td class="py-3 px-3 text-right font-black font-mono text-slate-950">{{ formatRupiah(it.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-between items-center p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="text-xs text-slate-700 space-y-1">
                            <p class="font-black text-slate-900 uppercase text-[10px] tracking-wider">Rekening Pembayaran Resmi Toko:</p>
                            <p>&bull; Bank BCA: <strong>8830-123-456</strong> a.n. TRISNA JAYA LISTRIK</p>
                            <p>&bull; Bank Mandiri: <strong>137-00-9876543-2</strong> a.n. TRISNA JAYA LISTRIK</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">TOTAL TAGIHAN:</span>
                            <span class="text-xl font-black text-slate-950">{{ formatRupiah(selectedOrder?.total_amount) }}</span>
                        </div>
                    </div>

                    <div class="pt-8 border-t text-xs flex justify-between text-center text-slate-800">
                        <div class="w-48">
                            <p class="font-bold">Penerima Tagihan,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold">( {{ selectedOrder?.customer?.name }} )</p>
                        </div>
                        <div class="w-48">
                            <p class="font-bold">Hormat Kami,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold">( TRISNA JAYA LISTRIK )</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2 no-print">
                    <button @click="printDocument('invoice')" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md">
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Faktur ({{ selectedInvoicePrintFormat === 'dot_matrix' ? 'Dot Matrix' : 'HVS A4' }})</span>
                    </button>
                    <button @click="isInvoiceModalOpen = false" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer">Tutup</button>
                </div>
            </div>
        </div>

        <!-- MODAL KONFIRMASI PICKING KHUSUS (STOK KOSONG DITEMUKAN) -->
        <Teleport to="body">
            <div 
                v-if="isPickingConfirmModalOpen" 
                class="fixed inset-0 z-[100] bg-slate-950/75 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-150"
            >
                <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full overflow-hidden flex flex-col animate-in zoom-in-95 duration-150">
                    <!-- Top Warning Banner Header -->
                    <div class="p-6 bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-transparent border-b border-amber-100 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/30 shrink-0">
                                <AlertTriangle class="w-6 h-6 stroke-[2.5]" />
                            </div>
                            <div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                                    Pengecekan Fisik Gudang
                                </span>
                                <h3 class="text-base font-black text-slate-900 mt-1">
                                    {{ uncheckedPickingItems.length }} Barang Belum Lengkap / Kosong
                                </h3>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Barang berikut tidak dicentang siap karena stok fisik di rak gudang kosong.
                                </p>
                            </div>
                        </div>
                        <button 
                            type="button"
                            @click="isPickingConfirmModalOpen = false" 
                            class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-white rounded-xl transition cursor-pointer"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- List of Out of Stock Items -->
                    <div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto">
                        <div class="space-y-2">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                Rincian Barang yang Ditandai Kosong:
                            </p>
                            <div class="space-y-2">
                                <div 
                                    v-for="it in uncheckedPickingItems" 
                                    :key="it.id"
                                    class="p-3 bg-rose-50/70 border border-rose-200/80 rounded-2xl flex items-center justify-between gap-3 shadow-2xs"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-xs text-slate-900 truncate">{{ it.product?.name }}</span>
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-rose-200 text-rose-800 shrink-0">KOSONG</span>
                                        </div>
                                        <p class="text-[10px] text-slate-500 font-mono mt-0.5">
                                            SKU: {{ it.product?.sku || '-' }} &bull; Dipesan: <strong class="text-slate-700">{{ it.qty }} {{ it.unit?.unit_name }}</strong>
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] text-rose-500 font-bold block">Tidak Ditagih:</span>
                                        <span class="text-xs font-black text-rose-700 line-through">{{ formatRupiah(it.subtotal) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Recalculation Summary -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Penyesuaian Total Tagihan Nota:</p>
                            
                            <div class="flex items-center justify-between text-xs text-slate-600">
                                <span>Total Dipesan Semula</span>
                                <span class="font-medium text-slate-800">{{ formatRupiah(originalOrderTotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-rose-600">
                                <span>Potongan Barang Kosong</span>
                                <span class="font-bold">- {{ formatRupiah(uncheckedItemsTotal) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                                <span class="text-xs font-black text-slate-900">Total Baru Nota (Siap Ditagih)</span>
                                <span class="text-base font-black text-emerald-600">{{ formatRupiah(newEstimatedOrderTotal) }}</span>
                            </div>
                        </div>

                        <!-- System Best Practice Note -->
                        <div class="p-3.5 bg-blue-50/80 border border-blue-200/80 rounded-2xl flex items-start gap-2.5 text-xs text-blue-900 leading-relaxed">
                            <Sparkles class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                            <div>
                                <p class="font-bold">Keamanan & Fleksibilitas Sistem:</p>
                                <p class="text-[11px] text-blue-700 mt-0.5">
                                    Barang kosong <strong>tidak dihapus permanen</strong> dan tidak akan muncul di Faktur atau Surat Jalan. Anda tetap dapat <strong>memulihkannya kembali</strong> kapan saja jika barang ditemukan di rak lain.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="p-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button 
                            type="button"
                            @click="isPickingConfirmModalOpen = false" 
                            class="px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 cursor-pointer transition active:scale-95 shadow-2xs"
                        >
                            Periksa Ulang Fisik
                        </button>
                        <button 
                            type="button"
                            @click="executePickingFinish()" 
                            class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md shadow-emerald-600/25 transition active:scale-95"
                        >
                            <CheckCircle2 class="w-4 h-4" />
                            <span>Konfirmasi Siap Kirim ({{ formatRupiah(newEstimatedOrderTotal) }})</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL KONFIRMASI AKSI ITEM (TOGGLE STATUS / REMOVE ITEM) -->
        <Teleport to="body">
            <div 
                v-if="isGenericConfirmModalOpen" 
                class="fixed inset-0 z-[100] bg-slate-950/75 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-150"
            >
                <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full overflow-hidden flex flex-col animate-in zoom-in-95 duration-150">
                    <div class="p-6 text-center space-y-4">
                        <!-- Icon Circle -->
                        <div 
                            :class="[
                                genericConfirmData.type === 'success' ? 'bg-emerald-100 text-emerald-600 shadow-emerald-500/20' : 
                                (genericConfirmData.type === 'danger' ? 'bg-rose-100 text-rose-600 shadow-rose-500/20' : 'bg-amber-100 text-amber-600 shadow-amber-500/20'),
                                'w-14 h-14 rounded-2xl flex items-center justify-center mx-auto shadow-lg'
                            ]"
                        >
                            <RotateCcw v-if="genericConfirmData.type === 'success'" class="w-7 h-7 stroke-[2.5]" />
                            <Trash2 v-else-if="genericConfirmData.type === 'danger'" class="w-7 h-7 stroke-[2.5]" />
                            <Ban v-else class="w-7 h-7 stroke-[2.5]" />
                        </div>

                        <!-- Title & Subtitle -->
                        <div>
                            <h3 class="text-base font-black text-slate-900">{{ genericConfirmData.title }}</h3>
                            <p v-if="genericConfirmData.subtitle" class="text-[11px] text-slate-500 mt-0.5">{{ genericConfirmData.subtitle }}</p>
                        </div>

                        <!-- Item highlight card if available -->
                        <div v-if="genericConfirmData.itemName" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-left">
                            <p class="font-black text-xs text-slate-900 truncate">{{ genericConfirmData.itemName }}</p>
                            <p v-if="genericConfirmData.itemDetail" class="text-[11px] text-slate-600 mt-0.5 font-medium">{{ genericConfirmData.itemDetail }}</p>
                        </div>

                        <!-- Message -->
                        <p class="text-xs text-slate-600 leading-relaxed px-2">
                            {{ genericConfirmData.message }}
                        </p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button 
                            type="button"
                            @click="isGenericConfirmModalOpen = false" 
                            class="px-4 py-2.5 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs border border-slate-200 cursor-pointer transition active:scale-95"
                        >
                            Batal
                        </button>
                        <button 
                            type="button"
                            @click="genericConfirmData.onConfirm()" 
                            :class="[
                                genericConfirmData.type === 'success' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/25' : 
                                (genericConfirmData.type === 'danger' ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/25' : 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/25'),
                                'px-5 py-2.5 text-white font-black rounded-xl text-xs transition cursor-pointer active:scale-95 shadow-md'
                            ]"
                        >
                            {{ genericConfirmData.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- FLOATING WARNING TOAST -->
        <Teleport to="body">
            <div 
                v-if="isWarningToastOpen"
                class="fixed top-6 left-1/2 -translate-x-1/2 z-[110] bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-xs font-bold border border-slate-700 animate-in fade-in slide-in-from-top-4 duration-150"
            >
                <AlertCircle class="w-4 h-4 text-amber-400 shrink-0" />
                <span>{{ warningToastMessage }}</span>
            </div>
        </Teleport>
    </MainLayout>
</template>
