import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import i18n from './i18n';

// Point d'entrée de la SPA : état global (Pinia), routeur (mode historique),
// internationalisation (fr / en / de), racine App.vue.
const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(i18n);

app.mount('#app');
