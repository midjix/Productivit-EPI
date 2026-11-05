<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { analyserDateSaisie, dateNumerique, masqueDate } from '@/utils/format';

/**
 * Champ de date qui suit la langue de l'application, pas celle du navigateur.
 * Un <input type="date"> natif s'écrit dans la langue du navigateur (« 12/31/2026 » sur un Edge en
 * anglais américain, quelle que soit la langue choisie dans l'application : retour client du 05/11/2026).
 * Ici : un champ texte au format de la langue (JJ/MM/AAAA, DD/MM/YYYY, TT.MM.JJJJ) et un bouton
 * calendrier qui ouvre le sélecteur natif (showPicker) d'un champ date masqué.
 * v-model en ISO (AAAA-MM-JJ), comme le champ natif : les pages n'ont rien à changer.
 */
const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /** Erreur signalée par la page (validation de l'API). */
    invalide: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const { t, locale } = useI18n();

const natif = ref(null);
const texte = ref('');
const erreurSaisie = ref(false);

const masque = computed(() => (locale.value, masqueDate()));

// Le texte suit la valeur (navigation, calendrier) sans réécrire ce que l'utilisateur est en train de taper.
watch(
    () => props.modelValue,
    (valeur) => {
        if (analyserDateSaisie(texte.value) !== (valeur || null)) texte.value = dateNumerique(valeur);
    },
    { immediate: true },
);
// Changement de langue : « 05/01/2026 » devient « 05.01.2026 » en allemand.
watch(locale, () => {
    texte.value = dateNumerique(props.modelValue);
});

function saisir(valeur) {
    texte.value = valeur;
    if (valeur.trim() === '') {
        erreurSaisie.value = false;
        emit('update:modelValue', '');
        return;
    }
    const iso = analyserDateSaisie(valeur);
    erreurSaisie.value = iso === null;
    if (iso !== null && iso !== props.modelValue) emit('update:modelValue', iso);
}

/** En quittant le champ : « 5/1/2026 » est normalisé, un texte impossible revient à la dernière date valide. */
function quitter() {
    texte.value = dateNumerique(props.modelValue);
    erreurSaisie.value = false;
}

function ouvrirCalendrier() {
    const el = natif.value;
    if (!el) return;
    try {
        if (typeof el.showPicker === 'function') el.showPicker();
        else el.focus();
    } catch {
        el.focus(); // sans activation utilisateur ou dans un cadre : le navigateur refuse, on n'insiste pas
    }
}
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :value="texte"
            :placeholder="masque"
            :title="masque"
            :required="required"
            :disabled="disabled"
            :aria-invalid="invalide || erreurSaisie"
            class="w-full rounded-md border bg-surface px-3 py-2 pr-10 text-ink tabular-nums outline-none transition focus:ring-2 disabled:opacity-60"
            :class="invalide || erreurSaisie ? 'border-bad focus:border-bad focus:ring-bad/30' : 'border-border focus:border-accent focus:ring-accent/30'"
            @input="saisir($event.target.value)"
            @blur="quitter"
        />
        <button
            type="button"
            :disabled="disabled"
            class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-ink-muted hover:text-ink disabled:opacity-50"
            :aria-label="t('champs.ouvrirCalendrier')"
            :title="t('champs.ouvrirCalendrier')"
            @click="ouvrirCalendrier"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
                <path stroke-linecap="round" d="M3.5 10h17M8 3v4M16 3v4" />
            </svg>
        </button>
        <!-- Sélecteur natif masqué : seul son calendrier sert (bouton ci-dessus) ; il reste rendu pour que showPicker fonctionne. -->
        <input
            ref="natif"
            type="date"
            tabindex="-1"
            aria-hidden="true"
            :value="modelValue"
            :disabled="disabled"
            class="pointer-events-none absolute bottom-0 right-2 h-px w-px opacity-0"
            @input="emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
