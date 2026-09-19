<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import { 
    Lock, User, ArrowRight, ShieldCheck 
} from 'lucide-vue-next';

const form = useForm({
    username: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <Head title="Masuk Sistem - Kantin RSIA Aisyiyah Pekajangan" />
    <div class="min-h-screen bg-gradient-to-br from-emerald-50/70 via-white to-slate-100 flex items-center justify-center p-4 selection:bg-emerald-500 selection:text-white">
        <div class="w-full max-w-md bg-white border border-slate-200/80 rounded-3xl p-7 sm:p-8 shadow-xl shadow-slate-200/50 space-y-6">
            
            <!-- Brand Header -->
            <div class="text-center space-y-3">
                <div class="w-20 h-20 mx-auto rounded-3xl bg-emerald-50/80 border border-emerald-200/60 p-2.5 flex items-center justify-center shadow-xs">
                    <img 
                        :src="$page.props.settings?.store_logo ? ($page.props.settings.store_logo + '?v=3') : '/pos-kantin/images/logo.png?v=3'" 
                        alt="Logo Kantin RSIA" 
                        class="w-full h-full object-contain" 
                    />
                </div>
                <div>
                    <h1 class="text-lg sm:text-xl font-black tracking-tight text-slate-900 uppercase">
                        {{ $page.props.settings?.store_name || 'KOPERASI RSIA AISYIYAH PEKAJANGAN' }}
                    </h1>
                    <p class="text-xs text-emerald-700 font-semibold mt-1 flex items-center justify-center gap-1.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ $page.props.settings?.store_tagline || 'Sistem Kasir Kantin' }}
                    </p>
                </div>
            </div>

            <!-- Official Login Form -->
            <form @submit.prevent="submit" class="space-y-4 text-xs pt-1">
                <div>
                    <label class="block text-slate-700 font-bold mb-1.5">Username Pengguna</label>
                    <div class="relative">
                        <User class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="form.username" 
                            type="text" 
                            required 
                            placeholder="Masukkan username akun Anda"
                            autocomplete="username"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:bg-white transition shadow-xs font-semibold"
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
                            autocomplete="current-password"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:bg-white transition shadow-xs"
                        />
                    </div>
                </div>

                <div v-if="form.errors.username" class="p-2.5 bg-rose-50 border border-rose-200 rounded-xl text-[11px] text-rose-700 font-medium">
                    {{ form.errors.username }}
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-slate-900/10 cursor-pointer text-xs mt-2 active:scale-98"
                >
                    <span>{{ form.processing ? 'Memproses Masuk...' : 'Masuk ke Sistem' }}</span>
                    <ArrowRight class="w-4 h-4 text-emerald-400" />
                </button>
            </form>

            <div class="pt-4 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-400 flex items-center justify-center gap-1.5 font-medium">
                    <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Sistem Terproteksi Hak Akses Resmi RSIA</span>
                </p>
            </div>
        </div>
    </div>
</template>
