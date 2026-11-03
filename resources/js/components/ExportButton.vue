<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotificationsStore } from '@/stores/notifications';
import { messageErreurTelechargement, telecharger } from '@/utils/telecharger';
import UiSpinner from '@/components/UiSpinner.vue';

/**
 * Bouton « Exporter en Excel » : télécharge la vue courante (mêmes paramètres que la vue)
 * via l'API, avec anneau d'attente pendant la génération et message en cas d'échec.
 * `variante` : « bandeau » (sur le fond coloré du rôle) ou « carte » (sur fond clair).
 */
const props = defineProps({
    url: { type: String, required: true },
    params: { type: Object, default: () => ({}) },
    variante: { type: String, default: 'bandeau' },
    desactive: { type: Boolean, default: false },
});
const { t } = useI18n();
const notifications = useNotificationsStore();
const enCours = ref(false);

async function exporter() {
    enCours.value = true;
    try {
        const nom = await telecharger(props.url, props.params);
        notifications.succes(t('export.succes', { nom }));
    } catch (e) {
        notifications.erreur(await messageErreurTelechargement(e, t('export.erreur'), t('erreurs.reseau')));
    } finally {
        enCours.value = false;
    }
}
</script>

<template>
    <button
        type="button"
        :disabled="enCours || desactive"
        class="inline-flex items-center gap-2 rounded-full text-sm disabled:opacity-60"
        :class="variante === 'bandeau' ? 'border border-current/60 px-3 py-1.5 hover:bg-white/15' : 'border border-accent px-4 py-2 font-semibold text-accent-strong hover:bg-accent/10'"
        @click="exporter"
    >
        <UiSpinner v-if="enCours" taille="h-4 w-4" couleur="text-current" />
        <svg v-else aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
        {{ enCours ? t('export.enCours') : t('export.bouton') }}
    </button>
</template>
