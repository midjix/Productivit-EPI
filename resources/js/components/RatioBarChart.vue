<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { BarController, BarElement, CategoryScale, Chart, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';
import { useThemeStore } from '@/stores/theme';
import { pourcentage } from '@/utils/format';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

/**
 * Barres d'efficacité (une seule série : mois, salariés ou services) et ligne
 * d'objectif pointillée neutre. Un seul axe ; une valeur nulle laisse la place vide.
 * Les couleurs sont lues dans les variables CSS, donc le graphique suit le thème.
 */
const props = defineProps({
    labels: { type: Array, required: true },
    valeurs: { type: Array, required: true }, // ratios 0..1 (chaîne ou nombre) ou null
    objectif: { type: [String, Number], default: '0.7500' },
    titres: { type: Array, default: () => [] }, // titres complets des infobulles (optionnel)
    horizontal: { type: Boolean, default: false },
});

const theme = useThemeStore();
const { t, locale } = useI18n();
const canvas = ref(null);
let chart = null;

function couleur(nom) {
    return getComputedStyle(document.documentElement).getPropertyValue(nom).trim();
}

function construire() {
    if (!canvas.value) return;
    chart?.destroy();

    const valeurs = props.valeurs.map((v) => (v === null || v === undefined || v === '' ? null : Number(v) * 100));
    const objectif = Number(props.objectif) * 100;
    const max = Math.max(100, ...valeurs.filter((v) => v !== null).map((v) => Math.ceil(v / 10) * 10));

    const serie = couleur('--chart-series');
    const grille = couleur('--chart-grid');
    const encre = couleur('--ink-secondary');
    const encreMuette = couleur('--ink-muted');
    const axeValeur = props.horizontal ? 'x' : 'y';
    const axeCategorie = props.horizontal ? 'y' : 'x';

    chart = new Chart(canvas.value, {
        data: {
            labels: props.labels,
            datasets: [
                {
                    type: 'bar',
                    label: t('chart.serie'),
                    data: valeurs,
                    backgroundColor: serie,
                    borderRadius: 4,
                    borderSkipped: props.horizontal ? 'left' : 'bottom',
                    maxBarThickness: 36,
                    categoryPercentage: 0.7,
                    barPercentage: 0.9,
                },
                {
                    type: 'line',
                    label: t('chart.objectif', { valeur: pourcentage(props.objectif, 0) }),
                    data: props.labels.map(() => objectif),
                    borderColor: encreMuette,
                    borderDash: [6, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHitRadius: 0,
                    fill: false,
                },
            ],
        },
        options: {
            indexAxis: props.horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                [axeCategorie]: { grid: { display: false }, ticks: { color: encre }, border: { color: grille } },
                [axeValeur]: {
                    min: 0,
                    max,
                    ticks: { color: encre, callback: (v) => pourcentage(v / 100, 0), stepSize: 25 },
                    grid: { color: grille },
                    border: { display: false },
                },
            },
            plugins: {
                legend: { position: 'bottom', labels: { color: encre, boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        title: (items) => props.titres[items[0].dataIndex] ?? props.labels[items[0].dataIndex],
                        label: (item) => {
                            if (item.dataset.type === 'line') return ` ${t('chart.objectif', { valeur: pourcentage(props.objectif, 0) })}`;
                            if (item.raw === null) return ` ${t('chart.nonCalculable')}`;
                            const statut = item.raw >= objectif ? t('chart.objectifAtteint') : t('chart.sousObjectif');
                            return ` ${t('chart.efficacite', { valeur: pourcentage(item.raw / 100), statut })}`;
                        },
                    },
                },
            },
        },
    });
}

onMounted(construire);
watch(() => [props.labels, props.valeurs, props.objectif, theme.effectif, locale.value], construire, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div class="w-full" :class="horizontal ? 'min-h-72' : 'h-72'" :style="horizontal ? { height: `${Math.max(12, labels.length * 2.2)}rem` } : {}">
        <canvas ref="canvas" role="img" :aria-label="t('chart.ariaMensuel')" />
    </div>
</template>
