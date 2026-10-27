<script setup>
import { useI18n } from 'vue-i18n';

/**
 * Liste des critères du mot de passe, cochés en direct pendant la saisie : l'utilisateur
 * voit d'emblée tout ce qui manque. Statut écrit (pictogramme + texte lu), pas seulement coloré.
 */
defineProps({ regles: { type: Object, required: true } });
const { t } = useI18n();
</script>

<template>
    <div class="rounded-md border border-border bg-surface-muted/60 px-3 py-2 text-sm">
        <p class="mb-1 text-xs uppercase tracking-wide text-ink-muted">{{ t('mdp.reglesTitre') }}</p>
        <ul class="space-y-0.5" aria-live="polite">
            <li v-for="(ok, cle) in regles" :key="cle" class="flex items-center gap-2" :class="ok ? 'text-good' : 'text-ink-secondary'">
                <span aria-hidden="true" class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[11px] font-bold" :class="ok ? 'bg-good/15' : 'bg-bad/15 text-bad'">{{ ok ? '✓' : '✕' }}</span>
                <span>{{ t(`mdp.regles.${cle}`) }}</span>
                <span class="sr-only">: {{ ok ? t('mdp.regleOk') : t('mdp.regleKo') }}</span>
            </li>
        </ul>
    </div>
</template>
