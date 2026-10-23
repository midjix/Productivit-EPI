import { beforeEach, describe, expect, it } from 'vitest';
import i18n, { CLE_LANGUE, changerLangue, detecterLangue, langueCourante } from './index';
import fr from '@/locales/fr.json';
import en from '@/locales/en.json';
import de from '@/locales/de.json';
import { localeFormat } from '@/utils/format';

/** Toutes les clés de la langue de référence (fr) existent dans les deux autres langues. */
function cles(obj, prefixe = '') {
    return Object.entries(obj).flatMap(([k, v]) => (typeof v === 'object' ? cles(v, `${prefixe}${k}.`) : [`${prefixe}${k}`]));
}

describe('i18n', () => {
    beforeEach(() => localStorage.clear());

    it('détecte la langue : mémorisée, puis navigateur, sinon français', () => {
        expect(detecterLangue({ languages: ['de-LU', 'fr'] })).toBe('de');
        expect(detecterLangue({ languages: ['pt-PT', 'en-GB'] })).toBe('en');
        expect(detecterLangue({ language: 'lb' })).toBe('fr');
        expect(detecterLangue(null)).toBe('fr');

        localStorage.setItem(CLE_LANGUE, 'en');
        expect(detecterLangue({ languages: ['de'] })).toBe('en');

        localStorage.setItem(CLE_LANGUE, 'xx');
        expect(detecterLangue({ languages: ['de'] })).toBe('de');
    });

    it('changer de langue met à jour les traductions, la mise en forme, <html lang> et la mémoire', () => {
        changerLangue('de');
        expect(langueCourante()).toBe('de');
        expect(i18n.global.t('nav.tableauDeBord')).toBe('Dashboard');
        expect(i18n.global.t('roles.salarie')).toBe('Mitarbeiter');
        expect(localeFormat()).toBe('de-LU');
        expect(document.documentElement.lang).toBe('de');
        expect(localStorage.getItem(CLE_LANGUE)).toBe('de');

        changerLangue('xx'); // ignorée
        expect(langueCourante()).toBe('de');

        changerLangue('fr');
        expect(i18n.global.t('roles.salarie')).toBe('Salarié');
    });

    it('les dictionnaires anglais et allemand couvrent toutes les clés du français', () => {
        const reference = cles(fr);
        expect(cles(en).filter((k) => !reference.includes(k))).toEqual([]);
        expect(reference.filter((k) => !cles(en).includes(k))).toEqual([]);
        expect(reference.filter((k) => !cles(de).includes(k))).toEqual([]);
    });

    it('chaque message des trois langues se compile (arobases et accolades échappées)', () => {
        // vue-i18n interprète « @ » (message lié) et « { } » (interpolation) : un « @ » brut
        // dans un email casse le rendu de la page entière (constaté sur le placeholder de connexion).
        const erreurs = [];
        for (const langue of ['fr', 'en', 'de']) {
            changerLangue(langue);
            for (const cle of cles(fr)) {
                try {
                    const rendu = i18n.global.t(cle, { n: 1, nom: 'X', annee: 2026, valeur: '1', objectif: '1', statut: 'S', code: 'C', minutes: 1, date: 'D', du: 'D', au: 'D', page: 1, total: 1 });
                    if (rendu === '' || rendu === cle) erreurs.push(`${langue}:${cle} (vide)`);
                } catch (e) {
                    erreurs.push(`${langue}:${cle} (${e.message})`);
                }
            }
        }
        expect(erreurs).toEqual([]);
        changerLangue('fr');
    });
});
