<script setup>
import { useRoute } from 'vue-router';
import UiCard from '@/components/UiCard.vue';

const route = useRoute();
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
        <UiCard :titre="route.meta.titre ?? 'Bientôt'">
            <p class="text-ink-secondary">Cet écran arrive dans la prochaine livraison. L'API correspondante est déjà en place.</p>
        </UiCard>
    </div>
</template>
