<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SearchBar from '@/components/SearchBar.vue';

defineProps({ ouvert: { type: Boolean, default: false } });
defineEmits(['fermer']);

const auth = useAuthStore();
const { t } = useI18n();

/** Entrées du menu, filtrées par rôle (héritage : un administrateur voit tout). */
const entrees = computed(() => [
    { nom: 'tableau-de-bord', libelle: t('nav.tableauDeBord'), icone: 'home' },
    { nom: 'service', libelle: t('nav.monService'), icone: 'users', role: 'coordinateur' },
    { nom: 'globales', libelle: t('nav.vueGlobale'), icone: 'globe', role: 'administrateur' },
    { nom: 'administration', libelle: t('nav.administration'), icone: 'cog', role: 'administrateur' },
    { nom: 'mot-de-passe', libelle: t('nav.motDePasse'), icone: 'key' },
].filter((e) => !e.role || auth.aRole(e.role)));

const ICONES = {
    home: 'M3 11.5 12 4l9 7.5M5 10v10h14V10',
    users: 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-12 9a8 8 0 0 1 16 0',
    globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-18c3 3 3 15 0 18m0-18c-3 3-3 15 0 18M3 12h18',
    cog: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7-3 1.5-1-1-2.5-1.8.3a7 7 0 0 0-1.5-1.5l.3-1.8L14 4l-1 1.5a7 7 0 0 0-2 0L10 4l-2.5 1 .3 1.8A7 7 0 0 0 6.3 8.3L4.5 8l-1 2.5L5 12l-1.5 1 1 2.5 1.8-.3a7 7 0 0 0 1.5 1.5l-.3 1.8L10 20l1-1.5a7 7 0 0 0 2 0l1 1.5 2.5-1-.3-1.8a7 7 0 0 0 1.5-1.5l1.8.3 1-2.5L19 12Z',
    key: 'M15 7a4 4 0 1 1-2.8 6.8L4 22l-1-1 2-2 2 2 2-2-2-2 2-2 2 2 1.2-1.2A4 4 0 0 1 15 7Zm1 2a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z',
};
</script>

<template>
    <!-- Voile sur petit écran : sous le bandeau (top-16), qui reste visible -->
    <div v-if="ouvert" class="fixed inset-x-0 bottom-0 top-16 z-20 bg-black/40 lg:hidden" @click="$emit('fermer')" />

    <!-- Tiroir sur petit écran, colonne fixe à partir de lg ; jamais par-dessus le bandeau -->
    <aside
        class="fixed bottom-0 left-0 top-16 z-20 flex w-64 flex-col overflow-y-auto bg-role text-role-ink transition-transform duration-200 lg:sticky lg:top-16 lg:h-[calc(100vh-4rem)] lg:translate-x-0"
        :class="ouvert ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex flex-col items-center px-4 pb-6 pt-8">
            <div class="relative">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-white text-3xl font-semibold text-epi-anthracite shadow-md">
                    {{ auth.initiales || '?' }}
                </div>
                <span class="absolute bottom-1 right-1 h-5 w-5 rounded-full border-2 border-role bg-good" :title="t('nav.connecte')" />
            </div>
            <p class="mt-4 max-w-full truncate text-center text-lg font-medium">{{ auth.utilisateur?.name ?? '' }}</p>
            <p class="text-sm text-role-ink-muted">{{ t(`roles.${auth.role}`) }}</p>
            <p v-if="auth.utilisateur?.service" class="text-xs text-role-ink-muted">{{ auth.utilisateur.service.nom }}</p>
            <div class="mt-5 h-px w-40 bg-white/50" />
        </div>

        <nav class="flex-1" :aria-label="t('nav.principale')">
            <RouterLink
                v-for="e in entrees"
                :key="e.nom"
                :to="{ name: e.nom }"
                class="flex items-center gap-3 border-b border-white/10 px-5 py-3.5 text-[15px] hover:bg-role-strong"
                active-class="bg-role-strong font-medium"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="ICONES[e.icone]" />
                </svg>
                <span>{{ e.libelle }}</span>
            </RouterLink>
        </nav>

        <div class="px-5 py-3 sm:hidden">
            <LanguageSwitcher />
        </div>
        <div v-if="auth.aRole('coordinateur')" class="px-5 pb-3 md:hidden">
            <SearchBar />
        </div>
        <p class="px-5 py-4 text-xs text-role-ink-muted">{{ t('app.pied') }}</p>
    </aside>
</template>
