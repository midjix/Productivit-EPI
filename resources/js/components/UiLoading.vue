<script setup>
import { useI18n } from 'vue-i18n';
import UiSpinner from '@/components/UiSpinner.vue';

/** Carte d'attente : anneau animé + message, à la place du contenu en cours de chargement. */
defineProps({ message: { type: String, default: '' } });
const { t } = useI18n();
</script>

<template>
    <section class="flex items-center gap-4 rounded-lg border border-border bg-surface-raised p-5 shadow-sm">
        <UiSpinner taille="h-8 w-8" />
        <div>
            <p class="font-medium uppercase tracking-wide text-ink">{{ t('dashboard.chargement') }}</p>
            <p class="text-sm text-ink-muted">{{ message || t('dashboard.lecture') }}</p>
        </div>
    </section>
</template>
