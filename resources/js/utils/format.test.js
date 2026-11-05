import { beforeEach, describe, expect, it } from 'vitest';
import { analyserDateSaisie, date, dateHeure, dateNumerique, definirLocaleFormat, heures, heuresDecimales, masqueDate, nomMois, nombre, pourcentage } from './format';

/** Les espaces insécables (fines ou non) produits par Intl sont ramenés à une espace simple pour comparer. */
const n = (s) => s.replace(/[  ]/g, ' ');

describe('format (français)', () => {
    beforeEach(() => definirLocaleFormat('fr'));

    it('convertit des minutes en heures lisibles', () => {
        expect(heures(7680)).toBe('128h00');
        expect(heures('5880.50')).toBe('98h01');
        expect(heures(0)).toBe('0h00');
        expect(heures(-90)).toBe('-1h30');
        expect(heures(null)).toBe('0h00');
    });

    it('sépare les milliers selon la langue', () => {
        // fr-LU et de-LU groupent par un point (CLDR), en-GB par une virgule : comme `nombre()`.
        expect(n(heures(675840))).toBe('11.264h00');
        definirLocaleFormat('en');
        expect(heures(675840)).toBe('11,264h00');
        definirLocaleFormat('de');
        expect(heures(675840)).toBe('11.264h00');
        expect(heures(7680)).toBe('128h00');
    });

    it('affiche des heures décimales à la française', () => {
        expect(n(heuresDecimales(7680))).toBe('128,0 h');
        expect(n(heuresDecimales('5880.00'))).toBe('98,0 h');
    });

    it('affiche un ratio en pourcentage et gère le non calculable', () => {
        expect(n(pourcentage('0.8367'))).toBe('83,7 %');
        expect(n(pourcentage('0.8367', 2))).toBe('83,67 %');
        expect(n(pourcentage('0.7500', 0))).toBe('75 %');
        expect(pourcentage(null)).toBe('—');
        expect(pourcentage('abc')).toBe('—');
    });

    it('formate un nombre de forfaits et nomme les mois', () => {
        expect(nombre('84.50')).toBe('84,5');
        expect(nombre('84.00')).toBe('84');
        expect(nomMois(4)).toBe('Avril');
        expect(nomMois(12, true).toLowerCase()).toContain('déc');
    });
});

describe('format (autres langues)', () => {
    it('suit la langue choisie pour les nombres et les mois', () => {
        definirLocaleFormat('en');
        expect(n(pourcentage('0.8367'))).toBe('83.7%');
        expect(nombre('84.50')).toBe('84.5');
        expect(nomMois(4)).toBe('April');

        definirLocaleFormat('de');
        expect(n(pourcentage('0.8367'))).toBe('83,7 %');
        expect(nomMois(3)).toBe('März');

        definirLocaleFormat('xx'); // inconnue → français
        expect(nomMois(4)).toBe('Avril');
    });

    it('écrit les dates dans un format propre à chaque langue', () => {
        // Le mois en lettres rend la langue visible : le format court fr et en-GB était identique (23/10/2026).
        definirLocaleFormat('fr');
        expect(n(date('2026-10-23'))).toBe('23 oct. 2026');
        expect(n(dateHeure('2026-10-23T09:10:36+00:00'))).toMatch(/^23 oct\. 2026,? \d{2}:\d{2}$/);
        expect(date(null)).toBe('—');

        definirLocaleFormat('en');
        expect(n(date('2026-10-23'))).toBe('23 Oct 2026');

        definirLocaleFormat('de');
        expect(n(date('2026-10-23'))).toBe('23.10.2026');
    });

    it('propose un gabarit de saisie et une forme numérique par langue', () => {
        definirLocaleFormat('fr');
        expect(masqueDate()).toBe('JJ/MM/AAAA');
        expect(dateNumerique('2026-01-05')).toBe('05/01/2026');
        expect(dateNumerique('')).toBe('');
        definirLocaleFormat('en');
        expect(masqueDate()).toBe('DD/MM/YYYY');
        expect(dateNumerique('2026-01-05')).toBe('05/01/2026');
        definirLocaleFormat('de');
        expect(masqueDate()).toBe('TT.MM.JJJJ');
        expect(dateNumerique('2026-01-05')).toBe('05.01.2026');
    });

    it('lit une date saisie jour en premier, quel que soit le séparateur', () => {
        expect(analyserDateSaisie('05/01/2026')).toBe('2026-01-05');
        expect(analyserDateSaisie('5.1.2026')).toBe('2026-01-05');
        expect(analyserDateSaisie(' 31-12-2026 ')).toBe('2026-12-31');
        expect(analyserDateSaisie('2026-01-05')).toBe('2026-01-05');
        expect(analyserDateSaisie('31/02/2026')).toBeNull(); // date impossible
        expect(analyserDateSaisie('01/13/2026')).toBeNull(); // mois/jour/année refusé
        expect(analyserDateSaisie('05/01/26')).toBeNull(); // année sur deux chiffres : ambiguë
        expect(analyserDateSaisie('05/01')).toBeNull(); // incomplète
        expect(analyserDateSaisie('')).toBeNull();
        expect(analyserDateSaisie(null)).toBeNull();
    });
});
