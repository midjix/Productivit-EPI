<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { heuresDecimales, nombre, pourcentage } from '@/utils/format';
import YearPicker from '@/components/YearPicker.vue';
import RatioRing from '@/components/RatioRing.vue';
import StatTile from '@/components/StatTile.vue';
import UiCard from '@/components/UiCard.vue';
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
            ? "Vous n'avez pas accès aux données de ce salarié."
            : messageErreur(e);
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
                        {{ estMoi ? 'Mon efficacité' : `Efficacité de ${donnees?.salarie?.name ?? '…'}` }}
                    </h2>
                    <YearPicker v-model="annee" :disponibles="anneesDisponibles" :max="anneeCourante" />
                </div>

                <p v-if="erreur" role="alert" class="mt-4 rounded-md bg-white/15 px-4 py-3 text-sm">{{ erreur }}</p>

                <div v-else class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,2fr)]">
                    <div class="flex items-center gap-6 rounded-lg bg-white/10 p-5">
                        <RatioRing :ratio="synthese?.ratio ?? null" :objectif="synthese?.objectif ?? '0.7500'" />
                        <div>
                            <p class="text-sm uppercase tracking-wide text-role-ink-muted">Efficacité {{ annee }}</p>
                            <p class="text-3xl font-semibold">{{ pourcentage(synthese?.ratio) }}</p>
                            <p class="mt-1 text-sm">
                                Objectif {{ pourcentage(synthese?.objectif, 0) }} —
                                <span v-if="synthese?.objectif_atteint === true">atteint ✓</span>
                                <span v-else-if="synthese?.objectif_atteint === false">non atteint</span>
                                <span v-else>non calculable</span>
                            </p>
                            <p class="mt-1 text-xs text-role-ink-muted">Efficacité brute : {{ pourcentage(synthese?.ratio_brut) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatTile libelle="Heures totales" :valeur="heuresDecimales(synthese?.minutes_total)" indice="pointage DSK" />
                        <StatTile libelle="Dispo client" :valeur="heuresDecimales(synthese?.minutes_dispo_client)" indice="après congés, maladie, formation, interne" />
                        <StatTile libelle="Prestations facturables" :valeur="heuresDecimales(synthese?.minutes_prestations_facturables)" indice="forfaits × barème" />
                        <StatTile libelle="Forfaits saisis" :valeur="nombre(synthese?.nb_prestations)" :indice="`sur ${synthese?.nb_mois ?? 0} mois`" />
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6">
            <UiCard v-if="chargement && !donnees" titre="Chargement…">
                <p class="text-ink-muted">Lecture des statistiques…</p>
            </UiCard>

            <UiCard v-else-if="!erreur && !aDesDonnees" :titre="`Aucune statistique pour ${annee}`">
                <p class="text-ink-secondary">
                    Les heures et les prestations sont importées chaque nuit depuis DSK et EpiLogg.
                    Choisissez une autre année ou revenez après le prochain import.
                </p>
            </UiCard>

            <template v-else-if="!erreur">
                <UiCard titre="Efficacité par mois" sous-titre="prestations facturables / heures disponibles pour les bénéficiaires">
                    <MonthlyChart :mensuelles="mensuelles" :objectif="synthese?.objectif ?? '0.7500'" />
                </UiCard>

                <UiCard titre="Détail des mois" sous-titre="en heures ; l'objectif est évalué mois par mois">
                    <MonthsTable :mensuelles="mensuelles" />
                </UiCard>

                <UiCard titre="Comment est calculée l'efficacité ?">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-secondary">
                        <li><strong class="text-ink">Heures totales</strong> : toutes les heures pointées dans DSK (normales, congés, maladie, formation, supervision).</li>
                        <li><strong class="text-ink">Dispo EPI</strong> = heures totales − congés − maladie.</li>
                        <li><strong class="text-ink">Dispo client</strong> = dispo EPI − formation − supervision − activité interne (comités, coordination…).</li>
                        <li><strong class="text-ink">Efficacité</strong> = prestations facturables (forfaits EpiLogg × barème en vigueur) / dispo client. Objectif de la Fondation : 75 %.</li>
                    </ol>
                </UiCard>
            </template>
        </section>
    </div>
</template>
