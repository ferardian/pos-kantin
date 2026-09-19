import { createApp, h } from 'vue';
import { createInertiaApp, Head, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = 'Trisna Jaya Listrik';

// Ensure all Inertia visits are prefixed with /pos-kantin when running under subpath
router.on('before', (event) => {
    const visit = event.detail.visit;
    if (visit && visit.url) {
        let path = visit.url.pathname;
        if (!path.startsWith('/pos-kantin')) {
            visit.url.pathname = '/pos-kantin' + (path.startsWith('/') ? path : '/' + path);
        }
    }
});


createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : `${appName} - POS & Sales System`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.component('Head', Head);
        app.use(plugin);
        app.mount(el);
    },
});
