import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/images/app-logo-wide.svg', 'resources/images/app-logo.svg'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    css: {
        devSourcemap: true,
    },
    build: {
        sourcemap: true,
    },
    server: {
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/storage/debugbar/**',
                '**/storage/logs/**',
            ],
        },
    },
});
