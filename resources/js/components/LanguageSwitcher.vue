<script setup>
import { useI18n } from 'vue-i18n';
import { LANGUES, changerLangue } from '@/i18n';

/** Choix de la langue (FR / EN / DE), mémorisé dans le navigateur, comme le réglage de langue de DSKnet. */
const { locale, t } = useI18n();
</script>

<template>
    <label class="flex items-center gap-1 text-sm">
        <span class="sr-only">{{ t('langue.libelle') }}</span>
        <select
            :value="locale"
            class="cursor-pointer rounded-md border border-border bg-surface px-2 py-1.5 text-header-ink outline-none focus:border-accent"
            :aria-label="t('langue.libelle')"
            @change="changerLangue($event.target.value)"
        >
            <option v-for="code in LANGUES" :key="code" :value="code">{{ t(`langue.${code}`) }}</option>
        </select>
    </label>
</template>
