<script setup>
import { useI18n } from 'vue-i18n';
import { heuresDecimales, nombre, pourcentage } from '@/utils/format';
import RatioRing from '@/components/RatioRing.vue';
import StatTile from '@/components/StatTile.vue';

/** Bandeau de synthèse d'un groupe (service ou Fondation) : anneau + indicateurs. */
defineProps({
    synthese: { type: Object, default: null },
    libelle: { type: String, required: true },
});
const { t } = useI18n();
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)]">
        <div class="flex items-center gap-6 rounded-lg bg-white/10 p-5">
            <RatioRing :ratio="synthese?.ratio ?? null" :objectif="synthese?.objectif ?? '0.7500'" />
            <div>
                <p class="text-sm uppercase tracking-wide text-role-ink-muted">{{ libelle }}</p>
                <p class="text-3xl font-semibold">{{ pourcentage(synthese?.ratio) }}</p>
                <p class="mt-1 text-sm">
                    {{ t('dashboard.objectif', { valeur: pourcentage(synthese?.objectif, 0) }) }} —
                    <span v-if="synthese?.objectif_atteint === true">{{ t('dashboard.atteint') }}</span>
                    <span v-else-if="synthese?.objectif_atteint === false">{{ t('dashboard.nonAtteint') }}</span>
                    <span v-else>{{ t('dashboard.nonCalculable') }}</span>
                </p>
                <p class="mt-1 text-xs text-role-ink-muted">{{ t('dashboard.brute', { valeur: pourcentage(synthese?.ratio_brut) }) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile :libelle="t('service.nbSalaries')" :valeur="String(synthese?.nb_salaries ?? 0)" :indice="t('dashboard.surMois', { n: synthese?.nb_mois ?? 0 })" />
            <StatTile :libelle="t('dashboard.heuresTotales')" :valeur="heuresDecimales(synthese?.minutes_total)" :indice="t('dashboard.pointageDsk')" />
            <StatTile :libelle="t('dashboard.dispoClient')" :valeur="heuresDecimales(synthese?.minutes_dispo_client)" :indice="t('dashboard.dispoClientIndice')" />
            <StatTile :libelle="t('dashboard.facturables')" :valeur="heuresDecimales(synthese?.minutes_prestations_facturables)" :indice="nombre(synthese?.nb_prestations) + ' ' + t('table.forfaits').toLowerCase()" />
        </div>
    </div>
</template>
