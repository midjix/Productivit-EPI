import { describe, expect, it } from 'vitest';
import { heures, heuresDecimales, nomMois, nombre, pourcentage } from './format';

describe('format', () => {
    it('convertit des minutes en heures lisibles', () => {
        expect(heures(7680)).toBe('128h00');
        expect(heures('5880.50')).toBe('98h01');
        expect(heures(0)).toBe('0h00');
        expect(heures(-90)).toBe('-1h30');
        expect(heures(null)).toBe('0h00');
    });

    it('affiche des heures décimales à la française', () => {
        expect(heuresDecimales(7680)).toBe('128,0 h');
        expect(heuresDecimales('5880.00')).toBe('98,0 h');
    });

    it('affiche un ratio en pourcentage et gère le non calculable', () => {
        expect(pourcentage('0.8367')).toBe('83,7 %');
        expect(pourcentage('0.8367', 2)).toBe('83,67 %');
        expect(pourcentage('0.7500', 0)).toBe('75 %');
        expect(pourcentage(null)).toBe('—');
        expect(pourcentage('abc')).toBe('—');
    });

    it('formate un nombre de forfaits et nomme les mois', () => {
        expect(nombre('84.50')).toBe('84,5');
        expect(nombre('84.00')).toBe('84');
        expect(nomMois(4)).toBe('Avril');
        expect(nomMois(12, true)).toBe('Déc');
    });
});
