import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth';

describe('store auth', () => {
    beforeEach(() => {
        localStorage.clear();
        setActivePinia(createPinia());
    });

    it('n\'est pas connecté sans jeton et applique l\'héritage des rôles', () => {
        const auth = useAuthStore();
        expect(auth.estConnecte).toBe(false);
        expect(auth.role).toBe('salarie');

        auth.utilisateur = { name: 'Marie DUPONT', role: 'coordinateur' };
        expect(auth.aRole('salarie')).toBe(true);
        expect(auth.aRole('coordinateur')).toBe(true);
        expect(auth.aRole('administrateur')).toBe(false);
        expect(auth.initiales).toBe('MD');
    });

    it('oublie le jeton et le profil à la déconnexion locale', () => {
        localStorage.setItem('epi.token', 'abc');
        setActivePinia(createPinia());
        const auth = useAuthStore();
        expect(auth.estConnecte).toBe(true);

        auth.utilisateur = { name: 'X', role: 'administrateur' };
        auth.deconnexionLocale();

        expect(auth.estConnecte).toBe(false);
        expect(auth.utilisateur).toBeNull();
        expect(localStorage.getItem('epi.token')).toBeNull();
    });
});
