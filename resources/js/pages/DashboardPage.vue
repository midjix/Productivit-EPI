<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { heuresDecimales, nombre, pourcentage } from '@/utils/format';
import YearPicker from '@/components/YearPicker.vue';
import RatioRing from '@/components/RatioRing.vue';
import StatTile from '@/components/StatTile.vue';
import UiCard from '@/components/UiCard.vue';
import UiLoading from '@/components/UiLoading.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import MonthlyChart from '@/components/MonthlyChart.vue';
import MonthsTable from '@/components/MonthsTable.vue';

/**
 * Cas d'utilisation « Consulter son tableau de bord individuel ».
 * Lit GET /api/statistiques/me (ou /salaries/{id} pour un coordinateur / administrateur)
 * et affiche les 12 mois de l'année + la synthèse annuelle. Aucun calcul ici :
 * tout vient du cache mensuel servi par l'API.
 */
const props = defineProps({ id: { type: String, default: null } });

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const anneeCourante = new Date().getFullYear();
const annee = ref(Number(route.query.annee) || anneeCourante);
const donnees = ref(null);
const chargement = ref(false);
const erreur = ref('');

const estMoi = computed(() => props.id === null || Number(props.id) === auth.utilisateur?.id);
const synthese = computed(() => donnees.value?.synthese ?? null);
const mensuelles = computed(() => donnees.value?.mensuelles ?? []);
const aDesDonnees = computed(() => mensuelles.value.length > 0);
const anneesDisponibles = computed(() => donnees.value?.annees_disponibles ?? []);

async function charger() {
    chargement.value = true;
    erreur.value = '';
    try {
        const url = props.id ? `/statistiques/salaries/${props.id}` : '/statistiques/me';
        const { data } = await client.get(url, { params: { annee: annee.value } });
        donnees.value = data;
    } catch (e) {
        donnees.value = null;
        erreur.value = e.response?.status === 403
            ? t('dashboard.accesRefuse')
            : messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'));
    } finally {
        chargement.value = false;
    }
}

watch(annee, (a) => {
    router.replace({ query: { ...route.query, annee: a } });
    charger();
});

onMounted(charger);
</script>

<template>
    <div>
        <!-- Bandeau de synthèse aux couleurs du rôle (comme le bandeau bleu de DSKnet) -->
        <section class="bg-role px-4 pb-8 pt-5 text-role-ink sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-medium uppercase tracking-wide text-role-ink-muted">
                        {{ estMoi ? t('dashboard.monEfficacite') : t('dashboard.efficaciteDe', { nom: donnees?.salarie?.name ?? '…' }) }}
                    </h2>
                    <YearPicker v-model="annee" :disponibles="anneesDisponibles" :max="anneeCourante" />
                </div>

                <p v-if="erreur" role="alert" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm">{{ erreur }}</p>
                <p v-else-if="chargement && !donnees" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm"><UiSpinner couleur="text-current" :libelle="t('dashboard.lecture')" /></p>

                <div v-else class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)]">
                    <div class="flex items-center gap-6 rounded-lg bg-white/10 p-5">
                        <RatioRing :ratio="synthese?.ratio ?? null" :objectif="synthese?.objectif ?? '0.7500'" />
                        <div>
                            <p class="text-sm uppercase tracking-wide text-role-ink-muted">{{ t('dashboard.efficacite', { annee }) }}</p>
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
                        <StatTile :libelle="t('dashboard.heuresTotales')" :valeur="heuresDecimales(synthese?.minutes_total)" :indice="t('dashboard.pointageDsk')" />
                        <StatTile :libelle="t('dashboard.dispoClient')" :valeur="heuresDecimales(synthese?.minutes_dispo_client)" :indice="t('dashboard.dispoClientIndice')" />
                        <StatTile :libelle="t('dashboard.facturables')" :valeur="heuresDecimales(synthese?.minutes_prestations_facturables)" :indice="t('dashboard.facturablesIndice')" />
                        <StatTile :libelle="t('dashboard.forfaits')" :valeur="nombre(synthese?.nb_prestations)" :indice="t('dashboard.surMois', { n: synthese?.nb_mois ?? 0 })" />
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6">
            <UiLoading v-if="chargement && !donnees" />

            <UiCard v-else-if="!erreur && !aDesDonnees" :titre="t('dashboard.aucune', { annee })">
                <p class="text-ink-secondary">{{ t('dashboard.aucuneTexte') }}</p>
            </UiCard>

            <template v-else-if="!erreur">
                <UiCard :titre="t('dashboard.parMois')" :sous-titre="t('dashboard.parMoisSousTitre')">
                    <MonthlyChart :mensuelles="mensuelles" :objectif="synthese?.objectif ?? '0.7500'" />
                </UiCard>

                <UiCard :titre="t('dashboard.detail')" :sous-titre="t('dashboard.detailSousTitre')">
                    <MonthsTable :mensuelles="mensuelles" />
                </UiCard>

                <UiCard :titre="t('dashboard.comment')">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-secondary [&_strong]:text-ink">
                        <!-- Textes traduits contenant uniquement des balises <strong> maîtrisées (fichiers de langue du projet). -->
                        <li v-html="t('dashboard.etape1')" />
                        <li v-html="t('dashboard.etape2')" />
                        <li v-html="t('dashboard.etape3')" />
                        <li v-html="t('dashboard.etape4', { objectif: pourcentage(synthese?.objectif ?? '0.75', 0) })" />
                    </ol>
                </UiCard>
            </template>
        </section>
    </div>
</template>
