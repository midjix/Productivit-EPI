<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import client, { messageErreur } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { verifier } from '@/utils/motDePasse';
import UiBusy from '@/components/UiBusy.vue';
import UiCard from '@/components/UiCard.vue';
import UiModal from '@/components/UiModal.vue';
import UiSpinner from '@/components/UiSpinner.vue';
import PasswordField from '@/components/PasswordField.vue';
import PasswordRules from '@/components/PasswordRules.vue';

/**
 * Cas d'utilisation « Gérer l'organigramme et les affectations » : services (libellé, activité),
 * utilisateurs (rôle, service, activité, email d'appariement) et mot de passe initial.
 */
const { t } = useI18n();
const auth = useAuthStore();
const notifications = useNotificationsStore();

const ROLES = ['salarie', 'coordinateur', 'administrateur'];

const services = ref([]);
const utilisateurs = ref([]);
const meta = ref(null);
const page = ref(1);
const filtres = reactive({ q: '', service_id: '', role: '', actif: '' });
const enregistrement = ref(null);
const chargementServices = ref(true);
const chargementUtilisateurs = ref(true);

const utilisateurEdite = ref(null);
const formUtilisateur = reactive({ role: '', service_id: '', actif: true, email: '' });
const motDePassePour = ref(null);
const formMdp = reactive({ password: '', password_confirmation: '' });
const erreurs = ref({});
const envoi = ref(false);
const reglesMdp = computed(() => verifier(formMdp.password, { confirmation: formMdp.password_confirmation }));

async function chargerServices() {
    try {
        const { data } = await client.get('/admin/services');
        services.value = data.map((s) => ({ ...s, brouillon: { nom: s.nom, actif: s.actif } }));
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        chargementServices.value = false;
    }
}

async function chargerUtilisateurs() {
    chargementUtilisateurs.value = true;
    try {
        const params = { page: page.value };
        for (const [k, v] of Object.entries(filtres)) if (v !== '') params[k] = v;
        const { data } = await client.get('/admin/utilisateurs', { params });
        utilisateurs.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        chargementUtilisateurs.value = false;
    }
}

function appliquerFiltres() {
    page.value = 1;
    chargerUtilisateurs();
}

async function enregistrerService(s) {
    enregistrement.value = s.id;
    try {
        const { data } = await client.patch(`/admin/services/${s.id}`, s.brouillon);
        Object.assign(s, data);
        notifications.succes(t('admin.organigramme.serviceEnregistre', { nom: s.nom }));
    } catch (e) {
        notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        enregistrement.value = null;
    }
}

function ouvrirUtilisateur(u) {
    utilisateurEdite.value = u;
    Object.assign(formUtilisateur, { role: u.role, service_id: u.service?.id ?? '', actif: u.actif, email: u.email });
    erreurs.value = {};
}

async function enregistrerUtilisateur() {
    erreurs.value = {};
    envoi.value = true;
    try {
        const { data } = await client.patch(`/admin/utilisateurs/${utilisateurEdite.value.id}`, {
            role: formUtilisateur.role,
            service_id: formUtilisateur.service_id === '' ? null : Number(formUtilisateur.service_id),
            actif: formUtilisateur.actif,
            email: formUtilisateur.email,
        });
        Object.assign(utilisateurEdite.value, data);
        notifications.succes(t('admin.organigramme.utilisateurEnregistre', { nom: data.name }));
        if (data.id === auth.utilisateur?.id) await auth.chargerProfil();
        utilisateurEdite.value = null;
    } catch (e) {
        erreurs.value = e.response?.data?.errors ?? {};
        if (!e.response?.data?.errors) notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        envoi.value = false;
    }
}

function ouvrirMotDePasse(u) {
    motDePassePour.value = u;
    Object.assign(formMdp, { password: '', password_confirmation: '' });
    erreurs.value = {};
}

async function enregistrerMotDePasse() {
    erreurs.value = {};
    envoi.value = true;
    try {
        await client.put(`/admin/utilisateurs/${motDePassePour.value.id}/mot-de-passe`, formMdp);
        notifications.succes(t('admin.organigramme.mdpDefini', { nom: motDePassePour.value.name }));
        motDePassePour.value = null;
    } catch (e) {
        erreurs.value = e.response?.data?.errors ?? {};
        if (!e.response?.data?.errors) notifications.erreur(messageErreur(e, t('erreurs.generique'), t('erreurs.reseau')));
    } finally {
        envoi.value = false;
    }
}

onMounted(() => {
    chargerServices();
    chargerUtilisateurs();
});
</script>

<template>
    <div class="space-y-4">
        <UiCard :titre="t('admin.organigramme.services')" :sous-titre="t('admin.organigramme.servicesSousTitre')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.code') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.nom') }}</th>
                            <th class="py-2 pr-3 text-center font-medium">{{ t('admin.actif') }}</th>
                            <th class="py-2 pr-3 text-right font-medium">{{ t('admin.organigramme.nbUtilisateurs') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="chargementServices"><td colspan="5" class="py-4 text-center text-ink-muted"><UiSpinner :libelle="t('dashboard.chargement')" /></td></tr>
                        <tr v-for="s in services" :key="s.id" class="border-b border-border/60 hover:bg-surface-muted">
                            <td class="py-2 pr-3 font-mono text-xs text-ink-muted">{{ s.code }}</td>
                            <td class="py-2 pr-3"><input v-model="s.brouillon.nom" type="text" class="w-full rounded-md border border-border bg-surface px-2 py-1 text-ink" :aria-label="t('admin.organigramme.nom')" /></td>
                            <td class="py-2 pr-3 text-center"><input v-model="s.brouillon.actif" type="checkbox" class="h-4 w-4 accent-accent" :aria-label="t('admin.actif')" /></td>
                            <td class="py-2 pr-3 text-right tabular-nums text-ink-secondary">{{ s.nb_utilisateurs }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="inline-flex items-center gap-1 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white hover:bg-accent-strong disabled:opacity-60" :disabled="enregistrement === s.id" @click="enregistrerService(s)">
                                    <UiSpinner v-if="enregistrement === s.id" taille="h-3 w-3" couleur="text-white" />{{ t('admin.enregistrer') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <UiCard :titre="t('admin.organigramme.utilisateurs')" :sous-titre="t('admin.organigramme.utilisateursSousTitre')">
            <form class="mb-3 flex flex-wrap items-center gap-2 text-sm" @submit.prevent="appliquerFiltres">
                <input v-model="filtres.q" type="search" :placeholder="t('admin.organigramme.rechercher')" class="rounded-md border border-border bg-surface px-3 py-1.5 text-ink" />
                <select v-model="filtres.service_id" class="rounded-md border border-border bg-surface px-2 py-1.5 text-ink" @change="appliquerFiltres">
                    <option value="">{{ t('admin.organigramme.tousServices') }}</option>
                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.nom }}</option>
                </select>
                <select v-model="filtres.role" class="rounded-md border border-border bg-surface px-2 py-1.5 text-ink" @change="appliquerFiltres">
                    <option value="">{{ t('admin.organigramme.tousRoles') }}</option>
                    <option v-for="r in ROLES" :key="r" :value="r">{{ t(`roles.${r}`) }}</option>
                </select>
                <select v-model="filtres.actif" class="rounded-md border border-border bg-surface px-2 py-1.5 text-ink" @change="appliquerFiltres">
                    <option value="">{{ t('admin.organigramme.tousEtats') }}</option>
                    <option value="1">{{ t('admin.organigramme.actifs') }}</option>
                    <option value="0">{{ t('admin.organigramme.inactifs') }}</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-1.5 hover:bg-surface-muted" :disabled="chargementUtilisateurs">
                    <UiSpinner v-if="chargementUtilisateurs" taille="h-3.5 w-3.5" />{{ t('admin.organigramme.filtrer') }}
                </button>
            </form>

            <UiBusy :actif="chargementUtilisateurs && utilisateurs.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-ink-muted">
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.nom') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.email') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.role') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.referentiels.service') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.etat') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ t('admin.organigramme.appariement') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in utilisateurs" :key="u.id" class="border-b border-border/60 hover:bg-surface-muted">
                            <td class="py-2 pr-3 font-medium text-ink">{{ u.name }}</td>
                            <td class="py-2 pr-3 text-ink-secondary">{{ u.email }}</td>
                            <td class="py-2 pr-3">{{ t(`roles.${u.role}`) }}</td>
                            <td class="py-2 pr-3">{{ u.service?.nom ?? '—' }}</td>
                            <td class="py-2 pr-3">
                                <span v-if="u.peut_se_connecter" class="rounded-full bg-good/15 px-2 py-0.5 text-xs font-medium text-good">{{ t('admin.organigramme.actif') }}</span>
                                <span v-else class="rounded-full bg-bad/15 px-2 py-0.5 text-xs font-medium text-bad">{{ u.date_sortie ? t('admin.organigramme.parti') : t('admin.organigramme.inactif') }}</span>
                            </td>
                            <td class="py-2 pr-3 text-xs">
                                <span :class="u.apparie_dsk ? 'text-good' : 'text-ink-muted'">DSK {{ u.apparie_dsk ? '✓' : '—' }}</span>
                                <span class="ml-2" :class="u.apparie_epilogg ? 'text-good' : 'text-bad'">EpiLogg {{ u.apparie_epilogg ? '✓' : '✕' }}</span>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <button type="button" class="text-xs text-accent-strong hover:underline" @click="ouvrirUtilisateur(u)">{{ t('admin.modifier') }}</button>
                                <button type="button" class="ml-3 text-xs text-accent-strong hover:underline" @click="ouvrirMotDePasse(u)">{{ t('admin.organigramme.motDePasse') }}</button>
                            </td>
                        </tr>
                        <tr v-if="chargementUtilisateurs && utilisateurs.length === 0"><td colspan="7" class="py-4 text-center text-ink-muted"><UiSpinner :libelle="t('dashboard.chargement')" /></td></tr>
                        <tr v-else-if="utilisateurs.length === 0"><td colspan="7" class="py-4 text-center text-ink-muted">{{ t('admin.aucunResultat') }}</td></tr>
                    </tbody>
                </table>
            </UiBusy>

            <div v-if="meta && meta.last_page > 1" class="mt-3 flex items-center justify-between text-sm">
                <button type="button" class="rounded-md border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page <= 1" @click="page--; chargerUtilisateurs()">‹</button>
                <span class="text-ink-muted">{{ t('admin.page', { page: meta.current_page, total: meta.last_page }) }}</span>
                <button type="button" class="rounded-md border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++; chargerUtilisateurs()">›</button>
            </div>
        </UiCard>

        <UiModal v-if="utilisateurEdite" :titre="utilisateurEdite.name" @fermer="utilisateurEdite = null">
            <form class="space-y-4" novalidate @submit.prevent="enregistrerUtilisateur">
                <div>
                    <label for="u-email" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.organigramme.emailAide') }}</label>
                    <input id="u-email" v-model="formUtilisateur.email" type="email" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink" />
                    <p v-if="erreurs.email" class="mt-1 text-sm text-bad">{{ erreurs.email[0] }}</p>
                </div>
                <div>
                    <label for="u-role" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.organigramme.role') }}</label>
                    <select id="u-role" v-model="formUtilisateur.role" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink">
                        <option v-for="r in ROLES" :key="r" :value="r">{{ t(`roles.${r}`) }}</option>
                    </select>
                    <p v-if="erreurs.role" class="mt-1 text-sm text-bad">{{ erreurs.role[0] }}</p>
                </div>
                <div>
                    <label for="u-service" class="mb-1 block text-sm text-ink-secondary">{{ t('admin.referentiels.service') }}</label>
                    <select id="u-service" v-model="formUtilisateur.service_id" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-ink">
                        <option value="">{{ t('admin.referentiels.aucunService') }}</option>
                        <option v-for="s in services" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <p v-if="erreurs.service_id" class="mt-1 text-sm text-bad">{{ erreurs.service_id[0] }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="formUtilisateur.actif" type="checkbox" class="h-4 w-4 accent-accent" />
                    {{ t('admin.organigramme.compteActif') }}
                </label>
                <p v-if="erreurs.actif" class="text-sm text-bad">{{ erreurs.actif[0] }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-full border border-border px-4 py-2 text-sm" @click="utilisateurEdite = null">{{ t('admin.annuler') }}</button>
                    <button type="submit" :disabled="envoi" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white hover:bg-accent-strong disabled:opacity-60">
                        <UiSpinner v-if="envoi" taille="h-4 w-4" couleur="text-white" />{{ t('admin.enregistrer') }}
                    </button>
                </div>
            </form>
        </UiModal>

        <UiModal v-if="motDePassePour" :titre="t('admin.organigramme.mdpPour', { nom: motDePassePour.name })" @fermer="motDePassePour = null">
            <form class="space-y-4" novalidate @submit.prevent="enregistrerMotDePasse">
                <PasswordField id="m-password" v-model="formMdp.password" :label="t('mdp.nouveau')" :erreurs="erreurs.password ?? []" />
                <PasswordRules :regles="reglesMdp" />
                <PasswordField id="m-confirm" v-model="formMdp.password_confirmation" :label="t('mdp.confirmer')" :erreurs="erreurs.password_confirmation ?? []" />
                <p class="text-xs text-ink-muted">{{ t('admin.organigramme.mdpAide') }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-full border border-border px-4 py-2 text-sm" @click="motDePassePour = null">{{ t('admin.annuler') }}</button>
                    <button type="submit" :disabled="envoi" class="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white hover:bg-accent-strong disabled:opacity-60">
                        <UiSpinner v-if="envoi" taille="h-4 w-4" couleur="text-white" />{{ t('admin.enregistrer') }}
                    </button>
                </div>
            </form>
        </UiModal>
    </div>
</template>
