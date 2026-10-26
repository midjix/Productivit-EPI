import { beforeEach, describe, expect, it } from 'vitest';
import { definirLocaleFormat, heures, heuresDecimales, nomMois, nombre, pourcentage } from './format';

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
});
