<script setup>
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import ThemeToggle from '@/components/ThemeToggle.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

defineProps({ titre: { type: String, default: '' } });
defineEmits(['basculer-menu']);

const auth = useAuthStore();
const router = useRouter();
const { t } = useI18n();

// Fichier servi par Laravel depuis public/ (hors pipeline Vite) : chemin lié dynamiquement.
const LOGO = '/images/logo-epi.png';

async function seDeconnecter() {
    await auth.deconnexion();
    router.push({ name: 'connexion' });
}
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-header px-4 text-header-ink shadow-sm">
        <button
            type="button"
            class="rounded-md p-2 hover:bg-surface-muted lg:hidden"
            :aria-label="t('nav.ouvrirMenu')"
            @click="$emit('basculer-menu')"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>

        <RouterLink :to="{ name: 'tableau-de-bord' }" class="flex items-center gap-3" :aria-label="t('nav.accueil')">
            <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-md bg-white shadow-sm">
                <img :src="LOGO" alt="Fondation EPI" class="h-10 w-10 object-contain" />
            </span>
        </RouterLink>

        <h1 class="min-w-0 flex-1 truncate text-lg font-medium uppercase tracking-[0.12em] sm:text-xl">
            {{ titre }}
        </h1>

        <LanguageSwitcher class="hidden sm:flex" />
        <ThemeToggle />

        <button
            type="button"
            class="rounded-md p-2 text-header-ink hover:bg-surface-muted hover:text-accent"
            :title="t('nav.deconnexion')"
            :aria-label="t('nav.deconnexion')"
            @click="seDeconnecter"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v9m6.36-5.36a9 9 0 1 1-12.72 0" />
            </svg>
        </button>
    </header>
</template>
