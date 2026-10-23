<script setup>
import { computed } from 'vue';

/** Sélecteur d'année « ‹ 2026 › » comme le sélecteur de mois du récapitulatif DSKnet. */
const props = defineProps({
    modelValue: { type: Number, required: true },
    disponibles: { type: Array, default: () => [] },
    max: { type: Number, default: () => new Date().getFullYear() },
    min: { type: Number, default: 2020 },
});
const emit = defineEmits(['update:modelValue']);

const peutReculer = computed(() => props.modelValue > props.min);
const peutAvancer = computed(() => props.modelValue < props.max);
const options = computed(() => {
    const annees = new Set([...props.disponibles, props.modelValue]);
    return [...annees].sort((a, b) => b - a);
});
</script>

<template>
    <div class="inline-flex items-center overflow-hidden rounded-full border border-white/60 text-sm">
        <button type="button" class="px-3 py-1.5 hover:bg-white/15 disabled:opacity-40" :disabled="!peutReculer" aria-label="Année précédente" @click="emit('update:modelValue', modelValue - 1)">‹</button>
        <label class="sr-only" for="annee">Année</label>
        <select
            id="annee"
            :value="modelValue"
            class="cursor-pointer appearance-none bg-transparent px-2 py-1.5 text-center font-medium outline-none"
            @change="emit('update:modelValue', Number($event.target.value))"
        >
            <option v-for="a in options" :key="a" :value="a" class="text-ink">{{ a }}</option>
        </select>
        <button type="button" class="px-3 py-1.5 hover:bg-white/15 disabled:opacity-40" :disabled="!peutAvancer" aria-label="Année suivante" @click="emit('update:modelValue', modelValue + 1)">›</button>
    </div>
</template>
