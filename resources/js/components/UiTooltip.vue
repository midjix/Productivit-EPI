<script setup>
/**
 * Info-bulle explicative : apparaît au survol ou au focus clavier de l'ancêtre `.group`
 * (une ligne de tableau rendue focalisable par `tabindex="0"`), sous le contenu du slot.
 * `id` permet à la ligne de la référencer par `aria-describedby` pour les lecteurs d'écran.
 */
defineProps({
    id: { type: String, required: true },
    texte: { type: String, required: true },
});
</script>

<template>
    <span class="relative inline-block">
        <slot />
        <span
            :id="id"
            role="tooltip"
            class="pointer-events-none invisible absolute left-0 top-full z-30 mt-1.5 w-72 max-w-[80vw] whitespace-pre-line rounded-md bg-ink px-3 py-2 text-left text-xs font-normal leading-snug text-surface opacity-0 shadow-lg transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
        >{{ texte }}</span>
    </span>
</template>
