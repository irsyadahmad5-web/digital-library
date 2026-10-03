import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
    title: (title) => title ? `${title} · Digital Library` : 'Digital Library',
    resolve: async (name) => {
        const resolver = pages[`./pages/${name}.vue`];

        if (!resolver) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return await resolver();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2563EB',
    },
});