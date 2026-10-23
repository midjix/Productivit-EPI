import { defineStore } from 'pinia';
import client, { ecrireJeton, lireJeton, surSessionExpiree } from '@/api/client';

const NIVEAUX = { salarie: 1, coordinateur: 2, administrateur: 3 };

/**
 * Session de l'utilisateur connecté : jeton Sanctum + profil (/api/me).
 * Le rôle sert à adapter le menu et la couleur de l'interface ; le serveur reste
 * seul juge des droits (policies) — le front ne fait que cacher ce qui est interdit.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        jeton: lireJeton(),
        utilisateur: null,
        chargement: false,
    }),

    getters: {
        estConnecte: (state) => Boolean(state.jeton),
        role: (state) => state.utilisateur?.role ?? 'salarie',
        aRole: (state) => (role) => (NIVEAUX[state.utilisateur?.role] ?? 0) >= (NIVEAUX[role] ?? 99),
        initiales: (state) => {
            const nom = state.utilisateur?.name ?? '';
            return nom
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((m) => m[0].toUpperCase())
                .join('');
        },
    },

    actions: {
        brancherExpiration() {
            surSessionExpiree(() => this.deconnexionLocale());
        },

        async connexion(email, password) {
            this.chargement = true;
            try {
                const { data } = await client.post('/login', { email, password, device_name: 'spa' });
                this.jeton = data.token;
                this.utilisateur = data.user;
                ecrireJeton(data.token);
            } finally {
                this.chargement = false;
            }
        },

        /** Recharge le profil au démarrage (jeton en stockage) ; une 401 est gérée par l'intercepteur. */
        async chargerProfil() {
            if (!this.jeton) return;
            try {
                const { data } = await client.get('/me');
                this.utilisateur = data;
            } catch {
                this.deconnexionLocale();
            }
        },

        async deconnexion() {
            try {
                await client.post('/logout');
            } catch {
                /* jeton déjà invalide : on nettoie quand même */
            }
            this.deconnexionLocale();
        },

        deconnexionLocale() {
            this.jeton = null;
            this.utilisateur = null;
            ecrireJeton(null);
        },
    },
});
