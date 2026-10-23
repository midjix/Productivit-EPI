<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { pourcentage } from '@/utils/format';

/**
 * Anneau de progression (comme « congé légal » dans DSKnet) : part de l'objectif atteinte.
 * Le repère de l'objectif est un trait sur l'anneau ; le pourcentage est écrit au centre,
 * la couleur ne porte jamais seule l'information.
 */
const props = defineProps({
    ratio: { type: [String, Number], default: null },
    objectif: { type: [String, Number], default: '0.7500' },
    taille: { type: Number, default: 128 },
});
const { t } = useI18n();

const rayon = 44;
const circonference = 2 * Math.PI * rayon;

const valeur = computed(() => (props.ratio === null || props.ratio === '' ? null : Math.max(0, Number(props.ratio))));
/** L'anneau est plein à 100 % ; au-delà, il reste plein (le texte dit la vraie valeur). */
const fraction = computed(() => (valeur.value === null ? 0 : Math.min(1, valeur.value)));
const decalage = computed(() => circonference * (1 - fraction.value));
const angleObjectif = computed(() => Math.min(1, Number(props.objectif)) * 360);
</script>

<template>
    <div class="relative shrink-0" :style="{ width: `${taille}px`, height: `${taille}px` }">
        <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" role="img" :aria-label="t('chart.ariaRing', { valeur: pourcentage(ratio), objectif: pourcentage(objectif, 0) })">
            <circle cx="50" cy="50" :r="rayon" fill="none" stroke="currentColor" stroke-opacity="0.25" stroke-width="8" />
            <circle
                cx="50" cy="50" :r="rayon" fill="none" stroke="currentColor" stroke-width="8" stroke-linecap="round"
                :stroke-dasharray="circonference" :stroke-dashoffset="decalage" class="transition-[stroke-dashoffset] duration-700"
            />
            <line x1="92" y1="50" x2="100" y2="50" stroke="currentColor" stroke-width="3" :transform="`rotate(${angleObjectif} 50 50)`" />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-xl font-semibold leading-none">{{ pourcentage(ratio, 0) }}</span>
            <span class="mt-1 text-[11px] uppercase tracking-wide opacity-80">{{ t('dashboard.objectif', { valeur: pourcentage(objectif, 0) }) }}</span>
        </div>
    </div>
</template>
