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

/**
 * 7680 → "128h00" ; "5880.50" → "98h01" (minutes décimales arrondies à la minute).
 * Les heures reçoivent le séparateur de milliers de la langue : "11 264h00" (fr), "11,264h00" (en), "11.264h00" (de).
 */
export function heures(minutes) {
    const total = Math.round(Number(minutes ?? 0));
    const signe = total < 0 ? '-' : '';
    const abs = Math.abs(total);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    return `${signe}${h.toLocaleString(localeCourante)}h${String(m).padStart(2, '0')}`;
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

/**
 * "2026-10-23T09:10:36+00:00" → date et heure locales : "23 oct. 2026, 09:10" (fr),
 * "23 Oct 2026, 09:10" (en), "23.10.2026, 09:10" (de).
 * Le style « medium » (mois en lettres) est préféré au style court : en français comme en
 * anglais britannique, le style court donne « 23/10/2026 », la langue choisie ne se voyait pas.
 */
export function dateHeure(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(localeCourante, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

/** "2026-10-23" → "23 oct. 2026" (fr), "23 Oct 2026" (en), "23.10.2026" (de). */
export function date(iso) {
    if (!iso) return '—';
    const [a, m, j] = String(iso).slice(0, 10).split('-').map(Number);
    return new Intl.DateTimeFormat(localeCourante, { dateStyle: 'medium' }).format(new Date(a, m - 1, j));
}

/* Saisie de dates (composant DateInput) : jour en premier dans les trois langues, séparateur selon la langue. */

const MASQUES = { 'fr-LU': 'JJ/MM/AAAA', 'en-GB': 'DD/MM/YYYY', 'de-LU': 'TT.MM.JJJJ' };

/** Gabarit de saisie dans la langue courante, affiché en indication du champ. */
export function masqueDate() {
    return MASQUES[localeCourante] ?? MASQUES['fr-LU'];
}

/** "2026-01-05" → "05/01/2026" (fr, en) / "05.01.2026" (de) : forme numérique pour les champs de saisie ; vide → "". */
export function dateNumerique(iso) {
    if (!iso) return '';
    const [a, m, j] = String(iso).slice(0, 10).split('-').map(Number);
    return new Intl.DateTimeFormat(localeCourante, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(a, m - 1, j));
}

/**
 * Texte saisi → "AAAA-MM-JJ", ou null s'il n'est pas (encore) une date.
 * Jour, mois, année sur quatre chiffres, séparés par « / », « . » ou « - » ("5/1/2026", "05.01.2026") ;
 * la forme ISO "2026-01-05" est acceptée telle quelle. Une date impossible (31/02) donne null.
 */
export function analyserDateSaisie(texte) {
    const t = String(texte ?? '').trim();
    let a;
    let m;
    let j;
    const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(t);
    if (iso) {
        [a, m, j] = iso.slice(1).map(Number);
    } else {
        const local = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(t);
        if (!local) return null;
        [j, m, a] = local.slice(1).map(Number);
    }
    const d = new Date(a, m - 1, j);
    if (d.getFullYear() !== a || d.getMonth() !== m - 1 || d.getDate() !== j) return null;
    return `${a}-${String(m).padStart(2, '0')}-${String(j).padStart(2, '0')}`;
}
