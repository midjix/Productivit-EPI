<script setup>
import { useI18n } from 'vue-i18n';
import { heures, nomMois, nombre, pourcentage } from '@/utils/format';
import ObjectifBadge from '@/components/ObjectifBadge.vue';

/** Vue tabulaire des mois : la version accessible et imprimable du graphique. */
defineProps({ mensuelles: { type: Array, default: () => [] } });
const { t } = useI18n();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                    <th class="py-2 pr-3 font-medium">{{ t('table.mois') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.total') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.congesMaladie') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.dispoEpi') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.formationSupervision') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.interne') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.dispoClient') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.facturables') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.forfaits') }}</th>
                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.efficacite') }}</th>
                    <th class="py-2 font-medium">{{ t('table.objectif') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="m in mensuelles" :key="`${m.annee}-${m.mois}`" class="border-b border-border/60 hover:bg-surface-muted">
                    <td class="py-2 pr-3 font-medium text-ink">{{ nomMois(m.mois) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ heures(m.minutes_total) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ heures(m.minutes_conge + m.minutes_maladie) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ heures(m.minutes_dispo_epi) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ heures(m.minutes_formation + m.minutes_supervision) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ heures(m.minutes_activite_interne) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ heures(m.minutes_dispo_client) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ heures(m.minutes_prestations_facturables) }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(m.nb_prestations) }}</td>
                    <td class="py-2 pr-3 text-right font-semibold tabular-nums text-ink">{{ pourcentage(m.ratio) }}</td>
                    <td class="py-2"><ObjectifBadge :atteint="m.objectif_atteint" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
