<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useRechercheStore } from '@/stores/recherche';
import { date, heures, nombre, pourcentage } from '@/utils/format';
import UiCard from '@/components/UiCard.vue';
import UiLoading from '@/components/UiLoading.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import UiTooltip from '@/components/UiTooltip.vue';
import ObjectifBadge from '@/components/ObjectifBadge.vue';

/**
 * Écran « Détail » : le niveau de détail de l'écran Excel de la Fondation.
 * Périmètre (selon le rôle), période libre, filtres par types de suivi et de prestation ;
 * résultats calculés par l'API depuis les flux bruts (GET /api/statistiques/detail).
 * L'état des filtres vit dans l'URL : un lien se partage et le bouton Retour fonctionne.
 * Chaque ligne porte une info-bulle (survol ou clavier) qui explique d'où vient le chiffre.
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

// ---- Couleurs : un ton par tarif facturable (le premier garde l'orange de la charte), un ton par catégorie DSK ----
const CLASSES_TARIF = ['bg-tarif-1', 'bg-tarif-2', 'bg-tarif-3', 'bg-tarif-4'];
const CLASSES_CATEGORIE = {
    normal: 'bg-cat-normal', conge: 'bg-cat-conge', maladie: 'bg-cat-maladie', formation: 'bg-cat-formation',
    supervision: 'bg-cat-supervision', deplacement: 'bg-cat-deplacement', autre: 'bg-cat-autre',
};
const codesFacturables = computed(() => (resultat.value?.tarifs ?? []).filter((l) => l.facturable && l.code).map((l) => l.code));
const couleurTarif = (code, facturable) => {
    if (!code || !facturable) return 'bg-tarif-interne';
    const i = codesFacturables.value.indexOf(code);
    return CLASSES_TARIF[Math.min(i < 0 ? 0 : i, CLASSES_TARIF.length - 1)];
};
const couleurCategorie = (categorie) => CLASSES_CATEGORIE[categorie] ?? 'bg-cat-autre';

/** Largeur d'une barre de proportion : part de la ligne dans le maximum de son tableau. */
const largeur = (minutes, lignes) => {
    const max = Math.max(0, ...lignes.map((l) => Number(l.minutes)));
    return max > 0 ? `${Math.round((Number(minutes) / max) * 100)}%` : '0%';
};

const partFacturable = (minutes) => {
    const total = Number(synthese.value?.minutes_prestations_facturables ?? 0);
    return total > 0 ? pourcentage(Number(minutes) / total, 0) : '—';
};
const totalPrestations = computed(() => (resultat.value?.types_prestation ?? []).reduce((s, l) => s + Number(l.minutes), 0));
const partPrestations = (minutes) => (totalPrestations.value > 0 ? pourcentage(Number(minutes) / totalPrestations.value, 0) : '—');

// ---- Info-bulles : l'explication ou le détail du calcul de chaque ligne ----
const bulleTypeSuivi = (l) => {
    if (!l.tarif_code) return t('detail.bulle.typeSuiviSansTarif', { libelle: l.libelle, forfaits: nombre(l.nb_forfaits) });
    const moyenne = Number(l.nb_forfaits) > 0 ? nombre(Number(l.minutes) / Number(l.nb_forfaits), 0) : '—';
    return t('detail.bulle.typeSuivi', { libelle: l.libelle, forfaits: nombre(l.nb_forfaits), tarif: l.tarif_code, moyenne, heures: heures(l.minutes) })
        + '\n' + t(l.facturable ? 'detail.bulle.typeSuiviFacturable' : 'detail.bulle.typeSuiviInterne');
};
const bulleTarif = (l) => {
    if (!l.code) return t('detail.bulle.tarifSans', { forfaits: nombre(l.nb_forfaits) });
    if (!l.facturable) return t('detail.bulle.tarifInterne', { code: l.code, heures: heures(l.minutes) });
    return t('detail.bulle.tarif', { code: l.code, forfaits: nombre(l.nb_forfaits), heures: heures(l.minutes), part: partFacturable(l.minutes), total: heures(synthese.value?.minutes_prestations_facturables) });
};
const bulleService = (l) => t(l.nom ? 'detail.bulle.service' : 'detail.bulle.serviceInterne', { nom: l.nom, forfaits: nombre(l.nb_forfaits), heures: heures(l.minutes) });
const bulleTypePrestation = (l) => t('detail.bulle.typePrestation', { libelle: l.libelle ?? '—', forfaits: nombre(l.nb_forfaits), heures: heures(l.minutes), part: partPrestations(l.minutes) });
const bulleCompte = (c) => t('detail.bulle.compte', { no: c.no_account, libelle: c.libelle, heures: heures(c.minutes), nb: c.nb_salaries })
    + '\n' + (c.compte_effectif ? t(`detail.bulle.cat.${c.categorie}`, { cat: c.categorie_label }) : t('detail.bulle.compteHorsTotal'));

/**
 * Lignes des indicateurs de synthèse, dans l'ordre du fichier Excel.
 * `role` : 'total' (point de départ), 'deduction' (ligne soustraite, affichée avec un signe −),
 * 'resultat' (sous-total de la cascade), 'info' (donnée complémentaire).
 */
const indicateurs = computed(() => {
    const s = synthese.value;
    if (!s) return [];
    const h = {
        total: heures(s.minutes_total), conge: heures(s.minutes_conge), maladie: heures(s.minutes_maladie), dispoEpi: heures(s.minutes_dispo_epi),
        formation: heures(s.minutes_formation), supervision: heures(s.minutes_supervision), interne: heures(s.minutes_activite_interne),
        dispoClient: heures(s.minutes_dispo_client), facturables: heures(s.minutes_prestations_facturables),
    };
    return [
        { cle: 'totalDispo', valeur: h.total, role: 'total', couleur: 'bg-cat-normal', bulle: t('detail.bulle.ind.totalDispo', { nb: s.nb_salaries }) },
        { cle: 'congesRecup', valeur: h.conge, role: 'deduction', couleur: 'bg-cat-conge', bulle: t('detail.bulle.ind.congesRecup') },
        { cle: 'maladie', valeur: h.maladie, role: 'deduction', couleur: 'bg-cat-maladie', bulle: t('detail.bulle.ind.maladie') },
        { cle: 'dispoEpi', valeur: h.dispoEpi, role: 'resultat', couleur: 'bg-accent', bulle: t('detail.bulle.ind.dispoEpi', { total: h.total, conge: h.conge, maladie: h.maladie, valeur: h.dispoEpi }) },
        { cle: 'formation', valeur: h.formation, role: 'deduction', couleur: 'bg-cat-formation', bulle: t('detail.bulle.ind.formation') },
        { cle: 'supervision', valeur: h.supervision, role: 'deduction', couleur: 'bg-cat-supervision', bulle: t('detail.bulle.ind.supervision') },
        { cle: 'interne', valeur: h.interne, role: 'deduction', couleur: 'bg-tarif-interne', bulle: t('detail.bulle.ind.interne') },
        { cle: 'dispoClient', valeur: h.dispoClient, role: 'resultat', couleur: 'bg-accent', bulle: t('detail.bulle.ind.dispoClient', { dispoEpi: h.dispoEpi, formation: h.formation, supervision: h.supervision, interne: h.interne, valeur: h.dispoClient }) },
        { cle: 'facturables', valeur: h.facturables, role: 'resultat', couleur: 'bg-good', bulle: t('detail.bulle.ind.facturables') },
        { cle: 'forfaits', valeur: nombre(s.nb_prestations), role: 'info', bulle: t('detail.bulle.ind.forfaits') },
        { cle: 'nbSalaries', valeur: String(s.nb_salaries), role: 'info', bulle: t('detail.bulle.ind.nbSalaries') },
    ];
});
const bulleEfficacite = computed(() => t('detail.bulle.ind.efficacite', { facturables: heures(synthese.value?.minutes_prestations_facturables), dispoClient: heures(synthese.value?.minutes_dispo_client), valeur: pourcentage(synthese.value?.ratio) }));
const bulleObjectif = computed(() => t('detail.bulle.ind.objectif', { objectif: pourcentage(synthese.value?.objectif, 0) }));
const bulleBrute = computed(() => t('detail.bulle.ind.brute', { facturables: heures(synthese.value?.minutes_prestations_facturables), interne: heures(synthese.value?.minutes_activite_interne), total: heures(synthese.value?.minutes_total), valeur: pourcentage(synthese.value?.ratio_brut) }));

/** Jauge de l'efficacité : la barre est bornée à 100 %, l'objectif est un repère vertical. */
const jaugeRatio = computed(() => `${Math.min(100, Math.round(Number(synthese.value?.ratio ?? 0) * 100))}%`);
const jaugeObjectif = computed(() => `${Math.min(100, Math.round(Number(synthese.value?.objectif ?? 0) * 100))}%`);

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
            <p class="flex items-center gap-1.5 text-xs text-ink-muted">
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 shrink-0 text-accent" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" /></svg>
                {{ t('detail.aideSurvol') }}
            </p>

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
                                    <th class="hidden w-28 py-2 pl-3 font-medium sm:table-cell">{{ t('detail.part') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in resultat.types_suivi" :key="l.id" tabindex="0" :aria-describedby="`b-ts-${l.id}`" class="group border-b border-border/60 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                                    <td class="py-1.5 pr-3 text-ink">
                                        <UiTooltip :id="`b-ts-${l.id}`" :texte="bulleTypeSuivi(l)">
                                            <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="h-2.5 w-2.5 shrink-0 rounded-full" :class="couleurTarif(l.tarif_code, l.facturable)" />{{ l.libelle }}</span>
                                        </UiTooltip>
                                    </td>
                                    <td class="py-1.5 pr-3 text-ink-secondary">{{ l.tarif_code ?? '—' }}<span v-if="l.tarif_code && !l.facturable" class="ml-1 text-xs text-ink-muted">({{ t('detail.interneCourt') }})</span></td>
                                    <td class="py-1.5 pr-3 text-right tabular-nums">{{ nombre(l.nb_forfaits) }}</td>
                                    <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                    <td class="hidden py-1.5 pl-3 sm:table-cell"><div class="h-2 w-full rounded-full bg-surface-muted"><div class="h-2 rounded-full" :class="couleurTarif(l.tarif_code, l.facturable)" :style="{ width: largeur(l.minutes, resultat.types_suivi) }" /></div></td>
                                </tr>
                                <tr v-if="resultat.types_suivi.length === 0"><td colspan="5" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                            </tbody>
                        </table>
                    </UiCard>

                    <div class="grid gap-4 md:grid-cols-2">
                        <UiCard :titre="t('detail.parTarif')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in resultat.tarifs" :key="l.code ?? 'x'" tabindex="0" :aria-describedby="`b-ta-${l.code ?? 'x'}`" class="group border-b border-border/60 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                                        <td class="py-1.5 pr-3 text-ink">
                                            <UiTooltip :id="`b-ta-${l.code ?? 'x'}`" :texte="bulleTarif(l)">
                                                <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="h-2.5 w-2.5 shrink-0 rounded-full" :class="couleurTarif(l.code, l.facturable)" />{{ l.code ? `${t('detail.tarif')} ${l.code}` : t('detail.nonQualifie') }}</span>
                                            </UiTooltip>
                                        </td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                        <td class="py-1.5 text-right tabular-nums text-ink-muted">{{ l.facturable ? partFacturable(l.minutes) : '' }}</td>
                                    </tr>
                                    <tr v-if="resultat.tarifs.length === 0"><td colspan="4" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                            <div v-if="resultat.tarifs.length" class="mt-3 flex h-2.5 w-full overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                                <div v-for="l in resultat.tarifs" :key="l.code ?? 'x'" class="h-full" :class="couleurTarif(l.code, l.facturable)" :style="{ width: `${(Number(l.minutes) / Math.max(1, resultat.tarifs.reduce((s, x) => s + Number(x.minutes), 0))) * 100}%` }" />
                            </div>
                        </UiCard>
                        <UiCard :titre="t('detail.parService')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="l in resultat.services" :key="l.id ?? 0" tabindex="0" :aria-describedby="`b-se-${l.id ?? 0}`" class="group border-b border-border/60 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                                        <td class="py-1.5 pr-3 text-ink"><UiTooltip :id="`b-se-${l.id ?? 0}`" :texte="bulleService(l)">{{ l.nom ?? t('detail.interne') }}</UiTooltip></td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                        <td class="hidden w-20 py-1.5 pl-3 sm:table-cell"><div class="h-2 w-full rounded-full bg-surface-muted"><div class="h-2 rounded-full" :class="l.id ? 'bg-accent' : 'bg-tarif-interne'" :style="{ width: largeur(l.minutes, resultat.services) }" /></div></td>
                                    </tr>
                                    <tr v-if="resultat.services.length === 0"><td colspan="4" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <UiCard :titre="t('detail.parCompte')" :sous-titre="t('detail.parCompteSousTitre')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="c in resultat.comptes_dsk" :key="c.id" tabindex="0" :aria-describedby="`b-co-${c.id}`" class="group border-b border-border/60 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted" :class="c.compte_effectif ? '' : 'text-ink-muted'">
                                        <td class="py-1.5 pr-3">
                                            <UiTooltip :id="`b-co-${c.id}`" :texte="bulleCompte(c)">
                                                <span class="inline-flex items-center gap-2"><span aria-hidden="true" class="h-2.5 w-2.5 shrink-0 rounded-full" :class="c.compte_effectif ? couleurCategorie(c.categorie) : 'bg-border'" />{{ c.libelle }}<span v-if="!c.compte_effectif" class="text-xs">({{ t('detail.horsTotal') }})</span></span>
                                                <span class="block pl-[18px] text-xs text-ink-muted">{{ c.categorie_label }}</span>
                                            </UiTooltip>
                                        </td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(c.minutes) }}</td>
                                        <td class="hidden w-24 py-1.5 pl-3 sm:table-cell"><div class="h-2 w-full rounded-full bg-surface-muted"><div class="h-2 rounded-full" :class="c.compte_effectif ? couleurCategorie(c.categorie) : 'bg-border'" :style="{ width: largeur(c.minutes, resultat.comptes_dsk) }" /></div></td>
                                    </tr>
                                    <tr v-if="resultat.comptes_dsk.length === 0"><td colspan="3" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                        <UiCard :titre="t('detail.parTypePrestation')">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="(l, i) in resultat.types_prestation" :key="l.libelle ?? 'x'" tabindex="0" :aria-describedby="`b-tp-${i}`" class="group border-b border-border/60 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                                        <td class="py-1.5 pr-3 text-ink"><UiTooltip :id="`b-tp-${i}`" :texte="bulleTypePrestation(l)">{{ l.libelle ?? '—' }}</UiTooltip></td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-ink-secondary">{{ nombre(l.nb_forfaits) }}</td>
                                        <td class="py-1.5 text-right tabular-nums font-medium">{{ heures(l.minutes) }}</td>
                                        <td class="hidden w-16 py-1.5 pl-3 sm:table-cell"><div class="h-2 w-full rounded-full bg-surface-muted"><div class="h-2 rounded-full bg-accent" :style="{ width: largeur(l.minutes, resultat.types_prestation) }" /></div></td>
                                    </tr>
                                    <tr v-if="resultat.types_prestation.length === 0"><td colspan="4" class="py-3 text-center text-ink-muted">{{ t('detail.aucuneDonnee') }}</td></tr>
                                </tbody>
                            </table>
                        </UiCard>
                    </div>
                </div>

                <UiCard :titre="t('detail.indicateurs')" :sous-titre="t('detail.indicateursSousTitre')">
                    <dl class="divide-y divide-border/60 text-sm">
                        <div
                            v-for="i in indicateurs"
                            :key="i.cle"
                            tabindex="0"
                            :aria-describedby="`b-in-${i.cle}`"
                            class="group flex items-baseline justify-between gap-3 py-1.5 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted"
                            :class="{ 'font-medium text-ink': i.role === 'total' || i.role === 'resultat', 'pl-4 text-ink-secondary': i.role === 'deduction', 'text-ink-secondary': i.role === 'info' }"
                        >
                            <dt>
                                <UiTooltip :id="`b-in-${i.cle}`" :texte="i.bulle">
                                    <span class="inline-flex items-center gap-2"><span v-if="i.couleur" aria-hidden="true" class="h-2.5 w-2.5 shrink-0 rounded-full" :class="i.couleur" />{{ t(`detail.ind.${i.cle}`) }}</span>
                                </UiTooltip>
                            </dt>
                            <dd class="tabular-nums"><span v-if="i.role === 'deduction'" class="mr-1 text-ink-muted" aria-hidden="true">−</span>{{ i.valeur }}</dd>
                        </div>
                        <div tabindex="0" aria-describedby="b-in-efficacite" class="group py-2 outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                            <div class="flex items-baseline justify-between gap-3 text-base font-semibold text-ink">
                                <dt><UiTooltip id="b-in-efficacite" :texte="bulleEfficacite">{{ t('detail.ind.efficacite') }}</UiTooltip></dt>
                                <dd class="tabular-nums" :class="synthese?.objectif_atteint === true ? 'text-good' : synthese?.objectif_atteint === false ? 'text-bad' : ''">{{ pourcentage(synthese?.ratio) }}</dd>
                            </div>
                            <div class="relative mt-1.5 h-2 w-full rounded-full bg-surface-muted" aria-hidden="true">
                                <div class="h-2 rounded-full" :class="synthese?.objectif_atteint === false ? 'bg-bad' : 'bg-good'" :style="{ width: jaugeRatio }" />
                                <div class="absolute -top-1 h-4 w-0.5 bg-ink" :style="{ left: jaugeObjectif }" />
                            </div>
                        </div>
                        <div tabindex="0" aria-describedby="b-in-objectif" class="group flex items-center justify-between gap-3 py-1.5 text-ink-secondary outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                            <dt><UiTooltip id="b-in-objectif" :texte="bulleObjectif">{{ t('detail.ind.objectif') }}</UiTooltip></dt>
                            <dd class="flex items-center gap-2 tabular-nums">{{ pourcentage(synthese?.objectif, 0) }} <ObjectifBadge :atteint="synthese?.objectif_atteint" /></dd>
                        </div>
                        <div tabindex="0" aria-describedby="b-in-brute" class="group flex items-baseline justify-between gap-3 py-1.5 text-ink-secondary outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                            <dt><UiTooltip id="b-in-brute" :texte="bulleBrute">{{ t('detail.ind.brute') }}</UiTooltip></dt>
                            <dd class="tabular-nums">{{ pourcentage(synthese?.ratio_brut) }}</dd>
                        </div>
                        <div v-if="Number(synthese?.forfaits_non_valorises) > 0" tabindex="0" aria-describedby="b-in-nonValorises" class="group flex items-baseline justify-between gap-3 py-1.5 text-bad outline-none hover:bg-surface-muted focus-visible:bg-surface-muted">
                            <dt><UiTooltip id="b-in-nonValorises" :texte="t('detail.bulle.ind.nonValorises')">{{ t('detail.ind.nonValorises') }}</UiTooltip></dt>
                            <dd class="tabular-nums">{{ nombre(synthese.forfaits_non_valorises) }}</dd>
                        </div>
                    </dl>
                </UiCard>
            </div>
        </template>
    </div>
</template>
