import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import i18n from '@/i18n';

/**
 * Routes de la SPA. `meta.requiresAuth` protège les vues ; `meta.role` cache celles
 * que le rôle n'a pas (le serveur reste le garant : 403 si l'URL est forgée).
 * `meta.titre` est une clé de traduction (titres.*).
 */
const routes = [
    {
        path: '/connexion',
        name: 'connexion',
        component: () => import('@/pages/LoginPage.vue'),
        meta: { invite: true, titre: 'connexion' },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'tableau-de-bord', component: () => import('@/pages/DashboardPage.vue'), meta: { titre: 'tableauDeBord' } },
            { path: 'salaries/:id(\\d+)', name: 'salarie', component: () => import('@/pages/DashboardPage.vue'), props: true, meta: { titre: 'tableauDeBord', role: 'coordinateur' } },
            { path: 'service', name: 'service', component: () => import('@/pages/ServicePage.vue'), meta: { titre: 'monService', role: 'coordinateur' } },
            { path: 'services/:id(\\d+)', name: 'service-detail', component: () => import('@/pages/ServicePage.vue'), props: true, meta: { titre: 'service', role: 'coordinateur' } },
            { path: 'globales', name: 'globales', component: () => import('@/pages/GlobalePage.vue'), meta: { titre: 'vueGlobale', role: 'administrateur' } },
            {
                path: 'administration',
                component: () => import('@/pages/admin/AdminLayout.vue'),
                meta: { titre: 'administration', role: 'administrateur' },
                children: [
                    { path: '', name: 'administration', redirect: { name: 'admin-imports' } },
                    { path: 'imports', name: 'admin-imports', component: () => import('@/pages/admin/ImportsPage.vue'), meta: { titre: 'administration', role: 'administrateur' } },
                    { path: 'referentiels', name: 'admin-referentiels', component: () => import('@/pages/admin/ReferentielsPage.vue'), meta: { titre: 'administration', role: 'administrateur' } },
                    { path: 'matrice', name: 'admin-matrice', component: () => import('@/pages/admin/MatricePage.vue'), meta: { titre: 'administration', role: 'administrateur' } },
                    { path: 'organigramme', name: 'admin-organigramme', component: () => import('@/pages/admin/OrganigrammePage.vue'), meta: { titre: 'administration', role: 'administrateur' } },
                ],
            },
            { path: 'detail', name: 'detail', component: () => import('@/pages/DetailPage.vue'), meta: { titre: 'detail' } },
            { path: 'mot-de-passe', name: 'mot-de-passe', component: () => import('@/pages/ChangerMotDePassePage.vue'), meta: { titre: 'motDePasse' } },
        ],
    },
    { path: '/:pathMatch(.*)*', name: 'introuvable', component: () => import('@/pages/NotFoundPage.vue'), meta: { titre: 'introuvable' } },
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

export function titreDocument(cle) {
    const t = i18n.global.t;
    return cle ? `${t(`titres.${cle}`)} — ${t('app.titre')} EPI` : `${t('app.titre')} — ${t('app.fondation')}`;
}

router.afterEach((to) => {
    document.title = titreDocument(to.meta.titre);
});

export default router;
