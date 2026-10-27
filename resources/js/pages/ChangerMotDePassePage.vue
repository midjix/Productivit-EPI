<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { verifier } from '@/utils/motDePasse';
import UiCard from '@/components/UiCard.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import PasswordField from '@/components/PasswordField.vue';
import PasswordRules from '@/components/PasswordRules.vue';

/**
 * Changement de son propre mot de passe (PUT /api/me/mot-de-passe).
 * Les critères sont vérifiés en direct (tout ce qui manque est listé d'emblée) ; l'API
 * reste l'autorité et toutes ses erreurs sont affichées, pas seulement la première.
 */
const { t } = useI18n();
const formulaire = reactive({ mot_de_passe_actuel: '', password: '', password_confirmation: '' });
const erreurs = ref({});
const message = ref('');
const envoi = ref(false);

const regles = computed(() => verifier(formulaire.password, { actuel: formulaire.mot_de_passe_actuel, confirmation: formulaire.password_confirmation }));

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
</script>

<template>
    <div class="mx-auto max-w-xl px-4 py-6 sm:px-6">
        <UiCard :titre="t('mdp.titre')">
            <form class="space-y-4" novalidate @submit.prevent="enregistrer">
                <PasswordField id="mot_de_passe_actuel" v-model="formulaire.mot_de_passe_actuel" :label="t('mdp.actuel')" autocomplete="current-password" :erreurs="erreurs.mot_de_passe_actuel ?? []" />
                <PasswordField id="password" v-model="formulaire.password" :label="t('mdp.nouveau')" :erreurs="erreurs.password ?? []" />
                <PasswordRules :regles="regles" />
                <PasswordField id="password_confirmation" v-model="formulaire.password_confirmation" :label="t('mdp.confirmer')" :erreurs="erreurs.password_confirmation ?? []" />

                <p v-if="erreurs.general" role="alert" class="text-sm text-bad">{{ erreurs.general[0] }}</p>
                <p v-if="message" role="status" class="rounded-md bg-good/10 px-3 py-2 text-sm text-good">{{ message }}</p>

                <button type="submit" :disabled="envoi" class="inline-flex items-center gap-2 rounded-full bg-accent px-6 py-2.5 font-semibold text-white hover:bg-accent-strong disabled:opacity-70">
                    <UiSpinner v-if="envoi" taille="h-4 w-4" couleur="text-white" />
                    {{ envoi ? t('mdp.enregistrement') : t('mdp.enregistrer') }}
                </button>
            </form>
        </UiCard>
    </div>
</template>
