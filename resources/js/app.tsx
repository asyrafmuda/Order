import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { bootEcho } from './echo';

const appName = import.meta.env.VITE_APP_NAME || 'Boss Burger';
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

bootEcho();

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        if (el) {
            createRoot(el).render(<App {...props} />);
        }
    },
    progress: { color: '#ee2b32', showSpinner: false },
});
