<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';

const auth = useAuthStore();
const theme = useThemeStore();

auth.brancherExpiration();

onMounted(() => {
    theme.initialiser();
});
</script>

<template>
    <RouterView />
</template>
