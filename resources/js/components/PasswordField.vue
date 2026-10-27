<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Champ mot de passe avec œil à deux états (masqué / visible) et liste complète des erreurs
 * renvoyées par l'API (toutes, pas seulement la première).
 */
defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    autocomplete: { type: String, default: 'new-password' },
    erreurs: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const afficher = ref(false);
</script>

<template>
    <div>
        <label :for="id" class="mb-1 block text-sm text-ink-secondary">{{ label }}</label>
        <div class="relative">
            <input
                :id="id"
                :type="afficher ? 'text' : 'password'"
                :value="modelValue"
                :autocomplete="autocomplete"
                required
                class="w-full rounded-md border bg-surface px-3 py-2.5 pr-11 text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
                :class="erreurs.length ? 'border-bad' : 'border-border'"
                :aria-invalid="erreurs.length > 0"
                :aria-describedby="erreurs.length ? `${id}-erreurs` : undefined"
                @input="emit('update:modelValue', $event.target.value)"
            />
            <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-ink-muted hover:text-ink"
                :aria-label="afficher ? t('mdp.masquer') : t('mdp.afficher')"
                :aria-pressed="afficher"
                @click="afficher = !afficher"
            >
                <!-- Œil ouvert : le mot de passe est masqué, cliquer l'affiche ; œil barré : il est visible -->
                <svg v-if="!afficher" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" />
                </svg>
                <svg v-else class="h-5 w-5 text-accent-strong" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6A3 3 0 0 0 13.4 13.4M9.9 5.8A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.7 16.7 0 0 1-3.2 3.9M6.6 6.6C4 8.4 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.5 0 2.8-.4 4-1" />
                </svg>
            </button>
        </div>
        <ul v-if="erreurs.length" :id="`${id}-erreurs`" class="mt-1 space-y-0.5 text-sm text-bad">
            <li v-for="m in erreurs" :key="m">{{ m }}</li>
        </ul>
    </div>
</template>
