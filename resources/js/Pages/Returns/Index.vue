<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    RotateCcw, Plus, Search, CheckCircle2, AlertTriangle, 
    Calendar, User, Building2, Truck, ShoppingCart, 
    Trash2, Printer, X, FileText, ChevronDown, Check,
    Receipt, DollarSign, ShieldAlert, ArrowLeftRight, Package,
    Sparkles, ArrowDownLeft
} from 'lucide-vue-next';

const props = defineProps({
    returns: Array,
    products: Array,
    customers: Array,
    suppliers: Array,
    recentTransactions: Array,
    recentSalesOrders: Array,
    recentGoodsReceipts: Array,
    totalSalesReturnsThisMonth: Number,
    totalSupplierReturnsThisMonth: Number,
    totalRefundAmountThisMonth: Number,
    user: Object,
    settings: Object,
});

const selectedPrintFormat = ref('dot_matrix'); // 'dot_matrix' or 'a4'

// Role-based Available Return Categories for Modal Form
const availableReturnTypes = computed(() => {
    const role = props.user?.role;
    if (role === 'gudang') {
        return [
            { id: 'purchase_supplier', name: '1. Retur ke Supplier (Pabrikan)', desc: 'Klaim barang cacat / rusak ke distributor', icon: Building2, activeClass: 'bg-blue-600 text-white' },
            { id: 'sales_order', name: '2. Retur Sales Proyek (SO)', desc: 'Penerimaan fisik sisa barang material proyek', icon: Truck, activeClass: 'bg-purple-600 text-white' },
        ];
    } else if (role === 'kasir') {
        return [
            { id: 'sales_pos', name: '1. Retur Kasir Toko', desc: 'Pembeli eceran bawa nota & refund dana', icon: ShoppingCart, activeClass: 'bg-amber-500 text-white' },
            { id: 'sales_order', name: '2. Retur Sales Proyek (SO)', desc: 'Koreksi sisa proyek & potong piutang bon', icon: Truck, activeClass: 'bg-purple-600 text-white' },
        ];
    } else {
        return [
            { id: 'sales_pos', name: '1. Retur Kasir Toko', desc: 'Pembeli eceran bawa nota kembali', icon: ShoppingCart, activeClass: 'bg-amber-500 text-white' },
            { id: 'sales_order', name: '2. Retur Sales Proyek (SO)', desc: 'Sisa material proyek kontraktor', icon: Truck, activeClass: 'bg-purple-600 text-white' },
            { id: 'purchase_supplier', name: '3. Retur ke Supplier', desc: 'Klaim barang cacat ke pabrikan', icon: Building2, activeClass: 'bg-blue-600 text-white' },
        ];
    }
});

// Role-based Tab Filter Pills on the Main Table
const availableTabs = computed(() => {
    const role = props.user?.role;
    if (role === 'gudang') {
        return [
            { id: 'all', label: 'Semua Retur Gudang' },
            { id: 'purchase_supplier', label: 'Retur ke Supplier' },
            { id: 'sales_order', label: 'Retur Sales Proyek' },
        ];
    } else if (role === 'kasir') {
        return [
            { id: 'all', label: 'Semua Retur Toko' },
            { id: 'sales_pos', label: 'Retur Kasir Toko' },
            { id: 'sales_order', label: 'Retur Sales Proyek' },
        ];
    } else {
        return [
            { id: 'all', label: 'Semua Retur' },
            { id: 'sales_pos', label: 'Retur Kasir Toko' },
            { id: 'sales_order', label: 'Retur Sales Proyek' },
            { id: 'purchase_supplier', label: 'Retur ke Supplier' },
        ];
    }
});

const defaultReturnType = computed(() => {
    return availableReturnTypes.value[0]?.id || 'sales_pos';
});

const activeTypeFilter = ref('all');
const searchQuery = ref('');
const isAddModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const selectedReturn = ref(null);

// Source Document Selector State for Instant Auto-Populate
const selectedSourceDocumentId = ref('');

// Searchable Product Combobox per row state
const activeProductDropdownIndex = ref(null);
const productSearchQueries = ref({});

const form = useForm({
    return_type: 'sales_pos',
    reference_number: '',
    customer_id: props.customers[0]?.id || null,
    customer_name: props.customers[0]?.name || '',
    supplier_id: props.suppliers[0]?.id || null,
    supplier_name: props.suppliers[0]?.name || '',
    return_date: new Date().toISOString().split('T')[0],
    resolution_type: 'refund_cash',
    reason: '',
    items: [
        {
            product_id: props.products[0]?.id || null,
            product_unit_id: props.products[0]?.units[0]?.id || null,
            qty_returned: 1,
            unit_price: props.products[0]?.units[0]?.price_retail || 0,
            condition: 'good_restock',
        }
    ]
});

onMounted(() => {
    form.return_type = defaultReturnType.value;
    if (form.return_type === 'purchase_supplier') {
        form.resolution_type = 'replacement';
        form.reason = 'Klaim barang cacat / rusak dari pabrikan';
    } else {
        form.resolution_type = 'refund_cash';
        form.reason = 'Kelebihan sisa proyek, segel utuh';
    }
});

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// AUTO-POPULATE LOGIC FROM SOURCE DOCUMENTS
const onSourceDocumentSelected = (docId) => {
    if (!docId) return;

    if (form.return_type === 'purchase_supplier') {
        const gr = props.recentGoodsReceipts.find(g => g.id === Number(docId));
        if (gr) {
            form.supplier_id = gr.supplier_id || null;
            form.supplier_name = gr.supplier_name;
            form.reference_number = gr.receipt_number + (gr.supplier_invoice_number ? ` (SJ: ${gr.supplier_invoice_number})` : '');
            form.resolution_type = 'replacement';
            form.reason = `Klaim retur dari Penerimaan ${gr.receipt_number}`;
            
            // Auto load received items
            if (gr.items && gr.items.length > 0) {
                form.items = gr.items.map(it => ({
                    product_id: it.product_id,
                    product_unit_id: it.product_unit_id,
                    qty_returned: Number(it.qty_received),
                    unit_price: Number(it.cost_price_per_unit || 0),
                    condition: 'damaged_claim',
                }));
            }
        }
    } else if (form.return_type === 'sales_order') {
        const so = props.recentSalesOrders.find(s => s.id === Number(docId));
        if (so) {
            form.customer_id = so.customer_id;
            form.customer_name = so.customer?.name || '';
            form.reference_number = so.so_number;
            form.resolution_type = 'debt_deduction';
            form.reason = `Sisa material proyek dari pesanan ${so.so_number}`;

            if (so.items && so.items.length > 0) {
                form.items = so.items.map(it => ({
                    product_id: it.product_id,
                    product_unit_id: it.product_unit_id,
                    qty_returned: Number(it.qty),
                    unit_price: Number(it.unit_price || 0),
                    condition: 'good_restock',
                }));
            }
        }
    } else if (form.return_type === 'sales_pos') {
        const tx = props.recentTransactions.find(t => t.id === Number(docId));
        if (tx) {
            form.customer_id = tx.customer_id || null;
            form.customer_name = tx.customer?.name || 'Pelanggan Umum';
            form.reference_number = tx.invoice_number;
            form.resolution_type = 'refund_cash';
            form.reason = `Pengembalian barang nota ${tx.invoice_number}`;

            if (tx.items && tx.items.length > 0) {
                form.items = tx.items.map(it => ({
                    product_id: it.product_id,
                    product_unit_id: it.product_unit_id,
                    qty_returned: Number(it.quantity),
                    unit_price: Number(it.unit_price || 0),
                    condition: 'good_restock',
                }));
            }
        }
    }
};

// Row item helpers
const addItemRow = () => {
    const defaultProduct = props.products[0];
    const defaultUnit = defaultProduct?.units[0];
    const price = form.return_type === 'purchase_supplier' ? defaultUnit?.cost_price : defaultUnit?.price_retail;

    form.items.push({
        product_id: defaultProduct?.id || null,
        product_unit_id: defaultUnit?.id || null,
        qty_returned: 1,
        unit_price: price || 0,
        condition: form.return_type === 'purchase_supplier' ? 'damaged_claim' : 'good_restock',
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
        item.unit_price = form.return_type === 'purchase_supplier' ? product.units[0].cost_price : product.units[0].price_retail;
    }
    activeProductDropdownIndex.value = null;
    productSearchQueries.value[idx] = '';
};

const onUnitChange = (item, unitId) => {
    const prod = getProductById(item.product_id);
    if (prod) {
        const unit = prod.units.find(u => u.id === Number(unitId));
        if (unit) {
            item.unit_price = form.return_type === 'purchase_supplier' ? unit.cost_price : unit.price_retail;
        }
    }
};

const onReturnTypeChange = (type) => {
    form.return_type = type;
    selectedSourceDocumentId.value = '';

    if (type === 'purchase_supplier') {
        form.resolution_type = 'replacement';
        form.reason = 'Klaim barang cacat / rusak dari pabrikan';
        form.items.forEach(it => {
            it.condition = 'damaged_claim';
            const prod = getProductById(it.product_id);
            const unit = prod?.units.find(u => u.id === Number(it.product_unit_id));
            if (unit) it.unit_price = unit.cost_price;
        });
    } else {
        form.resolution_type = type === 'sales_order' ? 'debt_deduction' : 'refund_cash';
        form.reason = 'Kelebihan sisa proyek, segel utuh';
        form.items.forEach(it => {
            it.condition = 'good_restock';
            const prod = getProductById(it.product_id);
            const unit = prod?.units.find(u => u.id === Number(it.product_unit_id));
            if (unit) it.unit_price = unit.price_retail;
        });
    }
};

const totalReturnEstimation = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.qty_returned) * Number(item.unit_price || 0)), 0);
});

// Role-strict filtering for the table list
const filteredReturns = computed(() => {
    const role = props.user?.role;

    return props.returns.filter(r => {
        if (role === 'gudang') {
            if (r.return_type === 'sales_pos') return false;
        } else if (role === 'kasir') {
            if (r.return_type === 'purchase_supplier') return false;
        }

        const matchesType = activeTypeFilter.value === 'all' || r.return_type === activeTypeFilter.value;
        const q = searchQuery.value.toLowerCase().trim();
        const matchesSearch = !q || 
            r.return_number.toLowerCase().includes(q) ||
            (r.reference_number && r.reference_number.toLowerCase().includes(q)) ||
            (r.customer_name && r.customer_name.toLowerCase().includes(q)) ||
            (r.supplier_name && r.supplier_name.toLowerCase().includes(q)) ||
            r.items.some(it => it.product?.name.toLowerCase().includes(q));
        return matchesType && matchesSearch;
    });
});

const openAddModal = () => {
    form.return_type = defaultReturnType.value;
    selectedSourceDocumentId.value = '';
    onReturnTypeChange(defaultReturnType.value);
    isAddModalOpen.value = true;
};

const submitReturn = () => {
    form.post('/returns', {
        onSuccess: () => {
            isAddModalOpen.value = false;
            form.reset();
            selectedSourceDocumentId.value = '';
            form.items = [
                {
                    product_id: props.products[0]?.id || null,
                    product_unit_id: props.products[0]?.units[0]?.id || null,
                    qty_returned: 1,
                    unit_price: props.products[0]?.units[0]?.price_retail || 0,
                    condition: 'good_restock',
                }
            ];
            productSearchQueries.value = {};
            activeProductDropdownIndex.value = null;
        }
    });
};

const openDetail = (ret) => {
    selectedReturn.value = ret;
    isDetailModalOpen.value = true;
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

const buildReturnDotMatrixHtml = (ret) => {
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
    const storeAddress = props.settings?.store_address || 'Jl. Raya Pekalongan No. 88';
    const storePhone = props.settings?.store_phone || '0812-3456-7890';
    const storeEmail = props.settings?.store_email || 'trisnajaya050@gmail.com';
    const storeLogo = props.settings?.store_logo || '/pos-kantin/images/logo.png';

    const storeNameDisplay = (storeName && storeName.trim() !== '') ? storeName : 'TRISNA JAYA LISTRIK';
    const storeAddressDisplay = storeAddress || 'Jl. Raya Karanganyar, Kebonsari, Karangsari, Kab. Pekalongan';
    const storePhoneDisplay = storePhone || '+62 815-7345-5951';
    const storeEmailDisplay = storeEmail || 'trisnajaya050@gmail.com';

    const partyName = ret.return_type === 'purchase_supplier' ? (ret.supplier_name || ret.supplier?.name || 'Supplier') : (ret.customer_name || ret.customer?.name || 'Pelanggan Umum');
    const formattedDate = formatCleanDate(ret.return_date || ret.created_at || new Date());
    const terbilangText = numberToWords(ret.total_refund_amount);

    let resolutionLabel = 'Refund Kas';
    if (ret.resolution_type === 'refund_cash') resolutionLabel = 'PENGEMBALIAN DANA (REFUND)';
    else if (ret.resolution_type === 'debt_deduction') resolutionLabel = 'POTONG PIUTANG (BON)';
    else if (ret.resolution_type === 'replacement') resolutionLabel = 'PENGGANTIAN BARANG BARU';

    let returnTypeLabel = 'RETUR KASIR TOKO';
    if (ret.return_type === 'purchase_supplier') returnTypeLabel = 'RETUR KE SUPPLIER (PABRIKAN)';
    else if (ret.return_type === 'sales_order') returnTypeLabel = 'RETUR SALES PROYEK (SO)';

    const items = ret.items || [];
    const dotMatrixItemsHtml = items.map((it) => {
        const qtyStr = `${it.qty_returned} ${it.unit?.unit_name || 'Pcs'}`;
        const condStr = it.condition === 'good_restock' ? 'Segel Baik' : 'Rusak/Cacat';
        const priceStr = formatNumberClean(it.unit_price);
        const subtotalStr = formatNumberClean(it.subtotal);

        return `
            <tr style="border-bottom: 1px dashed #000; font-size: 9.5pt; line-height: 1.25;">
                <td style="padding: 3px 4px; text-align: left; font-weight: bold; font-family: 'Courier New', monospace;">${it.product?.name || 'Item'}</td>
                <td style="padding: 3px 4px; text-align: center; white-space: nowrap; font-weight: bold;">${qtyStr}</td>
                <td style="padding: 3px 4px; text-align: center; white-space: nowrap; font-size: 8.5pt;">${condStr}</td>
                <td style="padding: 3px 4px; text-align: right; white-space: nowrap; font-weight: bold;">${priceStr}</td>
                <td style="padding: 3px 4px; text-align: right; font-weight: 900; white-space: nowrap;">${subtotalStr}</td>
            </tr>
        `;
    }).join('');

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Nota Retur ${ret.return_number}</title>
            <style>
                @page {
                    size: 215mm 139mm landscape;
                    margin: 2mm 2mm 2mm 2mm;
                }
                @media print {
                    html, body {
                        width: 100% !important;
                        max-width: 185mm !important;
                        margin: 0 auto !important;
                        padding: 7mm 0 0 0 !important;
                        overflow: visible !important;
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
                    padding: 7mm 0 0 0;
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
                        <div style="font-size: 13pt; font-weight: 900; letter-spacing: 0.5px; line-height: 1.1; text-transform: uppercase; color: #000;">
                            ${storeNameDisplay}
                        </div>
                        <div style="font-size: 8.5pt; font-weight: bold; color: #000; margin-top: 2px; line-height: 1.2;">
                            ${storeAddressDisplay}
                        </div>
                        <div style="font-size: 8.5pt; font-weight: bold; color: #000; line-height: 1.2;">
                            Telp/HP: ${storePhoneDisplay} | Email: ${storeEmailDisplay}
                        </div>
                    </td>
                    <td style="width: 42%; vertical-align: top; text-align: right;">
                        <div style="font-size: 14pt; font-weight: 900; letter-spacing: 1.5px; line-height: 1; text-transform: uppercase; margin-bottom: 3px; color: #000;">
                            NOTA RETUR
                        </div>
                        <table style="font-size: 9pt; font-weight: bold; margin-left: auto; width: 100%; table-layout: fixed; line-height: 1.25;">
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000; width: 45%;">No. Retur :</td>
                                <td style="font-weight: 900; text-align: left; width: 55%; font-family: 'Courier New', monospace;">${ret.return_number}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Tanggal :</td>
                                <td style="font-weight: bold; text-align: left;">${formattedDate}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Kategori :</td>
                                <td style="font-weight: bold; text-align: left; font-size: 8pt;">${returnTypeLabel}</td>
                            </tr>
                            <tr>
                                <td style="text-align: right; padding-right: 6px; color: #000;">Petugas :</td>
                                <td style="font-weight: bold; text-align: left; text-transform: uppercase;">${ret.user?.name || props.user?.name || '-'}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- DIVIDER LINE -->
            <div style="border-bottom: 2px solid #000; margin: 3px 0;"></div>

            <!-- SUB-HEADER: PARTY & TOTAL REFUND -->
            <table style="width: 100%; table-layout: fixed; margin-bottom: 3px;">
                <tr>
                    <td style="width: 58%; vertical-align: top; font-size: 9pt; line-height: 1.3; padding-right: 6px;">
                        <div style="color: #000; font-size: 8.5pt;">${ret.return_type === 'purchase_supplier' ? 'Supplier (Pabrikan):' : 'Pihak Pengembali:'}</div>
                        <div style="font-size: 11pt; font-weight: 900; text-transform: uppercase; color: #000;">${partyName}</div>
                        ${ret.reference_number ? `<div>No. Referensi: <strong>${ret.reference_number}</strong></div>` : ''}
                        <div>Penyelesaian: <strong>${resolutionLabel}</strong></div>
                    </td>
                    <td style="width: 42%; vertical-align: middle; text-align: right;">
                        <div style="font-size: 8.5pt; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #000;">
                            TOTAL NILAI RETUR :
                        </div>
                        <div style="font-size: 16pt; font-weight: 900; color: #000; letter-spacing: 0.5px; margin-top: 1px; font-family: 'Courier New', monospace;">
                            Rp${formatNumberClean(ret.total_refund_amount)}
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
                        <th style="padding: 3px 4px; text-align: center; font-weight: 900; width: 14%;">KONDISI</th>
                        <th style="padding: 3px 4px; text-align: right; font-weight: 900; width: 14%;">HARGA</th>
                        <th style="padding: 3px 4px; text-align: right; font-weight: 900; width: 14%;">SUBTOTAL</th>
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
                            <div style="font-weight: bold; margin-top: 2px;">Alasan Retur:</div>
                            <div style="line-height: 1.25;">"${ret.reason || '-'}"</div>
                            <div style="font-size: 7.5pt; margin-top: 3px; color: #000;">* Dokumen ini merupakan bukti sah serah terima pengembalian barang.</div>
                        </td>
                        <td style="width: 42%; vertical-align: top;">
                            <table style="width: 100%; table-layout: fixed; font-size: 9pt; line-height: 1.25;">
                                <tr style="border-top: 1.5px solid #000; font-weight: 900; font-size: 11pt;">
                                    <td style="text-align: right; padding-right: 6px; padding-top: 2px; width: 50%;">Total Retur :</td>
                                    <td style="text-align: right; padding-top: 2px; width: 50%; font-family: 'Courier New', monospace;">Rp${formatNumberClean(ret.total_refund_amount)}</td>
                                </tr>
                            </table>

                            <table style="width: 100%; table-layout: fixed; margin-top: 6px; text-align: center; font-size: 8.5pt;">
                                <tr>
                                    <td style="width: 50%; vertical-align: top;">
                                        <div>Pihak Pengembali,</div>
                                        <div style="height: 26px;"></div>
                                        <div style="font-weight: 900;">( ${partyName} )</div>
                                    </td>
                                    <td style="width: 50%; vertical-align: top;">
                                        <div>Petugas,</div>
                                        <div style="height: 26px;"></div>
                                        <div style="font-weight: 900;">( ${ret.user?.name || 'Petugas'} )</div>
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

const buildReturnA4Html = (ret) => {
    const printContent = document.getElementById('printable-document-a4');
    const content = printContent ? printContent.innerHTML : '';

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Nota Retur ${ret.return_number}</title>
            <style>
                @page {
                    margin: 8mm;
                    size: A4 portrait;
                }
                body, html {
                    margin: 0 !important;
                    padding: 8px !important;
                    background: white !important;
                    color: #0f172a !important;
                    width: 100% !important;
                    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                * { box-sizing: border-box !important; }
                table { width: 100%; border-collapse: collapse; }
            </style>
        </head>
        <body>
            <div>${content}</div>
        </body>
        </html>
    `;
};

const printDocument = () => {
    if (!selectedReturn.value) return;

    let html = '';
    if (selectedPrintFormat.value === 'dot_matrix') {
        html = buildReturnDotMatrixHtml(selectedReturn.value);
    } else {
        html = buildReturnA4Html(selectedReturn.value);
    }

    let iframe = document.getElementById('return-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'return-print-iframe';
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
            console.error('Iframe print error:', err);
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
        <Head title="Manajemen Retur Barang" />
        <div class="p-6 w-full space-y-6">
            <!-- Top Header Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <div class="flex items-center gap-2.5">
                        <RotateCcw class="w-6 h-6 text-rose-600" />
                        <h1 class="text-xl font-black text-slate-900">Manajemen Retur Barang</h1>
                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                            Akses: {{ user?.role }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <template v-if="user?.role === 'gudang'">
                            Fokus Gudang: Pengembalian/klaim barang cacat ke Supplier & penerimaan fisik sisa material proyek.
                        </template>
                        <template v-else-if="user?.role === 'kasir'">
                            Fokus Kasir: Pengembalian nota eceran kasir toko, refund dana & pemotongan piutang kontraktor.
                        </template>
                        <template v-else>
                            Kelola seluruh retur penjualan kasir, retur pesanan sales (SO), dan klaim retur ke supplier pabrikan.
                        </template>
                    </p>
                </div>

                <button 
                    @click="openAddModal"
                    class="bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition shadow-xs cursor-pointer justify-center shrink-0"
                >
                    <Plus class="w-4 h-4 text-amber-400" />
                    <span>Catat Retur Barang</span>
                </button>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ user?.role === 'gudang' ? 'Retur Material Proyek (SO)' : 'Retur Penjualan (Toko & SO)' }}
                        </p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ totalSalesReturnsThisMonth }} Dokumen</h3>
                        <p class="text-[10px] text-amber-700 font-bold mt-1 flex items-center gap-1">
                            <ShoppingCart class="w-3 h-3" /> Pelanggan & Kontraktor
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                        <ShoppingCart class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Retur ke Supplier (Pabrikan)</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ totalSupplierReturnsThisMonth }} Dokumen</h3>
                        <p class="text-[10px] text-blue-700 font-bold mt-1 flex items-center gap-1">
                            <Truck class="w-3 h-3" /> Klaim Cacat / Rusak
                        </p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <Truck class="w-5 h-5" />
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Nilai Pengembalian / Koreksi</p>
                        <h3 class="text-xl font-black text-slate-900 mt-1">{{ formatRupiah(totalRefundAmountThisMonth) }}</h3>
                        <p class="text-[10px] text-slate-400 font-medium mt-1">Bulan Berjalan</p>
                    </div>
                    <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                        <DollarSign class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Returns Table & Filter Bar Container -->
            <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <!-- Filter Pills -->
                    <div class="inline-flex items-center gap-1 p-1 bg-slate-100 rounded-2xl">
                        <button 
                            v-for="fl in availableTabs"
                            :key="fl.id"
                            @click="activeTypeFilter = fl.id"
                            :class="activeTypeFilter === fl.id ? 'bg-white text-slate-900 font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-semibold'"
                            class="px-3.5 py-1.5 rounded-xl text-xs transition whitespace-nowrap cursor-pointer"
                        >
                            {{ fl.label }}
                        </button>
                    </div>

                    <!-- Clean Compact Search Box -->
                    <div class="relative w-full sm:w-64">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Cari no retur / nama..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                        />
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto max-h-[calc(100vh-320px)]">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-50 border-b border-slate-200 shadow-xs">
                            <tr class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3.5 px-4 bg-slate-50">No. Retur & Tgl</th>
                                <th class="py-3.5 px-4 bg-slate-50">Tipe Retur</th>
                                <th class="py-3.5 px-4 bg-slate-50">Pihak / Referensi Dokumen</th>
                                <th class="py-3.5 px-4 bg-slate-50">Rincian Barang yang Diretur</th>
                                <th class="py-3.5 px-4 bg-slate-50">Solusi Retur</th>
                                <th class="py-3.5 px-4 text-right bg-slate-50">Total Nilai</th>
                                <th class="py-3.5 px-4 text-center bg-slate-50">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredReturns.length === 0">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    Belum ada data dokumen retur yang sesuai dengan kategori ini.
                                </td>
                            </tr>
                            <tr v-for="ret in filteredReturns" :key="ret.id" class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-mono font-bold text-slate-900 text-xs">{{ ret.return_number }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ new Date(ret.return_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span 
                                        v-if="ret.return_type === 'sales_pos'"
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200"
                                    >
                                        🛒 Retur Kasir Toko
                                    </span>
                                    <span 
                                        v-else-if="ret.return_type === 'sales_order'"
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-purple-50 text-purple-800 border border-purple-200"
                                    >
                                        🚚 Retur Sales Proyek
                                    </span>
                                    <span 
                                        v-else
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-blue-50 text-blue-800 border border-blue-200"
                                    >
                                        🏭 Retur ke Supplier
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">
                                        {{ ret.return_type === 'purchase_supplier' ? ret.supplier_name : (ret.customer_name || 'Pelanggan Umum') }}
                                    </div>
                                    <div v-if="ret.reference_number" class="text-[10px] text-slate-500 font-mono">
                                        Ref: {{ ret.reference_number }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="space-y-1">
                                        <div v-for="it in ret.items" :key="it.id" class="text-[11px] text-slate-700">
                                            &bull; <strong class="text-slate-900">{{ it.qty_returned }} {{ it.unit?.unit_name }}</strong> {{ it.product?.name }}
                                            <span 
                                                :class="it.condition === 'good_restock' ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50'"
                                                class="px-1.5 py-0.2 rounded text-[9px] font-bold ml-1"
                                            >
                                                {{ it.condition === 'good_restock' ? 'Segel Baik (Masuk Stok)' : 'Rusak (Klaim)' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span v-if="ret.resolution_type === 'refund_cash'" class="text-rose-700 font-bold text-xs">
                                        💵 Kembali Tunai (Refund)
                                    </span>
                                    <span v-else-if="ret.resolution_type === 'debt_deduction'" class="text-blue-700 font-bold text-xs">
                                        📝 Potong Piutang / Bon
                                    </span>
                                    <span v-else class="text-emerald-700 font-bold text-xs">
                                        🔄 Ganti Barang Baru
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-right font-black text-slate-900 text-xs whitespace-nowrap">
                                    {{ formatRupiah(ret.total_refund_amount) }}
                                </td>

                                <td class="py-3.5 px-4 text-center">
                                    <button 
                                        @click="openDetail(ret)"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-[11px] transition cursor-pointer"
                                    >
                                        Nota Retur
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Form Catat Retur Baru (Lengkap dengan Auto-Populate dari Penerimaan / SO / Kasir) -->
        <div v-if="isAddModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-5xl overflow-hidden shadow-2xl flex flex-col max-h-[94vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold">
                            <RotateCcw class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Catat Dokumen Pengembalian (Retur Barang)</h3>
                            <p class="text-xs text-slate-500">
                                Bisa tarik otomatis dari data Penerimaan Gudang / Faktur SO / Struk Kasir atau input manual.
                            </p>
                        </div>
                    </div>
                    <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitReturn" class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">
                    <!-- Tipe Retur Selector -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1.5">Pilih Kategori Retur *</label>
                        <div :class="availableReturnTypes.length === 2 ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'grid grid-cols-1 sm:grid-cols-3 gap-2.5'">
                            <button 
                                v-for="cat in availableReturnTypes"
                                :key="cat.id"
                                type="button"
                                @click="onReturnTypeChange(cat.id)"
                                :class="form.return_type === cat.id ? cat.activeClass + ' shadow-xs font-black' : 'bg-slate-50 text-slate-700 border border-slate-200'"
                                class="p-3.5 rounded-2xl text-left transition flex items-center gap-2.5 cursor-pointer"
                            >
                                <component :is="cat.icon" class="w-4 h-4 shrink-0" />
                                <div>
                                    <p class="text-xs font-bold leading-tight">{{ cat.name }}</p>
                                    <p class="text-[10px] opacity-80">{{ cat.desc }}</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- AUTO-POPULATE SOURCE DOCUMENT SELECTOR BANNER -->
                    <div class="p-4 bg-amber-50/80 border border-amber-200/80 rounded-2xl space-y-2">
                        <div class="flex items-center gap-2 text-amber-900 font-bold text-xs">
                            <Sparkles class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Tarik Otomatis dari Dokumen Asal (Penerimaan / Pesanan / Faktur):</span>
                        </div>

                        <!-- Dropdown for GR if purchase_supplier -->
                        <div v-if="form.return_type === 'purchase_supplier'" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                            <label class="sm:col-span-4 text-xs font-bold text-slate-700">Pilih Dokumen Penerimaan Barang (GR):</label>
                            <select 
                                v-model="selectedSourceDocumentId"
                                @change="onSourceDocumentSelected($event.target.value)"
                                class="sm:col-span-8 bg-white border border-amber-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                            >
                                <option value="">-- Pilih Dokumen Penerimaan Barang (Tarik Otomatis) --</option>
                                <option v-for="gr in recentGoodsReceipts" :key="gr.id" :value="gr.id">
                                    {{ gr.receipt_number }} &bull; {{ gr.supplier_name }} ({{ new Date(gr.receipt_date).toLocaleDateString('id-ID') }}) - {{ gr.items.length }} Item
                                </option>
                            </select>
                        </div>

                        <!-- Dropdown for SO if sales_order -->
                        <div v-else-if="form.return_type === 'sales_order'" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                            <label class="sm:col-span-4 text-xs font-bold text-slate-700">Pilih Dokumen Pesanan Sales (SO):</label>
                            <select 
                                v-model="selectedSourceDocumentId"
                                @change="onSourceDocumentSelected($event.target.value)"
                                class="sm:col-span-8 bg-white border border-amber-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                            >
                                <option value="">-- Pilih Dokumen Pesanan Sales Proyek (Tarik Otomatis) --</option>
                                <option v-for="so in recentSalesOrders" :key="so.id" :value="so.id">
                                    {{ so.so_number }} &bull; {{ so.customer?.name }} ({{ formatRupiah(so.total_amount) }}) - {{ so.items.length }} Item
                                </option>
                            </select>
                        </div>

                        <!-- Dropdown for POS Transaction if sales_pos -->
                        <div v-else-if="form.return_type === 'sales_pos'" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                            <label class="sm:col-span-4 text-xs font-bold text-slate-700">Pilih Struk / Faktur Kasir Toko:</label>
                            <select 
                                v-model="selectedSourceDocumentId"
                                @change="onSourceDocumentSelected($event.target.value)"
                                class="sm:col-span-8 bg-white border border-amber-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                            >
                                <option value="">-- Pilih Faktur Penjualan Kasir (Tarik Otomatis) --</option>
                                <option v-for="tx in recentTransactions" :key="tx.id" :value="tx.id">
                                    {{ tx.invoice_number }} &bull; {{ tx.customer?.name || 'Umum' }} ({{ formatRupiah(tx.total_amount) }}) - {{ tx.items.length }} Item
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Party & Resolution Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-slate-700 font-bold mb-1">
                                {{ form.return_type === 'purchase_supplier' ? 'Supplier / Distributor *' : 'Pelanggan / Pembeli *' }}
                            </label>
                            <select 
                                v-if="form.return_type === 'purchase_supplier'"
                                v-model="form.supplier_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs"
                            >
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <select 
                                v-else
                                v-model="form.customer_id"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs"
                            >
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.tier }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">No. Referensi Dokumen Asal</label>
                            <input 
                                v-model="form.reference_number" 
                                placeholder="Misal: GR-20260817-0001 / INV-... / SO-..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-mono text-xs font-bold"
                            />
                        </div>

                        <div>
                            <label class="block text-slate-700 font-bold mb-1">Solusi Penyelesaian Retur *</label>
                            <select 
                                v-model="form.resolution_type" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-900 font-bold text-xs"
                            >
                                <option value="replacement">Ganti Barang Baru (Tukar Unit)</option>
                                <option value="refund_cash">Kembalikan Uang Tunai (Refund)</option>
                                <option value="debt_deduction">Potong Tagihan Piutang / Bon</option>
                            </select>
                        </div>
                    </div>

                    <!-- Items Section with Spacious Table Structure -->
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black uppercase text-slate-900">Daftar Barang yang Dikembalikan</h4>
                                <p class="text-[11px] text-slate-500">Sesuaikan kuantiti barang yang diretur dan tentukan kondisi fisik.</p>
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
                                    <!-- Searchable Combobox for Product (Spacious) -->
                                    <div class="sm:col-span-5 relative">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Barang Listrik</label>
                                        <button 
                                            type="button"
                                            @click="activeProductDropdownIndex = (activeProductDropdownIndex === idx ? null : idx)"
                                            class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-left text-xs font-bold text-slate-900 flex items-center justify-between hover:border-amber-400 transition"
                                        >
                                            <span class="truncate">{{ getProductById(it.product_id)?.name || 'Pilih Produk...' }}</span>
                                            <ChevronDown class="w-4 h-4 text-slate-400 shrink-0 ml-1" />
                                        </button>

                                        <div 
                                            v-if="activeProductDropdownIndex === idx" 
                                            class="absolute top-full left-0 right-0 mt-1 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl p-2.5 space-y-2 max-h-64 overflow-y-auto"
                                        >
                                            <div class="relative">
                                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                                <input 
                                                    v-model="productSearchQueries[idx]"
                                                    type="text" 
                                                    placeholder="Ketik nama, kabel, saklar..."
                                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-900 focus:outline-none"
                                                />
                                            </div>

                                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                                <div 
                                                    v-for="p in getFilteredProductsForRow(idx)" 
                                                    :key="p.id"
                                                    @click="selectProductForRow(idx, p)"
                                                    :class="[
                                                        it.product_id === p.id ? 'bg-amber-50 text-amber-900 font-bold' : 'text-slate-700 hover:bg-slate-50',
                                                        'p-2 rounded-xl text-xs cursor-pointer flex items-center justify-between border transition'
                                                    ]"
                                                >
                                                    <div>
                                                        <p class="font-bold text-slate-900">{{ p.name }}</p>
                                                        <p class="text-[10px] text-slate-400">{{ p.brand?.name || '-' }} &bull; Sedia: {{ p.stock_physical }} {{ p.units[0]?.unit_name }}</p>
                                                    </div>
                                                    <Check v-if="it.product_id === p.id" class="w-4 h-4 text-amber-600 shrink-0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Satuan -->
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

                                    <!-- Qty Retur -->
                                    <div class="sm:col-span-1">
                                        <label class="block text-[10px] font-bold text-slate-700 mb-1">Qty</label>
                                        <input 
                                            v-model.number="it.qty_returned" 
                                            type="number" 
                                            step="0.1" 
                                            min="0.1" 
                                            required 
                                            class="w-full bg-white border border-slate-300 rounded-xl px-2 py-2 text-xs font-black text-slate-900 text-center"
                                        />
                                    </div>

                                    <!-- Kondisi Barang (Spacious Dropdown) -->
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 mb-1">Kondisi Barang</label>
                                        <select 
                                            v-model="it.condition" 
                                            class="w-full bg-white border border-slate-200 rounded-xl px-2.5 py-2 text-xs font-bold"
                                            :class="it.condition === 'good_restock' ? 'text-emerald-800' : 'text-rose-800'"
                                        >
                                            <option value="good_restock">Bagus (Masuk Stok)</option>
                                            <option value="damaged_claim">Rusak (Klaim)</option>
                                        </select>
                                    </div>

                                    <!-- Harga Satuan & Delete -->
                                    <div class="sm:col-span-2 flex items-center gap-1.5">
                                        <div class="w-full">
                                            <label class="block text-[10px] font-bold text-slate-500 mb-1">Harga Retur</label>
                                            <input 
                                                v-model.number="it.unit_price" 
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

                    <!-- Alasan Retur -->
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Alasan Pengembalian / Retur *</label>
                        <textarea 
                            v-model="form.reason" 
                            required
                            rows="2" 
                            placeholder="Contoh: Sisa proyek perumahan, barang utuh segel / MCB patah dari pabrik..."
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                        ></textarea>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-500">Estimasi Total Nilai Retur:</span>
                            <span class="text-sm font-black text-rose-700 ml-1.5">{{ formatRupiah(totalReturnEstimation) }}</span>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" @click="isAddModalOpen = false" class="px-4 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs cursor-pointer shadow-xs">
                                Proses & Simpan Retur
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DOKUMEN: Nota & Berita Acara Retur Resmi (Dot Matrix & A4 Support) -->
        <div v-if="isDetailModalOpen && selectedReturn" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white text-slate-900 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-4 border-b flex flex-wrap justify-between items-center gap-3 no-print bg-slate-50">
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-900">Nota Retur & Berita Acara</h3>
                        <p class="text-[11px] text-slate-500">Pilih format cetak sesuai jenis printer Anda</p>
                    </div>

                    <!-- Print Format Selector Tabs -->
                    <div class="flex items-center gap-1.5 bg-white p-1 rounded-2xl border border-slate-200 shadow-2xs">
                        <button 
                            @click="selectedPrintFormat = 'dot_matrix'"
                            :class="selectedPrintFormat === 'dot_matrix' ? 'bg-slate-900 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <span>📄 Print Dot Matrix</span>
                        </button>
                        <button 
                            @click="selectedPrintFormat = 'a4'"
                            :class="selectedPrintFormat === 'a4' ? 'bg-slate-900 text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                            class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer"
                        >
                            <span>📑 Standar HVS A4</span>
                        </button>
                    </div>

                    <button @click="isDetailModalOpen = false" class="text-slate-400 text-xs font-bold cursor-pointer hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- PREVIEW FORMAT 1: KERTAS CONTINUOUS FORM DOT MATRIX (9.5" x 11"/2) -->
                <div v-if="selectedPrintFormat === 'dot_matrix'" class="p-6 font-sans text-slate-900 bg-white border border-slate-300 rounded-2xl leading-normal space-y-3 print:border-none print:p-0 select-text max-w-3xl mx-auto shadow-sm overflow-y-auto flex-1">
                    <!-- Header Section -->
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-2.5">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-10 h-10 object-contain shrink-0 mt-0.5" />
                            <div>
                                <h2 class="font-black text-base uppercase tracking-tight text-slate-950">{{ settings?.store_name || 'TRISNA JAYA LISTRIK' }}</h2>
                                <p class="text-[11px] text-slate-600 mt-0.5">Alamat : {{ settings?.store_address || 'Jl. Raya Pekalongan No. 88' }}</p>
                                <p class="text-[11px] text-slate-600">Telepon/HP : {{ settings?.store_phone || '0812-3456-7890' }}</p>
                                <p class="text-[11px] text-slate-600">Email : {{ settings?.store_email || 'trisnajaya050@gmail.com' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <h3 class="font-black text-xl uppercase tracking-wider text-slate-950">NOTA RETUR</h3>
                            <table class="text-xs ml-auto mt-1 leading-tight">
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">No. Retur :</td>
                                    <td class="font-black font-mono text-slate-950">{{ selectedReturn.return_number }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Tanggal :</td>
                                    <td class="font-bold text-slate-900">{{ new Date(selectedReturn.return_date || selectedReturn.created_at).toLocaleDateString('id-ID') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Kategori :</td>
                                    <td class="font-bold text-slate-900 uppercase text-[10px]">
                                        {{ selectedReturn.return_type === 'purchase_supplier' ? 'Retur ke Supplier' : (selectedReturn.return_type === 'sales_order' ? 'Retur Proyek' : 'Retur Kasir Toko') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-right pr-2 text-slate-500">Petugas :</td>
                                    <td class="font-bold text-slate-900 uppercase">{{ selectedReturn.user?.name || user?.name }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="border-b-2 border-slate-950"></div>

                    <!-- Customer / Supplier Info & Big Total -->
                    <div class="flex justify-between items-center text-xs">
                        <div>
                            <p class="text-slate-500 text-[11px]">{{ selectedReturn.return_type === 'purchase_supplier' ? 'Supplier (Pabrikan):' : 'Pihak Pengembali:' }}</p>
                            <h4 class="font-black text-sm uppercase text-slate-950">
                                {{ selectedReturn.return_type === 'purchase_supplier' ? selectedReturn.supplier_name : (selectedReturn.customer_name || 'Pelanggan Umum') }}
                            </h4>
                            <p v-if="selectedReturn.reference_number" class="text-slate-700 text-[11px] font-mono">
                                Ref Asal: <strong>{{ selectedReturn.reference_number }}</strong>
                            </p>
                            <p class="text-slate-700 text-[11px]">
                                Penyelesaian: <strong>{{ selectedReturn.resolution_type === 'refund_cash' ? 'Pengembalian Dana (Refund)' : (selectedReturn.resolution_type === 'debt_deduction' ? 'Potong Piutang (Bon)' : 'Ganti Unit Baru') }}</strong>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">TOTAL NILAI RETUR</p>
                            <p class="text-2xl font-black text-rose-700 tracking-tight mt-0.5">{{ formatRupiah(selectedReturn.total_refund_amount) }}</p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table class="w-full border-collapse text-xs my-2">
                        <thead>
                            <tr class="border-y-2 border-slate-950 bg-slate-100 text-slate-900 font-black text-left">
                                <th class="py-1.5 px-2">NAMA BARANG</th>
                                <th class="py-1.5 px-2 text-center w-20">QTY</th>
                                <th class="py-1.5 px-2 text-center w-24">KONDISI</th>
                                <th class="py-1.5 px-2 text-right w-24">HARGA</th>
                                <th class="py-1.5 px-2 text-right w-28">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(it, idx) in selectedReturn.items" :key="idx">
                                <td class="py-1 px-2 font-medium text-slate-900">{{ it.product?.name }}</td>
                                <td class="py-1 px-2 text-center text-slate-800">{{ it.qty_returned }} {{ it.unit?.unit_name }}</td>
                                <td class="py-1 px-2 text-center text-[10px] font-semibold" :class="it.condition === 'good_restock' ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ it.condition === 'good_restock' ? 'Segel Baik' : 'Rusak/Cacat' }}
                                </td>
                                <td class="py-1 px-2 text-right text-slate-800">{{ formatRupiah(it.unit_price) }}</td>
                                <td class="py-1 px-2 text-right font-black text-slate-950">{{ formatRupiah(it.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Footer Section -->
                    <div class="border-t border-slate-950 pt-2 grid grid-cols-2 gap-4 text-xs">
                        <div class="space-y-2 text-[11px]">
                            <p class="italic text-slate-700">Terbilang: <span class="font-bold">{{ numberToWords(selectedReturn.total_refund_amount) }}</span></p>
                            <div>
                                <p class="font-bold text-slate-900">Alasan Retur:</p>
                                <p class="text-slate-700 italic">"{{ selectedReturn.reason || '-' }}"</p>
                            </div>
                            <p class="text-[10px] text-slate-500">*Dokumen ini merupakan bukti sah serah terima pengembalian barang.</p>
                        </div>

                        <div class="space-y-3">
                            <table class="w-full text-xs leading-tight">
                                <tr class="border-t border-slate-950 font-black text-sm">
                                    <td class="text-right pr-2 pt-1 text-slate-950">Total Retur :</td>
                                    <td class="text-right pt-1 text-rose-700">{{ formatRupiah(selectedReturn.total_refund_amount) }}</td>
                                </tr>
                            </table>

                            <!-- Signatures Block -->
                            <div class="grid grid-cols-2 gap-4 text-center pt-2">
                                <div>
                                    <p class="text-slate-600 font-medium">Pihak Pengembali,</p>
                                    <div class="h-10"></div>
                                    <p class="font-bold text-slate-900">( {{ selectedReturn.return_type === 'purchase_supplier' ? selectedReturn.supplier_name : (selectedReturn.customer_name || 'Pelanggan') }} )</p>
                                </div>
                                <div>
                                    <p class="text-slate-600 font-medium">Petugas Toko/Gudang,</p>
                                    <div class="h-10"></div>
                                    <p class="font-bold text-slate-900">( {{ selectedReturn.user?.name || user?.name || 'Petugas' }} )</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PREVIEW FORMAT 2: STANDAR HVS A4 -->
                <div v-else id="printable-document-a4" class="p-8 space-y-6 flex-1 overflow-y-auto bg-white">
                    <div class="border-b-2 border-slate-900 pb-4 flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo Trisna Jaya" class="w-14 h-14 object-contain" />
                            <div>
                                <h2 class="text-xl font-black tracking-tight text-slate-950 uppercase">{{ settings?.store_name || 'TRISNA JAYA LISTRIK' }}</h2>
                                <p class="text-xs text-slate-600 font-medium">Distributor & Suplier Perlengkapan Listrik</p>
                                <p class="text-xs text-slate-600">Jl. Raya Pekalongan No. 88 &bull; Telp: 0812-3456-7890</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm uppercase tracking-widest px-3.5 py-1 bg-rose-900 text-white font-black rounded-lg inline-block">
                                NOTA RETUR BARANG
                            </span>
                            <p class="text-xs text-slate-700 font-mono font-bold mt-2">No: <strong>{{ selectedReturn.return_number }}</strong></p>
                            <p class="text-xs text-slate-600">Tanggal: {{ new Date(selectedReturn.return_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs">
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Pihak Pengembali / Supplier:</p>
                            <p class="text-sm font-black text-slate-950">
                                {{ selectedReturn.return_type === 'purchase_supplier' ? selectedReturn.supplier_name : (selectedReturn.customer_name || 'Pelanggan Umum') }}
                            </p>
                            <p v-if="selectedReturn.reference_number" class="text-slate-700 font-mono mt-1">
                                Dokumen Referensi Asal: <strong>{{ selectedReturn.reference_number }}</strong>
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500 font-bold text-[10px] uppercase">Penyelesaian Retur:</p>
                            <p class="text-slate-900 font-bold">
                                {{ selectedReturn.resolution_type === 'refund_cash' ? 'Pengembalian Dana Tunai (Refund)' : (selectedReturn.resolution_type === 'debt_deduction' ? 'Pemotongan Piutang / Tagihan Bon' : 'Penggantian Unit Baru') }}
                            </p>
                            <p class="text-rose-900 font-bold italic mt-1 bg-rose-50 p-2 rounded-xl border border-rose-200">
                                Alasan: "{{ selectedReturn.reason }}"
                            </p>
                        </div>
                    </div>

                    <table class="w-full text-xs text-left border-collapse border border-slate-300">
                        <thead>
                            <tr class="bg-slate-100 font-black border-b border-slate-300 text-[11px]">
                                <th class="py-2.5 px-3 border-r border-slate-300 w-10 text-center">No</th>
                                <th class="py-2.5 px-3 border-r border-slate-300">Deskripsi Barang Listrik</th>
                                <th class="py-2.5 px-3 text-center border-r border-slate-300 w-28">Kuantiti</th>
                                <th class="py-2.5 px-3 text-center border-r border-slate-300 w-32">Kondisi Fisik</th>
                                <th class="py-2.5 px-3 text-right border-r border-slate-300 w-32">Harga Satuan</th>
                                <th class="py-2.5 px-3 text-right w-32">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="(it, i) in selectedReturn.items" :key="i">
                                <td class="py-3 px-3 border-r border-slate-300 text-center font-bold">{{ i + 1 }}</td>
                                <td class="py-3 px-3 border-r border-slate-300">
                                    <div class="font-bold text-slate-950 text-xs">{{ it.product?.name }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ it.product?.sku }}</div>
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-300 font-semibold">{{ it.qty_returned }} {{ it.unit?.unit_name }}</td>
                                <td class="py-3 px-3 text-center border-r border-slate-300">
                                    <span :class="it.condition === 'good_restock' ? 'text-emerald-800' : 'text-rose-800'" class="font-bold text-[10px]">
                                        {{ it.condition === 'good_restock' ? 'Segel Baik (Restock)' : 'Rusak / Cacat' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right border-r border-slate-300 font-mono">{{ formatRupiah(it.unit_price) }}</td>
                                <td class="py-3 px-3 text-right font-black font-mono text-slate-950">{{ formatRupiah(it.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-between items-center p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="text-xs text-slate-600">
                            <p>Petugas Penerima: <strong>{{ selectedReturn.user?.name }} ({{ selectedReturn.user?.role }})</strong></p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">TOTAL NILAI RETUR:</span>
                            <span class="text-xl font-black text-rose-700">{{ formatRupiah(selectedReturn.total_refund_amount) }}</span>
                        </div>
                    </div>

                    <div class="pt-8 border-t text-xs grid grid-cols-2 text-center text-slate-800">
                        <div>
                            <p class="font-bold">Pihak Pengembali,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-8">
                                {{ selectedReturn.return_type === 'purchase_supplier' ? selectedReturn.supplier_name : (selectedReturn.customer_name || 'Pelanggan') }}
                            </p>
                        </div>
                        <div>
                            <p class="font-bold">Petugas Gudang / Toko,</p>
                            <div class="h-16"></div>
                            <p class="border-t border-slate-400 pt-1 font-bold inline-block px-8">Trisna Jaya Listrik</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t flex justify-end gap-2 no-print">
                    <button @click="printDocument" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-2 cursor-pointer shadow-md">
                        <Printer class="w-4 h-4 text-amber-400" />
                        <span>Cetak Nota Retur ({{ selectedPrintFormat === 'dot_matrix' ? 'Dot Matrix' : 'HVS A4' }})</span>
                    </button>
                    <button @click="isDetailModalOpen = false" class="px-4 py-2.5 bg-slate-200 text-slate-700 font-bold text-xs rounded-xl cursor-pointer">Tutup</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
