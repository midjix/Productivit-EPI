<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useRechercheStore } from '@/stores/recherche';
import { date, heures, nombre, pourcentage } from '@/utils/format';
import UiCard from '@/components/UiCard.vue';
import UiLoading from '@/components/UiLoading.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import ObjectifBadge from '@/components/ObjectifBadge.vue';

/**
 * Écran « Détail » : le niveau de détail de l'écran Excel de la Fondation.
 * Périmètre (selon le rôle), période libre, filtres par types de suivi et de prestation ;
 * résultats calculés par l'API depuis les flux bruts (GET /api/statistiques/detail).
 * L'état des filtres vit dans l'URL : un lien se partage et le bouton Retour fonctionne.
 */
const auth = useAuthStore();
const recherche = useRechercheStore();
const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const anneeCourante = new Date().getFullYear();
const filtres = ref(null);
const chargementFiltres = ref(true);
const resultat = ref(null);
const chargement = ref(false);
const erreur = ref('');
const erreurs = ref({});

// Sans paramètre : un administrateur démarre sur Total EPI, les autres sur eux-mêmes.
const perimetreInitial = typeof route.query.perimetre === 'string' ? route.query.perimetre : (auth.estAdministrateurRole ? 'epi' : 'salarie');
const form = reactive({
    perimetre: perimetreInitial,
    id: route.query.id ? Number(route.query.id) : (['epi', 'interne'].includes(perimetreInitial) ? null : auth.utilisateur?.id ?? null),
    du: typeof route.query.du === 'string' ? route.query.du : `${anneeCourante}-01-01`,
    au: typeof route.query.au === 'string' ? route.query.au : new Date().toISOString().slice(0, 10),
    types_suivi: [].concat(route.query.types_suivi ?? []).map(Number).filter(Boolean),
    types_prestation: [].concat(route.query.types_prestation ?? []).filter(Boolean),
});

/** Options du sélecteur de périmètre : « type:id », construites d'après le rôle. */
const optionsPerimetre = computed(() => {
    const options = [];
    const autorises = filtres.value?.perimetres ?? ['salarie'];
    if (autorises.includes('epi')) options.push({ valeur: 'epi:', libelle: t('detail.epi'), groupe: '' });
    if (autorises.includes('interne')) options.push({ valeur: 'interne:', libelle: t('detail.interne'), groupe: '' });
    if (autorises.includes('tarif')) {
        for (const ta of filtres.value?.tarifs ?? []) options.push({ valeur: `tarif:${ta.id}`, libelle: `${t('detail.tarif')} ${ta.code} — ${ta.libelle}`, groupe: t('detail.tarifs') });
    }
    if (autorises.includes('service')) {
        for (const s of filtres.value?.services ?? []) options.push({ valeur: `service:${s.id}`, libelle: s.nom, groupe: t('detail.services') });
    }
    if (auth.aRole('coordinateur')) {
        for (const s of recherche.salaries) options.push({ valeur: `salarie:${s.id}`, libelle: s.name + (s.service ? ` (${s.service.nom})` : ''), groupe: t('detail.salaries') });
    } else {
        options.push({ valeur: `salarie:${auth.utilisateur?.id}`, libelle: t('detail.moi'), groupe: '' });
    }
    return options;
});
const groupes = computed(() => [...new Set(optionsPerimetre.value.map((o) => o.groupe))]);
const perimetreValeur = computed({
    get: () => `${form.perimetre}:${form.id ?? ''}`,
    set: (v) => {
        const [type, id] = v.split(':');
        form.perimetre = type;
        form.id = id ? Number(id) : null;
    },
});

async function chargerFiltres() {
    try {
        const { data } = await client.get('/statistiques/filtres');
        filtres.value = data;
    } catch (e) {
        erreur.value = messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'));
    } finally {
        chargementFiltres.value = false;
    }
}

async function appliquer() {
    chargement.value = true;
    erreur.value = '';
    erreurs.value = {};
    router.replace({ query: {
        perimetre: form.perimetre, id: form.id ?? undefined, du: form.du, au: form.au,
        types_suivi: form.types_suivi.length ? form.types_suivi : undefined,
        types_prestation: form.types_prestation.length ? form.types_prestation : undefined,
    } });
    try {
        const { data } = await client.get('/statistiques/detail', { params: {
            perimetre: form.perimetre, id: form.id ?? undefined, du: form.du, au: form.au,
            types_suivi: form.types_suivi, types_prestation: form.types_prestation,
        } });
        resultat.value = data;
    } catch (e) {
        resultat.value = null;
        erreurs.value = e.response?.data?.errors ?? {};
        erreur.value = e.response?.status === 422 ? '' : messageErreur(e, t('erreurs.generique'), t('erreurs.reseau'));
    } finally {
        chargement.value = false;
    }
}

const synthese = computed(() => resultat.value?.synthese ?? null);

/** Lignes des indicateurs de synthèse, dans l'ordre du fichier Excel. */
const indicateurs = computed(() => {
    const s = synthese.value;
    if (!s) return [];
    return [
        { cle: 'totalDispo', valeur: heures(s.minutes_total), fort: true },
        { cle: 'congesRecup', valeur: heures(s.minutes_conge) },
        { cle: 'maladie', valeur: heures(s.minutes_maladie) },
        { cle: 'dispoEpi', valeur: heures(s.minutes_dispo_epi), fort: true },
        { cle: 'formation', valeur: heures(s.minutes_formation) },
        { cle: 'supervision', valeur: heures(s.minutes_supervision) },
        { cle: 'interne', valeur: heures(s.minutes_activite_interne) },
        { cle: 'dispoClient', valeur: heures(s.minutes_dispo_client), fort: true },
        { cle: 'facturables', valeur: heures(s.minutes_prestations_facturables), fort: true },
        { cle: 'forfaits', valeur: nombre(s.nb_prestations) },
        { cle: 'nbSalaries', valeur: String(s.nb_salaries) },
    ];
});

const partFacturable = (minutes) => {
    const total = Number(synthese.value?.minutes_prestations_facturables ?? 0);
    return total > 0 ? pourcentage(Number(minutes) / total, 0) : '—';
};

onMounted(() => {
    // Trois appels indépendants lancés en parallèle : filtres, liste des salariés (sélecteur), résultat.
    chargerFiltres();
    recherche.charger();
    appliquer();
});
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6">
        <UiCard :titre="t('detail.titre')" :sous-titre="t('detail.sousTitre')">
            <UiLoading v-if="chargementFiltres" />
            <form v-else class="space-y-4" @submit.prevent="appliquer">
                <div class="grid gap-3 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto] md:items-end">
                    <div>
                        <label for="perimetre" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('detail.perimetre') }}</label>
                        <select id="perimetre" v-model="perimetreValeur" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink">
                            <template v-for="g in groupes" :key="g">
                                <optgroup v-if="g" :label="g">
                                    <option v-for="o in optionsPerimetre.filter((x) => x.groupe === g)" :key="o.valeur" :value="o.valeur">{{ o.libelle }}</option>
                                </optgroup>
                                <option v-for="o in optionsPerimetre.filter((x) => x.groupe === g && !g)" v-else :key="o.valeur" :value="o.valeur">{{ o.libelle }}</option>
                            </template>
                        </select>
                        <p v-if="erreurs.perimetre || erreurs.id" class="mt-1 text-xs text-bad">{{ (erreurs.perimetre ?? erreurs.id)[0] }}</p>
                    </div>
                    <div>
                        <label for="detail-du" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.du') }}</label>
                        <input id="detail-du" v-model="form.du" type="date" required class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreurs.du" class="mt-1 text-xs text-bad">{{ erreurs.du[0] }}</p>
                    </div>
                    <div>
                        <label for="detail-au" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.au') }}</label>
                        <input id="detail-au" v-model="form.au" type="date" required class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreurs.au" class="mt-1 text-xs text-bad">{{ erreurs.au[0] }}</p>
                    </div>
                    <button type="submit" :disabled="chargement" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white hover:bg-accent-strong disabled:opacity-60">
                        <UiSpinner v-if="chargement" taille="h-4 w-4" couleur="text-white" />{{ t('detail.appliquer') }}
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <details class="rounded-md border border-border p-3">
                        <summary class="cursor-pointer text-sm font-medium text-ink">
                            {{ t('detail.typesSuivi') }}
                            <span class="text-ink-muted">({{ form.types_suivi.length ? form.types_suivi.length : t('detail.tous') }})</span>
                        </summary>
                        <div class="mt-2 grid gap-1 sm:grid-cols-2">
                            <label v-for="ts in filtres?.types_suivi ?? []" :key="ts.id" class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.types_suivi" type="checkbox" :value="ts.id" class="h-4 w-4 accent-accent" />
                                <span>{{ ts.libelle }}</span>
                                <span v-if="ts.tarif_code" class="text-xs text-ink-muted">{{ ts.tarif_code }}</span>
                            </label>
                        </div>
                    </details>
                    <details class="rounded-md border border-border p-3">
                        <summary class="cursor-pointer text-sm font-medium text-ink">
                            {{ t('detail.typesPrestation') }}
                            <span class="text-ink-muted">({{ form.types_prestation.length ? form.types_prestation.length : t('detail.tous') }})</span>
                        </summary>
                        <div class="mt-2 grid gap-1">
                            <label v-for="tp in filtres?.types_prestation ?? []" :key="tp" class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.types_prestation" type="checkbox" :value="tp" class="h-4 w-4 accent-accent" />
                                <span>{{ tp }}</span>
                            </label>
                        </div>
                    </details>
                </div>
            </form>
        </UiCard>

        <p v-if="erreur" role="alert" class="rounded-md border border-bad/40 bg-bad/10 px-4 py-3 text-sm text-bad">{{ erreur }}</p>
        <UiLoading v-else-if="chargement && !resultat" />

        <template v-else-if="resultat">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold text-ink">{{ resultat.perimetre.libelle }}</h2>
                <p class="text-sm text-ink-muted">{{ date(resultat.du) }} → {{ date(resultat.au) }}</p>
            </div>
            <p v-if="resultat.perimetre.type === 'tarif' || resultat.perimetre.type === 'interne'" class="text-xs text-ink-muted">{{ t('detail.noteTarif') }}</p>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="space-y-4">
                    <UiCard :titre="t('detail.detailsAffiches')" :sous-titre="t('detail.parTypeSuivi')">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                                    <th class="py-2 pr-3 font-medium">{{ t('detail.typeSuivi') }}</th>
                                    <th class="py-2 pr-3 font-medium">{{ t('detail.tarif') }}</th>
                                    <th class="py-2 pr-3 text-right font-medium">{{ t('detail.forfaits') }}</th>
                                    <th class="py-2 text-right font-medium">{{ t('detail.heures') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in resultat.types_suivi" :key="l.id" class="border-b border-border/60">
                                    <td class="py-1.5 pr-3 text-ink">{{ l.libelle }}</td>
                                    <td class="py-1.5 pr-3 text-ink-secondary">{{ l.tarif_code ?? '—' }}<span v-if="l.tarif_code && !l.facturable" class="ml-1 text-xs text-ink-muted">({{ t('detail.interneCourt') }})</span></td>
                                    <td class="py-1.5 pr-3 text-right tabular-nums">{{ nombre(l.nb_forfaits) }}</td>
                                    <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                </tr>
                                <tr v-if="resultat.types_suivi.length === 0"><td colspan="4" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                            </tbody>
                        </table>
                    </UiCard>

                    <div class="grid gap-4 md:grid-cols-2">
                        <UiCard :titre="t('detail.parTarif')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in resultat.tarifs" :key="l.code ?? 'x'" class="border-b border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ l.code ? `${t('detail.tarif')} ${l.code}` : t('detail.nonQualifie') }}</td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                        <td class="py-1.5 text-right tabular-nums text-ink-muted">{{ l.facturable ? partFacturable(l.minutes) : '' }}</td>
                                    </tr>
                                    <tr v-if="resultat.tarifs.length === 0"><td colspan="4" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                        <UiCard :titre="t('detail.parService')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in resultat.services" :key="l.id ?? 0" class="border-b border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ l.nom ?? t('detail.interne') }}</td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                    </tr>
                                    <tr v-if="resultat.services.length === 0"><td colspan="3" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <UiCard :titre="t('detail.parCompte')" :sous-titre="t('detail.parCompteSousTitre')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="c in resultat.comptes_dsk" :key="c.id" class="border-b border-border/60" :class="c.compte_effectif ? '' : 'text-ink-muted'">
                                        <td class="py-1.5 pr-3">{{ c.libelle }}<span v-if="!c.compte_effectif" class="ml-1 text-xs">({{ t('detail.horsTotal') }})</span></td>
                                        <td class="py-1.5 pr-3 text-xs text-ink-muted">{{ c.categorie_label }}</td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(c.minutes) }}</td>
                                    </tr>
                                    <tr v-if="resultat.comptes_dsk.length === 0"><td colspan="3" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                        <UiCard :titre="t('detail.parTypePrestation')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in resultat.types_prestation" :key="l.libelle ?? 'x'" class="border-b border-border/60">
                                        <td class="py-1.5 pr-3 text-ink">{{ l.libelle ?? '—' }}</td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                    </tr>
                                    <tr v-if="resultat.types_prestation.length === 0"><td colspan="3" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                    </div>
                </div>

                <UiCard :titre="t('detail.indicateurs')" :sous-titre="t('detail.indicateursSousTitre')">
                    <dl class="divide-y divide-border/60 text-sm">
                        <div v-for="i in indicateurs" :key="i.cle" class="flex items-baseline justify-between gap-3 py-1.5" :class="i.fort ? 'font-medium text-ink' : 'text-ink-secondary'">
                            <dt>{{ t(`detail.ind.${i.cle}`) }}</dt>
                            <dd class="tabular-nums">{{ i.valeur }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 py-2 text-base font-semibold text-ink">
                            <dt>{{ t('detail.ind.efficacite') }}</dt>
                            <dd class="tabular-nums">{{ pourcentage(synthese?.ratio) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-1.5 text-ink-secondary">
                            <dt>{{ t('detail.ind.objectif') }}</dt>
                            <dd class="flex items-center gap-2 tabular-nums">{{ pourcentage(synthese?.objectif, 0) }} <ObjectifBadge :atteint="synthese?.objectif_atteint" /></dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 py-1.5 text-ink-secondary">
                            <dt>{{ t('detail.ind.brute') }}</dt>
                            <dd class="tabular-nums">{{ pourcentage(synthese?.ratio_brut) }}</dd>
                        </div>
                        <div v-if="Number(synthese?.forfaits_non_valorises) > 0" class="flex items-baseline justify-between gap-3 py-1.5 text-bad">
                            <dt>{{ t('detail.ind.nonValorises') }}</dt>
                            <dd class="tabular-nums">{{ nombre(synthese.forfaits_non_valorises) }}</dd>
                        </div>
                    </dl>
                </UiCard>
            </div>
        </template>
    </div>
</template>
