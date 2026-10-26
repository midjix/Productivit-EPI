<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useRechercheStore } from '@/stores/recherche';
import { classer } from '@/utils/recherche';
import UiSpinner from '@/components/UiSpinner.vue';

/**
 * Recherche d'un salarié (coordinateur : son service ; administrateur : tous) ou d'un
 * service (administrateur). Propose les dernières recherches, les plus consultées et
 * les résultats les plus probables pour le texte tapé, sans doublon.
 */
const auth = useAuthStore();
const recherche = useRechercheStore();
const router = useRouter();
const { t } = useI18n();

const texte = ref('');
const ouvert = ref(false);
const actif = ref(-1);
const champ = ref(null);

const groupes = computed(() => {
    const { recentes, frequentes, resultats } = classer({ entrees: recherche.entrees, historique: recherche.historique, texte: texte.value });
    return [
        { cle: 'recentes', entrees: recentes },
        { cle: 'frequentes', entrees: frequentes },
        { cle: 'resultats', entrees: resultats },
    ].filter((g) => g.entrees.length > 0);
});
const plates = computed(() => groupes.value.flatMap((g) => g.entrees));
const vide = computed(() => plates.value.length === 0);

function ouvrir() {
    recherche.chargerHistorique();
    recherche.charger();
    ouvert.value = true;
    actif.value = -1;
}

function fermer() {
    ouvert.value = false;
    actif.value = -1;
}

function choisir(entree) {
    recherche.memoriser(entree);
    texte.value = '';
    fermer();
    champ.value?.blur();
    router.push(entree.type === 'service'
        ? { name: 'service-detail', params: { id: entree.id } }
        : { name: 'salarie', params: { id: entree.id } });
}

function surTouche(e) {
    if (!ouvert.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        actif.value = Math.min(actif.value + 1, plates.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        actif.value = Math.max(actif.value - 1, -1);
    } else if (e.key === 'Enter') {
        const cible = actif.value >= 0 ? plates.value[actif.value] : plates.value[0];
        if (cible) choisir(cible);
    } else if (e.key === 'Escape') {
        fermer();
    }
}

function indexPlat(entree) {
    return plates.value.indexOf(entree);
}

watch(texte, () => {
    actif.value = -1;
    if (!ouvert.value) nextTick(ouvrir);
});

const placeholder = computed(() => (auth.estAdministrateurRole ? t('recherche.placeholderAdmin') : t('recherche.placeholder')));
</script>

<template>
    <div class="relative w-full max-w-md" @focusout="(e) => { if (!$el.contains(e.relatedTarget)) fermer(); }">
        <label class="sr-only" for="recherche">{{ placeholder }}</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
            <input
                id="recherche"
                ref="champ"
                v-model="texte"
                type="search"
                autocomplete="off"
                role="combobox"
                :aria-expanded="ouvert"
                aria-controls="recherche-liste"
                :placeholder="placeholder"
                class="w-full rounded-full border border-border bg-surface py-1.5 pl-9 pr-3 text-sm text-ink outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
                @focus="ouvrir"
                @keydown="surTouche"
            />
        </div>

        <div
            v-if="ouvert"
            id="recherche-liste"
            role="listbox"
            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-96 overflow-y-auto rounded-md border border-border bg-surface-raised p-1 text-sm shadow-xl"
        >
            <p v-if="vide && recherche.chargement" class="px-3 py-2 text-ink-muted"><UiSpinner taille="h-4 w-4" :libelle="t('recherche.chargement')" /></p>
            <p v-else-if="vide" class="px-3 py-2 text-ink-muted">{{ texte ? t('recherche.aucun') : t('recherche.aide') }}</p>
            <template v-for="g in groupes" :key="g.cle">
                <p class="px-3 pb-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-ink-muted">{{ t(`recherche.${g.cle}`) }}</p>
                <button
                    v-for="e in g.entrees"
                    :key="`${e.type}-${e.id}`"
                    type="button"
                    role="option"
                    :aria-selected="indexPlat(e) === actif"
                    class="flex w-full items-center gap-3 rounded px-3 py-1.5 text-left hover:bg-surface-muted"
                    :class="indexPlat(e) === actif ? 'bg-surface-muted' : ''"
                    @mousedown.prevent
                    @click="choisir(e)"
                >
                    <span class="w-14 shrink-0 rounded-full px-2 py-0.5 text-center text-[10px] font-medium uppercase" :class="e.type === 'service' ? 'bg-epi-anthracite/15 text-ink' : 'bg-accent/15 text-accent-strong'">
                        {{ t(`recherche.${e.type}`) }}
                    </span>
                    <span class="flex-1 truncate text-ink">{{ e.libelle }}</span>
                    <span v-if="e.detail" class="truncate text-xs text-ink-muted">{{ e.detail }}</span>
                </button>
            </template>
        </div>
    </div>
</template>
