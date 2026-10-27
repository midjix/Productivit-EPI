<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useNotificationsStore } from '@/stores/notifications';
import { date } from '@/utils/format';
import UiCard from '@/components/UiCard.vue';
import UiLoading from '@/components/UiLoading.vue';
import UiModal from '@/components/UiModal.vue';
import UiSpinner from '@/components/UiSpinner.vue';

/**
 * Cas d'utilisation « Gérer la matrice de conversion » : historique des barèmes par tarif
 * et création d'un nouveau barème (clôture de l'ancien + recalcul, côté serveur).
 */
const { t } = useI18n();
const notifications = useNotificationsStore();

const tarifs = ref([]);
const chargement = ref(true);
const tarifEdite = ref(null);
const form = reactive({ minutes: '', date_debut: '', commentaire: '' });
const erreurs = ref({});
const envoi = ref(false);

async function charger() {
    try {
        const { data } = await client.get('/admin/tarifs');
        tarifs.value = data;
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        chargement.value = false;
    }
}

function ouvrir(tarif) {
    tarifEdite.value = tarif;
    Object.assign(form, { minutes: tarif.minutes_en_vigueur ?? '', date_debut: '', commentaire: '' });
    erreurs.value = {};
}

async function enregistrer() {
    erreurs.value = {};
    envoi.value = true;
    try {
        await client.post(`/admin/tarifs/${tarifEdite.value.id}/regles`, {
            minutes: Number(form.minutes),
            date_debut: form.date_debut,
            commentaire: form.commentaire || null,
        });
        notifications.succes(t('admin.matrice.enregistre', { code: tarifEdite.value.code, minutes: form.minutes, date: date(form.date_debut) }));
        tarifEdite.value = null;
        chargement.value = true; // la carte d'attente réapparaît le temps de relire l'historique recalculé
        await charger();
    } catch (e) {
        erreurs.value = e.response?.data?.errors ?? {};
        if (!e.response?.data?.errors) notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        envoi.value = false;
    }
}

onMounted(charger);
</script>

<template>
    <div class="space-y-4">
        <UiCard :titre="t('admin.matrice.titre')" :sous-titre="t('admin.matrice.sousTitre')">
            <p class="text-sm text-ink-secondary">{{ t('admin.matrice.explication') }}</p>
        </UiCard>

        <UiLoading v-if="chargement" />

        <UiCard v-for="ta in tarifs" :key="ta.id" :titre="`${ta.code} — ${ta.libelle}`" :sous-titre="ta.facturable ? t('admin.matrice.facturable') : t('admin.matrice.nonFacturable')">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm">
                    <span class="text-ink-muted">{{ t('admin.matrice.enVigueur') }} :</span>
                    <strong class="text-ink">{{ ta.minutes_en_vigueur ?? '—' }} min</strong>
                    <span class="ml-3 text-ink-muted">{{ t('admin.matrice.nbTypes', { n: ta.nb_types_suivi ?? 0 }) }}</span>
                </p>
                <button type="button" class="rounded-full bg-accent px-4 py-1.5 text-sm font-semibold text-white hover:bg-accent-strong" @click="ouvrir(ta)">{{ t('admin.matrice.nouveau') }}</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">{{ t('admin.matrice.minutes') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.matrice.debut') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.matrice.fin') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.matrice.commentaire') }}</th>
                            <th class="py-2 font-medium">{{ t('admin.matrice.auteur') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in ta.regles" :key="r.id" class="border-b border-border/60" :class="r.en_vigueur ? 'font-medium text-ink' : 'text-ink-secondary'">
                            <td class="py-2 pr-3 tabular-nums">{{ r.minutes }} min</td>
                            <td class="py-2 pr-3">{{ date(r.date_debut_validite) }}</td>
                            <td class="py-2 pr-3">{{ r.date_fin_validite ? date(r.date_fin_validite) : t('admin.matrice.ouverte') }}</td>
                            <td class="py-2 pr-3">{{ r.commentaire ?? '—' }}</td>
                            <td class="py-2">{{ r.auteur ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <UiModal v-if="tarifEdite" :titre="t('admin.matrice.nouveauPour', { code: tarifEdite.code })" @fermer="tarifEdite = null">
            <form class="space-y-4" novalidate @submit.prevent="enregistrer">
                <div>
                    <label for="minutes" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.matrice.minutesParForfait') }}</label>
                    <input id="minutes" v-model="form.minutes" type="number" min="1" max="1440" required class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                    <p v-if="erreurs.minutes" class="mt-1 text-sm text-bad">{{ erreurs.minutes[0] }}</p>
                </div>
                <div>
                    <label for="date_debut" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.matrice.dateApplication') }}</label>
                    <input id="date_debut" v-model="form.date_debut" type="date" required class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                    <p v-if="erreurs.date_debut" class="mt-1 text-sm text-bad">{{ erreurs.date_debut[0] }}</p>
                </div>
                <div>
                    <label for="commentaire" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.matrice.commentaire') }}</label>
                    <input id="commentaire" v-model="form.commentaire" type="text" maxlength="255" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                </div>
                <p class="text-xs text-ink-muted">{{ t('admin.matrice.avertissement') }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-full border border-border px-4 py-2 text-sm" @click="tarifEdite = null">{{ t('admin.annuler') }}</button>
                    <button type="submit" :disabled="envoi" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white hover:bg-accent-strong disabled:opacity-60">
                        <UiSpinner v-if="envoi" taille="h-4 w-4" couleur="text-white" />{{ t('admin.enregistrer') }}
                    </button>
                </div>
            </form>
        </UiModal>
    </div>
</template>
