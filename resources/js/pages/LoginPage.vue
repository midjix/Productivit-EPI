<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { messageErreur } from '@/api/client';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

/**
 * Page de connexion : même organisation que DSKnet (carte centrée, logo en tête,
 * fond dégradé avec pans diagonaux), aux couleurs de la Fondation.
 * L'identifiant est l'adresse email professionnelle (clé d'appariement des sources).
 */
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const { t } = useI18n();

const LOGO = '/images/logo-epi.png';
const email = ref('');
const password = ref('');
const afficher = ref(false);
const erreur = ref('');

async function seConnecter() {
    erreur.value = '';
    try {
        await auth.connexion(email.value.trim(), password.value);
        const cible = typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'tableau-de-bord' };
        router.push(cible);
    } catch (e) {
        erreur.value = e.response?.status === 429
            ? t('connexion.tropDeTentatives')
            : messageErreur(e, t('connexion.impossible'), t('erreurs.reseau'));
        password.value = '';
    }
}
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[linear-gradient(135deg,#f39200_0%,#c96e00_100%)] px-4 py-10">
        <!-- Pans diagonaux décoratifs (clin d'œil à l'écran de connexion DSKnet) -->
        <div class="pointer-events-none absolute -left-40 top-0 h-[140%] w-[55%] -rotate-12 bg-white/10" aria-hidden="true" />
        <div class="pointer-events-none absolute -right-52 bottom-0 h-[140%] w-[45%] -rotate-12 bg-black/10" aria-hidden="true" />

        <div class="absolute right-4 top-4 rounded-md bg-white/90 px-1 text-ink">
            <LanguageSwitcher />
        </div>

        <div class="relative w-full max-w-sm overflow-hidden rounded-xl bg-surface-raised shadow-2xl">
            <div class="flex flex-col items-center bg-white px-6 pb-6 pt-7">
                <img :src="LOGO" alt="Fondation EPI" class="h-24 w-auto" />
            </div>

            <form class="space-y-4 px-6 pb-6 pt-5" novalidate @submit.prevent="seConnecter">
                <div>
                    <label for="email" class="mb-1 block text-sm text-ink-secondary">{{ t('connexion.identifiant') }}</label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        name="email"
                        autocomplete="username"
                        required
                        class="w-full rounded-md border border-border bg-surface px-3 py-2.5 text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
                        :placeholder="t('connexion.placeholder')"
                    />
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-ink-secondary">{{ t('connexion.motDePasse') }}</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="password"
                            :type="afficher ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-md border border-border bg-surface px-3 py-2.5 pr-11 text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 px-3 text-ink-muted hover:text-accent"
                            :aria-label="afficher ? t('connexion.masquer') : t('connexion.afficher')"
                            @click="afficher = !afficher"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <p v-if="erreur" role="alert" class="rounded-md border border-bad/40 bg-bad/10 px-3 py-2 text-sm text-bad">
                    {{ erreur }}
                </p>

                <button
                    type="submit"
                    :disabled="auth.chargement"
                    class="w-full rounded-full bg-accent px-4 py-3 font-semibold uppercase tracking-wide text-white shadow-md transition hover:bg-accent-strong disabled:cursor-wait disabled:opacity-70"
                >
                    {{ auth.chargement ? t('connexion.enCours') : t('connexion.bouton') }}
                </button>

                <p class="text-center text-sm text-ink-muted">{{ t('connexion.oubli') }}</p>
            </form>

            <div class="flex items-center justify-between bg-epi-anthracite px-6 py-3 text-xs text-white/90">
                <span>{{ t('app.fondation') }}</span>
                <span>{{ t('app.mvp') }}</span>
            </div>
        </div>
    </div>
</template>
