import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

// Point d'entrée de la SPA : état global (Pinia), routeur (mode historique), racine App.vue.
const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount('#app');
