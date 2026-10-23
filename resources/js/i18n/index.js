import { createI18n } from 'vue-i18n';
import fr from '@/locales/fr.json';
import en from '@/locales/en.json';
import de from '@/locales/de.json';
import { definirLocaleFormat } from '@/utils/format';

/**
 * Internationalisation : français (langue de référence de la Fondation), anglais, allemand.
 * La langue vient, dans l'ordre : du choix mémorisé dans le navigateur, de la langue du
 * navigateur, sinon du français. Elle est aussi envoyée à l'API (Accept-Language) pour
 * recevoir les messages d'erreur dans la même langue.
 */
export const LANGUES = ['fr', 'en', 'de'];
export const CLE_LANGUE = 'epi.locale';

export function detecterLangue(navigateur = typeof navigator !== 'undefined' ? navigator : null) {
    try {
        const memorisee = localStorage.getItem(CLE_LANGUE);
        if (LANGUES.includes(memorisee)) return memorisee;
    } catch {
        /* stockage indisponible */
    }

    const candidates = navigateur?.languages?.length ? navigateur.languages : [navigateur?.language];
    for (const c of candidates) {
        const code = String(c ?? '').slice(0, 2).toLowerCase();
        if (LANGUES.includes(code)) return code;
    }

    return 'fr';
}

const i18n = createI18n({
    legacy: false,
    locale: detecterLangue(),
    fallbackLocale: 'fr',
    messages: { fr, en, de },
});

export function langueCourante() {
    return i18n.global.locale.value;
}

export function changerLangue(code) {
    if (!LANGUES.includes(code)) return;
    i18n.global.locale.value = code;
    try {
        localStorage.setItem(CLE_LANGUE, code);
    } catch {
        /* stockage indisponible */
    }
    appliquerLangue(code);
}

export function appliquerLangue(code = langueCourante()) {
    definirLocaleFormat(code);
    if (typeof document !== 'undefined') {
        document.documentElement.lang = code;
    }
}

appliquerLangue();

export default i18n;
