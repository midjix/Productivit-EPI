/**
 * Mise en forme des valeurs de l'API pour l'affichage.
 * L'API parle en minutes (entiers) et en décimaux sérialisés en chaînes ("0.8367") ;
 * l'interface parle en heures et en pourcentages, à la française.
 */

export const MOIS = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

export const MOIS_COURT = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

/** 7680 → "128h00" ; "5880.50" → "98h00" (minutes décimales arrondies à la minute). */
export function heures(minutes) {
    const total = Math.round(Number(minutes ?? 0));
    const signe = total < 0 ? '-' : '';
    const abs = Math.abs(total);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    return `${signe}${h}h${String(m).padStart(2, '0')}`;
}

/** 7680 → "128,0 h" (heures décimales, pour les cartes de synthèse). */
export function heuresDecimales(minutes, decimales = 1) {
    const h = Number(minutes ?? 0) / 60;
    return `${h.toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })} h`;
}

/** "0.8367" → "83,7 %" ; null → "—". */
export function pourcentage(ratio, decimales = 1) {
    if (ratio === null || ratio === undefined || ratio === '') return '—';
    const v = Number(ratio) * 100;
    if (Number.isNaN(v)) return '—';
    return `${v.toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })} %`;
}

/** "84.50" → "84,5" (nombre de forfaits). */
export function nombre(valeur, decimales = 1) {
    const v = Number(valeur ?? 0);
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: decimales });
}

export function nomMois(mois, court = false) {
    const liste = court ? MOIS_COURT : MOIS;
    return liste[(Number(mois) - 1 + 12) % 12] ?? '';
}
