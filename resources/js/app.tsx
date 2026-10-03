import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

// The Laravel-served site. Pages live in ./Pages and each wraps itself in
// AppLayout. The UI lab (npm run ui-lab) is a separate entry: ui-lab/main.tsx.
const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · Popmart Tracker` : 'Popmart Tracker'),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        return (await page()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
