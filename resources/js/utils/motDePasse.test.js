import { describe, expect, it } from 'vitest';
import { toutRespecte, verifier } from './motDePasse';

describe('politique de mot de passe (vérification en direct)', () => {
    it('signale chaque critère séparément', () => {
        expect(verifier('motdepasse')).toEqual({ longueur: false, minuscule: true, majuscule: false, chiffre: false, symbole: false });
        expect(verifier('MOTDEPASSE2026')).toEqual({ longueur: true, minuscule: false, majuscule: true, chiffre: true, symbole: false });
        expect(verifier('')).toEqual({ longueur: false, minuscule: false, majuscule: false, chiffre: false, symbole: false });
    });

    it('accepte un mot de passe conforme, y compris avec accents, espace ou euro comme symbole', () => {
        expect(toutRespecte(verifier('MotDePasse2026!'))).toBe(true);
        expect(toutRespecte(verifier('Été2026 à Luxembourg'))).toBe(true); // l'espace est un symbole pour Laravel aussi
        expect(toutRespecte(verifier('Prix€2026abcd'))).toBe(true);
        expect(toutRespecte(verifier('MotDePasse2026'))).toBe(false);
    });

    it('compare au mot de passe actuel et à la confirmation quand ils sont fournis', () => {
        const r = verifier('MotDePasse2026!', { actuel: 'MotDePasse2026!', confirmation: 'MotDePasse2026?' });
        expect(r.different).toBe(false);
        expect(r.confirmation).toBe(false);
        expect(verifier('MotDePasse2026!', { actuel: 'Ancien1!', confirmation: 'MotDePasse2026!' })).toMatchObject({ different: true, confirmation: true });
        expect(verifier('', { actuel: '', confirmation: '' })).toMatchObject({ different: false, confirmation: false });
    });
});
