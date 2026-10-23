import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// SPA Vue.js servie par Laravel : Vite compile resources/js et resources/css,
// Laravel injecte les assets via @vite() dans resources/views/app.blade.php.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Police proche de celle de DSKnet (Poppins) : repères visuels familiers aux salariés.
                bunny('Poppins', { weights: [400, 500, 600, 700] }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    // Tests : voir vitest.config.js (sans le plugin Laravel).
});
