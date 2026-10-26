import { defineStore } from 'pinia';
import client from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { chargerHistorique, enregistrer, sauverHistorique } from '@/utils/recherche';

/**
 * Données de la barre de recherche : salariés et services consultables (chargés une fois
 * par session, filtrés côté client) et historique des consultations de l'utilisateur.
 */
export const useRechercheStore = defineStore('recherche', {
    state: () => ({
        salaries: [],
        services: [],
        charge: false,
        chargement: false,
        historique: [],
        historiqueDe: null,
    }),

    getters: {
        entrees: (state) => [
            ...state.salaries.map((s) => ({ type: 'salarie', id: s.id, libelle: s.name, detail: s.service?.nom ?? '' })),
            ...state.services.map((s) => ({ type: 'service', id: s.id, libelle: s.nom, detail: s.code })),
        ],
    },

    actions: {
        async charger() {
            const auth = useAuthStore();
            if (this.charge || this.chargement || !auth.aRole('coordinateur')) return;
            this.chargement = true;
            try {
                const [salaries, services] = await Promise.all([
                    client.get('/salaries'),
                    auth.estAdministrateurRole ? client.get('/services') : Promise.resolve({ data: [] }),
                ]);
                this.salaries = salaries.data;
                this.services = services.data;
                this.charge = true;
            } catch {
                /* la barre reste vide ; nouvelle tentative au prochain focus */
            } finally {
                this.chargement = false;
            }
        },

        chargerHistorique() {
            const auth = useAuthStore();
            const id = auth.utilisateur?.id ?? null;
            if (this.historiqueDe !== id) {
                this.historique = chargerHistorique(id);
                this.historiqueDe = id;
            }
        },

        memoriser(entree) {
            this.chargerHistorique();
            this.historique = enregistrer(this.historique, entree);
            sauverHistorique(this.historiqueDe, this.historique);
        },

        reinitialiser() {
            this.$reset();
        },
    },
});
