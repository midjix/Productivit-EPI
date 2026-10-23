import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

/**
 * Routes de la SPA. `meta.requiresAuth` protège les vues ; `meta.role` cache celles
 * que le rôle n'a pas (le serveur reste le garant : 403 si l'URL est forgée).
 * Les vues de l'étape B (service, globale, administration) sont déclarées
 * pour que le menu et les gardes soient en place.
 */
const routes = [
    {
        path: '/connexion',
        name: 'connexion',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { invite: true, titre: 'Connexion' },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'tableau-de-bord', component: () => import('@/pages/DashboardPage.vue'), meta: { titre: 'Tableau de bord' } },
            { path: 'salaries/:id(\\d+)', name: 'salarie', component: () => import('@/pages/DashboardPage.vue'), props: true, meta: { titre: 'Tableau de bord', role: 'coordinateur' } },
            { path: 'service', name: 'service', component: () => import('@/pages/BientotPage.vue'), meta: { titre: 'Mon service', role: 'coordinateur' } },
            { path: 'globales', name: 'globales', component: () => import('@/pages/BientotPage.vue'), meta: { titre: 'Vue globale', role: 'administrateur' } },
            { path: 'administration', name: 'administration', component: () => import('@/pages/BientotPage.vue'), meta: { titre: 'Administration', role: 'administrateur' } },
            { path: 'mot-de-passe', name: 'mot-de-passe', component: () => import('@/pages/ChangerMotDePassePage.vue'), meta: { titre: 'Changer mon mot de passe' } },
        ],
    },
    { path: '/:pathMatch(.*)*', name: 'introuvable', component: () => import('@/pages/NotFoundPage.vue'), meta: { titre: 'Page introuvable' } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (auth.estConnecte && !auth.utilisateur) {
        await auth.chargerProfil();
    }

    if (to.meta.requiresAuth && !auth.estConnecte) {
        return { name: 'connexion', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }

    if (to.meta.invite && auth.estConnecte) {
        return { name: 'tableau-de-bord' };
    }

    if (to.meta.role && !auth.aRole(to.meta.role)) {
        return { name: 'tableau-de-bord' };
    }

    return true;
});

router.afterEach((to) => {
    document.title = to.meta.titre ? `${to.meta.titre} — Suivi d'efficacité EPI` : "Suivi d'efficacité — Fondation EPI";
});

export default router;
