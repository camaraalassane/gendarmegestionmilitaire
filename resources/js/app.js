import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Import PrimeVue
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
import 'primeicons/primeicons.css';

const GendarmerieTheme = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{sky.50}',
            100: '{sky.100}',
            200: '{sky.200}',
            300: '{sky.300}',
            400: '{sky.400}',
            500: '{sky.700}',
            600: '{sky.800}',
            700: '{sky.900}',
            800: '{sky.950}',
            900: '{slate.900}',
            950: '{slate.950}'
        }
    }
});

const appName = 'Gestion Militaire - Gendarmerie';

// CrÃ©er une directive click-outside personnalisÃ©e
const ClickOutside = {
    beforeMount(el, binding) {
        el.clickOutsideEvent = (event) => {
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };
        document.addEventListener('click', el.clickOutsideEvent);
    },
    unmounted(el) {
        document.removeEventListener('click', el.clickOutsideEvent);
    }
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        app.use(ZiggyVue);
        app.use(PrimeVue, {
            theme: {
                preset: GendarmerieTheme,
                options: {
                    darkModeSelector: '.p-dark',
                }
            }
        });
        app.use(ToastService);

        // âœ… Enregistrer la directive tooltip de PrimeVue
        app.directive('tooltip', Tooltip);

        // Enregistrer la directive click-outside personnalisÃ©e
        app.directive('click-outside', ClickOutside);

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});