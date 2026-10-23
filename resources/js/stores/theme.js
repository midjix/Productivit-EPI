import { defineStore } from 'pinia';

const CLE = 'epi.theme';
const MEDIA = '(prefers-color-scheme: dark)';

/**
 * Thème clair / sombre : « auto » suit le système (comme DSKnet), sinon le choix
 * de l'utilisateur est mémorisé dans son navigateur. `effectif` sert aux graphiques,
 * qui ne lisent pas les variables CSS par eux-mêmes.
 */
export const useThemeStore = defineStore('theme', {
    state: () => ({
        choix: lireChoix(),
        systemeSombre: typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(MEDIA).matches : false,
    }),

    getters: {
        effectif: (state) => (state.choix === 'auto' ? (state.systemeSombre ? 'dark' : 'light') : state.choix),
    },

    actions: {
        initialiser() {
            this.appliquer();
            if (typeof window !== 'undefined' && window.matchMedia) {
                window.matchMedia(MEDIA).addEventListener('change', (e) => {
                    this.systemeSombre = e.matches;
                });
            }
        },

        choisir(choix) {
            this.choix = ['light', 'dark', 'auto'].includes(choix) ? choix : 'auto';
            try {
                if (this.choix === 'auto') localStorage.removeItem(CLE);
                else localStorage.setItem(CLE, this.choix);
            } catch {
                /* stockage indisponible */
            }
            this.appliquer();
        },

        appliquer() {
            const racine = document.documentElement;
            if (this.choix === 'auto') delete racine.dataset.theme;
            else racine.dataset.theme = this.choix;
        },
    },
});

function lireChoix() {
    try {
        const v = localStorage.getItem(CLE);
        return v === 'light' || v === 'dark' ? v : 'auto';
    } catch {
        return 'auto';
    }
}
