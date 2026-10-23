<script setup>
import { reactive, ref } from 'vue';
import client, { messageErreur } from '@/api/client';
import UiCard from '@/components/UiCard.vue';

/** Changement de son propre mot de passe (PUT /api/me/mot-de-passe). */
const formulaire = reactive({ mot_de_passe_actuel: '', password: '', password_confirmation: '' });
const erreurs = ref({});
const message = ref('');
const envoi = ref(false);

async function enregistrer() {
    erreurs.value = {};
    message.value = '';
    envoi.value = true;
    try {
        await client.put('/me/mot-de-passe', formulaire);
        message.value = 'Mot de passe modifié. Vos autres appareils ont été déconnectés.';
        Object.assign(formulaire, { mot_de_passe_actuel: '', password: '', password_confirmation: '' });
    } catch (e) {
        erreurs.value = e.response?.data?.errors ?? { general: [messageErreur(e)] };
    } finally {
        envoi.value = false;
    }
}

const champs = [
    { nom: 'mot_de_passe_actuel', libelle: 'Mot de passe actuel', autocomplete: 'current-password' },
    { nom: 'password', libelle: 'Nouveau mot de passe (12 caractères minimum, lettres et chiffres)', autocomplete: 'new-password' },
    { nom: 'password_confirmation', libelle: 'Confirmer le nouveau mot de passe', autocomplete: 'new-password' },
];
</script>

<template>
    <div class="mx-auto max-w-xl px-4 py-6 sm:px-6">
        <UiCard titre="Changer mon mot de passe">
            <form class="space-y-4" novalidate @submit.prevent="enregistrer">
                <div v-for="c in champs" :key="c.nom">
                    <label :for="c.nom" class="mb-1 block text-sm text-ink-secondary">{{ c.libelle }}</label>
                    <input
                        :id="c.nom"
                        v-model="formulaire[c.nom]"
                        type="password"
                        :autocomplete="c.autocomplete"
                        required
                        class="w-full rounded-md border border-border bg-surface px-3 py-2.5 text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
                        :aria-invalid="Boolean(erreurs[c.nom])"
                    />
                    <p v-if="erreurs[c.nom]" class="mt-1 text-sm text-bad">{{ erreurs[c.nom][0] }}</p>
                </div>

                <p v-if="erreurs.general" role="alert" class="text-sm text-bad">{{ erreurs.general[0] }}</p>
                <p v-if="message" role="status" class="rounded-md bg-good/10 px-3 py-2 text-sm text-good">{{ message }}</p>

                <button type="submit" :disabled="envoi" class="rounded-full bg-accent px-6 py-2.5 font-semibold text-white hover:bg-accent-strong disabled:opacity-70">
                    {{ envoi ? 'Enregistrement…' : 'Enregistrer' }}
                </button>
            </form>
        </UiCard>
    </div>
</template>
