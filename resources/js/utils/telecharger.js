import client, { messageErreur } from '@/api/client';

/**
 * Téléchargement d'un fichier servi par l'API (export Excel) : la requête passe par le
 * client axios (jeton, langue), la réponse binaire est proposée au navigateur sous le nom
 * choisi par le serveur (en-tête Content-Disposition).
 */

/** "attachment; filename=efficacite-2026.xlsx" ou "filename*=UTF-8''d%C3%A9tail.xlsx" → nom de fichier. */
export function nomDepuisDisposition(entete, defaut = 'export.xlsx') {
    if (!entete) return defaut;
    const etendu = /filename\*=(?:UTF-8|utf-8)''([^;]+)/.exec(entete);
    if (etendu) {
        try {
            return decodeURIComponent(etendu[1].trim());
        } catch {
            /* valeur mal encodée : on retombe sur le nom simple */
        }
    }
    const simple = /filename="?([^";]+)"?/.exec(entete);
    return simple ? simple[1].trim() : defaut;
}

/** Lance le téléchargement et renvoie le nom du fichier. */
export async function telecharger(url, params = {}, defaut = 'export.xlsx') {
    const reponse = await client.get(url, { params, responseType: 'blob', timeout: 120000 });
    const nom = nomDepuisDisposition(reponse.headers['content-disposition'], defaut);
    const lien = URL.createObjectURL(reponse.data);
    const a = document.createElement('a');
    a.href = lien;
    a.download = nom;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(lien), 1000);
    return nom;
}

/** Une erreur reçue en `responseType: 'blob'` porte son JSON dans un Blob : on le relit avant de bâtir le message. */
export async function messageErreurTelechargement(erreur, defaut, reseau) {
    const data = erreur?.response?.data;
    if (data instanceof Blob) {
        try {
            const json = JSON.parse(await data.text());
            return messageErreur({ ...erreur, response: { ...erreur.response, data: json } }, defaut, reseau);
        } catch {
            /* corps non JSON : message générique */
        }
    }
    return messageErreur(erreur, defaut, reseau);
}
