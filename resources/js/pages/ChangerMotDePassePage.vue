<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import UiCard from '@/components/UiCard.vue';

/** Changement de son propre mot de passe (PUT /api/me/mot-de-passe). */
const { t } = useI18n();
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
        message.value = t('mdp.succes');
        Object.assign(formulaire, { mot_de_passe_actuel: '', password: '', password_confirmation: '' });
    } catch (e) {
        erreurs.value = e.response?.data?.errors ?? { general: [messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'))] };
    } finally {
        envoi.value = false;
    }
}

const champs = computed(() => [
    { nom: 'mot_de_passe_actuel', libelle: t('mdp.actuel'), autocomplete: 'current-password' },
    { nom: 'password', libelle: t('mdp.nouveau'), autocomplete: 'new-password' },
    { nom: 'password_confirmation', libelle: t('mdp.confirmer'), autocomplete: 'new-password' },
]);
</script>

<template>
    <div class="mx-auto max-w-xl px-4 py-6 sm:px-6">
        <UiCard :titre="t('mdp.titre')">
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
                    {{ envoi ? t('mdp.enregistrement') : t('mdp.enregistrer') }}
                </button>
            </form>
        </UiCard>
    </div>
</template>
