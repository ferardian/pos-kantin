import { createApp, h } from 'vue';
import { createInertiaApp, Head } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = 'Trisna Jaya Listrik';

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
