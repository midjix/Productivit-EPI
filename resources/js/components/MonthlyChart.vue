<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { BarController, BarElement, CategoryScale, Chart, Legend, LineController, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';
import { useThemeStore } from '@/stores/theme';
import { nomMois, pourcentage } from '@/utils/format';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

/**
 * Efficacité mensuelle (barres, une seule série) et objectif (ligne pointillée neutre).
 * Un seul axe ; les mois non calculables restent vides. Les couleurs sont lues dans
 * les variables CSS, donc le graphique suit le thème clair / sombre.
 */
const props = defineProps({
    mensuelles: { type: Array, default: () => [] },
    objectif: { type: [String, Number], default: '0.7500' },
});

const theme = useThemeStore();
const canvas = ref(null);
let chart = null;

function couleur(nom) {
    return getComputedStyle(document.documentElement).getPropertyValue(nom).trim();
}

function construire() {
    if (!canvas.value) return;
    chart?.destroy();

    const parMois = new Map(props.mensuelles.map((m) => [Number(m.mois), m]));
    const labels = Array.from({ length: 12 }, (_, i) => nomMois(i + 1, true));
    const valeurs = labels.map((_, i) => {
        const m = parMois.get(i + 1);
        return m && m.ratio !== null ? Number(m.ratio) * 100 : null;
    });
    const objectif = Number(props.objectif) * 100;
    const max = Math.max(100, ...valeurs.filter((v) => v !== null).map((v) => Math.ceil(v / 10) * 10));

    const serie = couleur('--chart-series');
    const grille = couleur('--chart-grid');
    const encre = couleur('--ink-secondary');
    const encreMuette = couleur('--ink-muted');

    chart = new Chart(canvas.value, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Efficacité réelle',
                    data: valeurs,
                    backgroundColor: serie,
                    borderRadius: 4,
                    borderSkipped: 'bottom',
                    maxBarThickness: 36,
                    categoryPercentage: 0.7,
                    barPercentage: 0.9,
                },
                {
                    type: 'line',
                    label: `Objectif ${pourcentage(props.objectif, 0)}`,
                    data: labels.map(() => objectif),
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
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, ticks: { color: encre }, border: { color: grille } },
                y: {
                    min: 0,
                    max,
                    ticks: { color: encre, callback: (v) => `${v} %`, stepSize: 25 },
                    grid: { color: grille },
                    border: { display: false },
                },
            },
            plugins: {
                legend: { position: 'bottom', labels: { color: encre, boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        title: (items) => nomMois(items[0].dataIndex + 1),
                        label: (item) => {
                            if (item.dataset.type === 'line') return ` Objectif : ${pourcentage(props.objectif, 0)}`;
                            if (item.raw === null) return ' Non calculable';
                            const atteint = item.raw >= objectif ? 'objectif atteint' : 'sous l\'objectif';
                            return ` Efficacité : ${item.raw.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} % (${atteint})`;
                        },
                    },
                },
            },
        },
    });
}

onMounted(construire);
watch(() => [props.mensuelles, props.objectif, theme.effectif], construire, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div class="h-72 w-full">
        <canvas ref="canvas" role="img" aria-label="Efficacité mensuelle par rapport à l'objectif" />
    </div>
</template>
