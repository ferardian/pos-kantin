<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { Lock, Mail, ArrowRight, ShieldCheck, Zap } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <Head title="Masuk Sistem - Trisna Jaya Listrik" />
    <div class="min-h-screen bg-gradient-to-br from-slate-100 via-white to-amber-50/40 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white border border-slate-200/80 rounded-3xl p-8 shadow-xl space-y-6">
            <!-- Brand Header -->
            <div class="text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-amber-50 border border-amber-200/60 p-2 flex items-center justify-center shadow-xs">
                    <img :src="$page.props.settings?.store_logo || '/images/logo.png'" alt="Logo" class="w-full h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight text-slate-900 uppercase">{{ $page.props.settings?.store_name || 'TRISNA JAYA LISTRIK' }}</h1>
                    <p class="text-xs text-slate-500 font-medium mt-1">{{ $page.props.settings?.store_tagline || 'Sistem Manajemen POS, Stok & Sales Order' }}</p>
                </div>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-4 text-xs">
                <div>
                    <label class="block text-slate-700 font-bold mb-1.5">Email Pengguna</label>
                    <div class="relative">
                        <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="form.email" 
                            type="email" 
                            required 
                            placeholder="nama@tokolistrik.com"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition shadow-xs"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 font-bold mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="form.password" 
                            type="password" 
                            required 
                            placeholder="••••••••"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-amber-500 focus:bg-white transition shadow-xs"
                        />
                    </div>
                </div>

                <div v-if="form.errors.email" class="p-2.5 bg-rose-50 border border-rose-200 rounded-xl text-[11px] text-rose-700 font-medium">
                    {{ form.errors.email }}
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-slate-900/10 cursor-pointer text-xs mt-2"
                >
                    <span>Masuk ke Sistem</span>
                    <ArrowRight class="w-4 h-4 text-amber-400" />
                </button>
            </form>

            <div class="pt-4 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-400 flex items-center justify-center gap-1.5 font-medium">
                    <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Sistem Terproteksi Hak Akses Resmi</span>
                </p>
            </div>
        </div>
    </div>
</template>
