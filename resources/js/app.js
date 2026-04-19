import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import * as Sentry from '@sentry/vue';

// Prevent browser from opening files dropped anywhere on the page
document.addEventListener('dragover', (e) => e.preventDefault());
document.addEventListener('drop', (e) => e.preventDefault());

createInertiaApp({
    title: (title) => (title ? `${title} – Curzzo` : 'Curzzo'),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue', { eager: false })),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        if (
            import.meta.env.VITE_APP_ENV === 'production' &&
            import.meta.env.VITE_SENTRY_DSN_PUBLIC
        ) {
            Sentry.init({
                app,
                dsn: import.meta.env.VITE_SENTRY_DSN_PUBLIC,
                environment: 'production',
                tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0.1),
                replaysSessionSampleRate: 0,
                replaysOnErrorSampleRate: 1.0,
            });
        }

        app.use(plugin).mount(el);
    },
    progress: { color: '#4F46E5' },
});
