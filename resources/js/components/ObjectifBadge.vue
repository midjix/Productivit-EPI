<script setup>
import { useI18n } from 'vue-i18n';

/** Statut de l'objectif, écrit en toutes lettres avec un pictogramme : jamais la couleur seule. */
defineProps({ atteint: { type: Boolean, default: null } });
const { t } = useI18n();
</script>

<template>
    <span v-if="atteint === true" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-good/15 px-2 py-0.5 text-xs font-medium text-good">
        <span aria-hidden="true">✓</span> {{ t('table.atteint') }}
    </span>
    <span v-else-if="atteint === false" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-bad/15 px-2 py-0.5 text-xs font-medium text-bad">
        <span aria-hidden="true">✕</span> {{ t('table.nonAtteint') }}
    </span>
    <span v-else class="whitespace-nowrap text-xs text-ink-muted">{{ t('table.nonCalculable') }}</span>
</template>
