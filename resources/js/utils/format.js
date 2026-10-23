/**
 * Mise en forme des valeurs de l'API pour l'affichage, dans la langue courante.
 * L'API parle en minutes (entiers) et en décimaux sérialisés en chaînes ("0.8367") ;
 * l'interface parle en heures et en pourcentages, formatés selon la langue (fr / en / de).
 */

const LOCALES = { fr: 'fr-LU', en: 'en-GB', de: 'de-LU' };

let localeCourante = 'fr-LU';

/** Appelé par le module i18n à chaque changement de langue. */
export function definirLocaleFormat(code) {
    localeCourante = LOCALES[code] ?? LOCALES.fr;
}

export function localeFormat() {
    return localeCourante;
}

/** 7680 → "128h00" ; "5880.50" → "98h01" (minutes décimales arrondies à la minute). */
export function heures(minutes) {
    const total = Math.round(Number(minutes ?? 0));
    const signe = total < 0 ? '-' : '';
    const abs = Math.abs(total);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    return `${signe}${h}h${String(m).padStart(2, '0')}`;
}

/** 7680 → "128,0 h" (fr) / "128.0 h" (en). */
export function heuresDecimales(minutes, decimales = 1) {
    const h = Number(minutes ?? 0) / 60;
    return `${h.toLocaleString(localeCourante, { minimumFractionDigits: decimales, maximumFractionDigits: decimales })} h`;
}

/** "0.8367" → "83,7 %" (fr) / "83.7%" (en) ; null → "—". */
export function pourcentage(ratio, decimales = 1) {
    if (ratio === null || ratio === undefined || ratio === '') return '—';
    const v = Number(ratio);
    if (Number.isNaN(v)) return '—';
    return v.toLocaleString(localeCourante, { style: 'percent', minimumFractionDigits: decimales, maximumFractionDigits: decimales });
}

/** "84.50" → "84,5" (nombre de forfaits). */
export function nombre(valeur, decimales = 1) {
    const v = Number(valeur ?? 0);
    return v.toLocaleString(localeCourante, { minimumFractionDigits: 0, maximumFractionDigits: decimales });
}

/** Nom du mois (1..12) dans la langue courante, long ou abrégé. */
export function nomMois(mois, court = false) {
    const m = ((Number(mois) - 1) % 12 + 12) % 12;
    const nom = new Intl.DateTimeFormat(localeCourante, { month: court ? 'short' : 'long' }).format(new Date(2026, m, 15));
    return nom.charAt(0).toUpperCase() + nom.slice(1);
}

/** "2026-10-23T09:10:36+00:00" → date et heure locales courtes. */
export function dateHeure(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(localeCourante, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
}

/** "2026-10-23" → date locale courte. */
export function date(iso) {
    if (!iso) return '—';
    const [a, m, j] = String(iso).slice(0, 10).split('-').map(Number);
    return new Intl.DateTimeFormat(localeCourante, { dateStyle: 'short' }).format(new Date(a, m - 1, j));
}
