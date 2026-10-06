<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { appRoute } from '@/Utils/route';
import { 
    ShoppingBag, Clock, CheckCircle2, XCircle, Truck, PackageCheck, 
    User, Phone, MapPin, AlertCircle, RefreshCw, Volume2, VolumeX,
    CreditCard, Banknote, QrCode
} from 'lucide-vue-next';

const props = defineProps({
    orders: Object,
    counts: Object,
    currentStatus: String,
    user: Object,
});

const page = usePage();
const isRefreshing = ref(false);
const soundEnabled = ref(true);
let pollInterval = null;
let lastPendingCount = ref(props.counts?.pending || 0);

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
};

const playAlertSound = () => {
    if (!soundEnabled.value) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch (_) {}
};

const checkNewOrders = async () => {
    try {
        const res = await fetch(appRoute('/canteen-orders/check-pending'));
        if (res.ok) {
            const data = await res.json();
            if (data.pending_count > lastPendingCount.value) {
                playAlertSound();
                router.reload({ only: ['orders', 'counts'] });
            }
            lastPendingCount.value = data.pending_count;
        }
    } catch (_) {}
};

onMounted(() => {
    pollInterval = setInterval(checkNewOrders, 15000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const changeFilter = (st) => {
    router.get(appRoute('/canteen-orders'), { status: st }, { preserveState: true, preserveScroll: true });
};

const updateOrderStatus = (orderId, targetStatus, reason = null) => {
    if (targetStatus === 'cancelled' && !confirm('Yakin ingin membatalkan pesanan ini? Stok yang sempat di-booking akan dikembalikan.')) {
        return;
    }
    if (targetStatus === 'completed' && !confirm('Selesaikan pesanan ini? Stok fisik akan langsung dipotong dan transaksi/bon akan dibukukan secara otomatis.')) {
        return;
    }

    router.post(appRoute(`/canteen-orders/${orderId}/status`), {
        status: targetStatus,
        cancel_reason: reason,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Pesanan Online Karyawan - Kantin RSIA" />

    <MainLayout>
        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                            <ShoppingBag class="w-7 h-7" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pesanan Online Karyawan</h1>
                            <p class="text-sm text-slate-500">Order mandiri karyawan dari mess & ruangan kerja RSIA</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        @click="soundEnabled = !soundEnabled"
                        :class="soundEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold border flex items-center gap-2 transition"
                    >
                        <Volume2 v-if="soundEnabled" class="w-4 h-4 text-emerald-600" />
                        <VolumeX v-else class="w-4 h-4" />
                        {{ soundEnabled ? 'Suara Bel Aktif' : 'Suara Mati' }}
                    </button>

                    <button 
                        @click="router.reload({ only: ['orders', 'counts'] })"
                        class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold flex items-center gap-2 shadow-sm transition"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Status Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <button 
                    @click="changeFilter('active')"
                    :class="currentStatus === 'active' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap flex items-center gap-2"
                >
                    Semua Aktif
                    <span class="px-2 py-0.5 rounded-full text-xs" :class="currentStatus === 'active' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700'">
                        {{ (counts.pending || 0) + (counts.preparing || 0) + (counts.delivering || 0) }}
                    </span>
                </button>

                <button 
                    @click="changeFilter('pending')"
                    :class="currentStatus === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap flex items-center gap-2"
                >
                    Menunggu Konfirmasi
                    <span class="px-2 py-0.5 rounded-full text-xs" :class="currentStatus === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 font-bold'">
                        {{ counts.pending || 0 }}
                    </span>
                </button>

                <button 
                    @click="changeFilter('preparing')"
                    :class="currentStatus === 'preparing' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap flex items-center gap-2"
                >
                    Sedang Disiapkan
                    <span class="px-2 py-0.5 rounded-full text-xs" :class="currentStatus === 'preparing' ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700'">
                        {{ counts.preparing || 0 }}
                    </span>
                </button>

                <button 
                    @click="changeFilter('on_delivery')"
                    :class="currentStatus === 'on_delivery' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap flex items-center gap-2"
                >
                    Diantar / Siap Ambil
                    <span class="px-2 py-0.5 rounded-full text-xs" :class="currentStatus === 'on_delivery' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-700'">
                        {{ counts.delivering || 0 }}
                    </span>
                </button>

                <button 
                    @click="changeFilter('completed')"
                    :class="currentStatus === 'completed' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap flex items-center gap-2"
                >
                    Selesai Hari Ini
                    <span class="px-2 py-0.5 rounded-full text-xs" :class="currentStatus === 'completed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'">
                        {{ counts.completed || 0 }}
                    </span>
                </button>
            </div>

            <!-- Order Cards List -->
            <div v-if="orders.data && orders.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div 
                    v-for="order in orders.data" 
                    :key="order.id"
                    class="bg-white rounded-2xl p-5 border shadow-sm transition hover:shadow-md flex flex-col justify-between"
                    :class="{
                        'border-amber-300 ring-2 ring-amber-400/20': order.status === 'pending',
                        'border-blue-200': order.status === 'confirmed' || order.status === 'preparing',
                        'border-purple-200': order.status === 'on_delivery' || order.status === 'ready_for_pickup',
                        'border-slate-200': order.status === 'completed' || order.status === 'cancelled',
                    }"
                >
                    <div>
                        <!-- Header Card: Order No & Status Badge -->
                        <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <span class="text-xs font-mono font-bold text-slate-500 uppercase tracking-wider block">#{{ order.order_number }}</span>
                                <span class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                    <Clock class="w-3.5 h-3.5" />
                                    {{ formatDate(order.created_at) }}
                                </span>
                            </div>

                            <span 
                                class="px-3 py-1 rounded-full text-xs font-bold tracking-tight inline-flex items-center gap-1.5"
                                :class="{
                                    'bg-amber-100 text-amber-800 animate-pulse': order.status === 'pending',
                                    'bg-blue-100 text-blue-800': order.status === 'confirmed' || order.status === 'preparing',
                                    'bg-purple-100 text-purple-800': order.status === 'on_delivery' || order.status === 'ready_for_pickup',
                                    'bg-emerald-100 text-emerald-800': order.status === 'completed',
                                    'bg-rose-100 text-rose-800': order.status === 'cancelled',
                                }"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="{
                                    'bg-amber-500': order.status === 'pending',
                                    'bg-blue-500': order.status === 'confirmed' || order.status === 'preparing',
                                    'bg-purple-500': order.status === 'on_delivery' || order.status === 'ready_for_pickup',
                                    'bg-emerald-500': order.status === 'completed',
                                    'bg-rose-500': order.status === 'cancelled',
                                }"></span>
                                {{ order.status_label }}
                            </span>
                        </div>

                        <!-- Info Pemesan & Lokasi Pengantaran -->
                        <div class="py-3 space-y-2 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <User class="w-4 h-4 text-slate-400 shrink-0" />
                                <div class="text-sm font-semibold text-slate-800 truncate">
                                    {{ order.employee?.name || order.recipient_name }}
                                    <span v-if="order.employee?.department" class="text-xs font-normal text-slate-500">({{ order.employee.department }})</span>
                                </div>
                            </div>

                            <div class="flex items-start gap-2">
                                <MapPin class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                                <div class="text-xs text-slate-700 leading-snug">
                                    <span class="font-bold text-emerald-700 uppercase tracking-wide">
                                        {{ order.order_type === 'delivery' ? 'Antar Mess' : 'Ambil di Kantin' }}:
                                    </span>
                                    <span class="ml-1 font-medium">{{ order.delivery_location || 'Kantin RSIA (Self Pickup)' }}</span>
                                </div>
                            </div>

                            <!-- Metode Pembayaran -->
                            <div class="flex items-center gap-2 pt-1">
                                <span class="text-xs text-slate-400">Bayar:</span>
                                <span 
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold tracking-tight inline-flex items-center gap-1"
                                    :class="{
                                        'bg-rose-50 text-rose-700 border border-rose-200': order.payment_method === 'bon',
                                        'bg-indigo-50 text-indigo-700 border border-indigo-200': order.payment_method === 'qris',
                                        'bg-emerald-50 text-emerald-700 border border-emerald-200': order.payment_method === 'cash',
                                    }"
                                >
                                    <CreditCard v-if="order.payment_method === 'bon'" class="w-3 h-3" />
                                    <QrCode v-else-if="order.payment_method === 'qris'" class="w-3 h-3" />
                                    <Banknote v-else class="w-3 h-3" />
                                    {{ order.payment_method === 'bon' ? 'Bon Karyawan (Payroll)' : (order.payment_method === 'qris' ? 'QRIS' : 'Tunai COD') }}
                                </span>
                            </div>
                        </div>

                        <!-- Daftar Item Pesanan -->
                        <div class="py-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-2">Item Pesanan:</span>
                            <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                <div 
                                    v-for="item in order.items" 
                                    :key="item.id"
                                    class="flex items-start justify-between text-xs py-1 px-2 rounded-lg bg-slate-50"
                                >
                                    <div>
                                        <div class="font-semibold text-slate-800">
                                            {{ item.qty }}x {{ item.product_name }}
                                        </div>
                                        <div v-if="item.notes" class="text-[11px] text-amber-600 italic">
                                            ↳ Catatan: "{{ item.notes }}"
                                        </div>
                                    </div>
                                    <span class="font-mono text-slate-600 font-medium shrink-0 ml-2">
                                        {{ formatRupiah(item.subtotal) }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="order.notes" class="mt-2.5 p-2 bg-amber-50 rounded-xl text-xs text-amber-800 border border-amber-200/60 flex items-start gap-1.5">
                                <AlertCircle class="w-3.5 h-3.5 shrink-0 mt-0.5 text-amber-600" />
                                <div><span class="font-bold">Catatan Umum:</span> {{ order.notes }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Card: Total & Tombol Aksi -->
                    <div class="pt-3 border-t border-slate-100 mt-2">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs text-slate-500 font-medium">Total Tagihan (Harga Karyawan):</span>
                            <span class="text-base font-extrabold text-slate-900 font-mono">
                                {{ formatRupiah(order.total_amount) }}
                            </span>
                        </div>

                        <!-- Action Buttons based on status -->
                        <div class="space-y-2">
                            <!-- Pending Actions -->
                            <div v-if="order.status === 'pending'" class="grid grid-cols-2 gap-2">
                                <button 
                                    @click="updateOrderStatus(order.id, 'confirmed')"
                                    class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                    Terima Order
                                </button>
                                <button 
                                    @click="updateOrderStatus(order.id, 'cancelled')"
                                    class="w-full py-2 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1"
                                >
                                    <XCircle class="w-4 h-4" />
                                    Tolak
                                </button>
                            </div>

                            <!-- Confirmed Actions -->
                            <div v-else-if="order.status === 'confirmed'" class="space-y-2">
                                <button 
                                    @click="updateOrderStatus(order.id, 'preparing')"
                                    class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                                >
                                    <PackageCheck class="w-4 h-4" />
                                    Mulai Siapkan / Masak
                                </button>
                            </div>

                            <!-- Preparing Actions -->
                            <div v-else-if="order.status === 'preparing'" class="space-y-2">
                                <button 
                                    v-if="order.order_type === 'delivery'"
                                    @click="updateOrderStatus(order.id, 'on_delivery')"
                                    class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                                >
                                    <Truck class="w-4 h-4" />
                                    Kirim / Antar ke Mess
                                </button>
                                <button 
                                    v-else
                                    @click="updateOrderStatus(order.id, 'ready_for_pickup')"
                                    class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                                >
                                    <PackageCheck class="w-4 h-4" />
                                    Siap Diambil di Kantin
                                </button>
                            </div>

                            <!-- On Delivery / Ready Actions -> Complete -->
                            <div v-else-if="order.status === 'on_delivery' || order.status === 'ready_for_pickup'" class="space-y-2">
                                <button 
                                    @click="updateOrderStatus(order.id, 'completed')"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
                                >
                                    <CheckCircle2 class="w-4 h-4" />
                                    Selesai & Bukukan Kas/Bon
                                </button>
                            </div>

                            <!-- Completed Badge -->
                            <div v-else-if="order.status === 'completed'" class="p-2 bg-emerald-50 text-emerald-800 text-center rounded-xl text-xs font-semibold border border-emerald-200">
                                ✓ Pesanan Telah Selesai & Terbukukan ke POS
                            </div>

                            <!-- Cancelled Badge -->
                            <div v-else-if="order.status === 'cancelled'" class="p-2 bg-rose-50 text-rose-700 text-center rounded-xl text-xs font-semibold border border-rose-200">
                                ✕ Pesanan Telah Dibatalkan
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white rounded-2xl p-12 text-center border border-slate-100 shadow-sm">
                <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
                    <ShoppingBag class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-slate-800">Tidak ada pesanan online pada kategori ini</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    Pesanan baru dari karyawan di mess atau ruangan kerja akan otomatis muncul di sini dan membunyikan alarm bel.
                </p>
            </div>
        </div>
    </MainLayout>
</template>
