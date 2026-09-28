import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

// UI lab: serves resources/js/ui-lab (page mockups + module state sheets) without Laravel.
export default defineConfig({
    root: fileURLToPath(new URL('./resources/js/ui-lab', import.meta.url)),
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        port: 5174,
        strictPort: true,
    },
});
