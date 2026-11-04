import '../css/app.css';
import 'ant-design-vue/dist/reset.css';
import './bootstrap';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import axios from 'axios';
import { instalarBase } from './base';
import { ZiggyVue } from 'ziggy-js';
import Antd, { ConfigProvider } from 'ant-design-vue';
import esES from 'ant-design-vue/es/locale/es_ES';
import AppLayout from './Layouts/AppLayout.vue';
import { theme, palette } from './theme';

// Si el sistema está publicado en una carpeta del dominio, las rutas fijas reciben su prefijo
instalarBase(router, axios);

const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · VENTRACK` : 'VENTRACK · Promoción de ventas en tiempo real'),
    resolve: async (name) => {
        const page = await pages[`./Pages/${name}.vue`]();
        const component = page.default;
        // Layout persistente para todo excepto las pantallas de acceso
        if (component.layout === undefined && !name.startsWith('Auth/')) {
            component.layout = AppLayout;
        }
        return component;
    },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(ConfigProvider, { theme, locale: esES }, () => h(App, props)),
        })
            .use(plugin)
            .use(ZiggyVue)
            .use(Antd)
            .mount(el!);
    },
    progress: { color: palette.green, showSpinner: false },
});
