/**
 * Barre de recherche : classement des propositions.
 *
 * Une entrée est { type: 'salarie' | 'service', id, libelle, detail }.
 * L'historique est une liste { type, id, libelle, detail, count, lastAt }.
 * Trois groupes, sans doublon entre eux :
 *   1. dernières recherches (max 2, par date) ;
 *   2. les plus consultées (max 3, par fréquence, hors groupe 1) ;
 *   3. résultats les plus probables pour le texte tapé (max 5, hors groupes 1 et 2).
 */

export function normaliser(texte) {
    return String(texte ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();
}

export function cleEntree(e) {
    return `${e.type}:${e.id}`;
}

/** Ajoute (ou met à jour) une consultation dans l'historique ; borné à 50 entrées. */
export function enregistrer(historique, entree, maintenant = Date.now()) {
    const cle = cleEntree(entree);
    const liste = historique.filter((h) => cleEntree(h) !== cle);
    const existante = historique.find((h) => cleEntree(h) === cle);

    liste.push({
        type: entree.type,
        id: entree.id,
        libelle: entree.libelle,
        detail: entree.detail ?? '',
        count: (existante?.count ?? 0) + 1,
        lastAt: maintenant,
    });

    return liste.sort((a, b) => b.lastAt - a.lastAt).slice(0, 50);
}

/** Pertinence d'une entrée pour un texte normalisé : 3 début du libellé, 2 début d'un mot, 1 contenu, 0 rien. */
function score(entree, q) {
    const libelle = normaliser(entree.libelle);
    const detail = normaliser(entree.detail);
    if (libelle.startsWith(q)) return 3;
    if (libelle.split(/\s+/).some((mot) => mot.startsWith(q))) return 2;
    if (libelle.includes(q) || detail.includes(q)) return 1;
    return 0;
}

export function classer({ entrees, historique = [], texte = '', maxRecentes = 2, maxFrequentes = 3, maxResultats = 5 }) {
    const actives = new Map(entrees.map((e) => [cleEntree(e), e]));
    // Seules les entrées encore consultables sont proposées (un salarié parti disparaît de l'historique affiché).
    const histo = historique.filter((h) => actives.has(cleEntree(h)));
    const pris = new Set();

    const recentes = [...histo]
        .sort((a, b) => b.lastAt - a.lastAt)
        .slice(0, maxRecentes)
        .map((h) => actives.get(cleEntree(h)));
    recentes.forEach((e) => pris.add(cleEntree(e)));

    const frequentes = [...histo]
        .sort((a, b) => b.count - a.count || b.lastAt - a.lastAt)
        .filter((h) => !pris.has(cleEntree(h)))
        .slice(0, maxFrequentes)
        .map((h) => actives.get(cleEntree(h)));
    frequentes.forEach((e) => pris.add(cleEntree(e)));

    const q = normaliser(texte);
    const resultats = q === ''
        ? []
        : entrees
            .map((e) => [score(e, q), e])
            .filter(([s, e]) => s > 0 && !pris.has(cleEntree(e)))
            .sort((a, b) => b[0] - a[0] || a[1].libelle.localeCompare(b[1].libelle))
            .slice(0, maxResultats)
            .map(([, e]) => e);

    return { recentes, frequentes, resultats };
}

// ---- Persistance par utilisateur (navigateur) ----------------------------------

export function cleStockage(userId) {
    return `epi.recherche.${userId ?? 'anonyme'}`;
}

export function chargerHistorique(userId) {
    try {
        const brut = JSON.parse(localStorage.getItem(cleStockage(userId)) ?? '[]');
        return Array.isArray(brut) ? brut : [];
    } catch {
        return [];
    }
}

export function sauverHistorique(userId, historique) {
    try {
        localStorage.setItem(cleStockage(userId), JSON.stringify(historique));
    } catch {
        /* stockage indisponible */
    }
}
