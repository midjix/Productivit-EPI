<script setup>
import { useI18n } from 'vue-i18n';
import UiSpinner from '@/components/UiSpinner.vue';

/**
 * Voile d'attente sur un contenu déjà affiché (liste que l'on recharge après un filtre,
 * un changement de page ou un enregistrement) : le contenu reste visible mais estompé,
 * un anneau signale que l'action est prise en compte.
 */
defineProps({
    actif: { type: Boolean, default: false },
    libelle: { type: String, default: '' },
});
const { t } = useI18n();
</script>

<template>
    <div class="relative">
        <div :aria-busy="actif" class="transition-opacity" :class="actif ? 'pointer-events-none opacity-40' : ''">
            <slot />
        </div>
        <div v-if="actif" class="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-8">
            <span class="rounded-full border border-border bg-surface-raised px-3 py-1.5 text-sm text-ink shadow-md">
                <UiSpinner taille="h-4 w-4" :libelle="libelle || t('dashboard.chargement')" />
            </span>
        </div>
    </div>
</template>
