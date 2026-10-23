import { defineConfig } from 'vitest/config';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';

// Configuration de test séparée de vite.config.js : le plugin Laravel (serveur HMR,
// polices, manifeste) n'a rien à faire dans les tests et refuse de démarrer en CI.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        globals: true,
    },
});
