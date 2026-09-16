<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Store, ShoppingCart, ClipboardList, Package, Users, BarChart3, 
    LogOut, CheckCircle, AlertTriangle, Shield,
    RotateCcw, Settings, Menu, X, ArrowLeftRight,
    LayoutDashboard, Wallet, UserCog,
    PanelLeftClose, PanelLeftOpen
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.user || {});
const settings = computed(() => page.props.settings || {});
const flash = computed(() => page.props.flash || {});

const isMobileMenuOpen = ref(false);
const isSidebarCollapsed = ref(false);

onMounted(() => {
    const saved = localStorage.getItem('pos_sidebar_collapsed');
    if (saved !== null) {
        isSidebarCollapsed.value = saved === 'true';
    }
});

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('pos_sidebar_collapsed', isSidebarCollapsed.value ? 'true' : 'false');
};

const allNavigation = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, roles: ['admin', 'kasir'] },
    { name: 'Kasir POS', href: '/pos', icon: ShoppingCart, roles: ['admin', 'kasir'] },
    { name: 'Titip Jual (Konsinyasi)', href: '/consignments', icon: Store, roles: ['admin', 'kasir'] },
    
    
    
    { name: 'Master Produk & Stok', href: '/products', icon: Package, roles: ['admin', 'kasir', 'gudang'] },
    { name: 'Piutang Karyawan', href: '/receivables', icon: ClipboardList, roles: ['admin', 'kasir'] },
    { name: 'Buku Kas & Cashbox', href: '/cashboxes', icon: Wallet, roles: ['admin', 'kasir'] },
    { name: 'Laporan & Omset', href: '/reports', icon: BarChart3, roles: ['admin'] },
    { name: 'Kelola Pengguna & Staff', href: '/users', icon: UserCog, roles: ['admin'] },
    { name: 'Pengaturan Kantin', href: '/settings', icon: Settings, roles: ['admin'] },
];

const navigation = computed(() => {
    const role = user.value.role || 'kasir';
    return allNavigation.filter(item => {
        if (item.href === '/products' && role === 'kasir') {
            const canAccess = settings.value?.kasir_can_access_products;
            if (canAccess !== '1' && canAccess !== true && canAccess !== 1) {
                return false;
            }
        }
        return item.roles.includes(role);
    });
});

const currentUrl = computed(() => page.url);

watch(currentUrl, () => {
    isMobileMenuOpen.value = false;
});
</script>

<template>
    <div class="h-screen flex flex-col lg:flex-row bg-slate-50 text-slate-800 font-sans overflow-hidden">
        
        <!-- Mobile Top Navbar (lg:hidden) -->
        <header class="lg:hidden h-16 bg-white border-b border-slate-200 px-3.5 flex items-center justify-between shrink-0 z-30 sticky top-0 shadow-xs select-none">
            <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
                <button 
                    type="button"
                    @click.stop="isMobileMenuOpen = true" 
                    class="p-2 rounded-xl text-slate-700 hover:bg-slate-100 transition cursor-pointer active:scale-95 shrink-0"
                    title="Buka Menu"
                >
                    <Menu class="w-5 h-5" />
                </button>
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <img 
                        :src="settings.store_logo ? (settings.store_logo + '?v=3') : '/images/logo.png?v=3'" 
                        alt="Logo" 
                        class="w-7 h-7 object-contain shrink-0" 
                    />
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xs font-black tracking-tight text-slate-900 leading-tight truncate">
                            {{ settings.store_name || 'KOPERASI RSIA AISYIYAH' }}
                        </h1>
                        <p class="text-[9px] text-amber-600 font-extrabold uppercase truncate">
                            {{ settings.store_tagline || 'Koperasi Kantin RSIA' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                <span class="text-[9px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase shrink-0">
                    {{ user.role }}
                </span>
            </div>
        </header>

        <!-- Mobile Drawer (Teleported to body to ensure top-level rendering) -->
        <Teleport to="body">
            <!-- Mobile Drawer Backdrop -->
            <transition
                enter-active-class="transition-opacity ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="isMobileMenuOpen" 
                    @click="isMobileMenuOpen = false"
                    class="fixed inset-0 z-[9998] bg-slate-900/60 backdrop-blur-xs lg:hidden"
                ></div>
            </transition>

            <!-- Mobile Drawer Sidebar -->
            <transition
                enter-active-class="transition ease-out duration-200 transform"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition ease-in duration-150 transform"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <aside 
                    v-if="isMobileMenuOpen"
                    class="fixed inset-y-0 left-0 w-72 bg-white z-[9999] shadow-2xl flex flex-col justify-between lg:hidden overflow-hidden"
                >
                    <div class="flex flex-col min-w-0">
                        <!-- Drawer Header -->
                        <div class="h-16 px-4 flex items-center justify-between border-b border-slate-100 bg-white shrink-0">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
                                <img 
                                    :src="settings.store_logo ? (settings.store_logo + '?v=3') : '/images/logo.png?v=3'" 
                                    alt="Logo" 
                                    class="w-8 h-8 object-contain shrink-0" 
                                />
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-xs font-black tracking-tight text-slate-900 truncate">
                                        {{ settings.store_name || 'KOPERASI RSIA AISYIYAH' }}
                                    </h2>
                                    <p class="text-[9px] text-amber-600 font-extrabold uppercase truncate">
                                        {{ settings.store_tagline || 'Koperasi Kantin RSIA' }}
                                    </p>
                                </div>
                            </div>

                            <button 
                                type="button"
                                @click="isMobileMenuOpen = false" 
                                class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition shrink-0 cursor-pointer"
                                title="Tutup Menu"
                            >
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Navigation Links -->
                        <nav class="p-4 space-y-1.5 overflow-y-auto max-h-[calc(100vh-140px)]">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3 py-2 flex items-center justify-between">
                                <span>Menu Utama</span>
                                <span class="text-[9px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">
                                    {{ user.role }}
                                </span>
                            </div>
                            
                            <template v-for="item in navigation" :key="item.name">
                                <Link 
                                    :href="item.href"
                                    @click="isMobileMenuOpen = false"
                                    :class="[
                                        currentUrl.startsWith(item.href) 
                                            ? 'bg-amber-50 text-amber-700 font-bold border border-amber-200/80 shadow-xs' 
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border border-transparent',
                                        'flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150'
                                    ]"
                                >
                                    <div class="flex items-center gap-3">
                                        <component :is="item.icon" :class="currentUrl.startsWith(item.href) ? 'text-amber-600' : 'text-slate-400'" class="w-4 h-4 shrink-0" />
                                        <span>{{ item.name }}</span>
                                    </div>
                                    <span 
                                        v-if="item.badge > 0" 
                                        class="px-2 py-0.5 text-[10px] font-black bg-rose-500 text-white rounded-full animate-pulse shadow-xs"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </Link>
                            </template>


                        </nav>
                    </div>

                    <!-- User Profile & Logout -->
                    <div class="p-4 border-t border-slate-100 bg-slate-50/70">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-9 h-9 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                    {{ user.name ? user.name.charAt(0) : 'U' }}
                                </div>
                                <div class="truncate">
                                    <p class="text-xs font-bold text-slate-900 truncate">{{ user.name }}</p>
                                    <p class="text-[10px] text-amber-600 font-extrabold uppercase">{{ user.role }}</p>
                                </div>
                            </div>
                            <Link 
                                href="/logout" 
                                method="post" 
                                as="button" 
                                class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer"
                                title="Keluar / Logout"
                            >
                                <LogOut class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                </aside>
            </transition>
        </Teleport>

        <!-- Sidebar Desktop (hidden on mobile, visible on lg:flex) -->
        <aside 
            :class="[
                isSidebarCollapsed ? 'w-18' : 'w-64',
                'hidden lg:flex bg-white border-r border-slate-200 flex-col justify-between shrink-0 shadow-sm h-screen overflow-y-auto transition-all duration-200 ease-in-out select-none z-20'
            ]"
        >
            <div>
                <!-- Brand Header with Logo & Collapse Toggle Button -->
                <div 
                    :class="isSidebarCollapsed ? 'h-20 px-2 flex flex-col items-center justify-center border-b border-slate-100 bg-white gap-1' : 'h-20 px-4 flex items-center justify-between border-b border-slate-100 bg-white'"
                >
                    <div v-if="!isSidebarCollapsed" class="flex items-center gap-2.5 min-w-0 flex-1 mr-1">
                        <img 
                            :src="settings.store_logo ? (settings.store_logo + '?v=3') : '/images/logo.png?v=3'" 
                            alt="Logo" 
                            class="w-10 h-10 object-contain drop-shadow-xs shrink-0" 
                        />
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xs font-black tracking-tight text-slate-900 leading-tight truncate">
                                {{ settings.store_name || 'KOPERASI RSIA AISYIYAH' }}
                            </h1>
                            <p class="text-[9px] text-amber-600 font-extrabold tracking-wider uppercase truncate">
                                {{ settings.store_tagline || 'Koperasi Kantin RSIA' }}
                            </p>
                        </div>
                    </div>

                    <img 
                        v-else
                        :src="settings.store_logo ? (settings.store_logo + '?v=3') : '/images/logo.png?v=3'" 
                        alt="Logo" 
                        class="w-7 h-7 object-contain drop-shadow-xs" 
                    />

                    <button 
                        type="button"
                        @click="toggleSidebar"
                        class="p-1.5 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-800 transition cursor-pointer shrink-0 active:scale-95"
                        :title="isSidebarCollapsed ? 'Perluas Menu Navbar (Expand)' : 'Kecilkan Menu Navbar (Collapse)'"
                    >
                        <PanelLeftOpen v-if="isSidebarCollapsed" class="w-4 h-4 text-slate-600" />
                        <PanelLeftClose v-else class="w-4 h-4 text-slate-400 hover:text-slate-700" />
                    </button>
                </div>

                <!-- Navigation Links -->
                <nav :class="isSidebarCollapsed ? 'p-2 space-y-1.5' : 'p-4 space-y-1.5'">
                    <div v-if="!isSidebarCollapsed" class="text-[11px] font-bold uppercase tracking-wider text-slate-400 px-3 py-2 flex items-center justify-between">
                        <span>Menu Utama</span>
                        <span class="text-[9px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">
                            {{ user.role }}
                        </span>
                    </div>
                    <div v-else class="text-center py-1">
                        <span class="text-[8px] font-black px-1 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">
                            {{ user.role?.substring(0, 3) }}
                        </span>
                    </div>
                    
                    <template v-for="item in navigation" :key="item.name">
                        <Link 
                            :href="item.href"
                            :title="isSidebarCollapsed ? item.name : undefined"
                            :class="[
                                currentUrl.startsWith(item.href) 
                                    ? 'bg-amber-50 text-amber-700 font-bold border border-amber-200/80 shadow-xs' 
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border border-transparent',
                                isSidebarCollapsed 
                                    ? 'p-2.5 flex items-center justify-center relative rounded-xl transition-all duration-150 group'
                                    : 'flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150'
                            ]"
                        >
                            <div :class="isSidebarCollapsed ? 'flex items-center justify-center relative' : 'flex items-center gap-3'">
                                <component :is="item.icon" :class="currentUrl.startsWith(item.href) ? 'text-amber-600' : 'text-slate-400 group-hover:text-slate-700'" class="w-4 h-4 shrink-0" />
                                <span v-if="!isSidebarCollapsed">{{ item.name }}</span>
                                <span 
                                    v-if="isSidebarCollapsed && item.badge > 0" 
                                    class="absolute -top-1.5 -right-2 px-1 py-0.2 min-w-4 text-[9px] font-black bg-rose-500 text-white rounded-full text-center animate-pulse shadow-xs"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                            <span 
                                v-if="!isSidebarCollapsed && item.badge > 0" 
                                class="px-2 py-0.5 text-[10px] font-black bg-rose-500 text-white rounded-full animate-pulse shadow-xs"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </template>


                </nav>
            </div>

            <!-- User Profile & Official Logout Bottom -->
            <div :class="isSidebarCollapsed ? 'p-2 border-t border-slate-100 bg-slate-50/70 flex flex-col items-center gap-2' : 'p-4 border-t border-slate-100 bg-slate-50/70'">
                <div v-if="!isSidebarCollapsed" class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-9 h-9 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shadow-xs shrink-0">
                            {{ user.name ? user.name.charAt(0) : 'U' }}
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold text-slate-900 truncate">{{ user.name }}</p>
                            <p class="text-[10px] text-amber-600 font-extrabold uppercase">{{ user.role }}</p>
                        </div>
                    </div>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer"
                        title="Keluar / Logout"
                    >
                        <LogOut class="w-4 h-4" />
                    </Link>
                </div>

                <div v-else class="flex flex-col items-center gap-2">
                    <div 
                        class="w-9 h-9 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs shadow-xs shrink-0 cursor-default"
                        :title="`${user.name || 'User'} (${user.role || ''})`"
                    >
                        {{ user.name ? user.name.charAt(0) : 'U' }}
                    </div>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition cursor-pointer"
                        title="Keluar / Logout"
                    >
                        <LogOut class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 min-h-0 h-full overflow-hidden bg-slate-50">
            <!-- Top Alert Banner if any -->
            <div v-if="page.props.flash?.success" class="bg-emerald-50 border-b border-emerald-200 px-6 py-2.5 flex items-center gap-3 text-emerald-800 text-xs font-semibold">
                <CheckCircle class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>{{ page.props.flash.success }}</span>
            </div>
            <div v-if="page.props.flash?.error" class="bg-rose-50 border-b border-rose-200 px-6 py-2.5 flex items-center gap-3 text-rose-800 text-xs font-semibold">
                <AlertTriangle class="w-4 h-4 text-rose-600 shrink-0" />
                <span>{{ page.props.flash.error }}</span>
            </div>

            <!-- Page Slot -->
            <main class="flex-1 min-h-0 overflow-y-auto relative">
                <slot />
            </main>
        </div>
    </div>
</template>
