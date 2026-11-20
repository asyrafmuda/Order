import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Order';
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

/**
 * The theme and brand colours arrive with every page. Applying them here means
 * a change saved in Admin → Settings shows at once, without a full reload.
 */
function applyTheme(theme: { css: string; page_color: string } | undefined): void {
    const style = document.getElementById('brand-palette');

    if (theme && style && style.textContent !== theme.css) {
        style.textContent = theme.css;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme.page_color);
    }
}

// "success" covers every visit, including a form that redirects back to the same page;
// "navigate" covers the first load and the browser's back and forward buttons.
router.on('success', (event) => applyTheme(event.detail.page.props.theme));
router.on('navigate', (event) => applyTheme(event.detail.page.props.theme));

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
    // Follows the brand colour, whatever it is set to.
    progress: { color: 'var(--color-brand-500)', showSpinner: false },
});
