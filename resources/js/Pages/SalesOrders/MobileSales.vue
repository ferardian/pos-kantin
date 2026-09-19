<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link, router, Head } from '@inertiajs/vue3';
import { 
    Zap, Search, ShoppingBag, Plus, Minus, Trash2, User, 
    Clock, CheckCircle, ArrowLeft, ArrowRight, Phone, MapPin, 
    Calendar, FileText, Send, Sparkles, Filter, ChevronRight, 
    RefreshCw, LayoutDashboard, Monitor, LogOut, Download, X, Check,
    Edit3, AlertTriangle, RotateCcw
} from 'lucide-vue-next';

const props = defineProps({
    products: Array,
    customers: Array,
    myOrders: Array,
    user: Object,
    settings: Object,
});

const currentTab = ref('catalog'); // 'catalog', 'cart', 'history'
const searchQuery = ref('');
const selectedCategory = ref('all');
const selectedCustomer = ref(null);
const cart = ref([]);
const isCustomerModalOpen = ref(false);
const searchCustomerQuery = ref('');
const isAddingNewCustomer = ref(false);
const newCustomerForm = useForm({
    name: '',
    phone: '',
    address: '',
    tier: 'kontraktor',
    credit_limit: 0,
});
const toastMessage = ref('');
let toastTimer = null;

// State Edit Order & Batalkan Order
const editingOrder = ref(null);
const isCancelConfirmModalOpen = ref(false);
const orderToCancel = ref(null);

// PWA Install Prompt State
const deferredPrompt = ref(null);
const showInstallBanner = ref(false);

onMounted(() => {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
        showInstallBanner.value = true;
    });
});

const installPWA = async () => {
    if (!deferredPrompt.value) return;
    deferredPrompt.value.prompt();
    const { outcome } = await deferredPrompt.value.userChoice;
    if (outcome === 'accepted') {
        showInstallBanner.value = false;
    }
    deferredPrompt.value = null;
};

const triggerHaptic = () => {
    if (typeof window !== 'undefined' && window.navigator && window.navigator.vibrate) {
        try {
            window.navigator.vibrate(25);
        } catch (e) {}
    }
};

const showToast = (msg) => {
    toastMessage.value = msg;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = '';
    }, 1600);
};

const orderForm = useForm({
    customer_id: selectedCustomer.value?.id || null,
    delivery_date: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    payment_type: 'tempo',
    notes: '',
    items: [],
});

const strataList = [
    { key: 'eceran', label: 'Retail', short: 'Retail', icon: '🛒', activeClass: 'bg-slate-900 text-white shadow-xs font-black border-slate-900' },
    { key: 'tukang', label: 'Bronze', short: 'Bronze', icon: '🥉', activeClass: 'bg-amber-700 text-white shadow-xs font-black border-amber-700' },
    { key: 'kontraktor', label: 'Gold', short: 'Gold', icon: '🥇', activeClass: 'bg-amber-500 text-slate-950 shadow-xs font-black border-amber-500' },
    { key: 'grosir', label: 'Diamond', short: 'Diamond', icon: '💎', activeClass: 'bg-sky-600 text-white shadow-xs font-black border-sky-600' },
];

const selectedStrata = ref(selectedCustomer.value?.tier || 'kontraktor');

const setStrata = (tierKey) => {
    triggerHaptic();
    selectedStrata.value = tierKey;
    showToast(`Mode Harga: ${getTierLabel(tierKey)}`);
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

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
        default: return 'bg-slate-100 text-slate-800';
    }
};

const filteredProducts = computed(() => {
    return [...props.products]
        .filter(p => {
            const matchesCategory = selectedCategory.value === 'all' || p.category_id === selectedCategory.value;
            const q = searchQuery.value.toLowerCase().trim();
            const matchesSearch = !q || 
                p.name.toLowerCase().includes(q) || 
                (p.sku && p.sku.toLowerCase().includes(q)) || 
                (p.brand && p.brand.name.toLowerCase().includes(q));
            return matchesCategory && matchesSearch;
        })
        .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'id', { sensitivity: 'base', numeric: true }));
});

const getItemQty = (productId, unitId, tier = null) => {
    const targetTier = tier || selectedStrata.value;
    const item = cart.value.find(i => i.product.id === productId && i.unit.id === unitId && i.tier === targetTier);
    return item ? item.qty : 0;
};

const getTotalItemQtyAllTiers = (productId, unitId) => {
    return cart.value
        .filter(i => i.product.id === productId && i.unit.id === unitId)
        .reduce((sum, i) => sum + i.qty, 0);
};

const addToCart = (product, unit, customTier = null) => {
    triggerHaptic();
    if (!selectedCustomer.value) {
        showToast('Pilih pelanggan terlebih dahulu!');
        isCustomerModalOpen.value = true;
        return;
    }
    const tier = customTier || selectedStrata.value || selectedCustomer.value?.tier || 'eceran';
    const price = getUnitPrice(unit, tier);
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id && item.unit.id === unit.id && item.tier === tier);

    if (existingIndex > -1) {
        cart.value[existingIndex].qty += 1;
        cart.value[existingIndex].subtotal = cart.value[existingIndex].qty * cart.value[existingIndex].unit_price;
    } else {
        cart.value.push({
            product,
            unit,
            tier,
            qty: 1,
            unit_price: price,
            subtotal: price,
        });
    }
    showToast(`+1 ${product.name} (${unit.unit_name}) [${getTierLabel(tier)}]`);
};

const updateQty = (index, delta) => {
    triggerHaptic();
    const item = cart.value[index];
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        cart.value.splice(index, 1);
    } else {
        item.qty = Number(newQty.toFixed(2));
        item.subtotal = item.qty * item.unit_price;
    }
};

const updateUnitQtyInCatalog = (product, unit, delta) => {
    triggerHaptic();
    if (!selectedCustomer.value) {
        showToast('Pilih pelanggan terlebih dahulu!');
        isCustomerModalOpen.value = true;
        return;
    }
    const tier = selectedStrata.value || selectedCustomer.value?.tier || 'eceran';
    const index = cart.value.findIndex(item => item.product.id === product.id && item.unit.id === unit.id && item.tier === tier);
    if (index > -1) {
        updateQty(index, delta);
        if (delta > 0) {
            showToast(`+1 ${product.name} (${unit.unit_name}) [${getTierLabel(tier)}]`);
        }
    } else if (delta > 0) {
        addToCart(product, unit, tier);
    }
};

const changeCartItemTier = (index, newTier) => {
    triggerHaptic();
    const item = cart.value[index];
    if (!item) return;
    item.tier = newTier;
    item.unit_price = getUnitPrice(item.unit, newTier);
    item.subtotal = item.qty * item.unit_price;
    showToast(`${item.product.name} ➔ ${getTierLabel(newTier)}`);
};

const selectCustomer = (cust) => {
    selectedCustomer.value = cust;
    orderForm.customer_id = cust.id;
    if (cust.tier) {
        selectedStrata.value = cust.tier;
    }
    isCustomerModalOpen.value = false;
};

const filteredCustomersModal = computed(() => {
    const q = searchCustomerQuery.value.toLowerCase().trim();
    if (!q) return props.customers;
    return props.customers.filter(c => {
        return (c.name && c.name.toLowerCase().includes(q)) ||
               (c.address && c.address.toLowerCase().includes(q)) ||
               (c.phone && c.phone.toLowerCase().includes(q));
    });
});

const submitNewCustomer = () => {
    if (!newCustomerForm.name.trim()) {
        showToast('Nama pelanggan wajib diisi!');
        return;
    }

    newCustomerForm.post('/customers', {
        preserveScroll: true,
        onSuccess: () => {
            const addedName = newCustomerForm.name.trim().toLowerCase();
            isAddingNewCustomer.value = false;
            newCustomerForm.reset();

            // Find matching customer from newly updated props
            setTimeout(() => {
                const found = props.customers.find(c => c.name.toLowerCase() === addedName) || props.customers[0];
                if (found) {
                    selectCustomer(found);
                }
            }, 100);
            showToast('Pelanggan baru berhasil ditambahkan!');
        },
        onError: () => {
            showToast('Gagal menyimpan pelanggan.');
        }
    });
};

const totalCartAmount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

const totalCartQuantity = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.qty, 0);
});

const startEditOrder = (order) => {
    triggerHaptic();
    if (cart.value.length > 0 && (!editingOrder.value || editingOrder.value.id !== order.id)) {
        if (!confirm('Keranjang Anda saat ini sudah berisi barang. Mengedit pesanan ini akan menggantikan isi keranjang Anda. Lanjutkan?')) {
            return;
        }
    }

    editingOrder.value = order;

    // Set Customer
    const cust = props.customers.find(c => c.id === order.customer_id) || order.customer;
    if (cust) {
        selectCustomer(cust);
    }

    // Set Form Details
    orderForm.customer_id = order.customer_id;
    orderForm.delivery_date = order.delivery_date ? order.delivery_date.split('T')[0] : '';
    orderForm.payment_type = order.payment_type || 'tempo';
    orderForm.notes = order.notes || '';

    // Populate Cart with order items
    cart.value = (order.items || []).map(it => {
        const prod = props.products.find(p => p.id === it.product_id) || it.product || { id: it.product_id, name: 'Item', units: [] };
        const unit = (prod.units || []).find(u => u.id === it.product_unit_id) || it.unit || { id: it.product_unit_id, unit_name: 'Pcs' };

        let itemTier = cust?.tier || selectedStrata.value || 'eceran';
        for (const st of ['eceran', 'tukang', 'kontraktor', 'grosir']) {
            if (Math.abs(getUnitPrice(unit, st) - Number(it.unit_price)) < 1) {
                itemTier = st;
                break;
            }
        }

        return {
            product: prod,
            unit: unit,
            tier: itemTier,
            qty: Number(it.qty),
            unit_price: Number(it.unit_price),
            subtotal: Number(it.subtotal),
        };
    });

    currentTab.value = 'cart';
    showToast(`Mengedit: ${order.so_number}`);
};

const cancelEditOrder = () => {
    triggerHaptic();
    if (confirm('Batalkan pengeditan pesanan? Perubahan yang belum disimpan akan dibatalkan.')) {
        editingOrder.value = null;
        cart.value = [];
        orderForm.reset();
        currentTab.value = 'history';
        showToast('Mode edit dibatalkan.');
    }
};

const openCancelConfirm = (order) => {
    triggerHaptic();
    orderToCancel.value = order;
    isCancelConfirmModalOpen.value = true;
};

const confirmCancelOrder = () => {
    if (!orderToCancel.value) return;
    const ord = orderToCancel.value;
    isCancelConfirmModalOpen.value = false;

    router.delete(`/mobile-sales/orders/${ord.id}`, {
        onSuccess: () => {
            showToast(`Pesanan ${ord.so_number} berhasil dibatalkan.`);
            if (editingOrder.value?.id === ord.id) {
                editingOrder.value = null;
                cart.value = [];
                orderForm.reset();
            }
        },
        onError: () => {
            showToast('Gagal membatalkan pesanan.');
        }
    });
};

const submitOrder = () => {
    if (!selectedCustomer.value) {
        showToast('Pilih pelanggan terlebih dahulu!');
        isCustomerModalOpen.value = true;
        return;
    }
    if (cart.value.length === 0) {
        showToast('Keranjang order masih kosong!');
        return;
    }

    orderForm.items = cart.value.map(i => ({
        product_id: i.product.id,
        product_unit_id: i.unit.id,
        qty: i.qty,
        unit_price: i.unit_price,
        subtotal: i.subtotal,
    }));

    if (editingOrder.value) {
        orderForm.put(`/mobile-sales/orders/${editingOrder.value.id}`, {
            onSuccess: () => {
                const soNum = editingOrder.value.so_number;
                editingOrder.value = null;
                cart.value = [];
                orderForm.reset();
                currentTab.value = 'history';
                showToast(`Pesanan ${soNum} berhasil diperbarui!`);
            },
            onError: () => {
                showToast('Gagal memperbarui pesanan.');
            }
        });
    } else {
        orderForm.post('/mobile-sales/orders', {
            onSuccess: () => {
                cart.value = [];
                orderForm.reset();
                currentTab.value = 'history';
                showToast('Pesanan baru berhasil dikirim ke toko!');
            },
            onError: () => {
                showToast('Gagal mengirim pesanan.');
            }
        });
    }
};
</script>

<template>
    <Head title="Mode Sales HP (PWA)" />
    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col max-w-md mx-auto relative pb-28 shadow-2xl border-x border-slate-200 selection:bg-amber-100">
        <!-- Floating Micro Toast -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform -translate-y-4 opacity-0 scale-95"
            enter-to-class="transform translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100 scale-100"
            leave-to-class="transform -translate-y-2 opacity-0 scale-95"
        >
            <div 
                v-if="toastMessage" 
                class="fixed top-3 left-1/2 -translate-x-1/2 z-50 max-w-[90%] bg-slate-900/95 backdrop-blur-md text-white text-xs font-bold px-4 py-2 rounded-full shadow-xl flex items-center gap-2 border border-slate-700/50 pointer-events-none"
            >
                <div class="w-4 h-4 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center shrink-0">
                    <Check class="w-2.5 h-2.5 stroke-[3]" />
                </div>
                <span class="truncate">{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Top App Bar Mobile -->
        <header class="bg-white border-b border-slate-200 p-4 sticky top-0 z-30 backdrop-blur-md shadow-xs space-y-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <img :src="settings?.store_logo || '/pos-kantin/images/logo.png'" alt="Logo TJ" class="w-8 h-8 object-contain shrink-0" />
                    <div>
                        <h1 class="text-xs font-black tracking-tight text-slate-900 uppercase">{{ settings?.store_name || 'KANTIN RSIA AISYIYAH' }}</h1>
                        <p class="text-[10px] text-amber-600 font-bold flex items-center gap-1">
                            <span>Sales: {{ user?.name }}</span>
                        </p>
                    </div>
                </div>

                <!-- Desktop Return / Logout -->
                <div class="flex items-center gap-1.5">
                    <Link 
                        v-if="user?.role === 'admin' || user?.role === 'kasir'"
                        href="/pos" 
                        class="text-[11px] font-bold px-2.5 py-1.5 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 flex items-center gap-1 border border-slate-200 active:scale-95 transition"
                    >
                        <Monitor class="w-3 h-3 text-slate-900" />
                        <span>Kasir</span>
                    </Link>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 cursor-pointer active:scale-95 transition"
                        title="Logout"
                    >
                        <LogOut class="w-4 h-4" />
                    </Link>
                </div>
            </div>

            <!-- PWA Install Prompt Banner -->
            <div v-if="showInstallBanner" class="p-2.5 bg-amber-500 text-slate-950 rounded-2xl flex items-center justify-between shadow-md">
                <div class="flex items-center gap-2">
                    <Download class="w-4 h-4 shrink-0" />
                    <div>
                        <p class="text-[11px] font-black leading-tight">Install Aplikasi Sales ke HP</p>
                        <p class="text-[9px] font-medium opacity-90 leading-tight">Akses cepat seperti aplikasi native</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button @click="installPWA" class="bg-slate-950 text-white font-bold text-[10px] px-2.5 py-1 rounded-xl cursor-pointer active:scale-95 transition">
                        Install
                    </button>
                    <button @click="showInstallBanner = false" class="p-1 text-slate-900 opacity-70 hover:opacity-100 cursor-pointer">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Customer Selection Bar -->
            <div 
                @click="isCustomerModalOpen = true"
                :class="selectedCustomer ? 'bg-slate-50 border-slate-200 hover:border-amber-400' : 'bg-amber-500/10 border-2 border-dashed border-amber-500 hover:bg-amber-500/20'"
                class="p-2.5 rounded-2xl border flex items-center justify-between cursor-pointer active:scale-[0.99] transition shadow-xs"
            >
                <div class="flex items-center gap-2.5 min-w-0">
                    <div 
                        :class="selectedCustomer ? 'bg-amber-100 text-amber-700' : 'bg-amber-500 text-slate-950'" 
                        class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 shadow-xs"
                    >
                        <User class="w-4 h-4" />
                    </div>
                    <div v-if="selectedCustomer" class="truncate">
                        <p class="text-xs font-bold text-slate-900 truncate">{{ selectedCustomer.name }}</p>
                        <p class="text-[10px] text-slate-500 font-semibold">
                            {{ getTierLabel(selectedCustomer.tier) }} &bull; Sisa Bon: {{ formatRupiah(selectedCustomer.current_debt) }}
                        </p>
                    </div>
                    <div v-else class="truncate">
                        <p class="text-xs font-black text-amber-950">Pilih Pelanggan / Mitra</p>
                        <p class="text-[10px] text-amber-800 font-medium">Klik untuk memilih pelanggan sebelum order</p>
                    </div>
                </div>
                <span 
                    :class="selectedCustomer ? 'text-slate-900 bg-white border-slate-200' : 'text-slate-950 bg-amber-500 border-amber-600 font-black shadow-xs'" 
                    class="text-[10px] font-bold border px-2.5 py-1 rounded-xl shrink-0"
                >
                    {{ selectedCustomer ? 'Ganti' : '+ Pilih Pelanggan' }}
                </span>
            </div>

            <!-- Strata Tier Selector Bar (Subsidi Silang) -->
            <div class="space-y-1 pt-1">
                <div class="flex items-center justify-between px-0.5">
                    <span class="text-[10px] font-extrabold uppercase text-slate-600 tracking-wider flex items-center gap-1">
                        <Sparkles class="w-3 h-3 text-amber-500" />
                        <span>Pilih Strata Harga:</span>
                    </span>
                    <span class="text-[9px] text-amber-700 font-bold bg-amber-50 border border-amber-200 px-1.5 py-0.2 rounded-full">
                        Aktif: {{ getTierLabel(selectedStrata) }}
                    </span>
                </div>
                <div class="grid grid-cols-4 gap-1.5 p-1 bg-slate-100/90 rounded-2xl border border-slate-200/90">
                    <button 
                        v-for="st in strataList" 
                        :key="st.key"
                        type="button"
                        @click="setStrata(st.key)"
                        :class="selectedStrata === st.key ? st.activeClass : 'text-slate-600 hover:text-slate-900 bg-white/70 hover:bg-white border-transparent'"
                        class="py-1.5 px-1 rounded-xl text-center text-xs font-bold transition-all active:scale-95 cursor-pointer flex flex-col items-center justify-center border shadow-2xs"
                    >
                        <span class="text-[10px]">{{ st.icon }}</span>
                        <span class="leading-tight text-[11px]">{{ st.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Search Box (Sticky with Header) -->
            <div v-if="currentTab === 'catalog'" class="relative pt-0.5">
                <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    placeholder="Cari kabel, pipa, lampu, saklar..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-9 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white shadow-xs transition"
                />
                <button 
                    v-if="searchQuery" 
                    @click="searchQuery = ''" 
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </header>

        <!-- Mode Edit Sticky Banner -->
        <div 
            v-if="editingOrder" 
            class="bg-amber-500 text-slate-950 px-4 py-2 text-xs font-black flex items-center justify-between sticky top-[60px] z-20 shadow-md border-b border-amber-600 animate-in slide-in-from-top-2 duration-150"
        >
            <div class="flex items-center gap-2 truncate">
                <Edit3 class="w-4 h-4 shrink-0 animate-pulse text-slate-950" />
                <span class="truncate">Mode Edit: <strong>{{ editingOrder.so_number }}</strong> ({{ editingOrder.customer?.name }})</span>
            </div>
            <button 
                @click="cancelEditOrder" 
                type="button"
                class="text-[10px] bg-slate-950 hover:bg-slate-800 text-white font-black px-2.5 py-1 rounded-lg shrink-0 ml-2 active:scale-95 cursor-pointer shadow-xs"
            >
                Batal Edit
            </button>
        </div>

        <!-- Main Content based on Tab -->
        <main class="flex-1 p-4 space-y-4">
            <!-- TAB 1: KATALOG & ORDER -->
            <div v-if="currentTab === 'catalog'" class="space-y-4">
                <!-- Product Cards -->
                <div class="space-y-3">
                    <div 
                        v-for="product in filteredProducts" 
                        :key="product.id"
                        class="bg-white border border-slate-200 rounded-2xl p-3.5 space-y-2.5 shadow-xs transition-shadow hover:shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">{{ product.brand?.name }}</span>
                                    <span 
                                        :class="product.stock_available <= product.min_stock ? 'text-rose-700 bg-rose-50 border border-rose-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'"
                                        class="px-1.5 py-0.2 rounded text-[9px] font-black"
                                    >
                                        Stok: {{ product.stock_available }} {{ product.units[0]?.unit_name }}
                                    </span>
                                </div>
                                <h3 class="text-xs font-bold text-slate-900 mt-0.5 leading-snug">{{ product.name }}</h3>
                            </div>
                        </div>

                        <!-- Unit Option Buttons (Dynamic State: Normal or Stepper) -->
                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100">
                            <template v-for="unit in product.units" :key="unit.id">
                                <!-- CASE A: Item is ALREADY in Cart under current selectedStrata -->
                                <div 
                                    v-if="getItemQty(product.id, unit.id, selectedStrata) > 0"
                                    class="p-2 rounded-xl bg-amber-50/90 border-2 border-amber-500 text-left flex items-center justify-between shadow-xs transition-all"
                                >
                                    <div class="min-w-0 pr-1">
                                        <div class="flex items-center gap-1">
                                            <p class="text-[11px] font-bold text-amber-950 truncate">{{ unit.unit_name }}</p>
                                            <span class="text-[8px] font-black px-1 rounded uppercase bg-amber-500 text-slate-950">
                                                {{ getTierLabel(selectedStrata) }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-amber-900 font-extrabold">{{ formatRupiah(getUnitPrice(unit, selectedStrata)) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 bg-white border border-amber-300 rounded-lg p-0.5 shadow-2xs shrink-0">
                                        <button 
                                            @click.stop="updateUnitQtyInCatalog(product, unit, -1)" 
                                            class="w-5 h-5 rounded bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 flex items-center justify-center active:scale-75 transition cursor-pointer"
                                            title="Kurangi"
                                        >
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <span class="w-5 text-center text-xs font-black text-slate-900">{{ getItemQty(product.id, unit.id, selectedStrata) }}</span>
                                        <button 
                                            @click.stop="updateUnitQtyInCatalog(product, unit, 1)" 
                                            class="w-5 h-5 rounded bg-amber-500 text-white flex items-center justify-center active:scale-75 transition cursor-pointer shadow-xs"
                                            title="Tambah"
                                        >
                                            <Plus class="w-3 h-3 stroke-[2.5]" />
                                        </button>
                                    </div>
                                </div>

                                <!-- CASE B: Item is NOT yet in Cart under selectedStrata -->
                                <button 
                                    v-else
                                    @click="addToCart(product, unit, selectedStrata)"
                                    class="p-2 rounded-xl bg-slate-50 border border-slate-200 hover:border-amber-400 hover:bg-amber-50 active:scale-95 text-left transition-all flex items-center justify-between cursor-pointer group"
                                >
                                    <div class="min-w-0 pr-1">
                                        <div class="flex items-center gap-1">
                                            <p class="text-[11px] font-semibold text-slate-700 truncate group-hover:text-slate-900">{{ unit.unit_name }}</p>
                                            <span class="text-[8px] font-bold px-1 rounded uppercase" :class="getTierBadgeClass(selectedStrata)">
                                                {{ getTierLabel(selectedStrata) }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-900 font-black">{{ formatRupiah(getUnitPrice(unit, selectedStrata)) }}</p>
                                        <p v-if="getTotalItemQtyAllTiers(product.id, unit.id) > 0" class="text-[8px] text-amber-600 font-bold">
                                            ({{ getTotalItemQtyAllTiers(product.id, unit.id) }} di order)
                                        </p>
                                    </div>
                                    <div class="w-6 h-6 rounded-lg bg-amber-100 group-hover:bg-amber-200 text-amber-900 flex items-center justify-center font-bold text-xs shrink-0 transition-colors shadow-2xs">
                                        <Plus class="w-3 h-3" />
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: KERANJANG ORDER -->
            <div v-if="currentTab === 'cart'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">Keranjang Sales Order</h2>
                        <p class="text-[10px] text-amber-600 font-bold">Mitra: {{ selectedCustomer?.name }} ({{ getTierLabel(selectedCustomer?.tier) }})</p>
                    </div>
                    <span class="text-xs text-slate-500 font-bold">{{ cart.length }} item</span>
                </div>

                <div v-if="cart.length === 0" class="py-12 text-center text-slate-400 space-y-2">
                    <ShoppingBag class="w-12 h-12 stroke-1 mx-auto text-slate-300" />
                    <p class="text-xs font-bold text-slate-600">Keranjang masih kosong</p>
                    <button @click="currentTab = 'catalog'" class="text-xs text-amber-600 font-bold underline cursor-pointer">
                        Buka Katalog Produk
                    </button>
                </div>

                <div v-else class="space-y-3">
                    <div 
                        v-for="(item, index) in cart" 
                        :key="index"
                        class="bg-white border border-slate-200 rounded-2xl p-3.5 space-y-2.5 shadow-xs"
                    >
                        <div class="flex justify-between items-start">
                            <div class="space-y-0.5">
                                <h4 class="text-xs font-bold text-slate-900">{{ item.product.name }}</h4>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[11px] text-slate-600 font-semibold">{{ item.unit.unit_name }}</span>
                                    <span class="text-[9px] font-black px-1.5 py-0.2 rounded uppercase" :class="getTierBadgeClass(item.tier)">
                                        {{ getTierLabel(item.tier) }}
                                    </span>
                                    <span class="text-[11px] text-slate-900 font-bold">@ {{ formatRupiah(item.unit_price) }}</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-slate-900 block">{{ formatRupiah(item.subtotal) }}</span>
                                <button @click="cart.splice(index, 1)" class="text-[10px] font-bold text-rose-500 hover:text-rose-700 cursor-pointer pt-0.5 flex items-center gap-0.5 ml-auto">
                                    <Trash2 class="w-3 h-3" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </div>

                        <!-- Strata Switcher Pill Bar for this Item (Subsidi Silang) -->
                        <div class="pt-2 border-t border-slate-100 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-bold uppercase text-slate-400">Strata Harga Item Ini:</span>
                                <span class="text-[9px] font-black text-amber-700">{{ getTierLabel(item.tier) }}</span>
                            </div>
                            <div class="grid grid-cols-4 gap-1 p-0.5 bg-slate-100 rounded-xl">
                                <button 
                                    v-for="st in strataList" 
                                    :key="st.key"
                                    type="button"
                                    @click="changeCartItemTier(index, st.key)"
                                    :class="item.tier === st.key ? st.activeClass : 'bg-transparent text-slate-600 hover:text-slate-900'"
                                    class="py-1 text-[10px] font-bold rounded-lg text-center transition cursor-pointer active:scale-95"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Qty Controls -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <span class="text-[10px] font-bold text-slate-400">Jumlah Order:</span>
                            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg p-1">
                                <button @click="updateQty(index, -1)" class="w-6 h-6 rounded bg-white border border-slate-200 flex items-center justify-center text-slate-700 active:scale-90 transition cursor-pointer">
                                    <Minus class="w-3 h-3" />
                                </button>
                                <span class="w-8 text-center text-xs font-bold text-slate-900">{{ item.qty }}</span>
                                <button @click="updateQty(index, 1)" class="w-6 h-6 rounded bg-white border border-slate-200 flex items-center justify-center text-slate-700 active:scale-90 transition cursor-pointer">
                                    <Plus class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Details -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-4 space-y-3 text-xs shadow-xs">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Rencana Kirim Barang</label>
                            <input 
                                v-model="orderForm.delivery_date"
                                type="date" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900"
                            />
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Tipe Pembayaran</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    v-for="t in ['tempo', 'transfer', 'cash']" 
                                    :key="t"
                                    type="button"
                                    @click="orderForm.payment_type = t"
                                    :class="orderForm.payment_type === t ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-600 border border-slate-200'"
                                    class="py-2 text-[11px] rounded-lg uppercase transition cursor-pointer active:scale-95"
                                >
                                    {{ t }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Catatan Proyek / Pengiriman</label>
                            <textarea 
                                v-model="orderForm.notes"
                                rows="2"
                                placeholder="Contoh: Kirim ke lokasi perumahan Blok C..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-900 focus:outline-none focus:border-amber-500"
                            ></textarea>
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-sm">
                            <span class="font-bold text-slate-600">Total Estimasi:</span>
                            <span class="text-base font-black text-slate-900">{{ formatRupiah(totalCartAmount) }}</span>
                        </div>

                        <button 
                            @click="submitOrder"
                            :disabled="orderForm.processing"
                            :class="editingOrder ? 'bg-amber-500 hover:bg-amber-600 text-slate-950 shadow-amber-500/20' : 'bg-slate-900 hover:bg-slate-800 text-white shadow-slate-900/10'"
                            class="w-full font-black py-3 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2 transition cursor-pointer active:scale-98"
                        >
                            <Send v-if="!editingOrder" class="w-4 h-4 text-amber-400" />
                            <CheckCircle v-else class="w-4 h-4 text-slate-950" />
                            <span>{{ editingOrder ? `SIMPAN PERUBAHAN (${editingOrder.so_number})` : 'KIRIM SALES ORDER KE TOKO' }}</span>
                        </button>

                        <button 
                            v-if="editingOrder"
                            @click="cancelEditOrder"
                            type="button"
                            class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-3 rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer active:scale-98"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-slate-500" />
                            <span>Batalkan Mode Edit & Kosongkan Keranjang</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 3: RIWAYAT PESANAN SAYA -->
            <div v-if="currentTab === 'history'" class="space-y-3">
                <h2 class="text-sm font-black text-slate-900 mb-2">Riwayat Sales Order Saya</h2>

                <div 
                    v-for="ord in myOrders" 
                    :key="ord.id"
                    class="bg-white border border-slate-200 rounded-2xl p-3.5 space-y-2 text-xs shadow-xs"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900">{{ ord.so_number }}</span>
                                <span 
                                    v-if="editingOrder?.id === ord.id" 
                                    class="px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-500 text-slate-950 animate-pulse uppercase tracking-wider"
                                >
                                    Sedang Diedit
                                </span>
                            </div>
                            <p class="font-bold text-slate-800 mt-0.5">{{ ord.customer?.name }}</p>
                        </div>
                        <span 
                            :class="{
                                'bg-amber-100 text-amber-900': ord.status === 'pending',
                                'bg-blue-100 text-blue-900': ord.status === 'confirmed',
                                'bg-emerald-100 text-emerald-900': ord.status === 'completed',
                                'bg-rose-100 text-rose-900': ord.status === 'cancelled',
                            }"
                            class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase"
                        >
                            {{ ord.status }}
                        </span>
                    </div>

                    <div class="text-[11px] text-slate-500 space-y-0.5 border-t border-slate-100 pt-2">
                        <div v-for="it in ord.items" :key="it.id" class="flex justify-between">
                            <span>{{ it.qty }} {{ it.unit?.unit_name }} x {{ it.product?.name }}</span>
                            <span class="font-bold text-slate-800">{{ formatRupiah(it.subtotal) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex justify-between items-center font-bold">
                        <span class="text-slate-500">Total:</span>
                        <span class="text-slate-900 text-sm font-black">{{ formatRupiah(ord.total_amount) }}</span>
                    </div>

                    <!-- Action buttons for Pending Order -->
                    <div v-if="ord.status === 'pending'" class="pt-2 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button 
                            @click="openCancelConfirm(ord)"
                            type="button"
                            class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl text-xs flex items-center gap-1 transition cursor-pointer active:scale-95 border border-rose-200"
                        >
                            <Trash2 class="w-3.5 h-3.5 text-rose-600" />
                            <span>Batalkan</span>
                        </button>
                        <button 
                            @click="startEditOrder(ord)"
                            type="button"
                            :class="editingOrder?.id === ord.id ? 'bg-amber-500 text-slate-950 font-black' : 'bg-slate-900 hover:bg-slate-800 text-white font-bold'"
                            class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1 transition cursor-pointer active:scale-95 shadow-xs"
                        >
                            <Edit3 class="w-3.5 h-3.5" :class="editingOrder?.id === ord.id ? 'text-slate-950' : 'text-amber-400'" />
                            <span>{{ editingOrder?.id === ord.id ? 'Lanjut Edit' : 'Edit Order' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Floating Quick Summary Bar (Visible in Catalog when Cart has items) -->
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="transform translate-y-6 opacity-0 scale-95"
            enter-to-class="transform translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100 scale-100"
            leave-to-class="transform translate-y-6 opacity-0 scale-95"
        >
            <div 
                v-if="currentTab === 'catalog' && cart.length > 0"
                class="fixed bottom-16 left-0 right-0 max-w-md mx-auto px-4 z-40"
            >
                <div 
                    @click="currentTab = 'cart'"
                    class="bg-slate-900 text-white p-3 rounded-2xl shadow-xl border border-slate-800 flex items-center justify-between cursor-pointer active:scale-[0.98] transition-transform"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-black text-sm shrink-0 shadow-md">
                            <ShoppingBag class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-white leading-tight">
                                {{ formatRupiah(totalCartAmount) }}
                            </p>
                            <p class="text-[10px] text-amber-400 font-bold leading-tight">
                                {{ totalCartQuantity }} barang dipilih
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 bg-amber-500 text-slate-950 font-black text-xs px-3 py-1.5 rounded-xl shadow-xs">
                        <span>Lihat Order</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Bottom Navigation Bar for Mobile -->
        <nav class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white/95 border-t border-slate-200 py-2 px-4 flex items-center justify-around z-40 backdrop-blur-md shadow-lg">
            <button 
                @click="currentTab = 'catalog'"
                :class="currentTab === 'catalog' ? 'text-amber-600 font-bold' : 'text-slate-400'"
                class="flex flex-col items-center gap-1 text-[10px] transition cursor-pointer active:scale-95"
            >
                <Zap class="w-5 h-5" />
                <span>Katalog</span>
            </button>

            <button 
                @click="currentTab = 'cart'"
                :class="currentTab === 'cart' ? 'text-amber-600 font-bold' : 'text-slate-400'"
                class="flex flex-col items-center gap-1 text-[10px] relative transition cursor-pointer active:scale-95"
            >
                <div class="relative">
                    <ShoppingBag class="w-5 h-5" />
                    <span 
                        v-if="cart.length > 0" 
                        class="absolute -top-1 -right-2 bg-amber-500 text-white font-black text-[9px] w-4 h-4 rounded-full flex items-center justify-center animate-pulse"
                    >
                        {{ cart.length }}
                    </span>
                </div>
                <span>Order</span>
            </button>

            <button 
                @click="currentTab = 'history'"
                :class="currentTab === 'history' ? 'text-amber-600 font-bold' : 'text-slate-400'"
                class="flex flex-col items-center gap-1 text-[10px] transition cursor-pointer active:scale-95"
            >
                <FileText class="w-5 h-5" />
                <span>Riwayat</span>
            </button>
        </nav>

        <!-- Customer Selector & Add Customer Modal -->
        <div v-if="isCustomerModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
                <!-- Modal Header -->
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="isAddingNewCustomer" 
                            @click="isAddingNewCustomer = false"
                            class="p-1 rounded-lg hover:bg-slate-200 text-slate-600 transition cursor-pointer"
                        >
                            <ArrowLeft class="w-4 h-4" />
                        </button>
                        <h3 class="text-sm font-black text-slate-900">
                            {{ isAddingNewCustomer ? 'Tambah Pelanggan Baru' : 'Pilih Pelanggan / Mitra' }}
                        </h3>
                    </div>
                    <button @click="isCustomerModalOpen = false; isAddingNewCustomer = false;" class="text-slate-400 hover:text-slate-600 text-xs font-bold cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- VIEW 1: Customer List & Search -->
                <div v-if="!isAddingNewCustomer" class="flex flex-col flex-1 overflow-hidden">
                    <!-- Search & Add New Button -->
                    <div class="p-3 border-b border-slate-100 space-y-2 bg-white">
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input 
                                v-model="searchCustomerQuery" 
                                type="text" 
                                placeholder="Cari nama pelanggan, toko, proyek..."
                                class="w-full pl-9 pr-8 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                            />
                            <button 
                                v-if="searchCustomerQuery" 
                                @click="searchCustomerQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <button 
                            @click="isAddingNewCustomer = true"
                            class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black rounded-xl text-xs flex items-center justify-center gap-1.5 transition cursor-pointer shadow-xs active:scale-[0.99]"
                        >
                            <Plus class="w-4 h-4 stroke-[3]" />
                            <span>Tambah Pelanggan Baru</span>
                        </button>
                    </div>

                    <!-- Customer Cards List -->
                    <div class="p-3 space-y-2 overflow-y-auto flex-1">
                        <div 
                            v-for="c in filteredCustomersModal" 
                            :key="c.id"
                            @click="selectCustomer(c)"
                            :class="selectedCustomer?.id === c.id ? 'border-amber-500 bg-amber-50/40 ring-1 ring-amber-500' : 'border-slate-200 bg-slate-50 hover:border-amber-400'"
                            class="p-3 rounded-2xl border cursor-pointer flex justify-between items-center text-xs active:scale-[0.99] transition shadow-2xs"
                        >
                            <div class="space-y-0.5 truncate pr-2">
                                <div class="flex items-center gap-1.5">
                                    <p class="font-black text-slate-900 text-xs truncate">{{ c.name }}</p>
                                    <span 
                                        :class="{
                                            'bg-slate-100 text-slate-700 border border-slate-300': c.tier === 'eceran',
                                            'bg-amber-800 text-white border border-amber-900 shadow-2xs font-black': c.tier === 'tukang',
                                            'bg-amber-300 text-amber-950 border border-amber-500 shadow-2xs font-black': c.tier === 'kontraktor',
                                            'bg-sky-600 text-white border border-sky-700 shadow-2xs font-black': c.tier === 'grosir',
                                        }"
                                        class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded"
                                    >
                                        {{ getTierLabel(c.tier) }}
                                    </span>
                                </div>
                                <p v-if="c.address" class="text-[11px] text-slate-500 truncate flex items-center gap-1">
                                    <MapPin class="w-3 h-3 text-slate-400 shrink-0" />
                                    <span>{{ c.address }}</span>
                                </p>
                                <p v-if="c.phone" class="text-[10px] text-slate-500 font-mono">
                                    Telp: {{ c.phone }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[10px] text-slate-400 block">Bon/Hutang:</span>
                                <span :class="c.current_debt > 0 ? 'text-rose-600 font-bold' : 'text-slate-500'" class="text-xs">
                                    {{ formatRupiah(c.current_debt) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="filteredCustomersModal.length === 0" class="text-center py-8 text-slate-400 space-y-2">
                            <User class="w-8 h-8 mx-auto text-slate-300" />
                            <p class="text-xs">Pelanggan tidak ditemukan.</p>
                            <button 
                                @click="isAddingNewCustomer = true; newCustomerForm.name = searchCustomerQuery;"
                                class="text-xs font-bold text-amber-600 underline cursor-pointer"
                            >
                                Tambah "{{ searchCustomerQuery }}" sebagai pelanggan baru
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: Form Tambah Pelanggan Baru -->
                <div v-else class="p-4 space-y-3 overflow-y-auto flex-1 bg-white">
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
                            Kategori Strata Harga
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                v-for="t in [
                                    { id: 'eceran', label: '1. Retail' },
                                    { id: 'tukang', label: '2. Bronze' },
                                    { id: 'kontraktor', label: '3. Gold' },
                                    { id: 'grosir', label: '4. Diamond' }
                                ]" 
                                :key="t.id"
                                type="button"
                                @click="newCustomerForm.tier = t.id"
                                :class="newCustomerForm.tier === t.id ? 'bg-slate-900 text-white font-black border-slate-900' : 'bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-300'"
                                class="py-2 px-2.5 rounded-xl border text-[11px] text-center transition cursor-pointer"
                            >
                                {{ t.label }}
                            </button>
                        </div>
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
        <!-- Modal Konfirmasi Batalkan Pesanan -->
        <Teleport to="body">
            <div 
                v-if="isCancelConfirmModalOpen && orderToCancel" 
                class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4"
            >
                <div class="bg-white rounded-3xl max-w-sm w-full p-5 space-y-4 shadow-2xl border border-slate-100 animate-in zoom-in-95 duration-150">
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto shadow-inner">
                        <AlertTriangle class="w-6 h-6" />
                    </div>

                    <div class="text-center space-y-1">
                        <h3 class="text-base font-black text-slate-900">Batalkan Pesanan?</h3>
                        <p class="text-xs text-slate-500">
                            Pesanan <span class="font-mono font-bold text-slate-900">{{ orderToCancel.so_number }}</span> untuk pelanggan <strong class="text-slate-800">{{ orderToCancel.customer?.name }}</strong> akan dihapus dan dibatalkan.
                        </p>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 text-xs space-y-1">
                        <div class="flex justify-between text-slate-600">
                            <span>Total Pesanan:</span>
                            <span class="font-black text-slate-900">{{ formatRupiah(orderToCancel.total_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Jumlah Item:</span>
                            <span class="font-bold text-slate-800">{{ (orderToCancel.items || []).length }} macam barang</span>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button 
                            type="button" 
                            @click="isCancelConfirmModalOpen = false; orderToCancel = null"
                            class="w-1/2 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer active:scale-95"
                        >
                            Tutup
                        </button>
                        <button 
                            type="button" 
                            @click="confirmCancelOrder"
                            class="w-1/2 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-xl text-xs transition cursor-pointer shadow-md shadow-rose-600/20 active:scale-95 flex items-center justify-center gap-1.5"
                        >
                            <Trash2 class="w-4 h-4" />
                            <span>Ya, Batalkan</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

