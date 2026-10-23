import axios from 'axios';

/**
 * Client HTTP unique vers l'API Laravel (préfixe /api, JSON).
 * Le jeton Sanctum est ajouté à chaque requête ; une réponse 401 (jeton expiré,
 * révoqué par l'Administrateur) déconnecte localement et renvoie à la connexion.
 */
export const CLE_JETON = 'epi.token';

const client = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    timeout: 20000,
});

client.interceptors.request.use((config) => {
    const jeton = lireJeton();
    if (jeton) {
        config.headers.Authorization = `Bearer ${jeton}`;
    }
    return config;
});

let surDeconnexion = null;

/** Enregistre la réaction à une 401 (branchée par le store d'authentification). */
export function surSessionExpiree(callback) {
    surDeconnexion = callback;
}

client.interceptors.response.use(
    (reponse) => reponse,
    (erreur) => {
        const statut = erreur.response?.status;
        const url = erreur.config?.url ?? '';
        if (statut === 401 && !url.endsWith('/login') && surDeconnexion) {
            surDeconnexion();
        }
        return Promise.reject(erreur);
    },
);

export function lireJeton() {
    try {
        return localStorage.getItem(CLE_JETON);
    } catch {
        return null;
    }
}

export function ecrireJeton(jeton) {
    try {
        if (jeton) {
            localStorage.setItem(CLE_JETON, jeton);
        } else {
            localStorage.removeItem(CLE_JETON);
        }
    } catch {
        /* stockage indisponible (navigation privée) : la session ne survit pas au rechargement */
    }
}

/** Message lisible à partir d'une erreur axios (validation 422, 403, réseau…). */
export function messageErreur(erreur, defaut = 'Une erreur est survenue, veuillez réessayer.') {
    const data = erreur?.response?.data;
    if (data?.errors) {
        const premier = Object.values(data.errors).flat()[0];
        if (premier) return premier;
    }
    if (data?.message) return data.message;
    if (erreur?.code === 'ECONNABORTED' || erreur?.message === 'Network Error') {
        return 'Le serveur est injoignable. Vérifiez votre connexion.';
    }
    return defaut;
}

export default client;
