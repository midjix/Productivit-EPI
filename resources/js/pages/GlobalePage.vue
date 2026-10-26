<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { heuresDecimales, pourcentage } from '@/utils/format';
import PeriodeFilters from '@/components/PeriodeFilters.vue';
import SyntheseHero from '@/components/SyntheseHero.vue';
import UiCard from '@/components/UiCard.vue';
import RatioBarChart from '@/components/RatioBarChart.vue';
import ObjectifBadge from '@/components/ObjectifBadge.vue';

/** Cas d'utilisation « Consulter les statistiques globales » : GET /api/statistiques/globales (Total EPI par service). */
const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const annee = ref(Number(route.query.annee) || new Date().getFullYear());
const mois = ref(route.query.mois ? Number(route.query.mois) : null);
const donnees = ref(null);
const chargement = ref(false);
const erreur = ref('');

const synthese = computed(() => donnees.value?.synthese ?? null);
const services = computed(() => donnees.value?.services ?? []);
const nomService = (s) => s.service?.nom ?? t('globale.sansService');
const labels = computed(() => services.value.map(nomService));
const valeurs = computed(() => services.value.map((s) => s.synthese.ratio));

async function charger() {
    chargement.value = true;
    erreur.value = '';
    try {
        const { data } = await client.get('/statistiques/globales', { params: { annee: annee.value, mois: mois.value ?? undefined } });
        donnees.value = data;
    } catch (e) {
        donnees.value = null;
        erreur.value = messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'));
    } finally {
        chargement.value = false;
    }
}

function ouvrirService(index) {
    const s = services.value[index];
    if (s?.service) router.push({ name: 'service-detail', params: { id: s.service.id }, query: { annee: annee.value, ...(mois.value ? { mois: mois.value } : {}) } });
}

watch([annee, mois], () => {
    router.replace({ query: { annee: annee.value, ...(mois.value ? { mois: mois.value } : {}) } });
    charger();
});

onMounted(charger);
</script>

<template>
    <div>
        <section class="bg-role px-4 pb-8 pt-5 text-role-ink sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-medium uppercase tracking-wide text-role-ink-muted">{{ t('globale.totalEpi') }}</h2>
                    <PeriodeFilters v-model:annee="annee" v-model:mois="mois" />
                </div>

                <p v-if="erreur" role="alert" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm">{{ erreur }}</p>
                <div v-else class="mt-4">
                    <SyntheseHero :synthese="synthese" :libelle="mois ? t('service.periodeMois') : t('dashboard.efficacite', { annee })" />
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6">
            <UiCard v-if="chargement && !donnees" :titre="t('dashboard.chargement')">
                <p class="text-ink-muted">{{ t('dashboard.lecture') }}</p>
            </UiCard>

            <UiCard v-else-if="!erreur && donnees && services.length === 0" :titre="t('dashboard.aucune', { annee })">
                <p class="text-ink-secondary">{{ t('dashboard.aucuneTexte') }}</p>
            </UiCard>

            <template v-else-if="!erreur && donnees">
                <UiCard :titre="t('globale.parService')" :sous-titre="t('globale.parServiceSousTitre')">
                    <RatioBarChart :labels="labels" :valeurs="valeurs" :objectif="synthese?.objectif ?? '0.7500'" horizontal cliquable @select="ouvrirService" />
                    <p class="mt-1 text-xs text-ink-muted">{{ t('globale.cliquezService') }}</p>
                </UiCard>

                <UiCard :titre="t('globale.detail')">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                                    <th class="py-2 pr-3 font-medium">{{ t('globale.service') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('service.nbSalaries') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.total') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.dispoClient') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.facturables') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.efficacite') }}</th>
                                    <th class="py-2 font-medium">{{ t('table.objectif') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in services" :key="s.service?.id ?? 0" class="border-b border-border/60 hover:bg-surface-muted">
                                    <td class="py-2 pr-3 font-medium">
                                        <RouterLink v-if="s.service" :to="{ name: 'service-detail', params: { id: s.service.id }, query: { annee, ...(mois ? { mois } : {}) } }" class="text-accent-strong hover:underline">{{ s.service.nom }}</RouterLink>
                                        <span v-else class="text-ink-muted">{{ t('globale.sansService') }}</span>
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ s.synthese.nb_salaries }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_total) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_dispo_client) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_prestations_facturables) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold tabular-nums text-ink">{{ pourcentage(s.synthese.ratio) }}</td>
                                    <td class="py-2"><ObjectifBadge :atteint="s.synthese.objectif_atteint" /></td>
                                </tr>
                            </tbody>
                            <tfoot v-if="synthese">
                                <tr class="border-t-2 border-border font-semibold">
                                    <td class="py-2 pr-3">{{ t('globale.totalEpi') }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ synthese.nb_salaries }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(synthese.minutes_total) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(synthese.minutes_dispo_client) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(synthese.minutes_prestations_facturables) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ pourcentage(synthese.ratio) }}</td>
                                    <td class="py-2"><ObjectifBadge :atteint="synthese.objectif_atteint" /></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </UiCard>
            </template>
        </section>
    </div>
</template>
