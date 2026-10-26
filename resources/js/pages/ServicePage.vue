<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { heuresDecimales, pourcentage } from '@/utils/format';
import PeriodeFilters from '@/components/PeriodeFilters.vue';
import SyntheseHero from '@/components/SyntheseHero.vue';
import UiCard from '@/components/UiCard.vue';
import UiLoading from '@/components/UiLoading.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import RatioBarChart from '@/components/RatioBarChart.vue';
import ObjectifBadge from '@/components/ObjectifBadge.vue';

/**
 * Cas d'utilisation « Consulter les statistiques de son service » :
 * GET /api/statistiques/services/{id} (le sien pour un coordinateur, n'importe lequel
 * pour un administrateur). Synthèse du service + une ligne par salarié.
 */
const props = defineProps({ id: { type: String, default: null } });

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const annee = ref(Number(route.query.annee) || new Date().getFullYear());
const mois = ref(route.query.mois ? Number(route.query.mois) : null);
const services = ref([]);
const serviceId = ref(props.id ? Number(props.id) : auth.utilisateur?.service?.id ?? null);
const donnees = ref(null);
const chargement = ref(false);
const erreur = ref('');

const synthese = computed(() => donnees.value?.synthese ?? null);
const salaries = computed(() => donnees.value?.salaries ?? []);
const trie = computed(() => [...salaries.value].sort((a, b) => Number(b.synthese.ratio ?? -1) - Number(a.synthese.ratio ?? -1)));
const labels = computed(() => trie.value.map((s) => s.salarie.name));
/** Bornes de la période courante (année ou mois) pour l'écran Détail. */
const periodeDetail = computed(() => {
    if (!mois.value) return { du: `${annee.value}-01-01`, au: `${annee.value}-12-31` };
    const dernier = new Date(annee.value, mois.value, 0).getDate();
    const m = String(mois.value).padStart(2, '0');
    return { du: `${annee.value}-${m}-01`, au: `${annee.value}-${m}-${dernier}` };
});
const valeurs = computed(() => trie.value.map((s) => s.synthese.ratio));

async function chargerServices() {
    try {
        const { data } = await client.get('/services');
        services.value = data;
        if (serviceId.value === null && data.length > 0) serviceId.value = data[0].id;
    } catch {
        services.value = [];
    }
}

async function charger() {
    if (serviceId.value === null) {
        donnees.value = null;
        return;
    }
    chargement.value = true;
    erreur.value = '';
    try {
        const { data } = await client.get(`/statistiques/services/${serviceId.value}`, { params: { annee: annee.value, mois: mois.value ?? undefined } });
        donnees.value = data;
    } catch (e) {
        donnees.value = null;
        erreur.value = messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'));
    } finally {
        chargement.value = false;
    }
}

function ouvrirSalarie(index) {
    const s = trie.value[index];
    if (s) router.push({ name: 'salarie', params: { id: s.salarie.id }, query: { annee: annee.value } });
}

watch([annee, mois], () => {
    router.replace({ query: { annee: annee.value, ...(mois.value ? { mois: mois.value } : {}) } });
    charger();
});
watch(serviceId, charger);

onMounted(async () => {
    chargement.value = true; // couvre aussi le chargement de la liste des services
    await chargerServices();
    await charger();
});
</script>

<template>
    <div>
        <section class="bg-role px-4 pb-8 pt-5 text-role-ink sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-base font-medium uppercase tracking-wide text-role-ink-muted">
                            {{ donnees?.service?.nom ?? t('service.titre') }}
                        </h2>
                        <select
                            v-if="services.length > 1"
                            v-model="serviceId"
                            class="rounded-full border border-current/60 bg-transparent px-3 py-1.5 text-sm font-medium outline-none"
                            :aria-label="t('service.choisir')"
                        >
                            <option v-for="s in services" :key="s.id" :value="s.id" class="text-ink">{{ s.nom }}</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <RouterLink
                            v-if="serviceId !== null"
                            :to="{ name: 'detail', query: { perimetre: 'service', id: serviceId, du: periodeDetail.du, au: periodeDetail.au } }"
                            class="rounded-full border border-current/60 px-3 py-1.5 text-sm hover:bg-white/15"
                        >{{ t('detail.voirDetail') }}</RouterLink>
                        <PeriodeFilters v-model:annee="annee" v-model:mois="mois" />
                    </div>
                </div>

                <p v-if="erreur" role="alert" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm">{{ erreur }}</p>
                <p v-else-if="chargement && !donnees" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm"><UiSpinner couleur="text-current" :libelle="t('dashboard.lecture')" /></p>
                <p v-else-if="serviceId === null" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm">{{ t('service.aucunService') }}</p>
                <div v-else class="mt-4">
                    <SyntheseHero :synthese="synthese" :libelle="mois ? t('service.periodeMois') : t('dashboard.efficacite', { annee })" />
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6">
            <UiLoading v-if="chargement && !donnees" />

            <UiCard v-else-if="!erreur && donnees && salaries.length === 0" :titre="t('dashboard.aucune', { annee })">
                <p class="text-ink-secondary">{{ t('dashboard.aucuneTexte') }}</p>
            </UiCard>

            <template v-else-if="!erreur && donnees">
                <UiCard :titre="t('service.parSalarie')" :sous-titre="t('service.parSalarieSousTitre')">
                    <RatioBarChart :labels="labels" :valeurs="valeurs" :objectif="synthese?.objectif ?? '0.7500'" horizontal cliquable @select="ouvrirSalarie" />
                    <p class="mt-1 text-xs text-ink-muted">{{ t('service.cliquezSalarie') }}</p>
                </UiCard>

                <UiCard :titre="t('service.detail')">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                                    <th class="py-2 pr-3 font-medium">{{ t('service.salarie') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('service.mois') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.total') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.dispoClient') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.facturables') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('table.efficacite') }}</th>
                                    <th class="py-2 font-medium">{{ t('table.objectif') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in trie" :key="s.salarie.id" class="border-b border-border/60 hover:bg-surface-muted">
                                    <td class="py-2 pr-3 font-medium">
                                        <RouterLink :to="{ name: 'salarie', params: { id: s.salarie.id }, query: { annee } }" class="text-accent-strong hover:underline">{{ s.salarie.name }}</RouterLink>
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ s.synthese.nb_mois }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_total) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_dispo_client) }}</td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ heuresDecimales(s.synthese.minutes_prestations_facturables) }}</td>
                                    <td class="py-2 pr-3 text-right font-semibold tabular-nums text-ink">{{ pourcentage(s.synthese.ratio) }}</td>
                                    <td class="py-2"><ObjectifBadge :atteint="s.synthese.objectif_atteint" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </UiCard>
            </template>
        </section>
    </div>
</template>
