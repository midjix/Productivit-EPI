<script setup>
import { onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { definirLangue } from '@/api/client';
import { appliquerLangue } from '@/i18n';

const auth = useAuthStore();
const theme = useThemeStore();
const { locale } = useI18n();

auth.brancherExpiration();
definirLangue(() => locale.value);

watch(locale, (code) => appliquerLangue(code));

onMounted(() => {
    theme.initialiser();
});
</script>

<template>
    <RouterView />
</template>
