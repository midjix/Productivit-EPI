<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useRechercheStore } from '@/stores/recherche';
import { titreDocument } from '@/router';
import AppHeader from '@/components/AppHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import ToastList from '@/components/ToastList.vue';

/**
 * Gabarit des écrans connectés, organisé comme DSKnet : bandeau en haut (logo, titre
 * de la page, actions), menu latéral (avatar, nom, navigation) et zone de contenu.
 * La couleur du menu dépend du rôle (attribut data-role lu par la feuille de style).
 */
const auth = useAuthStore();
const recherche = useRechercheStore();
const route = useRoute();
const { t, locale } = useI18n();
const menuOuvert = ref(false);

// La barre de recherche (coordinateur, administrateur) est prête dès le premier écran.
onMounted(() => recherche.charger());

watch(() => route.fullPath, () => {
    menuOuvert.value = false;
});

// Le titre de l'onglet suit la langue.
watch(locale, () => {
    document.title = titreDocument(route.meta.titre);
});
</script>

<template>
    <div :data-role="auth.role" class="flex min-h-screen flex-col bg-surface-muted">
        <AppHeader :titre="route.meta.titre ? t(`titres.${route.meta.titre}`) : ''" @basculer-menu="menuOuvert = !menuOuvert" />

        <div class="flex flex-1">
            <AppSidebar :ouvert="menuOuvert" @fermer="menuOuvert = false" />

            <main class="min-w-0 flex-1">
                <RouterView :key="route.fullPath" />
            </main>
        </div>

        <ToastList />
    </div>
</template>
