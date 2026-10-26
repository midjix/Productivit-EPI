<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';

/**
 * Fine barre animée sous le bandeau pendant un changement de page (chargement du
 * morceau de code de la vue, garde d'authentification) : la page n'est pas figée.
 */
const router = useRouter();
const actif = ref(false);
let minuteur = null;

const retirerAvant = router.beforeEach(() => {
    clearTimeout(minuteur);
    // Évite un clignotement sur les navigations instantanées.
    minuteur = setTimeout(() => (actif.value = true), 120);
});
const retirerApres = router.afterEach(() => {
    clearTimeout(minuteur);
    actif.value = false;
});

onBeforeUnmount(() => {
    retirerAvant();
    retirerApres();
    clearTimeout(minuteur);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-16 z-40 h-1 overflow-hidden" aria-hidden="true">
        <div v-if="actif" class="h-full w-1/3 animate-[progression_1.1s_ease-in-out_infinite] rounded-r bg-accent" />
    </div>
</template>

<style>
@keyframes progression {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(300%); }
}
</style>
