<script setup>
import { heures, nomMois, nombre, pourcentage } from '@/utils/format';

/** Vue tabulaire des mois : la version accessible et imprimable du graphique. */
defineProps({ mensuelles: { type: Array, default: () => [] } });
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                    <th class="py-2 pr-3 font-medium">Mois</th>
                    <th class="py-2 pr-3 text-right font-medium">Total</th>
                    <th class="py-2 pr-3 text-right font-medium">Congés + maladie</th>
                    <th class="py-2 pr-3 text-right font-medium">Dispo EPI</th>
                    <th class="py-2 pr-3 text-right font-medium">Formation + supervision</th>
                    <th class="py-2 pr-3 text-right font-medium">Interne</th>
                    <th class="py-2 pr-3 text-right font-medium">Dispo client</th>
                    <th class="py-2 pr-3 text-right font-medium">Facturables</th>
                    <th class="py-2 pr-3 text-right font-medium">Forfaits</th>
                    <th class="py-2 pr-3 text-right font-medium">Efficacité</th>
                    <th class="py-2 font-medium">Objectif</th>
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
                    <td class="py-2">
                        <span v-if="m.objectif_atteint === true" class="inline-flex items-center gap-1 rounded-full bg-good/15 px-2 py-0.5 text-xs font-medium text-good">
                            <span aria-hidden="true">✓</span> atteint
                        </span>
                        <span v-else-if="m.objectif_atteint === false" class="inline-flex items-center gap-1 rounded-full bg-bad/15 px-2 py-0.5 text-xs font-medium text-bad">
                            <span aria-hidden="true">✕</span> non atteint
                        </span>
                        <span v-else class="text-xs text-ink-muted">non calculable</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
