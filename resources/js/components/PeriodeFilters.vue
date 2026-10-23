<script setup>
import { useI18n } from 'vue-i18n';
import { nomMois } from '@/utils/format';
import YearPicker from '@/components/YearPicker.vue';

/** Filtres année + mois (ou toute l'année) des vues agrégées. */
defineProps({
    annee: { type: Number, required: true },
    mois: { type: Number, default: null },
    disponibles: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:annee', 'update:mois']);
const { t, locale } = useI18n();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <YearPicker :model-value="annee" :disponibles="disponibles" @update:model-value="emit('update:annee', $event)" />
        <label class="sr-only" for="mois">{{ t('filtres.mois') }}</label>
        <select
            id="mois"
            :key="locale"
            :value="mois ?? ''"
            class="cursor-pointer rounded-full border border-current/60 bg-transparent px-3 py-1.5 text-sm font-medium outline-none"
            @change="emit('update:mois', $event.target.value === '' ? null : Number($event.target.value))"
        >
            <option value="" class="text-ink">{{ t('filtres.touteAnnee') }}</option>
            <option v-for="m in 12" :key="m" :value="m" class="text-ink">{{ nomMois(m) }}</option>
        </select>
    </div>
</template>
