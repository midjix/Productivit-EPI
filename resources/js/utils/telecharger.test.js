import { describe, expect, it } from 'vitest';
import { nomDepuisDisposition } from './telecharger';

describe('nom de fichier depuis Content-Disposition', () => {
    it('lit le nom simple, avec ou sans guillemets', () => {
        expect(nomDepuisDisposition('attachment; filename=efficacite-sandy-exemple-2026.xlsx')).toBe('efficacite-sandy-exemple-2026.xlsx');
        expect(nomDepuisDisposition('attachment; filename="detail-total-epi.xlsx"')).toBe('detail-total-epi.xlsx');
    });

    it('préfère la forme étendue encodée (accents)', () => {
        expect(nomDepuisDisposition("attachment; filename=detail.xlsx; filename*=UTF-8''d%C3%A9tail.xlsx")).toBe('détail.xlsx');
    });

    it('retombe sur le nom par défaut', () => {
        expect(nomDepuisDisposition(undefined, 'export.xlsx')).toBe('export.xlsx');
        expect(nomDepuisDisposition('inline')).toBe('export.xlsx');
    });
});
