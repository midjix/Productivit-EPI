/**
 * Politique de mot de passe, vérifiée en direct dans le formulaire pour dire d'emblée
 * tout ce qui ne va pas. Mêmes critères que la règle `Password::defaults()` de l'API
 * (12 caractères, minuscule, majuscule, chiffre, symbole) ; le serveur reste l'autorité.
 */
export const CRITERES = ['longueur', 'minuscule', 'majuscule', 'chiffre', 'symbole'];

/**
 * @param {string} motDePasse
 * @param {{ actuel?: string|null, confirmation?: string|null }} options  critères supplémentaires si fournis
 * @returns {Record<string, boolean>}  critère → respecté ?
 */
export function verifier(motDePasse, { actuel = null, confirmation = null } = {}) {
    const mdp = motDePasse ?? '';
    const resultat = {
        longueur: [...mdp].length >= 12,
        minuscule: /\p{Ll}/u.test(mdp),
        majuscule: /\p{Lu}/u.test(mdp),
        chiffre: /\p{N}/u.test(mdp),
        symbole: /[\p{Z}\p{S}\p{P}]/u.test(mdp), // même définition que Laravel (espace, symbole, ponctuation)
    };
    if (actuel !== null) resultat.different = mdp !== '' && mdp !== actuel;
    if (confirmation !== null) resultat.confirmation = mdp !== '' && mdp === confirmation;
    return resultat;
}

export function toutRespecte(resultat) {
    return Object.values(resultat).every(Boolean);
}
