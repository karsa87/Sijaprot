import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'SiJaProt';

createInertiaApp({
    title: (title) => `${title ? title + ' — ' : ''}${appName} Kemendikdasmen`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        delay: 0,
        color: '#1A5276', // Primary Kemendikdasmen Navy Blue
        includeCSS: true,
        showSpinner: true,
    },
});
