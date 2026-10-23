<script setup>
import { useI18n } from 'vue-i18n';

/** Écrans d'administration : sous-navigation par onglets, comme les onglets « Mes paramètres » de DSKnet. */
const { t } = useI18n();

const onglets = [
    { nom: 'admin-imports', cle: 'imports' },
    { nom: 'admin-referentiels', cle: 'referentiels' },
    { nom: 'admin-matrice', cle: 'matrice' },
    { nom: 'admin-organigramme', cle: 'organigramme' },
];
</script>

<template>
    <div>
        <nav class="bg-role px-4 text-role-ink sm:px-6" :aria-label="t('nav.administration')">
            <div class="mx-auto flex max-w-6xl gap-1 overflow-x-auto">
                <RouterLink
                    v-for="o in onglets"
                    :key="o.nom"
                    :to="{ name: o.nom }"
                    class="whitespace-nowrap border-b-4 border-transparent px-4 py-3 text-sm font-medium text-role-ink-muted hover:text-role-ink"
                    active-class="border-white text-role-ink"
                >
                    {{ t(`admin.onglets.${o.cle}`) }}
                </RouterLink>
            </div>
        </nav>

        <div class="mx-auto max-w-6xl space-y-4 px-4 py-6 sm:px-6">
            <RouterView />
        </div>
    </div>
</template>
