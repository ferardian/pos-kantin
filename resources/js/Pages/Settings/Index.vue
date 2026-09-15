<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    Settings, Store, Phone, MapPin, Mail, FileText, 
    CreditCard, Printer, Save, CheckCircle2, ShieldAlert,
    Sparkles, Receipt, HelpCircle, Image, Upload, Trash2,
    RefreshCw, Lock, EyeOff, ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
    settings: Object,
});

const logoPreview = ref(props.settings.store_logo || '/images/logo.png');
const fileInput = ref(null);

const form = useForm({
    store_name: props.settings.store_name || '',
    store_tagline: props.settings.store_tagline || '',
    store_address: props.settings.store_address || '',
    store_phone: props.settings.store_phone || '',
    store_email: props.settings.store_email || '',
    receipt_footer: props.settings.receipt_footer || '',
    invoice_terms: props.settings.invoice_terms || '',
    bank_info: props.settings.bank_info || '',
    default_print_format: props.settings.default_print_format || 'thermal',
    logo: null,
    remove_logo: false,
    kasir_can_access_products: (props.settings.kasir_can_access_products === '1' || props.settings.kasir_can_access_products === 1) ? '1' : '0',
    kasir_can_see_cost_price: (props.settings.kasir_can_see_cost_price === '1' || props.settings.kasir_can_see_cost_price === 1) ? '1' : '0',
});

const printFormats = [
    { id: 'thermal', title: 'Struk Nota Thermal (58/80mm)', desc: 'Printer kasir roll mini eceran/POS' },
    { id: 'dot_matrix', title: 'Faktur Print Dot Matrix', desc: 'Kertas continuous form rangkap 9.5 x 5.5 inch (Epson LX/LQ-310)' },
    { id: 'invoice', title: 'Invoice Standar (Kertas A4)', desc: 'Tagihan formal PDF / Printer biasa' },
];

const onLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        form.remove_logo = false;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const resetToDefaultLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = '/images/logo.png';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const submit = () => {
    form.post('/settings', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Pengaturan Toko & Cetak" />
    <MainLayout>
        <div class="p-8 max-w-5xl mx-auto space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 flex items-center gap-2.5">
                        <Settings class="w-6 h-6 text-amber-500" />
                        <span>Pengaturan Toko & Format Cetak</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 font-medium">
                        Kelola profil identitas koperasi kantin RSIA, logo usaha, kontak, serta pengaturan nota kasir.
                    </p>
                </div>

                <button 
                    @click="submit"
                    :disabled="form.processing"
                    class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-2 shadow-md hover:shadow-lg transition cursor-pointer active:scale-95 disabled:opacity-50 shrink-0"
                >
                    <Save class="w-4 h-4 text-amber-400" />
                    <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                </button>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Form Settings (Left 2 Cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- SECTION 1: Identitas Toko & Logo -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                            <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-black">
                                <Store class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Identitas & Logo Toko</h3>
                                <p class="text-[11px] text-slate-400">Informasi dan logo yang muncul pada sidebar, kop nota struk, dan faktur penjualan.</p>
                            </div>
                        </div>

                        <!-- Logo Upload Card -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex flex-col sm:flex-row items-center gap-4">
                            <div class="relative group w-20 h-20 bg-white border border-slate-200 rounded-2xl p-2 flex items-center justify-center shadow-xs shrink-0 overflow-hidden">
                                <img :src="logoPreview" alt="Logo Toko" class="w-full h-full object-contain" />
                            </div>
                            <div class="space-y-2 flex-1 text-center sm:text-left">
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">Logo Usaha Toko</h4>
                                    <p class="text-[11px] text-slate-500">Format JPG, PNG, WEBP, atau SVG (Maks. 2MB). Disarankan rasio 1:1.</p>
                                </div>
                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                    <input 
                                        ref="fileInput" 
                                        type="file" 
                                        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml" 
                                        class="hidden" 
                                        @change="onLogoChange" 
                                    />
                                    <button 
                                        type="button" 
                                        @click="triggerFileInput"
                                        class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer shadow-xs"
                                    >
                                        <Upload class="w-3.5 h-3.5 text-amber-400" />
                                        <span>Ganti Logo Toko</span>
                                    </button>
                                    <button 
                                        v-if="form.logo || props.settings.store_logo" 
                                        type="button" 
                                        @click="resetToDefaultLogo"
                                        class="px-3 py-1.5 bg-slate-200 hover:bg-rose-50 hover:text-rose-700 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition cursor-pointer"
                                        title="Kembalikan ke logo default"
                                    >
                                        <RefreshCw class="w-3.5 h-3.5" />
                                        <span>Reset ke Default</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3.5 text-xs">
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Nama Toko</label>
                                <input 
                                    v-model="form.store_name" 
                                    type="text" 
                                    required
                                    placeholder="Contoh: KOPERASI RSIA AISYIYAH PEKAJANGAN"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 font-bold focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                />
                            </div>

                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Slogan / Tagline Usaha</label>
                                <input 
                                    v-model="form.store_tagline" 
                                    type="text" 
                                    placeholder="Contoh: Kantin & Koperasi RSIA Aisyiyah Pekajangan"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-slate-700 font-bold mb-1">Nomor Telepon / WhatsApp</label>
                                    <input 
                                        v-model="form.store_phone" 
                                        type="text" 
                                        required
                                        placeholder="Contoh: 0812-3456-7890"
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                    />
                                </div>
                                <div>
                                    <label class="block text-slate-700 font-bold mb-1">Email Toko (Opsional)</label>
                                    <input 
                                        v-model="form.store_email" 
                                        type="email" 
                                        placeholder="Contoh: kantin@rsia-aisyiyah.com"
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Alamat Lengkap Toko</label>
                                <textarea 
                                    v-model="form.store_address" 
                                    rows="2" 
                                    required
                                    placeholder="Contoh: Jl. Raya Pekajangan No. 610, Pekalongan"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-amber-500 focus:bg-white transition text-xs"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Rekening Bank Pembayaran -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                            <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center font-black">
                                <CreditCard class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Rekening Pembayaran Bank (Transfer & Tempo)</h3>
                                <p class="text-[11px] text-slate-400">Dicantumkan pada lembar Invoice A4 dan Faktur Penjualan Dot Matrix.</p>
                            </div>
                        </div>

                        <div class="text-xs space-y-1">
                            <label class="block text-slate-700 font-bold mb-1">Daftar Rekening Bank (Bisa beberapa baris)</label>
                            <textarea 
                                v-model="form.bank_info" 
                                rows="3" 
                                placeholder="BCA: 8830-123-456 a.n. KOPERASI RSIA AISYIYAH PEKAJANGAN&#10;MANDIRI: 137-00-9876543-2 a.n. KOPERASI RSIA AISYIYAH PEKAJANGAN"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 font-mono text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition"
                            ></textarea>
                            <p class="text-[10px] text-slate-400">Tips: Buat 1 baris per rekening untuk keterbacaan yang rapi saat dicetak.</p>
                        </div>
                    </div>

                    <!-- SECTION 3: Catatan Kaki & Syarat Ketentuan Nota -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black">
                                <FileText class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Catatan Kaki Nota & Syarat Faktur</h3>
                                <p class="text-[11px] text-slate-400">Pesan penutup struk thermal dan klausul hukum/ketentuan retur faktur.</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs">
                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Catatan Kaki Struk Thermal (Receipt Footer)</label>
                                <textarea 
                                    v-model="form.receipt_footer" 
                                    rows="3" 
                                    placeholder="Contoh: Terima kasih atas kunjungan Anda!&#10;Barang yang sudah dibeli dapat ditukar maksimal 3 hari..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-slate-700 font-bold mb-1">Syarat & Ketentuan Faktur Dot Matrix / Invoice A4</label>
                                <textarea 
                                    v-model="form.invoice_terms" 
                                    rows="4" 
                                    placeholder="1. Pembayaran tempo wajib dilunasi sebelum tanggal jatuh tempo.&#10;2. Barang yang sudah diterima dalam kondisi baik..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-900 text-xs focus:outline-none focus:border-amber-500 focus:bg-white transition"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Hak Akses & Privasi Akun Kasir -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-black">
                                    <ShieldCheck class="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-slate-900">Hak Akses & Privasi Akun Kasir</h3>
                                    <p class="text-[11px] text-slate-400">Batasi menu & informasi sensitif untuk peran kasir.</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-100">
                                Keamanan
                            </span>
                        </div>

                        <div class="space-y-4">
                            <!-- Toggle 1: Akses Menu Master Produk -->
                            <div class="p-4 rounded-2xl border transition" :class="form.kasir_can_access_products === '1' ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200 bg-slate-50/50'">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <Lock class="w-4 h-4 text-slate-600" />
                                            <span class="text-xs font-bold text-slate-900">Beri Akses Master Produk & Stok</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 leading-relaxed">
                                            Bila dinonaktifkan (default), akun Kasir tidak dapat melihat menu Master Produk di navigasi dan dilarang mengakses halaman produk secara langsung.
                                        </p>
                                    </div>
                                    <button 
                                        type="button" 
                                        @click="form.kasir_can_access_products = (form.kasir_can_access_products === '1' ? '0' : '1')"
                                        :class="form.kasir_can_access_products === '1' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-600'"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    >
                                        <span 
                                            :class="form.kasir_can_access_products === '1' ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        />
                                    </button>
                                </div>
                            </div>

                            <!-- Toggle 2: Visibilitas HPP (Harga Pokok Penjualan / Modal) -->
                            <div class="p-4 rounded-2xl border transition" :class="form.kasir_can_see_cost_price === '1' ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200 bg-slate-50/50'">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <EyeOff class="w-4 h-4 text-slate-600" />
                                            <span class="text-xs font-bold text-slate-900">Izinkan Kasir Melihat HPP (Harga Modal)</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 leading-relaxed">
                                            Bila dinonaktifkan (default), kolom HPP / Modal disembunyikan dan di-strip secara aman dari respon server, sehingga Kasir tidak dapat mengetahui harga modal toko.
                                        </p>
                                    </div>
                                    <button 
                                        type="button" 
                                        @click="form.kasir_can_see_cost_price = (form.kasir_can_see_cost_price === '1' ? '0' : '1')"
                                        :class="form.kasir_can_see_cost_price === '1' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-600'"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    >
                                        <span 
                                            :class="form.kasir_can_see_cost_price === '1' ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Format Cetak & Live Simulation -->
                <div class="space-y-6">
                    <!-- Format Cetak Default Selector -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                            <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-black">
                                <Printer class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Format Cetak Utama</h3>
                                <p class="text-[11px] text-slate-400">Pilihan printer kasir standar saat transaksi selesai.</p>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <label 
                                v-for="fmt in printFormats" 
                                :key="fmt.id"
                                :class="[
                                    form.default_print_format === fmt.id ? 'border-amber-500 bg-amber-50/60 ring-2 ring-amber-500/20' : 'border-slate-200 hover:border-slate-300 bg-slate-50/50',
                                    'p-3.5 rounded-2xl border flex items-start gap-3 cursor-pointer transition'
                                ]"
                                @click="form.default_print_format = fmt.id"
                            >
                                <input 
                                    type="radio" 
                                    :value="fmt.id" 
                                    v-model="form.default_print_format" 
                                    class="mt-0.5 text-amber-600 focus:ring-amber-500" 
                                />
                                <div>
                                    <p class="text-xs font-bold text-slate-900">{{ fmt.title }}</p>
                                    <p class="text-[10px] text-slate-500">{{ fmt.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Live Simulation Preview of Thermal Footer -->
                    <div class="bg-slate-900 text-white rounded-3xl p-5 shadow-xl space-y-3">
                        <div class="flex items-center justify-between text-xs border-b border-slate-800 pb-2">
                            <span class="text-[11px] font-bold text-amber-400 flex items-center gap-1.5">
                                <Receipt class="w-3.5 h-3.5" />
                                <span>Simulasi Tampilan Struk</span>
                            </span>
                            <span class="text-[9px] font-mono text-slate-400 uppercase">Live Preview</span>
                        </div>

                        <div class="bg-white text-slate-900 rounded-xl p-4 font-mono text-[10px] space-y-2 shadow-inner border border-slate-300">
                            <div class="text-center border-b border-dashed border-slate-300 pb-2">
                                <p class="font-black text-xs uppercase">{{ form.store_name || 'NAMA TOKO' }}</p>
                                <p class="text-[8px] text-slate-500">{{ form.store_tagline || 'Tagline Toko' }}</p>
                                <p class="text-[8px] text-slate-500 mt-0.5">{{ form.store_address }}</p>
                                <p class="text-[8px] text-slate-700 font-bold">Telp: {{ form.store_phone }}</p>
                            </div>

                            <div class="space-y-1 py-1 text-[9px]">
                                <div class="flex justify-between">
                                    <span>1x Nasi Rames Ayam</span>
                                    <span class="font-bold">Rp 14.500</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>2x Lampu LED 10W</span>
                                    <span class="font-bold">Rp 76.000</span>
                                </div>
                                <div class="flex justify-between border-t border-slate-200 pt-1 font-bold">
                                    <span>TOTAL</span>
                                    <span>Rp 90.500</span>
                                </div>
                            </div>

                            <div class="text-center border-t border-dashed border-slate-300 pt-2 text-[8px] text-slate-600 whitespace-pre-line leading-tight">
                                {{ form.receipt_footer || 'Catatan kaki nota thermal' }}
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </MainLayout>
</template>
