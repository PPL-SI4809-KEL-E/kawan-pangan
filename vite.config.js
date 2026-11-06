import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/design-system.css',
                'resources/css/registrasi.css',
                'resources/js/registrasi.js',
                'resources/css/wilayah.css',
                'resources/js/admin.js',
                'resources/css/beranda.css',
                'resources/js/beranda.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
