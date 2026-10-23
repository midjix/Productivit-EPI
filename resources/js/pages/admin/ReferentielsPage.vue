<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useNotificationsStore } from '@/stores/notifications';
import { date } from '@/utils/format';
import UiCard from '@/components/UiCard.vue';

/**
 * Cas d'utilisation « Qualifier les référentiels importés » : compteurs DSK (catégorie,
 * entre dans le total ?) et types de suivi EpiLogg (tarif, service). Chaque enregistrement
 * déclenche le recalcul des mois concernés côté serveur.
 */
const { t } = useI18n();
const notifications = useNotificationsStore();

const CATEGORIES = ['normal', 'conge', 'maladie', 'formation', 'supervision', 'deplacement', 'autre'];

const comptes = ref([]);
const types = ref([]);
const tarifs = ref([]);
const services = ref([]);
const seulementNonQualifies = ref(false);
const enregistrement = ref(null);

const typesAffiches = computed(() => (seulementNonQualifies.value ? types.value.filter((x) => !x.qualifie) : types.value));
const nbNonQualifies = computed(() => types.value.filter((x) => !x.qualifie).length);

async function charger() {
    try {
        const [c, ty, ta, se] = await Promise.all([
            client.get('/admin/comptes-dsk'),
            client.get('/admin/types-suivi'),
            client.get('/admin/tarifs'),
            client.get('/admin/services'),
        ]);
        comptes.value = c.data.map((x) => ({ ...x, brouillon: { categorie: x.categorie, compte_effectif: x.compte_effectif, actif: x.actif } }));
        types.value = ty.data.map((x) => ({ ...x, brouillon: { tarif_id: x.tarif?.id ?? '', service_id: x.service?.id ?? '', actif: x.actif } }));
        tarifs.value = ta.data;
        services.value = se.data;
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    }
}

function messageRecalcul(recalcul) {
    return recalcul ? t('admin.referentiels.recalculPlanifie', { du: date(recalcul.du), au: date(recalcul.au) }) : t('admin.referentiels.sansRecalcul');
}

async function enregistrerCompte(c) {
    enregistrement.value = `c${c.id}`;
    try {
        const { data } = await client.patch(`/admin/comptes-dsk/${c.id}`, c.brouillon);
        Object.assign(c, data.compte);
        notifications.succes(`${c.libelle} : ${messageRecalcul(data.recalcul)}`);
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        enregistrement.value = null;
    }
}

async function enregistrerType(x) {
    if (x.brouillon.tarif_id === '') {
        notifications.erreur(t('admin.referentiels.tarifRequis'));
        return;
    }
    enregistrement.value = `t${x.id}`;
    try {
        const { data } = await client.patch(`/admin/types-suivi/${x.id}`, {
            tarif_id: Number(x.brouillon.tarif_id),
            service_id: x.brouillon.service_id === '' ? null : Number(x.brouillon.service_id),
            actif: x.brouillon.actif,
        });
        Object.assign(x, data.type);
        notifications.succes(`${x.libelle} : ${messageRecalcul(data.recalcul)}`);
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        enregistrement.value = null;
    }
}

onMounted(charger);
</script>

<template>
    <div class="space-y-4">
        <UiCard :titre="t('admin.referentiels.comptes')" :sous-titre="t('admin.referentiels.comptesSousTitre')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">N°</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.libelle') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.categorie') }}</th>
                            <th class="py-2 pr-3 text-center font-medium">{{ t('admin.referentiels.dansTotal') }}</th>
                            <th class="py-2 pr-3 text-center font-medium">{{ t('admin.actif') }}</th>
                            <th class="py-2 pr-3 text-right font-medium">{{ t('admin.referentiels.lignes') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in comptes" :key="c.id" class="border-b border-border/60 hover:bg-surface-muted">
                            <td class="py-2 pr-3 tabular-nums text-ink-muted">{{ c.no_account }}</td>
                            <td class="py-2 pr-3 font-medium text-ink">{{ c.libelle }}</td>
                            <td class="py-2 pr-3">
                                <select v-model="c.brouillon.categorie" class="rounded-md border border-border bg-surface px-2 py-1 text-ink" :aria-label="t('admin.referentiels.categorie')">
                                    <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ t(`admin.categories.${cat}`) }}</option>
                                </select>
                            </td>
                            <td class="py-2 pr-3 text-center"><input v-model="c.brouillon.compte_effectif" type="checkbox" class="h-4 w-4 accent-accent" :aria-label="t('admin.referentiels.dansTotal')" /></td>
                            <td class="py-2 pr-3 text-center"><input v-model="c.brouillon.actif" type="checkbox" class="h-4 w-4 accent-accent" :aria-label="t('admin.actif')" /></td>
                            <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ c.nb_lignes ?? 0 }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white hover:bg-accent-strong disabled:opacity-60" :disabled="enregistrement === `c${c.id}`" @click="enregistrerCompte(c)">{{ t('admin.enregistrer') }}</button>
                            </td>
                        </tr>
                        <tr v-if="comptes.length === 0"><td colspan="7" class="py-4 text-center text-ink-muted">{{ t('admin.aucunResultat') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <UiCard :titre="t('admin.referentiels.types')" :sous-titre="t('admin.referentiels.typesSousTitre')">
            <label class="mb-3 inline-flex items-center gap-2 text-sm">
                <input v-model="seulementNonQualifies" type="checkbox" class="h-4 w-4 accent-accent" />
                {{ t('admin.referentiels.seulementNonQualifies', { n: nbNonQualifies }) }}
            </label>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.libelle') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.tarif') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.service') }}</th>
                            <th class="py-2 pr-3 text-center font-medium">{{ t('admin.actif') }}</th>
                            <th class="py-2 pr-3 text-right font-medium">{{ t('admin.referentiels.prestations') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="x in typesAffiches" :key="x.id" class="border-b border-border/60 hover:bg-surface-muted">
                            <td class="py-2 pr-3 font-medium text-ink">
                                {{ x.libelle }}
                                <span v-if="!x.qualifie" class="ml-2 rounded-full bg-bad/15 px-2 py-0.5 text-xs font-medium text-bad">{{ t('admin.referentiels.nonQualifie') }}</span>
                            </td>
                            <td class="py-2 pr-3">
                                <select v-model="x.brouillon.tarif_id" class="rounded-md border border-border bg-surface px-2 py-1 text-ink" :aria-label="t('admin.referentiels.tarif')">
                                    <option value="">—</option>
                                    <option v-for="ta in tarifs" :key="ta.id" :value="ta.id">{{ ta.code }} — {{ ta.libelle }}</option>
                                </select>
                            </td>
                            <td class="py-2 pr-3">
                                <select v-model="x.brouillon.service_id" class="rounded-md border border-border bg-surface px-2 py-1 text-ink" :aria-label="t('admin.referentiels.service')">
                                    <option value="">{{ t('admin.referentiels.aucunService') }}</option>
                                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.nom }}</option>
                                </select>
                            </td>
                            <td class="py-2 pr-3 text-center"><input v-model="x.brouillon.actif" type="checkbox" class="h-4 w-4 accent-accent" :aria-label="t('admin.actif')" /></td>
                            <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ x.nb_prestations ?? 0 }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white hover:bg-accent-strong disabled:opacity-60" :disabled="enregistrement === `t${x.id}`" @click="enregistrerType(x)">{{ t('admin.enregistrer') }}</button>
                            </td>
                        </tr>
                        <tr v-if="typesAffiches.length === 0"><td colspan="6" class="py-4 text-center text-ink-muted">{{ t('admin.aucunResultat') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </div>
</template>
