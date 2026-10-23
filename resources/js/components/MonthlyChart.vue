<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { nomMois } from '@/utils/format';
import RatioBarChart from '@/components/RatioBarChart.vue';

/** Efficacité mensuelle : les 12 mois de l'année, vides quand non calculables. */
const props = defineProps({
    mensuelles: { type: Array, default: () => [] },
    objectif: { type: [String, Number], default: '0.7500' },
});
const { locale } = useI18n();

const parMois = computed(() => new Map(props.mensuelles.map((m) => [Number(m.mois), m])));
// `locale.value` est lu pour recalculer les libellés au changement de langue.
const labels = computed(() => (locale.value, Array.from({ length: 12 }, (_, i) => nomMois(i + 1, true))));
const titres = computed(() => (locale.value, Array.from({ length: 12 }, (_, i) => nomMois(i + 1))));
const valeurs = computed(() => Array.from({ length: 12 }, (_, i) => parMois.value.get(i + 1)?.ratio ?? null));
</script>

<template>
    <RatioBarChart :labels="labels" :valeurs="valeurs" :titres="titres" :objectif="objectif" />
</template>
