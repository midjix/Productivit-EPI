import { defineStore } from 'pinia';

let compteur = 0;

/** Messages éphémères (succès / erreur) affichés en bas de l'écran après une action. */
export const useNotificationsStore = defineStore('notifications', {
    state: () => ({ liste: [] }),

    actions: {
        notifier(message, type = 'succes', duree = 4000) {
            const id = ++compteur;
            this.liste.push({ id, message, type });
            if (duree > 0) {
                setTimeout(() => this.fermer(id), duree);
            }
            return id;
        },

        succes(message) {
            return this.notifier(message, 'succes');
        },

        erreur(message) {
            return this.notifier(message, 'erreur', 7000);
        },

        fermer(id) {
            this.liste = this.liste.filter((n) => n.id !== id);
        },
    },
});
