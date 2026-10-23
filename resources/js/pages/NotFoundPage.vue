<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-surface-muted px-4 text-center">
        <p class="text-6xl font-semibold text-accent">404</p>
        <p class="text-ink-secondary">{{ t('erreurs.introuvable') }}</p>
        <RouterLink :to="{ name: 'tableau-de-bord' }" class="rounded-full bg-accent px-5 py-2 font-medium text-white hover:bg-accent-strong">{{ t('erreurs.retour') }}</RouterLink>
    </div>
</template>
