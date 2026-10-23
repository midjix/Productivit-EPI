<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useNotificationsStore } from '@/stores/notifications';
import { date, dateHeure } from '@/utils/format';
import UiCard from '@/components/UiCard.vue';

/**
 * Cas d'utilisation « Superviser les imports », « Importer les données » (manuel)
 * et « Recalculer » : journal paginé, lancement d'un import ou d'un recalcul (file d'attente).
 */
const { t } = useI18n();
const notifications = useNotificationsStore();

const imports = ref([]);
const meta = ref(null);
const page = ref(1);
const filtres = reactive({ source: '', statut: '' });
const chargement = ref(false);
const ouvert = ref(null);

const formImport = reactive({ source: 'all', du: '', au: '' });
const formRecalcul = reactive({ du: '', au: '' });
const erreursImport = ref({});
const erreursRecalcul = ref({});
const envoi = ref(false);

const enCours = computed(() => imports.value.some((i) => i.statut === 'en_cours'));
let minuteur = null;

async function charger() {
    chargement.value = true;
    try {
        const { data } = await client.get('/admin/imports', {
            params: { page: page.value, source: filtres.source || undefined, statut: filtres.statut || undefined },
        });
        imports.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        chargement.value = false;
    }
}

function nettoyer(form) {
    return Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
}

async function lancerImport() {
    erreursImport.value = {};
    envoi.value = true;
    try {
        const { data } = await client.post('/admin/imports', nettoyer(formImport));
        notifications.succes(`${data.message} (${t(`admin.imports.sources.${data.source}`)}, ${date(data.du)} → ${date(data.au)})`);
        page.value = 1;
        await charger();
    } catch (e) {
        erreursImport.value = e.response?.data?.errors ?? {};
        if (!e.response?.data?.errors) notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        envoi.value = false;
    }
}

async function lancerRecalcul() {
    erreursRecalcul.value = {};
    envoi.value = true;
    try {
        const { data } = await client.post('/admin/recalculs', nettoyer(formRecalcul));
        notifications.succes(data.du ? `${data.message} (${date(data.du)} → ${date(data.au)})` : data.message);
    } catch (e) {
        erreursRecalcul.value = e.response?.data?.errors ?? {};
        if (!e.response?.data?.errors) notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        envoi.value = false;
    }
}

function changerPage(p) {
    page.value = p;
    charger();
}

function appliquerFiltres() {
    page.value = 1;
    charger();
}

onMounted(() => {
    charger();
    // Rafraîchissement automatique tant qu'un import est en cours (file d'attente).
    minuteur = setInterval(() => {
        if (enCours.value) charger();
    }, 15000);
});
onBeforeUnmount(() => clearInterval(minuteur));

const classeStatut = {
    en_cours: 'bg-accent/15 text-accent-strong',
    succes: 'bg-good/15 text-good',
    echec: 'bg-bad/15 text-bad',
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-2">
            <UiCard :titre="t('admin.imports.lancer')" :sous-titre="t('admin.imports.lancerSousTitre')">
                <form class="grid gap-3 sm:grid-cols-[minmax(9rem,1fr)_1fr_1fr_auto] sm:items-end" @submit.prevent="lancerImport">
                    <div>
                        <label for="source" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.imports.source') }}</label>
                        <select id="source" v-model="formImport.source" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink">
                            <option v-for="s in ['all', 'dsk', 'epilogg']" :key="s" :value="s">{{ t(`admin.imports.sources.${s}`) }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="import-du" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.du') }}</label>
                        <input id="import-du" v-model="formImport.du" type="date" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreursImport.du" class="mt-1 text-xs text-bad">{{ erreursImport.du[0] }}</p>
                    </div>
                    <div>
                        <label for="import-au" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.au') }}</label>
                        <input id="import-au" v-model="formImport.au" type="date" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreursImport.au" class="mt-1 text-xs text-bad">{{ erreursImport.au[0] }}</p>
                    </div>
                    <button type="submit" :disabled="envoi" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white hover:bg-accent-strong disabled:opacity-60">{{ t('admin.imports.boutonImport') }}</button>
                </form>
                <p class="mt-2 text-xs text-ink-muted">{{ t('admin.imports.aide') }}</p>
            </UiCard>

            <UiCard :titre="t('admin.imports.recalculer')" :sous-titre="t('admin.imports.recalculerSousTitre')">
                <form class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="lancerRecalcul">
                    <div>
                        <label for="recalc-du" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.du') }}</label>
                        <input id="recalc-du" v-model="formRecalcul.du" type="date" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreursRecalcul.du" class="mt-1 text-xs text-bad">{{ erreursRecalcul.du[0] }}</p>
                    </div>
                    <div>
                        <label for="recalc-au" class="mb-1 block text-xs uppercase tracking-wide text-ink-muted">{{ t('admin.au') }}</label>
                        <input id="recalc-au" v-model="formRecalcul.au" type="date" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                        <p v-if="erreursRecalcul.au" class="mt-1 text-xs text-bad">{{ erreursRecalcul.au[0] }}</p>
                    </div>
                    <button type="submit" :disabled="envoi" class="rounded-full border border-accent px-5 py-2 text-sm font-semibold text-accent-strong hover:bg-accent/10 disabled:opacity-60">{{ t('admin.imports.boutonRecalcul') }}</button>
                </form>
                <p class="mt-2 text-xs text-ink-muted">{{ t('admin.imports.aideRecalcul') }}</p>
            </UiCard>
        </div>

        <UiCard :titre="t('admin.imports.journal')" :sous-titre="t('admin.imports.journalSousTitre')">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <select v-model="filtres.source" class="rounded-md border border-border bg-surface px-2 py-1.5 text-ink" :aria-label="t('admin.imports.source')" @change="appliquerFiltres">
                    <option value="">{{ t('admin.imports.toutesSources') }}</option>
                    <option value="dsk">DSK</option>
                    <option value="epilogg">EpiLogg</option>
                </select>
                <select v-model="filtres.statut" class="rounded-md border border-border bg-surface px-2 py-1.5 text-ink" :aria-label="t('admin.imports.statut')" @change="appliquerFiltres">
                    <option value="">{{ t('admin.imports.tousStatuts') }}</option>
                    <option v-for="s in ['en_cours', 'succes', 'echec']" :key="s" :value="s">{{ t(`admin.imports.statuts.${s}`) }}</option>
                </select>
                <button type="button" class="rounded-md border border-border px-3 py-1.5 hover:bg-surface-muted" @click="charger">{{ t('admin.rafraichir') }}</button>
                <span v-if="enCours" class="text-xs text-accent-strong">{{ t('admin.imports.enCoursInfo') }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">{{ t('admin.imports.demarre') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.imports.source') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.imports.statut') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.imports.periode') }}</th>
                            <th class="py-2 pr-3 text-right font-medium">{{ t('admin.imports.lignes') }}</th>
                            <th class="py-2 pr-3 text-right font-medium">{{ t('admin.imports.duree') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.imports.declencheur') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="i in imports" :key="i.id">
                            <tr class="border-b border-border/60 hover:bg-surface-muted">
                                <td class="py-2 pr-3 whitespace-nowrap tabular-nums">{{ dateHeure(i.demarre_le) }}</td>
                                <td class="py-2 pr-3 uppercase">{{ i.source }}</td>
                                <td class="py-2 pr-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="classeStatut[i.statut]">{{ t(`admin.imports.statuts.${i.statut}`) }}</span></td>
                                <td class="py-2 pr-3 whitespace-nowrap">{{ date(i.periode_debut) }} → {{ date(i.periode_fin) }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ i.nb_lignes }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ i.duree_secondes === null ? '—' : `${i.duree_secondes} s` }}</td>
                                <td class="py-2 pr-3">{{ i.declencheur }}</td>
                                <td class="py-2 text-right">
                                    <button v-if="i.resume || i.erreur" type="button" class="text-xs text-accent-strong hover:underline" @click="ouvert = ouvert === i.id ? null : i.id">
                                        {{ ouvert === i.id ? t('admin.masquer') : t('admin.details') }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="ouvert === i.id" class="border-b border-border/60 bg-surface-muted">
                                <td colspan="8" class="px-3 py-3">
                                    <p v-if="i.erreur" class="text-sm text-bad">{{ i.erreur }}</p>
                                    <dl v-else class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                        <template v-for="(valeur, cle) in i.resume" :key="cle">
                                            <dt class="text-ink-muted">{{ t(`admin.imports.resume.${cle}`, cle) }}</dt>
                                            <dd class="text-ink">{{ Array.isArray(valeur) ? (valeur.length ? valeur.join(', ') : '—') : valeur }}</dd>
                                        </template>
                                    </dl>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!chargement && imports.length === 0">
                            <td colspan="8" class="py-4 text-center text-ink-muted">{{ t('admin.aucunResultat') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-between text-sm">
                <button type="button" class="rounded-md border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page <= 1" @click="changerPage(page - 1)">‹</button>
                <span class="text-ink-muted">{{ t('admin.page', { page: meta.current_page, total: meta.last_page }) }}</span>
                <button type="button" class="rounded-md border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page >= meta.last_page" @click="changerPage(page + 1)">›</button>
            </div>
        </UiCard>
    </div>
</template>
