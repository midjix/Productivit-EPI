import { beforeEach, describe, expect, it } from 'vitest';
import { chargerHistorique, classer, enregistrer, normaliser, sauverHistorique } from './recherche';

const s = (id, libelle, detail = '') => ({ type: 'salarie', id, libelle, detail });
const entrees = [
    s(1, 'Alice MARTIN', 'AISP'),
    s(2, 'Hélène MASSON', 'Follow Up'),
    s(3, 'Marc MARTINEZ', 'PSY'),
    s(4, 'Martine DUBOIS', 'AISP'),
    s(5, 'Robert BONNET', 'AISP'),
    s(6, 'Victor LEGENDRE', 'PSY'),
    s(7, 'Olivia LE GOFF', 'Follow Up'),
    { type: 'service', id: 1, libelle: 'AISP', detail: 'AISP' },
    { type: 'service', id: 3, libelle: 'Service Psychologique', detail: 'PSY' },
];

describe('recherche', () => {
    beforeEach(() => localStorage.clear());

    it('normalise accents et casse', () => {
        expect(normaliser('  Hélène MASSON ')).toBe('helene masson');
    });

    it('classe les résultats par pertinence, sans doublon et au plus cinq', () => {
        const { recentes, frequentes, resultats } = classer({ entrees, texte: 'mar' });
        expect(recentes).toEqual([]);
        expect(frequentes).toEqual([]);
        // Début du libellé d'abord (Marc, Martine), puis début d'un mot (Alice MARTIN), puis contenu.
        expect(resultats.map((e) => e.libelle)).toEqual(['Marc MARTINEZ', 'Martine DUBOIS', 'Alice MARTIN']);

        const beaucoup = classer({ entrees, texte: 'a' }).resultats;
        expect(beaucoup.length).toBeLessThanOrEqual(5);
    });

    it('propose deux dernières recherches, trois plus consultées, sans doublon ni entrée disparue', () => {
        let h = [];
        // Victor consulté 4 fois (le plus fréquent), Robert 3, Alice 2, Hélène et Marc 1 (les plus récents).
        for (let i = 0; i < 4; i++) h = enregistrer(h, s(6, 'Victor LEGENDRE'), 100 + i);
        for (let i = 0; i < 3; i++) h = enregistrer(h, s(5, 'Robert BONNET'), 200 + i);
        for (let i = 0; i < 2; i++) h = enregistrer(h, s(1, 'Alice MARTIN'), 300 + i);
        h = enregistrer(h, s(2, 'Hélène MASSON'), 400);
        h = enregistrer(h, s(3, 'Marc MARTINEZ'), 500);
        h = enregistrer(h, s(99, 'Parti DEPUIS'), 600); // n'existe plus dans les entrées

        const { recentes, frequentes, resultats } = classer({ entrees, historique: h, texte: 'ma' });
        expect(recentes.map((e) => e.libelle)).toEqual(['Marc MARTINEZ', 'Hélène MASSON']);
        expect(frequentes.map((e) => e.libelle)).toEqual(['Victor LEGENDRE', 'Robert BONNET', 'Alice MARTIN']);
        // « ma » correspond à Marc, Martine, Alice MARTIN, Hélène MASSON : seuls ceux absents des deux premiers groupes restent.
        expect(resultats.map((e) => e.libelle)).toEqual(['Martine DUBOIS']);
    });

    it('persiste l\'historique par utilisateur', () => {
        const h = enregistrer([], { type: 'service', id: 1, libelle: 'AISP' }, 1);
        sauverHistorique(12, h);
        expect(chargerHistorique(12)).toHaveLength(1);
        expect(chargerHistorique(13)).toEqual([]);
        expect(chargerHistorique(12)[0].count).toBe(1);
    });
});
