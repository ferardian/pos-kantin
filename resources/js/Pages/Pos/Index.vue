<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Search, Barcode, ShoppingCart, Plus, Minus, Trash2, User, 
    CreditCard, DollarSign, QrCode, Clock, Printer, CheckCircle, Check,
    AlertCircle, Tag, Layers, ArrowRight, ArrowLeft, X, Phone, MapPin, Sparkles, ChevronDown,
    History, RotateCcw, FileText, Tablet, Monitor
} from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    employees: {
        type: Array,
        default: () => [],
    },
    customers: {
        type: Array,
        default: () => [],
    },
    recentTransactions: {
        type: Array,
        default: () => [],
    },
    pendingOrdersCount: Number,
    confirmedOrdersCount: Number,
    user: Object,
});

const page = usePage();
const settings = computed(() => page.props.settings || {});

// State
const searchQuery = ref('');

// Cek apakah device menggunakan layar sentuh (Tablet / iPad / Android Tab / HP)
const isTouchDevice = () => {
    if (typeof window === 'undefined') return false;
    return (
        'ontouchstart' in window ||
        navigator.maxTouchPoints > 0 ||
        (window.matchMedia && window.matchMedia('(pointer: coarse)').matches)
    );
};

const isTouchMode = ref(false);

const toggleTouchMode = () => {
    isTouchMode.value = !isTouchMode.value;
    localStorage.setItem('pos_touch_mode', isTouchMode.value ? 'true' : 'false');
};

// Global Search Autofocus Helper
const focusSearchInput = (selectAll = false, force = false) => {
    // Pada Mode Tablet / Layar Sentuh, cegah auto-focus agar virtual keyboard tidak terus-menerus muncul
    if (!force && isTouchMode.value) {
        return;
    }
    nextTick(() => {
        const input = document.getElementById('product-search-input');
        if (input) {
            input.focus({ preventScroll: true });
            if (selectAll) {
                input.select();
            }
        }
    });
};

const selectedCategory = ref('all');
const selectedCustomer = ref(
    (props.customers && props.customers.length > 0)
        ? (props.customers.find(c => c.tier === 'eceran') || props.customers[0])
        : { id: null, name: 'Pelanggan Umum', tier: 'eceran' }
);
const activePriceTier = ref(selectedCustomer.value?.tier || 'eceran'); // Can be overridden
const cart = ref([]);
const isMobileCartOpen = ref(false);
const isCheckoutOpen = ref(false);
const isCustomerModalOpen = ref(false);
const searchCustomerQuery = ref('');
const isAddingNewCustomer = ref(false);
const newCustomerForm = useForm({
    name: '',
    phone: '',
    address: '',
    tier: 'eceran',
    credit_limit: 0,
});
const isHistoryModalOpen = ref(false);
const historySearch = ref('');
const isReceiptOpen = ref(false);
const normalizePrintFormat = (format) => {
    if (!format) return 'thermal';
    const f = String(format).toLowerCase().trim();
    if (f.startsWith('thermal')) return 'thermal';
    if (f.includes('dot') || f.includes('matrix')) return 'dot_matrix';
    if (f.includes('invoice') || f.includes('a4')) return 'invoice';
    return 'thermal';
};

const selectedPrintFormat = ref('thermal'); // 'thermal', 'invoice', 'dot_matrix'

const lastTransaction = ref(null);

// Auto-refocus ke search input setiap kali modal checkout / customer / history / receipt ditutup
watch([isCheckoutOpen, isCustomerModalOpen, isHistoryModalOpen, isReceiptOpen], ([checkout, customer, history, receipt]) => {
    if (!checkout && !customer && !history && !receipt) {
        focusSearchInput();
    }
});

const printFormats = [
    { id: 'thermal', label: '🧾 Thermal', title: 'Printer Kasir Mini Roll (58/80mm)' },
    { id: 'dot_matrix', label: '📄 Print Dot Matrix', title: 'Faktur Continuous Form Ukuran 9.5 x 5.5 inch' },
    { id: 'invoice', label: '📄 Invoice A4', title: 'Faktur Standar Formal Kertas A4' }
];

const priceTiers = [
    { id: 'eceran', label: 'Retail' },
    { id: 'tukang', label: 'Bronze' },
    { id: 'kontraktor', label: 'Gold' },
    { id: 'grosir', label: 'Diamond' }
];

// Payment Form
const checkoutForm = useForm({
    customer_id: selectedCustomer.value?.id || null,
    items: [],
    total_gross: 0,
    discount: 0,
    total_net: 0,
    paid_amount: 0,
    payment_method: 'cash',
    due_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    notes: '',
    sales_order_id: null,
    employee_receivable: null,
});

// Categories list
const categories = computed(() => {
    const cats = [{ id: 'all', name: 'Semua Kategori' }];
    const uniqueCats = new Map();
    props.products.forEach(p => {
        if (p.category && !uniqueCats.has(p.category.id)) {
            uniqueCats.set(p.category.id, p.category);
            cats.push({ id: p.category.id, name: p.category.name });
        }
    });
    return cats;
});

// Filtered Products
const filteredProducts = computed(() => {
    return props.products.filter(p => {
        const matchesCategory = selectedCategory.value === 'all' || p.category_id === selectedCategory.value;
        const q = searchQuery.value.toLowerCase().trim();
        const matchesSearch = !q || 
            p.name.toLowerCase().includes(q) || 
            p.sku.toLowerCase().includes(q) || 
            (p.barcode && p.barcode.includes(q)) ||
            (p.brand && p.brand.name.toLowerCase().includes(q));
        return matchesCategory && matchesSearch;
    });
});

// 4 Price Tiers calculation
const getUnitPrice = (unit) => {
    if (!unit) return 0;
    return Number(unit.price_retail || 0);
};

const getTierLabel = (tier) => {
    switch (tier) {
        case 'eceran': return 'Retail';
        case 'tukang': return 'Bronze';
        case 'kontraktor': return 'Gold';
        case 'grosir': return 'Diamond';
        default: return tier;
    }
};

const getTierBadgeClass = (tier) => {
    switch (tier) {
        case 'eceran': return 'bg-slate-100 text-slate-700 border-slate-300';
        case 'tukang': return 'bg-amber-800 text-white border-amber-900 shadow-2xs font-black';
        case 'kontraktor': return 'bg-amber-300 text-amber-950 border-amber-500 shadow-2xs font-black';
        case 'grosir': return 'bg-sky-600 text-white border-sky-700 shadow-2xs font-black';
        default: return 'bg-slate-100 text-slate-700 border-slate-300';
    }
};

// Add to Cart
const addToCart = (product, specificUnit = null) => {
    const unit = specificUnit || product.units.find(u => u.is_base_unit) || product.units[0];
    const price = getUnitPrice(unit);
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id && item.unit.id === unit.id);

    if (existingIndex > -1) {
        cart.value[existingIndex].qty += 1;
        cart.value[existingIndex].subtotal = cart.value[existingIndex].qty * cart.value[existingIndex].unit_price;
    } else {
        cart.value.push({
            product,
            unit,
            qty: 1,
            unit_price: price,
            subtotal: price,
        });
    }

    focusSearchInput();
};

const getItemQtyInCart = (productId, unitId = null) => {
    if (unitId) {
        const item = cart.value.find(i => i.product.id === productId && i.unit.id === unitId);
        return item ? item.qty : 0;
    }
    return cart.value
        .filter(i => i.product.id === productId)
        .reduce((sum, i) => sum + i.qty, 0);
};

const updateUnitQtyInCatalog = (product, unit, delta) => {
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id && item.unit.id === unit.id);
    if (existingIndex > -1) {
        const newQty = cart.value[existingIndex].qty + delta;
        if (newQty <= 0) {
            cart.value.splice(existingIndex, 1);
        } else {
            cart.value[existingIndex].qty = Number(newQty.toFixed(2));
            cart.value[existingIndex].subtotal = cart.value[existingIndex].qty * cart.value[existingIndex].unit_price;
        }
    } else if (delta > 0) {
        addToCart(product, unit);
    }
    focusSearchInput();
};

// Update unit inside cart (recalculate using item's own tier)
const changeItemUnit = (itemIndex, newUnitId) => {
    const item = cart.value[itemIndex];
    if (!item) return;
    const newUnit = item.product.units.find(u => u.id === Number(newUnitId));
    if (newUnit) {
        item.unit = newUnit;
        item.unit_price = getUnitPrice(newUnit);
        item.subtotal = item.qty * item.unit_price;
    }
};



// Change active price tier (sets active tier for catalog & future items without altering existing locked items in cart)
const setPriceTier = (tier) => {
    activePriceTier.value = tier;
    focusSearchInput();
};

// Customer Selection
const selectCustomer = (cust) => {
    selectedCustomer.value = cust;
    checkoutForm.customer_id = cust.id;

    isCustomerModalOpen.value = false;
    searchCustomerQuery.value = '';
    isAddingNewCustomer.value = false;
    focusSearchInput();
};

const filteredCustomersModal = computed(() => {
    const q = searchCustomerQuery.value.toLowerCase().trim();
    const list = [...(props.customers || [])].sort((a, b) => {
        if (a.tier === 'eceran' && b.tier !== 'eceran') return -1;
        if (b.tier === 'eceran' && a.tier !== 'eceran') return 1;
        return (a.name || '').localeCompare(b.name || '', 'id', { numeric: true });
    });
    if (!q) return list;
    return list.filter(c => {
        return (c.name && c.name.toLowerCase().includes(q)) ||
               (c.phone && c.phone.toLowerCase().includes(q)) ||
               (c.address && c.address.toLowerCase().includes(q));
    });
});

const submitNewCustomer = () => {
    if (!newCustomerForm.name.trim()) return;

    newCustomerForm.post('/customers', {
        preserveScroll: true,
        onSuccess: () => {
            const addedName = newCustomerForm.name.trim().toLowerCase();
            isAddingNewCustomer.value = false;
            newCustomerForm.reset();
            searchCustomerQuery.value = '';

            setTimeout(() => {
                const found = (props.customers || []).find(c => c.name.toLowerCase() === addedName) || { id: null, name: 'Pelanggan Umum', tier: 'eceran' };
                if (found) {
                    selectCustomer(found);
                }
            }, 100);
        },
    });
};

// Cart Calculations
const subtotalGross = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

const totalNet = computed(() => {
    return Math.max(0, subtotalGross.value - (checkoutForm.discount || 0));
});

const changeAmount = computed(() => {
    return Math.max(0, (checkoutForm.paid_amount || 0) - totalNet.value);
});

// Modify Qty
const updateQty = (index, delta) => {
    const item = cart.value[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        cart.value.splice(index, 1);
    } else {
        item.qty = Number(newQty.toFixed(2));
        item.subtotal = item.qty * item.unit_price;
    }
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    cart.value = [];
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

// Bon / Piutang Karyawan RSIA State
const isReceivableChecked = ref(false);
const selectedEmployeeId = ref('');
const receivableAmount = ref(0);
const receivableNotes = ref('');
const employeeSearchQuery = ref('');
const isEmployeeDropdownOpen = ref(false);
const employeeDropdownRef = ref(null);

const selectedEmployeeObj = computed(() => {
    return (props.employees || []).find(e => e.id === selectedEmployeeId.value) || null;
});

const filteredEmployees = computed(() => {
    const list = props.employees || [];
    const q = (employeeSearchQuery.value || '').toLowerCase().trim();
    if (!q) return list;
    return list.filter(e => 
        (e.name && e.name.toLowerCase().includes(q)) ||
        (e.department && e.department.toLowerCase().includes(q)) ||
        (e.nik && e.nik.toLowerCase().includes(q))
    );
});

const pickEmployee = (emp) => {
    selectedEmployeeId.value = emp.id;
    isEmployeeDropdownOpen.value = false;
    employeeSearchQuery.value = '';
};

const clearSelectedEmployee = () => {
    selectedEmployeeId.value = '';
    employeeSearchQuery.value = '';
};

const selectFirstEmployee = () => {
    if (filteredEmployees.value.length > 0) {
        pickEmployee(filteredEmployees.value[0]);
    }
};

watch(isEmployeeDropdownOpen, (val) => {
    if (val && !isTouchMode.value) {
        nextTick(() => {
            const el = document.getElementById('employee-search-dropdown-input');
            if (el) el.focus();
        });
    }
});

const handleClickOutsideEmployee = (e) => {
    if (employeeDropdownRef.value && !employeeDropdownRef.value.contains(e.target)) {
        isEmployeeDropdownOpen.value = false;
    }
};

// Open Checkout Modal
const openCheckout = () => {
    if (cart.value.length === 0) return;
    checkoutForm.items = cart.value.map(i => ({
        product_id: i.product.id,
        product_unit_id: i.unit.id,
        qty: i.qty,
        unit_price: i.unit_price,
        subtotal: i.subtotal,
    }));
    checkoutForm.total_gross = subtotalGross.value;
    checkoutForm.total_net = totalNet.value;
    checkoutForm.paid_amount = totalNet.value;
    isReceivableChecked.value = false;
    selectedEmployeeId.value = '';
    employeeSearchQuery.value = '';
    isEmployeeDropdownOpen.value = false;
    receivableAmount.value = 0;
    receivableNotes.value = '';
    isCheckoutOpen.value = true;
};

const setExactPayment = () => {
    checkoutForm.paid_amount = totalNet.value;
};

const addQuickMoney = (amount) => {
    checkoutForm.paid_amount = (Number(checkoutForm.paid_amount) || 0) + amount;
};

const setBonFull = () => {
    receivableAmount.value = totalNet.value;
    checkoutForm.paid_amount = 0;
    receivableNotes.value = 'Belum bayar / uang dibawa dulu';
};

const setBonChange = () => {
    if (changeAmount.value > 0) {
        receivableAmount.value = changeAmount.value;
        receivableNotes.value = 'Kembalian Rp ' + changeAmount.value + ' belum diambil';
    } else {
        receivableAmount.value = totalNet.value;
        receivableNotes.value = 'Sisa kembalian belum diserahkan';
    }
};

watch(isReceivableChecked, (val) => {
    if (val) {
        if (!receivableAmount.value || Number(receivableAmount.value) === 0) {
            if (changeAmount.value > 0) {
                setBonChange();
            } else {
                setBonFull();
            }
        }
    } else {
        if (checkoutForm.paid_amount === 0) {
            checkoutForm.paid_amount = totalNet.value;
        }
    }
});

// Filtered History Transactions
const filteredHistoryTransactions = computed(() => {
    const q = historySearch.value.toLowerCase().trim();
    const list = props.recentTransactions || [];
    if (!q) return list;
    return list.filter(trx => 
        (trx.invoice_number && trx.invoice_number.toLowerCase().includes(q)) ||
        (trx.customer?.name && trx.customer.name.toLowerCase().includes(q)) ||
        (trx.payment_method && trx.payment_method.toLowerCase().includes(q)) ||
        (trx.items && trx.items.some(it => it.product?.name?.toLowerCase().includes(q)))
    );
});

// Open Reprint from History
const openReprint = (trx) => {
    lastTransaction.value = {
        id: trx.id,
        invoice_number: trx.invoice_number,
        customer: trx.customer,
        customer_name: trx.customer?.name || 'Pelanggan Umum',
        tier_label: getTierLabel(trx.customer?.tier || 'eceran'),
        items: (trx.items || []).map(it => ({
            product: it.product || { name: 'Item Penjualan' },
            unit: it.unit || { unit_name: 'Pcs' },
            qty: it.qty,
            unit_price: it.unit_price,
            subtotal: it.subtotal,
        })),
        total_gross: trx.total_gross || trx.total_net,
        discount: trx.discount || 0,
        total_net: trx.total_net,
        paid_amount: trx.paid_amount,
        change_amount: trx.change_amount || 0,
        payment_method: trx.payment_method,
        due_date: trx.due_date,
        notes: trx.notes,
        date: new Date(trx.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }),
        raw_date: new Date(trx.created_at).toLocaleDateString('id-ID'),
        is_reprint: true, // Mark as reprint copy!
    };
    isHistoryModalOpen.value = false;
    selectedPrintFormat.value = normalizePrintFormat(settings.value?.default_print_format);
    isReceiptOpen.value = true;
};

// Submit Checkout
const submitCheckout = () => {
    if (isReceivableChecked.value) {
        if (!selectedEmployeeId.value) {
            alert('Silakan pilih Pegawai RSIA terlebih dahulu!');
            return;
        }
        if (!Number(receivableAmount.value) || Number(receivableAmount.value) <= 0) {
            alert('Nominal Bon / Piutang harus lebih dari 0!');
            return;
        }
        checkoutForm.employee_receivable = {
            employee_id: selectedEmployeeId.value,
            amount: Number(receivableAmount.value),
            notes: receivableNotes.value || 'Bon Kasir POS Kantin',
        };
    } else {
        checkoutForm.employee_receivable = null;
        if (checkoutForm.payment_method === 'cash' && Number(checkoutForm.paid_amount) < Number(totalNet.value)) {
            alert('Jumlah uang diterima kurang dari total tagihan! Jika pegawai belum bayar, silakan centang "Catat Bon / Piutang Karyawan RSIA".');
            return;
        }
    }
    checkoutForm.post('/pos/checkout', {
        onSuccess: () => {
            isCheckoutOpen.value = false;
            isMobileCartOpen.value = false;
            lastTransaction.value = {
                invoice_number: 'INV-' + Math.floor(Math.random() * 900000 + 100000),
                customer: selectedCustomer.value,
                customer_name: selectedCustomer.value?.name,
                tier_label: getTierLabel(activePriceTier.value),
                items: [...cart.value],
                total_gross: subtotalGross.value,
                discount: checkoutForm.discount,
                total_net: totalNet.value,
                paid_amount: checkoutForm.paid_amount,
                change_amount: changeAmount.value,
                payment_method: checkoutForm.payment_method,
                due_date: checkoutForm.due_date,
                notes: checkoutForm.notes,
                date: new Date().toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }),
                raw_date: new Date().toLocaleDateString('id-ID'),
                is_reprint: false,
            };
            selectedPrintFormat.value = normalizePrintFormat(settings.value?.default_print_format);
            clearCart();
            isReceiptOpen.value = true;
        },
    });
};

const nl2br = (str) => {
    if (!str) return '';
    return str.replace(/\\n/g, '\n').replace(/\n/g, '<br>');
};

const formatTextWithBreaks = (text) => {
    if (!text) return '';
    return text.replace(/\\n/g, '\n');
};

// Standalone Self-Contained Receipt HTML Builder (No external network/CSS dependencies)
const buildPrintReceiptHtml = (trx, format, st, user) => {
    const normFmt = normalizePrintFormat(format);
    const isThermal = normFmt === 'thermal';
    const isDotMatrix = normFmt === 'dot_matrix';
    const isReprint = trx.is_reprint;

    const storeName = st.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN';
    const storeTagline = st.store_tagline || 'Kantin & Koperasi RSIA Aisyiyah Pekajangan';
    const storeAddress = st.store_address || '';
    const storePhone = st.store_phone || '';
    const storeEmail = st.store_email || '';
    const bankInfo = st.bank_info || 'BCA: 8830-123-456 a.n KOPERASI RSIA AISYIYAH PEKAJANGAN';
    const invoiceTerms = st.invoice_terms || 'Barang yang sudah diterima dalam kondisi baik menjadi tanggung jawab pembeli.';
    const receiptFooter = st.receipt_footer || 'Terima kasih atas kunjungan Anda!';
    const cashierName = user?.name || 'Kasir';
    const storeLogo = st.store_logo || '/pos-kantin/images/logo.png';

    const items = trx.items || [];
    const invoiceNum = trx.invoice_number || 'INV-00000';
    const custName = trx.customer_name || trx.customer?.name || 'Pelanggan Umum';
    const tierLabel = trx.tier_label || 'Retail';
    const payMethod = (trx.payment_method || 'cash').toUpperCase();
    const dateStr = trx.date || new Date().toLocaleString('id-ID');
    const rawDate = trx.raw_date || dateStr;
    const dueDate = trx.due_date ? `(Jatuh Tempo: ${trx.due_date})` : '';

    if (isThermal) {
        const itemsHtml = items.map(it => `
            <div style="margin-bottom: 3px;">
                <div style="font-weight: 700; font-size: 7.5pt; color: #000000; line-height: 1.15; word-break: break-word;">${it.product?.name || 'Item'}</div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 7pt; color: #000000; margin-top: 1px;">
                    <span>${it.qty} ${it.unit?.unit_name || 'Pcs'} x ${formatRupiah(it.unit_price)}</span>
                    <span style="font-weight: 700; text-align: right; white-space: nowrap;">${formatRupiah(it.subtotal)}</span>
                </div>
            </div>
        `).join('');

        return `
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="utf-8">
                <title>Cetak Nota ${invoiceNum}</title>
                <style>
                    @page { 
                        margin: 0 !important; 
                        size: auto; 
                    }
                    * { 
                        box-sizing: border-box; 
                        margin: 0; 
                        padding: 0; 
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    html, body {
                        margin: 0 !important; 
                        padding: 1mm 1mm 2mm 1mm !important;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        font-size: 7.2pt; 
                        line-height: 1.2;
                        color: #000000; 
                        background: #ffffff;
                        width: 42mm !important; 
                        max-width: 42mm !important;
                        font-weight: 600;
                    }
                    .dashed { 
                        border-bottom: 1px dashed #000000; 
                        margin: 2.5px 0; 
                        padding-bottom: 2.5px; 
                    }
                    .row { 
                        display: flex; 
                        justify-content: space-between; 
                        align-items: flex-start;
                        margin-bottom: 1.5px; 
                        gap: 2px;
                    }
                    .val {
                        text-align: right;
                        font-weight: bold;
                        white-space: nowrap;
                    }
                </style>
            </head>
            <body>
                ${isReprint ? '<div style="text-align:center; font-weight:900; font-size:6.8pt; border:1px solid #000000; padding:1px; margin-bottom:2.5px; letter-spacing:0.2px;">*** SALINAN / CETAK ULANG ***</div>' : ''}
                <div style="text-align: center;" class="dashed">
                    <h2 style="margin: 0 0 1.5px 0; font-size: 9pt; font-weight: 900; text-transform: uppercase; color: #000000; letter-spacing: 0.2px;">${storeName}</h2>
                    <div style="font-size: 6.8pt; color: #000000; line-height: 1.15;">${storeTagline}</div>
                    <div style="font-size: 6.8pt; color: #000000; line-height: 1.15;">${storeAddress}</div>
                    <div style="font-size: 6.8pt; font-weight: 700; color: #000000; margin-top: 1px;">Telp/WA: ${storePhone}</div>
                </div>

                <div class="dashed" style="font-size: 7pt; color: #000000;">
                    <div class="row"><span>No:</span><span class="val">${invoiceNum}</span></div>
                    <div class="row"><span>Tgl:</span><span class="val">${dateStr}</span></div>
                    <div class="row"><span>Pelanggan:</span><span class="val">${custName}</span></div>
                    <div class="row"><span>Kasir:</span><span class="val">${cashierName}</span></div>
                </div>

                <div class="dashed">
                    ${itemsHtml}
                </div>

                <div class="dashed" style="font-size: 7.2pt; color: #000000;">
                    <div class="row"><span>Subtotal:</span><span class="val">${formatRupiah(trx.total_gross)}</span></div>
                    ${trx.discount > 0 ? `<div class="row"><span>Diskon:</span><span class="val">-${formatRupiah(trx.discount)}</span></div>` : ''}
                    <div class="row" style="font-weight: 900; font-size: 8.5pt; border-top: 1px solid #000000; padding-top: 2px; margin-top: 2px;">
                        <span>TOTAL:</span><span class="val">${formatRupiah(trx.total_net)}</span>
                    </div>
                    <div class="row" style="margin-top: 2px;"><span>Metode:</span><span class="val">${payMethod}</span></div>
                    ${trx.payment_method === 'tempo' ? `<div class="row" style="font-weight: bold;"><span>Jatuh Tempo:</span><span class="val">${trx.due_date}</span></div>` : `
                        <div class="row"><span>Bayar:</span><span class="val">${formatRupiah(trx.paid_amount)}</span></div>
                        <div class="row"><span>Kembali:</span><span class="val">${formatRupiah(trx.change_amount)}</span></div>
                    `}
                </div>

                <div style="text-align: center; font-size: 6.8pt; color: #000000; margin-top: 3px; white-space: pre-line; line-height: 1.2;">
                    ${nl2br(receiptFooter)}
                </div>
            </body>
            </html>
        `;
    }

    if (isDotMatrix) {
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

        const custAddress = trx.customer?.address || trx.customer_address || '-';
        const custPhone = trx.customer?.phone || trx.customer_phone || '-';
        const custEmail = trx.customer?.email || trx.customer_email || '-';
        const salesOrCashier = trx.sales?.name || trx.cashier?.name || cashierName;
        const formattedDate = formatCleanDate(trx.created_at || trx.date || new Date());
        const terbilangText = numberToWords(trx.total_net);

        let payMethodLabel = 'Tunai';
        if (trx.payment_method === 'transfer') payMethodLabel = 'Transfer Bank';
        else if (trx.payment_method === 'tempo') payMethodLabel = `Tempo ${trx.due_date ? '(Jatuh Tempo: ' + trx.due_date + ')' : ''}`;
        else if (trx.payment_method === 'qris') payMethodLabel = 'QRIS';

        const storeNameDisplay = (storeName && storeName.trim() !== '') ? storeName : 'KOPERASI RSIA AISYIYAH PEKAJANGAN';
        const storeAddressDisplay = storeAddress || 'Jl. Raya Karanganyar, Kebonsari, Karangsari, Kab. Pekalongan';
        const storePhoneDisplay = storePhone || '+62 815-7345-5951';
        const storeEmailDisplay = storeEmail || 'kantin@rsiaaisyiyah.com';

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

        const bankInfoFormatted = (bankInfo || 'Bank : BCA\nNo. Rekening : 2501294511\nAtas Nama : YUNIAR DWI RAHMAWATI')
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
                ${isReprint ? '<div style="text-align:center; font-weight:900; font-size:9.5pt; border-top:2px dashed #000; border-bottom:2px dashed #000; padding:2px 0; margin-bottom:3px; letter-spacing:1px;">[ *** SALINAN FAKTUR / CETAK ULANG *** ]</div>' : ''}

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
                                    <td style="text-align: right; padding-right: 6px; color: #000;">Sales/Kasir :</td>
                                    <td style="font-weight: bold; text-align: left; text-transform: uppercase;">${salesOrCashier}</td>
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
                                Rp${formatNumberClean(trx.total_net)}
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
                                        <td style="text-align: right; font-weight: bold; width: 45%;">${formatNumberClean(trx.total_gross)}</td>
                                    </tr>
                                    ${trx.discount > 0 ? `
                                    <tr>
                                        <td style="text-align: right; padding-right: 6px;">Diskon :</td>
                                        <td style="text-align: right; font-weight: bold;">${formatNumberClean(trx.discount)}</td>
                                    </tr>` : ''}
                                    <tr style="border-top: 1.5px solid #000; font-weight: 900; font-size: 11pt;">
                                        <td style="text-align: right; padding-right: 6px; padding-top: 2px;">TOTAL :</td>
                                        <td style="text-align: right; padding-top: 2px; font-family: 'Courier New', monospace;">Rp${formatNumberClean(trx.total_net)}</td>
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
                                            <div style="font-weight: 900;">( .................... )</div>
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
    }

    // Default: INVOICE A4
    const a4Rows = items.map((it, idx) => `
        <tr style="border-bottom: 1px solid #e2e8f0;">
            <td style="padding: 10px 12px; text-align: center; color: #64748b; font-weight: bold;">${idx + 1}</td>
            <td style="padding: 10px 12px; font-weight: bold; color: #0f172a;">${it.product?.name || 'Item'}</td>
            <td style="padding: 10px 12px; text-align: center; font-weight: 900;">${it.qty}</td>
            <td style="padding: 10px 12px; text-align: center; color: #475569;">${it.unit?.unit_name || 'Pcs'}</td>
            <td style="padding: 10px 12px; text-align: right; color: #334155;">${formatRupiah(it.unit_price)}</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 900; color: #0f172a;">${formatRupiah(it.subtotal)}</td>
        </tr>
    `).join('');

    return `
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Invoice ${invoiceNum}</title>
            <style>
                @page { margin: 10mm; size: A4 portrait; }
                * { box-sizing: border-box; }
                body {
                    margin: 0; padding: 12px;
                    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    font-size: 11px; color: #0f172a; background: #fff; line-height: 1.4;
                }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #475569; }
            </style>
        </head>
        <body>
            ${isReprint ? '<div style="background:#fef3c7; border:1px solid #f59e0b; color:#92400e; font-weight:900; text-align:center; padding:6px; border-radius:8px; margin-bottom:14px; text-transform:uppercase; font-size:11px; letter-spacing:1px;">*** SALINAN FAKTUR RESMI / CETAK ULANG (REPRINT) ***</div>' : ''}
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px;">
                <div>
                    <h1 style="margin: 0 0 2px 0; font-size: 18px; font-weight: 900; text-transform: uppercase; color: #0f172a;">${storeName}</h1>
                    <div style="font-size: 11px; font-weight: bold; color: #d97706;">${storeTagline}</div>
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">${storeAddress}</div>
                    <div style="font-size: 10px; color: #475569; font-weight: 600;">Telp: ${storePhone} ${storeEmail ? `| Email: ${storeEmail}` : ''}</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #0f172a;">INVOICE</div>
                    <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #334155; margin-top: 2px;">${invoiceNum}</div>
                    <div style="display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 9px; font-weight: 900; text-transform: uppercase; margin-top: 6px; ${trx.payment_method === 'tempo' ? 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d;' : 'background:#dcfce7; color:#166534; border:1px solid #86efac;'}">
                        ${trx.payment_method === 'tempo' ? 'STATUS: TEMPO / PIUTANG' : 'STATUS: LUNAS'}
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 14px; margin-bottom: 20px;">
                <div>
                    <span style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px;">Ditagihkan Kepada:</span>
                    <div style="font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px;">${custName}</div>
                    <div style="font-size: 10px; color: #475569;">${trx.customer?.address || 'Pelanggan Toko'}</div>
                    <div style="font-size: 10px; color: #475569;">Telp: ${trx.customer?.phone || '-'}</div>
                </div>
                <div style="text-align: right; font-size: 11px;">
                    <div><span style="color: #64748b;">Tanggal Faktur: </span><strong style="color: #0f172a;">${rawDate}</strong></div>
                    <div style="margin-top: 2px;"><span style="color: #64748b;">Metode Bayar: </span><strong style="color: #0f172a; text-transform: uppercase;">${payMethod}</strong></div>
                    ${trx.due_date ? `<div style="margin-top: 2px; color: #dc2626; font-weight: bold;"><span>Jatuh Tempo: </span><span>${trx.due_date}</span></div>` : ''}
                    <div style="margin-top: 2px;"><span style="color: #64748b;">Kasir Pelaksana: </span><strong style="color: #0f172a;">${cashierName}</strong></div>
                </div>
            </div>

            <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px; text-align: center;">#</th>
                            <th>Deskripsi Produk / Menu</th>
                            <th style="width: 60px; text-align: center;">Qty</th>
                            <th style="width: 70px; text-align: center;">Satuan</th>
                            <th style="width: 110px; text-align: right;">Harga Satuan</th>
                            <th style="width: 120px; text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${a4Rows}
                    </tbody>
                </table>
            </div>

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; align-items: start;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; font-size: 10px;">
                        <strong style="font-size: 10px; text-transform: uppercase; color: #1e293b; display: block; margin-bottom: 4px;">Rekening Pembayaran Bank:</strong>
                        <div style="color: #334155; font-family: monospace; line-height: 1.5;">${nl2br(bankInfo)}</div>
                    </div>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; font-size: 10px;">
                        <strong style="font-size: 10px; text-transform: uppercase; color: #1e293b; display: block; margin-bottom: 4px;">Syarat & Ketentuan Faktur:</strong>
                        <div style="color: #475569; line-height: 1.4;">${nl2br(invoiceTerms)}</div>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;">
                    <div style="display: flex; justify-content: space-between; color: #475569; margin-bottom: 6px;">
                        <span>Subtotal Penjualan:</span>
                        <strong style="color: #0f172a;">${formatRupiah(trx.total_gross)}</strong>
                    </div>
                    ${trx.discount > 0 ? `
                        <div style="display: flex; justify-content: space-between; color: #dc2626; font-weight: bold; margin-bottom: 6px;">
                            <span>Potongan / Diskon:</span>
                            <span>-${formatRupiah(trx.discount)}</span>
                        </div>
                    ` : ''}
                    <div style="display: flex; justify-content: space-between; font-weight: 900; font-size: 14px; border-top: 2px solid #cbd5e1; padding-top: 8px; color: #0f172a; margin-top: 4px;">
                        <span>TOTAL TAGIHAN:</span>
                        <span style="color: #d97706;">${formatRupiah(trx.total_net)}</span>
                    </div>
                    <div style="margin-top: 10px; padding: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 9px; color: #475569;">
                        Terbilang: <strong style="color: #0f172a;">${numberToWords(trx.total_net)}</strong>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 40px; font-size: 11px;">
                <div style="width: 200px;">
                    <p style="color: #64748b; margin: 0;">Tanda Terima Pelanggan,</p>
                    <div style="height: 50px;"></div>
                    <p style="font-weight: bold; border-top: 1px solid #94a3b8; padding-top: 4px; margin: 0;">( ${custName} )</p>
                </div>
                <div style="width: 200px;">
                    <p style="color: #64748b; margin: 0;">Hormat Kami,</p>
                    <div style="height: 50px;"></div>
                    <p style="font-weight: bold; border-top: 1px solid #94a3b8; padding-top: 4px; margin: 0;">( ${storeName} )</p>
                </div>
            </div>
        </body>
        </html>
    `;
};

const printReceipt = () => {
    if (!lastTransaction.value) return;

    let iframe = document.getElementById('pos-print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'pos-print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
    }

    const html = buildPrintReceiptHtml(lastTransaction.value, selectedPrintFormat.value, settings.value, props.user);

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    const triggerPrint = () => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (err) {
            console.error('Iframe print error, falling back to window.print():', err);
            window.print();
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
        setTimeout(triggerPrint, 200);
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

// Smart Barcode Scanner Listener & Search Enter Handler
let barcodeBuffer = '';
let lastKeyTime = 0;
let lastScannedCode = '';
let lastScanTime = 0;

const handleSearchEnter = (e) => {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const q = searchQuery.value.trim();
    barcodeBuffer = ''; // Reset buffer agar window listener tidak memicu duplikasi
    if (!q) return;

    const now = Date.now();
    // Cegah double scan dari hardware scanner / event ganda dalam rentang 400ms
    if (q.toLowerCase() === lastScannedCode.toLowerCase() && now - lastScanTime < 400) {
        return;
    }

    // 1. Cari exact match barcode atau SKU terlebih dahulu
    const exactMatch = props.products.find(p => 
        (p.barcode && p.barcode.toLowerCase() === q.toLowerCase()) || 
        (p.sku && p.sku.toLowerCase() === q.toLowerCase())
    );

    if (exactMatch) {
        lastScannedCode = q;
        lastScanTime = now;
        addToCart(exactMatch);
        searchQuery.value = '';
        focusSearchInput();
        return;
    }

    // 2. Jika hasil filter hanya 1 barang, langsung masukkan keranjang
    if (filteredProducts.value.length === 1) {
        lastScannedCode = q;
        lastScanTime = now;
        addToCart(filteredProducts.value[0]);
        searchQuery.value = '';
        focusSearchInput();
    }
};

const clearProductSearch = () => {
    searchQuery.value = '';
    focusSearchInput();
};

const handleCatalogAreaClick = (e) => {
    const target = e.target;
    if (
        !target.closest('button') && 
        !target.closest('a') && 
        !target.closest('input') && 
        !target.closest('select') && 
        !target.closest('textarea')
    ) {
        focusSearchInput();
    }
};

// Keyboard Shortcuts & Global Barcode Scanner Auto-Detection
const handleKeyDown = (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        focusSearchInput(true);
        return;
    } else if (e.key === 'F8') {
        e.preventDefault();
        openCheckout();
        return;
    } else if (e.key === 'F4') {
        e.preventDefault();
        isCustomerModalOpen.value = true;
        return;
    } else if (e.key === 'F9') {
        e.preventDefault();
        isHistoryModalOpen.value = true;
        return;
    }

    // Jika sedang membuka modal receipt struk
    if (isReceiptOpen.value) {
        if (e.key === 'Enter') {
            e.preventDefault();
            printReceipt();
            return;
        } else if (e.key === 'Escape') {
            e.preventDefault();
            isReceiptOpen.value = false;
            return;
        }
        return;
    }

    // Abaikan jika sedang membuka modal checkout / customer / history
    if (isCheckoutOpen.value || isCustomerModalOpen.value || isHistoryModalOpen.value) {
        return;
    }

    // Abaikan jika user sedang mengetik di input selain search bar (misal input qty keranjang / diskon)
    const activeEl = document.activeElement;
    const isSearchFocused = activeEl?.id === 'product-search-input';
    const isOtherInput = activeEl && (
        activeEl.tagName === 'INPUT' || 
        activeEl.tagName === 'TEXTAREA' || 
        activeEl.tagName === 'SELECT' || 
        activeEl.isContentEditable
    ) && !isSearchFocused;
    if (isOtherInput) return;

    // PENTING: Jika search input sedang aktif/terfokus, serahkan penanganan Enter sepenuhnya
    // kepada @keydown.enter.stop pada input agar TIDAK terjadi penambahan ganda (kelipatan 2).
    if (isSearchFocused) {
        if (e.key === 'Enter') {
            barcodeBuffer = '';
        }
        return;
    }

    const currentTime = Date.now();

    // Global Type-to-Search: Jika kasir mengetik langsung karakter apapun tanpa klik kolom pencarian lebih dulu
    if (!isSearchFocused && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
        const input = document.getElementById('product-search-input');
        if (input) {
            e.preventDefault();
            searchQuery.value = (searchQuery.value || '') + e.key;
            input.focus();
            nextTick(() => {
                input.selectionStart = input.selectionEnd = input.value.length;
            });
        }
    } else if (!isSearchFocused && e.key === 'Backspace') {
        focusSearchInput();
    }

    // Jika scanner mengirimkan tombol Enter di akhir kode (saat search input tidak sedang fokus)
    if (e.key === 'Enter') {
        if (barcodeBuffer.length >= 3) {
            const scannedCode = barcodeBuffer.trim();
            const now = Date.now();
            if (scannedCode.toLowerCase() === lastScannedCode.toLowerCase() && now - lastScanTime < 400) {
                barcodeBuffer = '';
                return;
            }

            const product = props.products.find(p => 
                (p.barcode && p.barcode.toLowerCase() === scannedCode.toLowerCase()) || 
                (p.sku && p.sku.toLowerCase() === scannedCode.toLowerCase())
            );

            if (product) {
                e.preventDefault();
                lastScannedCode = scannedCode;
                lastScanTime = now;
                addToCart(product);
                searchQuery.value = '';
                barcodeBuffer = '';
                focusSearchInput();
                return;
            }
        }
        barcodeBuffer = '';
    } else if (e.key.length === 1) {
        // Deteksi kecepatan ketikan scanner (biasanya < 50ms per karakter)
        if (currentTime - lastKeyTime > 120) {
            barcodeBuffer = e.key;
        } else {
            barcodeBuffer += e.key;
        }
        lastKeyTime = currentTime;
    }
};

onMounted(() => {
    // Deteksi Mode Tablet / Mode PC
    const savedTouchMode = localStorage.getItem('pos_touch_mode');
    if (savedTouchMode !== null) {
        isTouchMode.value = savedTouchMode === 'true';
    } else {
        isTouchMode.value = isTouchDevice();
    }

    window.addEventListener('keydown', handleKeyDown);
    document.addEventListener('click', handleClickOutsideEmployee);
    
    // Auto focus ke search input saat kasir dibuka (hanya jika mode PC)
    if (!isTouchMode.value) {
        focusSearchInput();
        setTimeout(() => {
            focusSearchInput();
        }, 150);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    document.removeEventListener('click', handleClickOutsideEmployee);
});
</script>

<template>
    <Head title="Kasir POS" />
    <MainLayout>
        <div class="flex flex-col lg:flex-row h-full w-full min-h-0 bg-slate-100 overflow-hidden font-sans relative">
            <!-- Left Panel: Product Catalog & Fast Search -->
            <div 
                @click="handleCatalogAreaClick"
                class="flex-1 min-h-0 flex flex-col min-w-0 bg-slate-50 border-r border-slate-200 overflow-hidden"
            >
                <!-- Top Toolbar: Search Bar, Customer Selector & Mode Switcher -->
                <div class="p-2.5 sm:p-3 bg-white border-b border-slate-200 flex flex-wrap items-center gap-2 justify-between shadow-xs shrink-0">
                    <!-- Search Input with Barcode Icon & Quick Clear Button -->
                    <div class="relative flex-1 min-w-[200px]">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            id="product-search-input"
                            v-model="searchQuery" 
                            @keydown.enter.prevent.stop="handleSearchEnter"
                            @keydown.esc.prevent="clearProductSearch"
                            type="text" 
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="Cari nama barang / scan barcode (F2)..." 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-14 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition font-medium"
                        />
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <button 
                                v-if="searchQuery"
                                @click="clearProductSearch"
                                type="button"
                                class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 transition cursor-pointer active:scale-95"
                                title="Bersihkan Pencarian (Esc)"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                            <Barcode class="w-4 h-4 text-slate-400" />
                        </div>
                    </div>

                    <!-- Action Buttons Group (Never squishes search bar) -->
                    <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                        <!-- Mobile Cart Toggle Button in Toolbar -->
                        <button 
                            @click="isMobileCartOpen = true"
                            class="lg:hidden flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs transition cursor-pointer active:scale-95 shrink-0 shadow-xs"
                            title="Buka Keranjang Belanja"
                        >
                            <ShoppingCart class="w-4 h-4" />
                            <span>Keranjang ({{ cart.reduce((sum, item) => sum + item.qty, 0) }})</span>
                        </button>

                        <!-- History / Reprint Button (F9) -->
                        <button 
                            @click="isHistoryModalOpen = true"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer active:scale-95 shrink-0 border border-slate-200"
                            title="Riwayat Transaksi & Cetak Ulang Faktur (Tekan F9)"
                        >
                            <History class="w-3.5 h-3.5 text-amber-600" />
                            <span>Riwayat (F9)</span>
                        </button>

                        <!-- Toggle Mode Tablet (Mencegah virtual keyboard otomatis muncul) -->
                        <button 
                            @click="toggleTouchMode"
                            type="button"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer active:scale-95 shrink-0 border"
                            :class="[
                                isTouchMode 
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' 
                                    : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
                            ]"
                            :title="isTouchMode ? 'Mode Tablet: Keyboard virtual tidak muncul otomatis. Klik untuk ubah ke Mode PC' : 'Mode PC: Auto-focus aktif untuk barcode scanner. Klik untuk ubah ke Mode Tablet'"
                        >
                            <Tablet v-if="isTouchMode" class="w-3.5 h-3.5 text-emerald-600" />
                            <Monitor v-else class="w-3.5 h-3.5 text-slate-500" />
                            <span>{{ isTouchMode ? 'Mode Tab' : 'Mode PC' }}</span>
                        </button>

                        <!-- Customer Selector Pill -->
                        <button 
                            @click="isCustomerModalOpen = true"
                            class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-amber-400 transition text-left cursor-pointer active:scale-95 shrink-0"
                            title="Pilih / Ganti Pelanggan"
                        >
                            <div class="w-6 h-6 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                <User class="w-3.5 h-3.5" />
                            </div>
                            <span class="text-xs font-bold text-slate-800 truncate max-w-[120px]">
                                {{ selectedCustomer?.name || 'Pelanggan Umum' }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Category Chips -->
                <div class="px-4 py-2.5 border-b border-slate-200 bg-white/50 flex gap-2 overflow-x-auto no-scrollbar shrink-0">
                    <button 
                        v-for="cat in categories" 
                        :key="cat.id"
                        @click="selectedCategory = cat.id; focusSearchInput()"
                        :class="[
                            selectedCategory === cat.id 
                                ? 'bg-slate-900 text-white font-bold shadow-xs' 
                                : 'bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300',
                            'px-3.5 py-1.5 rounded-lg text-xs font-semibold shrink-0 transition cursor-pointer'
                        ]"
                    >
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Product Grid List (Responsive 1-col on small phone, 2-col on tablet/phone landscape, 3-4 col on desktop) -->
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-y-contain p-3 sm:p-4 pb-28 lg:pb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 content-start items-start">
                    <div 
                        v-for="product in filteredProducts" 
                        :key="product.id"
                        :class="getItemQtyInCart(product.id) > 0 ? 'border-amber-400 bg-amber-50/25 ring-1 ring-amber-400 shadow-sm' : 'border-slate-200/90 bg-white hover:border-amber-300'"
                        class="rounded-2xl p-3 sm:p-3.5 flex flex-col justify-between transition duration-150 hover:shadow-md group shadow-xs border relative"
                    >
                        <!-- In-Cart Top Floating Badge -->
                        <div 
                            v-if="getItemQtyInCart(product.id) > 0" 
                            class="absolute -top-2 -right-2 bg-amber-500 text-slate-950 font-black text-[10px] px-2 py-0.5 rounded-full shadow-md flex items-center gap-1 border-2 border-white z-10 animate-in fade-in zoom-in duration-150"
                        >
                            <Check class="w-3 h-3 stroke-[3]" />
                            <span>{{ getItemQtyInCart(product.id) }} di Keranjang</span>
                        </div>

                        <div>
                            <!-- Header Product: Brand & Stock Available -->
                            <div class="flex items-center justify-between text-[11px] mb-1.5">
                                <span class="text-slate-500 font-bold uppercase text-[10px] truncate">{{ product.brand?.name || 'Umum' }}</span>
                                <span 
                                    :class="product.stock_available <= product.min_stock ? 'text-rose-700 bg-rose-50 border border-rose-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-extrabold"
                                >
                                    Sedia: {{ product.stock_available }} {{ product.units[0]?.unit_name }}
                                </span>
                            </div>

                            <!-- Product Name -->
                            <h3 :class="getItemQtyInCart(product.id) > 0 ? 'text-amber-950 font-black' : 'text-slate-900 font-bold'" class="text-xs group-hover:text-amber-600 transition line-clamp-2 leading-snug mb-1.5">
                                {{ product.name }}
                            </h3>

                            <!-- SKU -->
                            <p class="text-[10px] font-mono text-slate-400 mb-2">{{ product.sku }}</p>
                        </div>

                        <!-- Multi-Unit Price Chips with Active Tier Pricing -->
                        <div class="space-y-1.5 pt-2 border-t border-slate-100">
                            <template v-for="unit in product.units" :key="unit.id">
                                <!-- CASE A: Unit is in Cart (Show Stepper) -->
                                <div 
                                    v-if="getItemQtyInCart(product.id, unit.id) > 0"
                                    class="p-2 rounded-xl bg-amber-50/90 border-2 border-amber-500 flex items-center justify-between shadow-xs transition-all"
                                >
                                    <div class="min-w-0 pr-1">
                                        <p class="text-[11px] font-bold text-amber-950 truncate">{{ unit.unit_name }}</p>
                                        <p class="text-[10px] text-amber-900 font-black">{{ formatRupiah(getUnitPrice(unit)) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 bg-white border border-amber-300 rounded-lg p-0.5 shadow-2xs shrink-0">
                                        <button 
                                            @click.stop="updateUnitQtyInCatalog(product, unit, -1)" 
                                            class="w-5 h-5 rounded bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 flex items-center justify-center active:scale-75 transition cursor-pointer"
                                            title="Kurangi"
                                        >
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <span class="w-5 text-center text-xs font-black text-slate-900">{{ getItemQtyInCart(product.id, unit.id) }}</span>
                                        <button 
                                            @click.stop="updateUnitQtyInCatalog(product, unit, 1)" 
                                            class="w-5 h-5 rounded bg-amber-500 text-slate-950 flex items-center justify-center active:scale-75 transition cursor-pointer shadow-xs font-black"
                                            title="Tambah"
                                        >
                                            <Plus class="w-3 h-3 stroke-[2.5]" />
                                        </button>
                                    </div>
                                </div>

                                <!-- CASE B: Unit is not in Cart for this active tier -->
                                <div 
                                    v-else
                                    @click="addToCart(product, unit)"
                                    class="flex items-center justify-between px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition text-xs group/unit"
                                >
                                    <div class="flex items-center gap-1 min-w-0">
                                        <span class="text-slate-700 font-semibold group-hover/unit:text-slate-900 truncate">{{ unit.unit_name }}</span>
                                        <span v-if="getItemQtyInCart(product.id, unit.id) > 0" class="text-[8px] font-bold px-1 rounded bg-slate-200 text-slate-600 shrink-0">
                                            ({{ getItemQtyInCart(product.id, unit.id) }} di keranjang)
                                        </span>
                                    </div>
                                    <span class="text-slate-900 font-black shrink-0">
                                        {{ formatRupiah(getUnitPrice(unit)) }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating Mobile Cart Bar (Visible on mobile when cart has items) -->
            <div 
                v-if="cart.length > 0 && !isMobileCartOpen" 
                class="lg:hidden fixed bottom-3 left-3 right-3 z-40 bg-slate-900 text-white p-3 rounded-2xl shadow-2xl flex items-center justify-between border border-slate-700 animate-in fade-in slide-in-from-bottom-3 duration-200"
            >
                <button @click="isMobileCartOpen = true" class="flex items-center gap-2.5 text-left min-w-0 flex-1">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
                        {{ cart.reduce((sum, item) => sum + item.qty, 0) }}
                    </div>
                    <div class="truncate">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">{{ cart.length }} Jenis Belanja</p>
                        <p class="text-xs font-black text-amber-400 truncate">{{ formatRupiah(totalNet) }}</p>
                    </div>
                </button>

                <div class="flex items-center gap-2 shrink-0 ml-2">
                    <button 
                        @click="isMobileCartOpen = true" 
                        class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold border border-slate-700 cursor-pointer"
                    >
                        Keranjang
                    </button>
                    <button 
                        @click="openCheckout" 
                        class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs flex items-center gap-1.5 shadow-md cursor-pointer active:scale-95"
                    >
                        <span>Bayar</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Right Panel: POS Cart & Checkout (Desktop sidebar or full-screen drawer on mobile) -->
            <div 
                :class="[
                    isMobileCartOpen ? 'fixed inset-0 z-50 flex' : 'hidden lg:flex',
                    'w-full lg:w-[420px] bg-white border-l border-slate-200 flex-col justify-between shrink-0 shadow-lg'
                ]"
            >
                <!-- Cart Header -->
                <div class="h-16 px-4 sm:px-5 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-2.5">
                        <button 
                            @click="isMobileCartOpen = false" 
                            class="lg:hidden p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition mr-1"
                        >
                            <X class="w-5 h-5" />
                        </button>
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                            <ShoppingCart class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h2 class="text-sm font-black text-slate-900">Keranjang</h2>
                            </div>
                            <p class="text-[11px] text-slate-400">{{ cart.length }} jenis barang</p>
                        </div>
                    </div>

                    <button 
                        v-if="cart.length > 0"
                        @click="clearCart"
                        class="text-xs text-rose-600 hover:text-rose-700 font-bold transition flex items-center gap-1 cursor-pointer"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Kosongkan</span>
                    </button>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 bg-slate-50/50">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                        <ShoppingCart class="w-12 h-12 stroke-1 mb-3 text-slate-300" />
                        <p class="text-xs font-bold text-slate-600">Keranjang Masih Kosong</p>
                        <p class="text-[11px] text-slate-400 mt-1 max-w-xs">Klik pilihan satuan di sebelah kiri atau scan barcode untuk memasukkan barang.</p>
                    </div>

                    <div 
                        v-for="(item, index) in cart" 
                        :key="index"
                        class="bg-white border border-slate-200/90 rounded-2xl p-3 space-y-2 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h4 class="text-xs font-bold text-slate-900 line-clamp-1 leading-snug">{{ item.product.name }}</h4>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[10px] text-slate-400 font-mono">{{ item.product.sku }}</span>
                                </div>
                            </div>
                            <button @click="removeFromCart(index)" class="text-slate-400 hover:text-rose-600 transition p-1 cursor-pointer">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- Unit Selector & Price -->
                        <div class="flex items-center justify-between gap-2">
                            <select 
                                :value="item.unit.id"
                                @change="changeItemUnit(index, $event.target.value)"
                                class="bg-slate-50 border border-slate-200 text-slate-800 text-xs font-bold rounded-lg px-2 py-1 focus:outline-none focus:border-amber-500"
                            >
                                <option 
                                    v-for="u in item.product.units" 
                                    :key="u.id" 
                                    :value="u.id"
                                >
                                    {{ u.unit_name }} ({{ formatRupiah(getUnitPrice(u)) }})
                                </option>
                            </select>

                            <div class="text-right">
                                <p class="text-xs font-black text-slate-900">{{ formatRupiah(item.subtotal) }}</p>
                                <p class="text-[10px] text-slate-400">@ {{ formatRupiah(item.unit_price) }}</p>
                            </div>
                        </div>

                        

                        <!-- Qty Controls -->
                        <div class="flex items-center justify-between pt-1.5 border-t border-slate-100">
                            <span class="text-[11px] text-slate-500 font-medium">Jumlah:</span>
                            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg p-1">
                                <button 
                                    @click="updateQty(index, -1)" 
                                    class="w-6 h-6 rounded bg-white hover:bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 font-bold transition cursor-pointer"
                                >
                                    <Minus class="w-3 h-3" />
                                </button>
                                <input 
                                    v-model.number="item.qty"
                                    @input="item.subtotal = item.qty * item.unit_price"
                                    type="number" 
                                    step="0.1"
                                    min="0.1"
                                    class="w-12 text-center text-xs font-bold bg-transparent text-slate-900 focus:outline-none"
                                />
                                <button 
                                    @click="updateQty(index, 1)" 
                                    class="w-6 h-6 rounded bg-white hover:bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 font-bold transition cursor-pointer"
                                >
                                    <Plus class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Summary & Checkout Button -->
                <div class="p-4 bg-white border-t border-slate-200 space-y-3">
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal Barang</span>
                            <span class="font-bold text-slate-800">{{ formatRupiah(subtotalGross) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Diskon Nota (Rp)</span>
                            <input 
                                v-model.number="checkoutForm.discount"
                                type="number" 
                                min="0" 
                                class="w-24 bg-slate-50 border border-slate-200 rounded px-2 py-0.5 text-right text-xs text-slate-900 font-bold"
                            />
                        </div>
                        <div class="flex justify-between text-base font-black text-slate-900 pt-2 border-t border-slate-100">
                            <span>Total Bayar</span>
                            <span class="text-amber-600 text-lg font-black">{{ formatRupiah(totalNet) }}</span>
                        </div>
                    </div>

                    <!-- Shortcut & Checkout Action -->
                    <button 
                        @click="openCheckout"
                        :disabled="cart.length === 0"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-3.5 px-4 rounded-xl shadow-lg shadow-slate-900/10 flex items-center justify-between transition-all transform active:scale-98 disabled:opacity-40 cursor-pointer"
                    >
                        <div class="flex items-center gap-2">
                            <DollarSign class="w-4 h-4 text-amber-400" />
                            <span class="text-xs tracking-wide">BAYAR (F8)</span>
                        </div>
                        <span class="text-xs font-bold bg-white/10 px-2.5 py-1 rounded-lg">{{ formatRupiah(totalNet) }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Customer Selector -->
        <div v-if="isCustomerModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[85vh]">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-2.5">
                        <button 
                            v-if="isAddingNewCustomer" 
                            @click="isAddingNewCustomer = false" 
                            class="p-1 rounded-xl hover:bg-slate-100 text-slate-600 transition cursor-pointer"
                        >
                            <ArrowLeft class="w-5 h-5" />
                        </button>
                        <div v-else class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <User class="w-4 h-4 text-amber-700" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">
                                {{ isAddingNewCustomer ? 'Tambah Pelanggan Baru' : 'Pilih Pelanggan / Proyek' }}
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                {{ isAddingNewCustomer ? 'Masukkan data pelanggan untuk langsung dipakai transaksi' : 'Pilih pelanggan / karyawan untuk bon atau riwayat transaksi' }}
                            </p>
                        </div>
                    </div>
                    <button @click="isCustomerModalOpen = false; isAddingNewCustomer = false;" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- VIEW 1: Customer List & Search -->
                <div v-if="!isAddingNewCustomer" class="flex flex-col flex-1 overflow-hidden">
                    <!-- Search Input & Quick Add Button -->
                    <div class="p-3.5 sm:p-4 border-b border-slate-100 space-y-2.5 bg-slate-50/50 shrink-0">
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="searchCustomerQuery" 
                                type="text" 
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                spellcheck="false"
                                placeholder="Cari nama pelanggan, toko, proyek, nomor HP..." 
                                class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-9 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 shadow-xs transition"
                            />
                            <button 
                                v-if="searchCustomerQuery" 
                                @click="searchCustomerQuery = ''"
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <button 
                            @click="isAddingNewCustomer = true; newCustomerForm.name = searchCustomerQuery;"
                            type="button"
                            class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs active:scale-[0.99]"
                        >
                            <Plus class="w-4 h-4 stroke-[3]" />
                            <span>Tambah Pelanggan Baru</span>
                        </button>
                    </div>

                    <!-- Customer Cards List -->
                    <div class="p-3.5 sm:p-4 space-y-2.5 overflow-y-auto flex-1">
                        <div 
                            v-for="cust in filteredCustomersModal" 
                            :key="cust.id"
                            @click="selectCustomer(cust)"
                            :class="[
                                selectedCustomer?.id === cust.id 
                                    ? 'bg-amber-50/80 border-amber-400 ring-1 ring-amber-400 shadow-xs' 
                                    : 'bg-white border-slate-200 hover:border-amber-300 hover:bg-slate-50/50',
                                'p-3.5 rounded-2xl border cursor-pointer transition flex items-center justify-between text-xs'
                            ]"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-bold text-slate-900">{{ cust.name }}</h4>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1.5">
                                    <Phone class="w-3.5 h-3.5 text-slate-400" />
                                    <span>{{ cust.phone || '-' }}</span>
                                </p>
                                <p v-if="cust.address" class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                                    <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                                    <span class="truncate max-w-[280px]">{{ cust.address }}</span>
                                </p>
                            </div>

                            <div class="text-right shrink-0">
                                <p v-if="cust.current_debt > 0" class="text-xs text-rose-600 font-black">
                                    Bon: {{ formatRupiah(cust.current_debt) }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    Limit: {{ formatRupiah(cust.credit_limit) }}
                                </p>
                            </div>
                        </div>

                        <div v-if="filteredCustomersModal.length === 0" class="text-center py-8 text-slate-400 space-y-2">
                            <User class="w-8 h-8 mx-auto text-slate-300" />
                            <p class="text-xs">Pelanggan tidak ditemukan.</p>
                            <button 
                                @click="isAddingNewCustomer = true; newCustomerForm.name = searchCustomerQuery;"
                                class="text-xs font-bold text-amber-600 hover:underline cursor-pointer"
                            >
                                Tambah "{{ searchCustomerQuery }}" sebagai pelanggan baru
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: Form Tambah Pelanggan Baru -->
                <div v-else class="p-4 sm:p-5 space-y-3 overflow-y-auto flex-1 bg-white">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                            Nama Pelanggan / Toko / Proyek <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            v-model="newCustomerForm.name" 
                            type="text" 
                            placeholder="Contoh: Toko Berkah Mandiri / Pak Budi"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                            Nomor WhatsApp / HP
                        </label>
                        <input 
                            v-model="newCustomerForm.phone" 
                            type="tel" 
                            placeholder="Contoh: 08123456789"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                            Alamat Tujuan / Lokasi Proyek
                        </label>
                        <textarea 
                            v-model="newCustomerForm.address" 
                            rows="2" 
                            placeholder="Contoh: Jl. Raya Pekalongan No. 12, Proyek Perumahan Griya Blok B"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white resize-none"
                        ></textarea>
                    </div>



                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                            Plafon Limit Bon / Kredit (Rp)
                        </label>
                        <input 
                            v-model.number="newCustomerForm.credit_limit" 
                            type="number" 
                            placeholder="0 jika tidak boleh bon"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex gap-2">
                        <button 
                            type="button" 
                            @click="isAddingNewCustomer = false" 
                            class="w-1/3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            @click="submitNewCustomer" 
                            :disabled="newCustomerForm.processing"
                            class="w-2/3 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs transition cursor-pointer shadow-md flex items-center justify-center gap-1.5 disabled:opacity-50"
                        >
                            <CheckCircle class="w-4 h-4" />
                            <span>{{ newCustomerForm.processing ? 'Menyimpan...' : 'Simpan & Pilih' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Riwayat Transaksi Kasir & Cetak Ulang Faktur -->
        <div v-if="isHistoryModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[88vh]">
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                            <History class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Riwayat Transaksi Kasir</h3>
                            <p class="text-[11px] text-slate-400">Pilih transaksi untuk cetak ulang faktur (Thermal, Dot Matrix, Invoice A4)</p>
                        </div>
                    </div>
                    <button @click="isHistoryModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Search Input in History Modal -->
                <div class="p-3.5 bg-slate-50 border-b border-slate-100 shrink-0">
                    <div class="relative">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="historySearch" 
                            type="text" 
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                            placeholder="Cari nomor faktur / nama pelanggan / nama barang..." 
                            class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 transition"
                        />
                    </div>
                </div>

                <!-- List of Transactions -->
                <div class="p-4 space-y-2.5 overflow-y-auto flex-1 bg-slate-50/50">
                    <div v-if="filteredHistoryTransactions.length === 0" class="py-12 text-center text-slate-400 text-xs">
                        <History class="w-8 h-8 mx-auto mb-2 text-slate-300 stroke-1" />
                        <p class="font-bold text-slate-600">Belum ada riwayat transaksi</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Transaksi yang diselesaikan di kasir akan muncul di sini.</p>
                    </div>

                    <div 
                        v-for="trx in filteredHistoryTransactions" 
                        :key="trx.id"
                        class="bg-white border border-slate-200 hover:border-amber-300 rounded-2xl p-3.5 shadow-xs transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-xs text-slate-900">{{ trx.invoice_number }}</span>
                                <span 
                                    :class="[
                                        trx.payment_method === 'cash' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                                        trx.payment_method === 'tempo' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                                        'bg-blue-50 text-blue-800 border-blue-200',
                                        'px-2 py-0.5 rounded text-[9px] font-black uppercase border'
                                    ]"
                                >
                                    {{ trx.payment_method }}
                                </span>
                            </div>
                            <p class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <User class="w-3.5 h-3.5 text-slate-400" />
                                <span>{{ trx.customer?.name || 'Pelanggan Umum' }}</span>
                                <span class="text-[10px] text-slate-400 font-normal">({{ trx.items?.length || 0 }} item)</span>
                            </p>
                            <p class="text-[10px] text-slate-400 font-mono">
                                {{ new Date(trx.created_at).toLocaleString('id-ID') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <p class="text-xs font-black text-slate-900">{{ formatRupiah(trx.total_net) }}</p>
                                <p class="text-[10px] text-slate-400 capitalize">{{ trx.payment_status || 'lunas' }}</p>
                            </div>

                            <button 
                                @click="openReprint(trx)"
                                class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-xs transition cursor-pointer active:scale-95"
                            >
                                <Printer class="w-3.5 h-3.5 text-amber-400" />
                                <span>Cetak Ulang</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Payment Checkout -->
        <div v-if="isCheckoutOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-xl shadow-2xl flex flex-col max-h-[92vh] my-auto overflow-hidden">
                <!-- Modal Header (Pinned) -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white z-10">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Pembayaran Kasir</h3>
                        <p class="text-xs text-slate-500">Pelanggan: <span class="text-slate-900 font-bold">{{ selectedCustomer?.name }}</span> </p>
                    </div>
                    <button @click="isCheckoutOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="p-4 sm:p-6 space-y-4 sm:space-y-5 flex-1 overflow-y-auto overscroll-contain">
                    <!-- Total Net Display -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Tagihan</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ formatRupiah(totalNet) }}</p>
                    </div>

                    <!-- Payment Method Select -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button 
                                v-for="m in [
                                    { id: 'cash', label: 'Tunai / Cash', icon: DollarSign },
                                    { id: 'transfer', label: 'Transfer', icon: CreditCard },
                                    { id: 'qris', label: 'QRIS', icon: QrCode },
                                    { id: 'tempo', label: 'Bon / Tempo', icon: Clock }
                                ]" 
                                :key="m.id"
                                type="button"
                                @click="checkoutForm.payment_method = m.id"
                                :class="[
                                    checkoutForm.payment_method === m.id 
                                        ? 'bg-slate-900 text-white font-bold shadow-xs' 
                                        : 'bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100',
                                    'py-2.5 px-2 rounded-xl text-xs flex flex-col items-center gap-1.5 transition text-center cursor-pointer'
                                ]"
                            >
                                <component :is="m.icon" class="w-4 h-4" />
                                <span>{{ m.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Input Jumlah Bayar / Cash Quick Buttons -->
                    <div v-if="checkoutForm.payment_method === 'cash'">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Jumlah Uang Diterima</label>
                        <input 
                            v-model.number="checkoutForm.paid_amount"
                            type="number" 
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-lg font-black text-slate-900 focus:outline-none focus:border-amber-500"
                        />

                        <!-- Quick Cash Buttons -->
                        <div class="grid grid-cols-4 gap-2 mt-2">
                            <button @click="setExactPayment" class="py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold rounded-lg cursor-pointer">Uang Pas</button>
                            <button @click="addQuickMoney(50000)" class="py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold rounded-lg cursor-pointer">+50k</button>
                            <button @click="addQuickMoney(100000)" class="py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold rounded-lg cursor-pointer">+100k</button>
                            <button @click="addQuickMoney(500000)" class="py-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-bold rounded-lg cursor-pointer">+500k</button>
                        </div>

                        <!-- Change Amount -->
                        <div class="mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 flex justify-between items-center text-xs">
                            <span class="text-emerald-800 font-bold">Kembalian:</span>
                            <span class="text-emerald-700 font-black text-base">{{ formatRupiah(changeAmount) }}</span>
                        </div>
                    </div>

                    <!-- Tempo Form Details -->
                    <div v-if="checkoutForm.payment_method === 'tempo'" class="p-4 bg-amber-50 border border-amber-200 rounded-2xl space-y-3">
                        <div class="flex items-center gap-2 text-amber-800 text-xs font-bold">
                            <Clock class="w-4 h-4" />
                            <span>Pencatatan Nota Piutang (Bon Proyek)</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1">Tanggal Jatuh Tempo</label>
                            <input 
                                v-model="checkoutForm.due_date"
                                type="date" 
                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- Pencatatan Bon / Piutang Karyawan RSIA (Opsional) -->
                    <div class="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-2xl space-y-2.5">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input 
                                type="checkbox" 
                                v-model="isReceivableChecked"
                                class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500 border-slate-300 cursor-pointer"
                            />
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                Catat Bon / Piutang Karyawan RSIA
                                <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Opsional</span>
                            </span>
                        </label>

                        <div v-if="isReceivableChecked" class="space-y-2 pt-1">
                            <div class="relative" ref="employeeDropdownRef">
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Pilih Karyawan RSIA *</label>
                                
                                <!-- Trigger Combobox -->
                                <div 
                                    @click="isEmployeeDropdownOpen = !isEmployeeDropdownOpen"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 flex items-center justify-between cursor-pointer hover:border-emerald-500 transition shadow-2xs"
                                    :class="{ 'border-emerald-500 ring-2 ring-emerald-500/20': isEmployeeDropdownOpen }"
                                >
                                    <div class="flex items-center gap-2 truncate">
                                        <User class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span v-if="selectedEmployeeObj" class="font-bold text-slate-900 truncate">
                                            {{ selectedEmployeeObj.name }} 
                                            <span class="text-[10px] font-normal text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded ml-1">
                                                {{ selectedEmployeeObj.department || '-' }}
                                            </span>
                                        </span>
                                        <span v-else class="text-slate-400">-- Cari Nama / Unit Pegawai RSIA --</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button 
                                            v-if="selectedEmployeeId"
                                            type="button" 
                                            @click.stop="clearSelectedEmployee"
                                            class="text-slate-400 hover:text-rose-500 p-0.5 rounded cursor-pointer"
                                            title="Hapus pilihan"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                        </button>
                                        <ChevronDown class="w-4 h-4 text-slate-400 transition" :class="{ 'rotate-180': isEmployeeDropdownOpen }" />
                                    </div>
                                </div>

                                <!-- Dropdown Menu with Search Input -->
                                <div 
                                    v-if="isEmployeeDropdownOpen"
                                    class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
                                >
                                    <!-- Search Input Header -->
                                    <div class="p-2 border-b border-slate-100 bg-slate-50/70 flex items-center gap-2">
                                        <Search class="w-3.5 h-3.5 text-slate-400 ml-1.5 shrink-0" />
                                        <input 
                                            id="employee-search-dropdown-input"
                                            v-model="employeeSearchQuery"
                                            type="text"
                                            placeholder="Ketik nama atau unit..."
                                            class="w-full bg-transparent text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                            autocomplete="off"
                                            @keydown.esc="isEmployeeDropdownOpen = false"
                                            @keydown.enter.prevent="selectFirstEmployee"
                                        />
                                        <button 
                                            v-if="employeeSearchQuery" 
                                            type="button"
                                            @click="employeeSearchQuery = ''" 
                                            class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                                        >
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>

                                    <!-- Scrollable Options List -->
                                    <div class="max-h-52 overflow-y-auto divide-y divide-slate-50 p-1">
                                        <div 
                                            v-for="emp in filteredEmployees" 
                                            :key="emp.id"
                                            @click="pickEmployee(emp)"
                                            class="px-3 py-2 text-xs rounded-xl cursor-pointer transition flex items-center justify-between gap-2 hover:bg-emerald-50/80"
                                            :class="{ 'bg-emerald-50 font-bold text-emerald-900': emp.id === selectedEmployeeId }"
                                        >
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-900 truncate">{{ emp.name }}</div>
                                                <div class="text-[10px] text-slate-500 font-mono flex items-center gap-1.5 mt-0.5">
                                                    <span class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{{ emp.department || 'Umum' }}</span>
                                                    <span v-if="emp.nik" class="text-slate-400">NIP: {{ emp.nik }}</span>
                                                </div>
                                            </div>
                                            <Check v-if="emp.id === selectedEmployeeId" class="w-4 h-4 text-emerald-600 shrink-0" />
                                        </div>

                                        <div v-if="filteredEmployees.length === 0" class="py-6 text-center text-xs text-slate-400">
                                            Tidak ada pegawai "{{ employeeSearchQuery }}"
                                        </div>
                                    </div>

                                    <!-- Footer Info -->
                                    <div class="px-3 py-1.5 bg-slate-50 border-t border-slate-100 text-[10px] text-slate-400 flex items-center justify-between">
                                        <span>{{ filteredEmployees.length }} pegawai ditemukan</span>
                                        <span class="text-slate-300">Esc untuk tutup</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Pilihan Cepat / Preset Kondisi Bon -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1.5">Pilih Kondisi Bon / Piutang:</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <!-- Tombol 1: Pegawai Belum Bayar Penuh -->
                                    <button 
                                        type="button"
                                        @click="setBonFull"
                                        class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col justify-between"
                                        :class="[
                                            checkoutForm.paid_amount === 0 && receivableAmount === totalNet
                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-md ring-2 ring-emerald-500/20'
                                                : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold flex items-center gap-1">
                                                🏷️ Belum Bayar
                                            </span>
                                            <Check v-if="checkoutForm.paid_amount === 0 && receivableAmount === totalNet" class="w-3.5 h-3.5 text-white shrink-0" />
                                        </div>
                                        <div class="text-[10px] mt-1 opacity-90 leading-tight">
                                            Bayar Rp 0 • Bon <span class="font-bold">{{ formatRupiah(totalNet) }}</span>
                                        </div>
                                    </button>

                                    <!-- Tombol 2: Kembalian Belum Diambil -->
                                    <button 
                                        type="button"
                                        @click="setBonChange"
                                        class="p-2.5 rounded-xl border text-left transition cursor-pointer flex flex-col justify-between"
                                        :class="[
                                            changeAmount > 0 && receivableAmount === changeAmount && checkoutForm.paid_amount > totalNet
                                                ? 'bg-amber-600 text-white border-amber-600 shadow-md ring-2 ring-amber-500/20'
                                                : 'bg-white text-slate-700 border-slate-200 hover:border-amber-400 hover:bg-amber-50/50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold flex items-center gap-1">
                                                ↩️ Sisa Kembalian
                                            </span>
                                            <Check v-if="changeAmount > 0 && receivableAmount === changeAmount && checkoutForm.paid_amount > totalNet" class="w-3.5 h-3.5 text-white shrink-0" />
                                        </div>
                                        <div class="text-[10px] mt-1 opacity-90 leading-tight">
                                            {{ changeAmount > 0 ? 'Kembalian ' + formatRupiah(changeAmount) : 'Sesuai uang kembali' }}
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Input Detail Nominal & Catatan -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Nominal Bon (Rp) *</label>
                                    <input 
                                        type="number"
                                        v-model.number="receivableAmount"
                                        min="1"
                                        placeholder="Contoh: 8000"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:outline-none focus:border-emerald-500 font-mono"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Keterangan / Catatan</label>
                                    <input 
                                        type="text"
                                        v-model="receivableNotes"
                                        placeholder="Contoh: Belum bayar / uang dibawa dulu"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer / Action Button (Pinned) -->
                <div class="p-4 bg-white border-t border-slate-100 shrink-0">
                    <button 
                        @click="submitCheckout"
                        :disabled="checkoutForm.processing"
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-3.5 px-4 rounded-xl shadow-lg shadow-slate-900/10 flex items-center justify-center gap-2 transition text-xs cursor-pointer disabled:opacity-50"
                    >
                        <CheckCircle class="w-4 h-4 text-amber-400" />
                        <span>SELESAIKAN & CETAK STRUK</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Multi-Format Cetak (Thermal, Invoice A4, Dot Matrix) -->
        <div v-if="isReceiptOpen" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-3 sm:p-6 print:p-0 print:bg-white print:static">
            <div 
                :class="[
                    selectedPrintFormat === 'thermal' ? 'max-w-sm' : selectedPrintFormat === 'invoice' ? 'max-w-3xl' : 'max-w-4xl',
                    'bg-white text-slate-900 rounded-3xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[92vh] print:max-h-none print:shadow-none print:rounded-none print:w-full print:max-w-none transition-all duration-200'
                ]"
            >
                <!-- Top Toolbar (Non-Printable) -->
                <div class="p-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-800 no-print">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-bold">
                            <CheckCircle class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-xs font-black leading-tight text-white">
                                {{ lastTransaction?.is_reprint ? 'Cetak Ulang Faktur / Salinan' : 'Transaksi Berhasil!' }}
                            </h3>
                            <p class="text-[10px] text-slate-400 leading-tight">Pilih format cetak:</p>
                        </div>
                    </div>

                    <!-- Print Format Selector Tabs -->
                    <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
                        <button 
                            v-for="fmt in printFormats" 
                            :key="fmt.id"
                            @click="selectedPrintFormat = fmt.id"
                            :title="fmt.title"
                            :class="[
                                selectedPrintFormat === fmt.id 
                                    ? 'bg-amber-500 text-slate-950 font-black shadow-xs' 
                                    : 'text-slate-300 hover:text-white hover:bg-slate-700/60',
                                'px-2.5 py-1 rounded-lg text-[11px] font-bold transition cursor-pointer'
                            ]"
                        >
                            {{ fmt.label }}
                        </button>
                    </div>

                    <button @click="isReceiptOpen = false" class="text-slate-400 hover:text-white p-1 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Printable Content Area -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-100/60 print:p-0 print:bg-white flex justify-center">
                    <div id="printable-receipt-modal" class="w-full bg-white print:w-full">
                        
                        <!-- ================= FORMAT 1: NOTA THERMAL (58mm / 80mm) ================= -->
                        <div 
                            v-if="selectedPrintFormat === 'thermal'" 
                            class="max-w-[340px] mx-auto p-4 font-mono text-xs space-y-2.5 leading-tight bg-white border border-slate-200 rounded-2xl shadow-xs print:border-none print:shadow-none print:p-0"
                        >
                            <!-- Reprint Badge for Thermal -->
                            <div v-if="lastTransaction?.is_reprint" class="text-center font-black text-[9px] py-0.5 bg-slate-100 border border-slate-300 rounded tracking-widest uppercase">
                                *** SALINAN / CETAK ULANG ***
                            </div>

                            <div class="text-center border-b border-dashed border-slate-300 pb-2.5">
                                <img :src="settings.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-10 h-10 object-contain mx-auto mb-1" />
                                <h2 class="font-black text-xs uppercase tracking-tight">{{ settings.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN' }}</h2>
                                <p class="text-[9px] text-slate-600">{{ settings.store_tagline || 'Kantin & Koperasi RSIA Aisyiyah Pekajangan' }}</p>
                                <p class="text-[9px] text-slate-600">{{ settings.store_address }}</p>
                                <p class="text-[9px] text-slate-700 font-bold">Telp/WA: {{ settings.store_phone }}</p>
                            </div>

                            <div class="text-[9px] space-y-0.5 border-b border-dashed border-slate-300 pb-2">
                                <div class="flex justify-between">
                                    <span>No: {{ lastTransaction?.invoice_number }}</span>
                                    <span>{{ lastTransaction?.date }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Pelanggan:</span>
                                    <span class="font-bold">{{ lastTransaction?.customer_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Kasir:</span>
                                    <span>{{ user?.name }}</span>
                                </div>
                            </div>

                            <!-- Items -->
                            <div class="space-y-1.5 border-b border-dashed border-slate-300 pb-2 text-[10px]">
                                <div v-for="(it, i) in lastTransaction?.items" :key="i" class="space-y-0.5">
                                    <p class="font-bold text-slate-900">{{ it.product.name }}</p>
                                    <div class="flex justify-between text-slate-700 text-[9px]">
                                        <span>{{ it.qty }} {{ it.unit.unit_name }} x {{ formatRupiah(it.unit_price) }}</span>
                                        <span class="font-bold text-slate-900">{{ formatRupiah(it.subtotal) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Totals -->
                            <div class="space-y-1 text-[10px] border-b border-dashed border-slate-300 pb-2">
                                <div class="flex justify-between">
                                    <span>Subtotal:</span>
                                    <span>{{ formatRupiah(lastTransaction?.total_gross) }}</span>
                                </div>
                                <div v-if="lastTransaction?.discount > 0" class="flex justify-between text-slate-600">
                                    <span>Diskon:</span>
                                    <span>-{{ formatRupiah(lastTransaction?.discount) }}</span>
                                </div>
                                <div class="flex justify-between font-black text-xs pt-1 border-t border-slate-200">
                                    <span>TOTAL:</span>
                                    <span>{{ formatRupiah(lastTransaction?.total_net) }}</span>
                                </div>
                                <div class="flex justify-between pt-0.5">
                                    <span>Metode:</span>
                                    <span class="uppercase font-bold">{{ lastTransaction?.payment_method }}</span>
                                </div>
                                <div v-if="lastTransaction?.payment_method === 'tempo'" class="flex justify-between text-rose-700 font-bold">
                                    <span>Jatuh Tempo:</span>
                                    <span>{{ lastTransaction?.due_date }}</span>
                                </div>
                                <div v-else class="space-y-0.5">
                                    <div class="flex justify-between">
                                        <span>Bayar:</span>
                                        <span>{{ formatRupiah(lastTransaction?.paid_amount) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Kembali:</span>
                                        <span>{{ formatRupiah(lastTransaction?.change_amount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center text-[8px] text-slate-600 pt-1 whitespace-pre-line leading-tight">
                                {{ formatTextWithBreaks(settings.receipt_footer || 'Terima kasih atas kunjungan Anda!') }}
                            </div>
                        </div>


                        <!-- ================= FORMAT 2: FAKTUR 2 PLY CONTINUOUS FORM (9.5" x 11"/2) ================= -->
                        <div 
                            v-else-if="selectedPrintFormat === 'dot_matrix'" 
                            class="p-6 font-sans text-slate-900 bg-white border border-slate-300 rounded-2xl leading-normal space-y-3 print:border-none print:p-0 select-text max-w-3xl mx-auto shadow-sm"
                        >
                            <!-- Reprint Badge -->
                            <div v-if="lastTransaction?.is_reprint" class="text-center font-black text-[10px] tracking-widest text-amber-900 bg-amber-50 border border-amber-300 py-1 rounded-lg uppercase">
                                *** SALINAN FAKTUR / CETAK ULANG (REPRINT) ***
                            </div>

                            <!-- Header Section -->
                            <div class="flex justify-between items-start">
                                <div class="flex items-start gap-2.5">
                                    <img :src="settings.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-10 h-10 object-contain shrink-0 mt-0.5" />
                                    <div>
                                        <h2 class="font-black text-base uppercase tracking-tight text-slate-950">{{ settings.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN' }}</h2>
                                        <p class="text-[11px] text-slate-600 mt-0.5">Alamat : {{ settings.store_address || 'Jl. Raya Pantura No. 99, Pekalongan' }}</p>
                                        <p class="text-[11px] text-slate-600">Telepon/HP : {{ settings.store_phone || '+62 815 7345 5951' }}</p>
                                        <p class="text-[11px] text-slate-600">Email : {{ settings.store_email || 'kantin@rsiaaisyiyah.com' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <h3 class="font-black text-xl uppercase tracking-wider text-slate-950">INVOICE</h3>
                                    <table class="text-xs ml-auto mt-1 leading-tight">
                                        <tr>
                                            <td class="text-right pr-2 text-slate-500">No. Invoice :</td>
                                            <td class="font-black font-mono text-slate-950">{{ lastTransaction?.invoice_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-500">Tanggal :</td>
                                            <td class="font-bold text-slate-900">{{ lastTransaction?.raw_date || lastTransaction?.date }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-500">Pembayaran :</td>
                                            <td class="font-bold text-slate-900 uppercase">
                                                {{ lastTransaction?.payment_method }} {{ lastTransaction?.due_date ? `(Jatuh Tempo: ${lastTransaction?.due_date})` : '' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-500">Sales :</td>
                                            <td class="font-bold text-slate-900 uppercase">{{ lastTransaction?.sales?.name || user?.name }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="border-b-2 border-slate-950"></div>

                            <!-- Customer & Big Total Header -->
                            <div class="flex justify-between items-center text-xs">
                                <div>
                                    <p class="text-slate-500 text-[11px]">Kepada Yth.</p>
                                    <h4 class="font-black text-sm uppercase text-slate-950">{{ lastTransaction?.customer_name || 'Pelanggan Umum' }}</h4>
                                    <p class="text-slate-600 text-[11px]">Alamat : {{ lastTransaction?.customer?.address || '-' }}</p>
                                    <p class="text-slate-600 text-[11px]">No. HP / WA : {{ lastTransaction?.customer?.phone || '-' }}</p>
                                    <p class="text-slate-600 text-[11px]">Email : {{ lastTransaction?.customer?.email || '-' }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">JUMLAH YANG HARUS DIBAYAR</p>
                                    <p class="text-2xl font-black text-slate-950 tracking-tight mt-0.5">{{ formatRupiah(lastTransaction?.total_net) }}</p>
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
                                    <tr v-for="(it, idx) in lastTransaction?.items" :key="idx">
                                        <td class="py-1 px-2 font-medium text-slate-900">{{ it.product.name }}</td>
                                        <td class="py-1 px-2 text-center text-slate-800">{{ it.qty }} {{ it.unit.unit_name }}</td>
                                        <td class="py-1 px-2 text-right text-slate-800">{{ formatRupiah(it.unit_price) }}</td>
                                        <td class="py-1 px-2 text-right text-slate-600">{{ it.discount > 0 ? formatRupiah(it.discount) : '-' }}</td>
                                        <td class="py-1 px-2 text-right font-black text-slate-950">{{ formatRupiah(it.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Footer Section -->
                            <div class="border-t border-slate-950 pt-2 grid grid-cols-2 gap-4 text-xs">
                                <div class="space-y-2 text-[11px]">
                                    <p class="italic text-slate-700">Terbilang: <span class="font-bold">{{ numberToWords(lastTransaction?.total_net) }}</span></p>
                                    <div>
                                        <p class="font-bold text-slate-900">Keterangan:</p>
                                        <p class="text-slate-600">Terima kasih atas kepercayaan Anda.</p>
                                        <p class="text-slate-600">Mohon simpan dokumen ini sebagai bukti transaksi.</p>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900">Metode Pembayaran:</p>
                                        <p class="text-slate-700 whitespace-pre-line leading-tight">{{ formatTextWithBreaks(settings.bank_info || 'Bank : BCA\nNo. Rekening : 2501294511\nAtas Nama : YUNIAR DWI RAHMAWATI') }}</p>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <table class="w-full text-xs leading-tight">
                                        <tr>
                                            <td class="text-right pr-2 text-slate-600">Subtotal :</td>
                                            <td class="text-right font-bold text-slate-900 w-28">{{ formatRupiah(lastTransaction?.total_gross) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-600">Diskon :</td>
                                            <td class="text-right font-bold text-slate-900">{{ lastTransaction?.discount > 0 ? formatRupiah(lastTransaction?.discount) : '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-600">Total Sblm Pajak :</td>
                                            <td class="text-right font-bold text-slate-900">{{ formatRupiah(lastTransaction?.total_net) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-right pr-2 text-slate-600">Pajak 000% :</td>
                                            <td class="text-right font-bold text-slate-900">-</td>
                                        </tr>
                                        <tr class="border-t border-slate-950 font-black text-sm">
                                            <td class="text-right pr-2 pt-1 text-slate-950">Total :</td>
                                            <td class="text-right pt-1 text-slate-950">{{ formatRupiah(lastTransaction?.total_net) }}</td>
                                        </tr>
                                    </table>

                                    <!-- 2 Signatures Block -->
                                    <div class="grid grid-cols-2 gap-2 text-center text-[11px] pt-2">
                                        <div>
                                            <p class="text-slate-700 font-medium">Hormat Kami,</p>
                                            <div class="h-4"></div>
                                            <p class="font-bold text-slate-900">( TJL )</p>
                                        </div>
                                        <div>
                                            <p class="text-slate-700 font-medium">Diterima Oleh,</p>
                                            <div class="h-4"></div>
                                            <p class="font-bold text-slate-900">( .................... )</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- ================= FORMAT 3: INVOICE STANDAR (KERTAS A4) ================= -->
                        <div 
                            v-else-if="selectedPrintFormat === 'invoice'" 
                            class="p-8 font-sans text-xs text-slate-800 bg-white border border-slate-200 rounded-2xl shadow-xs print:border-none print:p-0 space-y-6 select-text"
                        >
                            <!-- Reprint Badge for Invoice A4 -->
                            <div v-if="lastTransaction?.is_reprint" class="p-2 bg-amber-50 border border-amber-300 text-amber-900 font-black text-center text-[11px] uppercase tracking-widest rounded-xl">
                                *** SALINAN FAKTUR RESMI / CETAK ULANG (REPRINT) ***
                            </div>

                            <!-- Header A4 -->
                            <div class="flex justify-between items-start border-b border-slate-200 pb-5">
                                <div class="flex items-center gap-3">
                                    <img :src="settings.store_logo || '/pos-kantin/images/logo.png'" alt="Logo" class="w-14 h-14 object-contain" />
                                    <div>
                                        <h1 class="text-lg font-black text-slate-900 uppercase tracking-tight">{{ settings.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN' }}</h1>
                                        <p class="text-xs text-amber-600 font-bold">{{ settings.store_tagline || 'Kantin & Koperasi RSIA Aisyiyah Pekajangan' }}</p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">{{ settings.store_address }}</p>
                                        <p class="text-[11px] text-slate-600 font-semibold">Telp: {{ settings.store_phone }} | Email: {{ settings.store_email }}</p>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <span class="text-xl font-black text-slate-900 tracking-wider uppercase block">INVOICE</span>
                                    <p class="font-mono font-bold text-xs text-slate-700 mt-1">{{ lastTransaction?.invoice_number }}</p>
                                    <span 
                                        :class="lastTransaction?.payment_method === 'tempo' ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-emerald-100 text-emerald-900 border-emerald-300'"
                                        class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase border mt-2"
                                    >
                                        {{ lastTransaction?.payment_method === 'tempo' ? 'STATUS: TEMPO / PIUTANG' : 'STATUS: LUNAS' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Customer & Invoice Info Grid -->
                            <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Ditagihkan Kepada:</span>
                                    <p class="text-sm font-black text-slate-900 mt-0.5">{{ lastTransaction?.customer_name }}</p>
                                    <p class="text-[11px] text-slate-600">{{ lastTransaction?.customer?.address || 'Pelanggan Toko' }}</p>
                                    <p class="text-[11px] text-slate-600">Telp: {{ lastTransaction?.customer?.phone || '-' }}</p>
                                </div>

                                <div class="space-y-1 text-right text-xs">
                                    <div class="flex justify-between sm:justify-end gap-3">
                                        <span class="text-slate-500">Tanggal Faktur:</span>
                                        <span class="font-bold text-slate-900">{{ lastTransaction?.raw_date || lastTransaction?.date }}</span>
                                    </div>
                                    <div class="flex justify-between sm:justify-end gap-3">
                                        <span class="text-slate-500">Metode Bayar:</span>
                                        <span class="font-bold text-slate-900 uppercase">{{ lastTransaction?.payment_method }}</span>
                                    </div>
                                    <div v-if="lastTransaction?.due_date" class="flex justify-between sm:justify-end gap-3 text-rose-600 font-bold">
                                        <span>Jatuh Tempo:</span>
                                        <span>{{ lastTransaction?.due_date }}</span>
                                    </div>
                                    <div class="flex justify-between sm:justify-end gap-3">
                                        <span class="text-slate-500">Kasir Pelaksana:</span>
                                        <span class="font-bold text-slate-900">{{ user?.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Table of Items A4 -->
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-100 border-b border-slate-200">
                                        <tr class="text-slate-700 font-bold uppercase text-[10px]">
                                            <th class="py-3 px-4 w-10 text-center">#</th>
                                            <th class="py-3 px-4">Deskripsi Produk / Menu</th>
                                            <th class="py-3 px-4 text-center w-20">Qty</th>
                                            <th class="py-3 px-4 text-center w-24">Satuan</th>
                                            <th class="py-3 px-4 text-right w-32">Harga Satuan</th>
                                            <th class="py-3 px-4 text-right w-36">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(it, idx) in lastTransaction?.items" :key="idx" class="hover:bg-slate-50/50">
                                            <td class="py-3 px-4 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ it.product.name }}</td>
                                            <td class="py-3 px-4 text-center font-black">{{ it.qty }}</td>
                                            <td class="py-3 px-4 text-center text-slate-600 font-semibold">{{ it.unit.unit_name }}</td>
                                            <td class="py-3 px-4 text-right text-slate-700">{{ formatRupiah(it.unit_price) }}</td>
                                            <td class="py-3 px-4 text-right font-black text-slate-900">{{ formatRupiah(it.subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary & Payment Info Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                                <div class="space-y-3 text-xs">
                                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl">
                                        <p class="font-bold text-slate-800 text-[11px] uppercase mb-1">Rekening Pembayaran Bank:</p>
                                        <p class="font-mono text-xs text-slate-700 whitespace-pre-line leading-relaxed">{{ formatTextWithBreaks(settings.bank_info) }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-[11px] uppercase mb-0.5">Syarat & Ketentuan:</p>
                                        <p class="text-[10px] text-slate-500 whitespace-pre-line leading-tight">{{ formatTextWithBreaks(settings.invoice_terms) }}</p>
                                    </div>
                                </div>

                                <div class="space-y-2 text-xs bg-slate-50 p-4 rounded-2xl border border-slate-100 self-start">
                                    <div class="flex justify-between text-slate-600">
                                        <span>Total Tagihan (Gross):</span>
                                        <span class="font-bold text-slate-800">{{ formatRupiah(lastTransaction?.total_gross) }}</span>
                                    </div>
                                    <div v-if="lastTransaction?.discount > 0" class="flex justify-between text-rose-600 font-semibold">
                                        <span>Potongan Diskon:</span>
                                        <span>-{{ formatRupiah(lastTransaction?.discount) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm font-black text-slate-900 border-t border-slate-200 pt-2">
                                        <span>TOTAL NET:</span>
                                        <span class="text-base text-slate-950">{{ formatRupiah(lastTransaction?.total_net) }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 italic pt-1 border-t border-slate-200">
                                        Terbilang: <strong class="text-slate-800">{{ numberToWords(lastTransaction?.total_net) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Signatures A4 -->
                            <div class="grid grid-cols-2 gap-8 pt-8 text-center text-xs">
                                <div>
                                    <p class="text-slate-500 font-medium">Penerima / Pelanggan,</p>
                                    <div class="h-16"></div>
                                    <p class="font-black text-slate-900 border-t border-slate-300 pt-1 inline-block min-w-[160px]">{{ lastTransaction?.customer_name }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-500 font-medium">Hormat Kami,</p>
                                    <div class="h-16"></div>
                                    <p class="font-black text-slate-900 border-t border-slate-300 pt-1 inline-block min-w-[160px]">{{ settings.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN' }}</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Modal Bottom Action Bar (Non-Printable) -->
                <div class="p-4 bg-white border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 no-print">
                    <div class="text-xs text-slate-500">
                        Format aktif: <strong class="text-slate-900">{{ printFormats.find(f => f.id === selectedPrintFormat)?.label || selectedPrintFormat }}</strong>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            @click="isReceiptOpen = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition text-xs cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button 
                            @click="printReceipt"
                            class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl flex items-center gap-2 shadow-md hover:shadow-lg transition text-xs cursor-pointer active:scale-95"
                        >
                            <Printer class="w-4 h-4 text-amber-400" />
                            <span>Cetak (Print)</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
@media print {
    /* Hide Everything in Background */
    :deep(aside),
    :deep(header),
    :deep(nav),
    .no-print {
        display: none !important;
    }

    body, html {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    #printable-receipt-modal {
        position: static !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>

