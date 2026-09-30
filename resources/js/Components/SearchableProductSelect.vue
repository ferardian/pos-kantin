<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Search, ChevronDown, Check, X, Store, Tag } from 'lucide-vue-next';

const props = defineProps({
    modelValue: [String, Number],
    products: {
        type: Array,
        default: () => []
    },
    preferredConsignorId: {
        type: [String, Number],
        default: null
    },
    placeholder: {
        type: String,
        default: '-- Cari & Pilih dari Katalog Jajan --'
    }
});

const emit = defineEmits(['update:modelValue', 'select']);

const isOpen = ref(false);
const searchQuery = ref('');
const highlightedIndex = ref(0);
const rootRef = ref(null);
const searchInputRef = ref(null);

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(val || 0);
};

// Selected product object
const selectedProduct = computed(() => {
    if (!props.modelValue) return null;
    return (props.products || []).find(p => p.id == props.modelValue) || null;
});

// Filtered and sorted products
const filteredProducts = computed(() => {
    let list = [...(props.products || [])];
    const q = (searchQuery.value || '').toLowerCase().trim();

    if (q) {
        list = list.filter(p => 
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.barcode && p.barcode.toLowerCase().includes(q)) ||
            (p.consignor?.name && p.consignor.name.toLowerCase().includes(q))
        );
    }

    // Prioritize products from preferred consignor if specified
    if (props.preferredConsignorId) {
        list.sort((a, b) => {
            const aMatch = a.consignor_id == props.preferredConsignorId ? 1 : 0;
            const bMatch = b.consignor_id == props.preferredConsignorId ? 1 : 0;
            if (aMatch !== bMatch) return bMatch - aMatch;
            return (a.name || '').localeCompare(b.name || '');
        });
    }

    return list;
});

watch(searchQuery, () => {
    highlightedIndex.value = 0;
});

const openDropdown = () => {
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
    setTimeout(() => {
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    }, 60);
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
};

const toggleDropdown = () => {
    if (isOpen.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
};

const selectProduct = (product) => {
    if (!product) return;
    emit('update:modelValue', product.id);
    emit('select', product);
    closeDropdown();
};

const clearSelection = (e) => {
    e.stopPropagation();
    emit('update:modelValue', '');
    emit('select', null);
    searchQuery.value = '';
};

const onKeyDown = (e) => {
    if (!isOpen.value) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (highlightedIndex.value < filteredProducts.value.length - 1) {
            highlightedIndex.value++;
            scrollToHighlighted();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (highlightedIndex.value > 0) {
            highlightedIndex.value--;
            scrollToHighlighted();
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredProducts.value.length > 0) {
            selectProduct(filteredProducts.value[highlightedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        closeDropdown();
    }
};

const scrollToHighlighted = () => {
    const el = document.getElementById(`prod-opt-${highlightedIndex.value}`);
    if (el) {
        el.scrollIntoView({ block: 'nearest' });
    }
};

// Handle click outside to close dropdown
const handleClickOutside = (e) => {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="rootRef" class="relative w-full" :class="{ 'z-50': isOpen }">
        <!-- Trigger Button / Display Box -->
        <div 
            @click="toggleDropdown"
            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium cursor-pointer transition flex items-center justify-between gap-2 shadow-2xs hover:border-amber-400 focus:border-amber-500 select-none"
            :class="{ 'ring-2 ring-amber-500/20 border-amber-500': isOpen }"
        >
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <Search class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                <div v-if="selectedProduct" class="truncate flex items-center gap-2">
                    <span class="font-bold text-slate-900">{{ selectedProduct.name }}</span>
                    <span class="text-[10px] text-slate-500 font-mono hidden sm:inline">
                        (Setor: {{ formatRupiah(selectedProduct.base_unit?.cost_price || 0) }} | Jual: {{ formatRupiah(selectedProduct.base_unit?.price_retail || 0) }})
                    </span>
                </div>
                <span v-else class="text-slate-400 truncate">{{ placeholder }}</span>
            </div>

            <div class="flex items-center gap-1 shrink-0">
                <button 
                    v-if="selectedProduct" 
                    type="button" 
                    @click="clearSelection" 
                    class="p-0.5 text-slate-400 hover:text-rose-600 rounded-md hover:bg-rose-50 transition cursor-pointer"
                    title="Kosongkan pilihan"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown class="w-4 h-4 text-slate-400 transition" :class="{ 'rotate-180': isOpen }" />
            </div>
        </div>

        <!-- Dropdown Menu with Search Input & Results -->
        <div 
            v-if="isOpen"
            class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-100"
        >
            <!-- Search Input Header -->
            <div class="p-2 border-b border-slate-100 bg-slate-50/90 flex items-center gap-2">
                <Search class="w-3.5 h-3.5 text-amber-600 ml-1.5 shrink-0" />
                <input 
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Ketik nama jajan untuk mencari (Tekan ↑↓ Enter)..."
                    class="w-full bg-transparent text-xs text-slate-900 font-medium placeholder:text-slate-400 focus:outline-none py-1"
                    autocomplete="off"
                    @keydown="onKeyDown"
                />
                <button 
                    v-if="searchQuery" 
                    type="button"
                    @click="searchQuery = ''; highlightedIndex = 0;" 
                    class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                >
                    <X class="w-3 h-3" />
                </button>
            </div>

            <!-- Scrollable Options List -->
            <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 p-1">
                <div 
                    v-if="filteredProducts.length === 0" 
                    class="py-6 text-center text-xs text-slate-400"
                >
                    Tidak ada jajan yang cocok dengan "<strong class="text-slate-600">{{ searchQuery }}</strong>"
                </div>

                <div 
                    v-for="(p, idx) in filteredProducts" 
                    :id="`prod-opt-${idx}`"
                    :key="p.id"
                    @click="selectProduct(p)"
                    @mouseenter="highlightedIndex = idx"
                    class="p-2.5 text-xs rounded-xl cursor-pointer transition flex items-center justify-between gap-2"
                    :class="{
                        'bg-amber-100/90 font-bold text-amber-950 border border-amber-300': idx === highlightedIndex,
                        'hover:bg-amber-50/80': idx !== highlightedIndex,
                        'bg-amber-50/50': p.id == modelValue && idx !== highlightedIndex
                    }"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 truncate">{{ p.name }}</span>
                            <span 
                                v-if="preferredConsignorId && p.consignor_id == preferredConsignorId" 
                                class="px-1.5 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-700 shrink-0"
                            >
                                Jajan Penitip Ini
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5 flex-wrap">
                            <span class="text-slate-700 font-medium">
                                Setor: <strong class="text-slate-900">{{ formatRupiah(p.base_unit?.cost_price || 0) }}</strong>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="text-slate-700 font-medium">
                                Jual: <strong class="text-slate-900">{{ formatRupiah(p.base_unit?.price_retail || p.base_unit?.selling_price || 0) }}</strong>
                            </span>
                            <span v-if="p.consignor" class="text-slate-400 flex items-center gap-0.5">
                                • <Store class="w-3 h-3 text-slate-400 inline" /> {{ p.consignor.name }}
                            </span>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span 
                            class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold"
                            :class="Number(p.stock_physical) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'"
                        >
                            Stok: {{ Number(p.stock_physical) || 0 }} pcs
                        </span>
                        <Check v-if="p.id == modelValue" class="w-4 h-4 text-amber-600 mt-1 ml-auto" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
