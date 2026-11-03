<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Export Excel (.xlsx) de la vue courante — cas d'utilisation « Exporter la vue courante en Excel ».
 *
 * Le classeur reprend les chiffres servis à l'écran (mêmes services, même cascade) : l'application
 * reste la source de vérité, les cellules contiennent des VALEURS et une colonne « Calcul » explique
 * chaque ligne de la synthèse. (Des formules Excel ont été écartées : OpenSpout n'écrit pas de valeur
 * en cache avec une formule, certains tableurs afficheraient alors des cellules vides à l'ouverture.)
 * Les heures sont en heures décimales (utilisables dans Excel), les libellés dans la langue de la
 * requête. Écriture en flux (OpenSpout) : mémoire constante quel que soit le volume.
 */
class ExportExcelService
{
    public const TYPE_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private Style $enTete;

    private Style $gras;

    private Style $heures;

    private Style $heuresGras;

    private Style $pourcent;

    private Style $pourcentGras;

    private Style $nombre;

    public function __construct()
    {
        $this->enTete = (new Style)->setFontBold()->setBackgroundColor('F39200')->setFontColor('FFFFFF');
        $this->gras = (new Style)->setFontBold();
        $this->heures = (new Style)->setFormat('0.00');
        $this->heuresGras = (new Style)->setFormat('0.00')->setFontBold();
        $this->pourcent = (new Style)->setFormat('0.0%');
        $this->pourcentGras = (new Style)->setFormat('0.0%')->setFontBold();
        $this->nombre = (new Style)->setFormat('0.##');
    }

    /**
     * Vue de consultation (tableau de bord, service, Total EPI) : feuille Synthèse,
     * feuille Mois (salarié) ou feuille par salarié / par service.
     *
     * @param  array<string, mixed>  $synthese  sortie de StatistiquesService::synthese()
     * @param  iterable<\App\Models\StatistiqueMensuelle>  $mensuelles  mois du salarié (vue individuelle)
     * @param  list<array{libelle: string, detail?: string|null, synthese: array<string, mixed>}>  $lignes  une ligne par salarié ou par service
     * @return string chemin du fichier temporaire
     */
    public function statistiques(string $perimetre, string $periode, User $auteur, array $synthese, iterable $mensuelles = [], array $lignes = [], string $feuilleLignes = ''): string
    {
        [$writer, $chemin] = $this->ouvrir($auteur);

        $this->feuilleSynthese($writer, $perimetre, $periode, $auteur, $synthese);

        $mensuelles = collect($mensuelles);
        if ($mensuelles->isNotEmpty()) {
            $this->feuilleCascade($writer, __('epi.export.feuilles.mois'), __('epi.export.colonnes.mois'), $mensuelles->map(fn ($m) => [
                'libelle' => $this->nomMois((int) $m->annee, (int) $m->mois),
                'synthese' => $m->toArray() + ['objectif_atteint' => $m->atteintObjectif()],
            ])->all());
        }
        if ($lignes !== []) {
            $this->feuilleCascade($writer, $feuilleLignes, $feuilleLignes, $lignes, true);
        }

        $writer->close();

        return $chemin;
    }

    /**
     * Écran Détail : Synthèse + une feuille par tableau (types de suivi, tarifs, services,
     * types de prestation, compteurs DSK).
     *
     * @param  array<string, mixed>  $resultat  sortie de DetailService::calculer()
     * @param  array{types_suivi: list<string>, types_prestation: list<string>}  $filtres  libellés des filtres appliqués
     * @return string chemin du fichier temporaire
     */
    public function detail(string $perimetre, CarbonInterface $du, CarbonInterface $au, User $auteur, array $resultat, array $filtres): string
    {
        [$writer, $chemin] = $this->ouvrir($auteur);
        $periode = $this->date($du).' → '.$this->date($au);

        $this->feuilleSynthese($writer, $perimetre, $periode, $auteur, $resultat['synthese'], [
            __('epi.export.meta.filtre_types_suivi') => $filtres['types_suivi'] === [] ? __('epi.export.meta.tous') : implode(', ', $filtres['types_suivi']),
            __('epi.export.meta.filtre_types_prestation') => $filtres['types_prestation'] === [] ? __('epi.export.meta.tous') : implode(', ', $filtres['types_prestation']),
        ]);

        $c = fn (string $cle) => __('epi.export.colonnes.'.$cle);
        $ouiNon = fn (bool $v) => __($v ? 'epi.export.meta.oui' : 'epi.export.meta.non');

        $this->feuilleTableau($writer, __('epi.export.feuilles.types_suivi'),
            [$c('type_suivi'), $c('tarif'), $c('facturable'), $c('service'), $c('forfaits'), $c('heures')],
            array_map(fn (array $l) => [$l['libelle'], $l['tarif_code'] ?? '—', $ouiNon((bool) $l['facturable']), $l['service'] ?? '—', $this->nombreCellule($l['nb_forfaits']), $this->heuresCellule($l['minutes'])], $resultat['types_suivi']),
            [38, 10, 12, 24, 12, 12]);

        $this->feuilleTableau($writer, __('epi.export.feuilles.tarifs'),
            [$c('tarif'), $c('libelle'), $c('facturable'), $c('forfaits'), $c('heures'), $c('part')],
            array_map(fn (array $l) => [$l['code'] ?? __('epi.export.meta.sans_tarif'), $l['libelle'] ?? '—', $ouiNon((bool) $l['facturable']), $this->nombreCellule($l['nb_forfaits']), $this->heuresCellule($l['minutes']),
                $l['facturable'] && bccomp((string) $resultat['synthese']['minutes_prestations_facturables'], '0', 2) > 0
                    ? Cell::fromValue((float) bcdiv((string) $l['minutes'], (string) $resultat['synthese']['minutes_prestations_facturables'], 4), $this->pourcent)
                    : Cell::fromValue(''),
            ], $resultat['tarifs']),
            [12, 38, 12, 12, 12, 12]);

        $this->feuilleTableau($writer, __('epi.export.feuilles.services'),
            [$c('service'), $c('forfaits'), $c('heures')],
            array_map(fn (array $l) => [$l['nom'] ?? __('epi.detail.interne'), $this->nombreCellule($l['nb_forfaits']), $this->heuresCellule($l['minutes'])], $resultat['services']),
            [32, 12, 12]);

        $this->feuilleTableau($writer, __('epi.export.feuilles.types_prestation'),
            [$c('type_prestation'), $c('forfaits'), $c('heures')],
            array_map(fn (array $l) => [$l['libelle'] ?? '—', $this->nombreCellule($l['nb_forfaits']), $this->heuresCellule($l['minutes'])], $resultat['types_prestation']),
            [48, 12, 12]);

        $this->feuilleTableau($writer, __('epi.export.feuilles.comptes_dsk'),
            [$c('no'), $c('compte'), $c('categorie'), $c('dans_total'), $c('heures'), $c('nb_salaries')],
            array_map(fn (array $l) => [$l['no_account'], $l['libelle'], $l['categorie_label'], $ouiNon((bool) $l['compte_effectif']), $this->heuresCellule($l['minutes']), $l['nb_salaries']], $resultat['comptes_dsk']),
            [8, 34, 26, 14, 12, 12]);

        $writer->close();

        return $chemin;
    }

    /** Nom de fichier proposé au navigateur : efficacite-<périmètre>-<période>.xlsx. */
    public static function nomFichier(string $prefixe, string $perimetre, string $periode): string
    {
        return mb_strtolower(str($prefixe.'-'.$perimetre.'-'.$periode)->slug()->toString()).'.xlsx';
    }

    // ------------------------------------------------------------------ feuilles

    /**
     * Feuille « Synthèse » : en-tête (périmètre, période, auteur, filtres) puis la cascade
     * d'efficacité dans l'ordre du fichier de la Fondation, avec l'explication de chaque ligne.
     *
     * @param  array<string, mixed>  $s
     * @param  array<string, string>  $meta  lignes supplémentaires d'en-tête (filtres)
     */
    private function feuilleSynthese(Writer $writer, string $perimetre, string $periode, User $auteur, array $s, array $meta = []): void
    {
        $writer->getCurrentSheet()->setName($this->nomFeuille(__('epi.export.feuilles.synthese')));
        $writer->getOptions()->setColumnWidth(34, 1);
        $writer->getOptions()->setColumnWidth(14, 2);
        $writer->getOptions()->setColumnWidth(60, 3);

        $m = fn (string $cle) => __('epi.export.meta.'.$cle);
        $writer->addRow(Row::fromValues([$m('titre')], $this->gras));
        $writer->addRow(Row::fromValues([$m('perimetre'), $perimetre]));
        $writer->addRow(Row::fromValues([$m('periode'), $periode]));
        foreach ($meta as $libelle => $valeur) {
            $writer->addRow(Row::fromValues([$libelle, $valeur]));
        }
        $writer->addRow(Row::fromValues([$m('genere_le'), CarbonImmutable::now()->locale(app()->getLocale())->isoFormat('L LT')]));
        $writer->addRow(Row::fromValues([$m('par'), $auteur->name]));
        $writer->addRow(Row::fromValues([$m('source')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([__('epi.export.colonnes.indicateur'), __('epi.export.colonnes.heures'), __('epi.export.colonnes.formule')], $this->enTete));

        $h = fn (int|string $minutes) => $this->minutesEnHeures($minutes);
        $f = fn (string $cle) => __('epi.export.formules.'.$cle);
        $ajouter = function (string $cle, Cell $valeur, string $formule = '', bool $gras = false) use ($writer) {
            $writer->addRow(new Row([Cell::fromValue(__('epi.export.indicateurs.'.$cle), $gras ? $this->gras : null), $valeur, Cell::fromValue($formule)]));
        };

        $ajouter('total', Cell::fromValue($h($s['minutes_total']), $this->heuresGras), $f('total'), true);
        $ajouter('conge', Cell::fromValue($h($s['minutes_conge']), $this->heures), $f('conge'));
        $ajouter('maladie', Cell::fromValue($h($s['minutes_maladie']), $this->heures), $f('maladie'));
        $ajouter('dispo_epi', Cell::fromValue($h($s['minutes_dispo_epi']), $this->heuresGras), $f('dispo_epi'), true);
        $ajouter('formation', Cell::fromValue($h($s['minutes_formation']), $this->heures), $f('formation'));
        $ajouter('supervision', Cell::fromValue($h($s['minutes_supervision']), $this->heures), $f('supervision'));
        $ajouter('interne', Cell::fromValue($h($s['minutes_activite_interne']), $this->heures), $f('interne'));
        $ajouter('dispo_client', Cell::fromValue($h($s['minutes_dispo_client']), $this->heuresGras), $f('dispo_client'), true);
        $ajouter('facturables', Cell::fromValue($h($s['minutes_prestations_facturables']), $this->heuresGras), $f('facturables'), true);
        $ajouter('forfaits', Cell::fromValue((float) $s['nb_prestations'], $this->nombre), $f('forfaits'));
        if (isset($s['nb_salaries'])) {
            $ajouter('nb_salaries', Cell::fromValue((int) $s['nb_salaries']));
        }
        $ajouter('efficacite', $this->ratioCellule($s['ratio'], $this->pourcentGras), $f('efficacite'), true);
        $ajouter('objectif', Cell::fromValue((float) $s['objectif'], $this->pourcent), $f('objectif'));
        $ajouter('objectif_atteint', Cell::fromValue($this->ouiNon($s['objectif_atteint'])));
        $ajouter('brute', $this->ratioCellule($s['ratio_brut'], $this->pourcent), $f('brute'));
    }

    /**
     * Feuille à une ligne par entité (mois, salarié ou service) : la cascade en colonnes,
     * statut de l'objectif en toutes lettres.
     *
     * @param  list<array{libelle: string, detail?: string|null, synthese: array<string, mixed>}>  $lignes
     */
    private function feuilleCascade(Writer $writer, string $nomFeuille, string $premiereColonne, array $lignes, bool $avecDetail = false): void
    {
        $feuille = $writer->addNewSheetAndMakeItCurrent();
        $feuille->setName($this->nomFeuille($nomFeuille));
        $writer->getOptions()->setColumnWidth(30, 1);

        $c = fn (string $cle) => __('epi.export.colonnes.'.$cle);
        $entete = [$premiereColonne];
        if ($avecDetail) {
            $entete[] = $c('service');
        }
        $entete = [...$entete, $c('total'), $c('conge'), $c('maladie'), $c('dispo_epi'), $c('formation'), $c('supervision'), $c('interne'), $c('dispo_client'), $c('facturables'), $c('forfaits'), $c('efficacite'), $c('objectif_atteint'), $c('brute')];
        $writer->addRow(Row::fromValues($entete, $this->enTete));

        foreach ($lignes as $l) {
            $s = $l['synthese'];
            $h = fn (string $cle) => Cell::fromValue($this->minutesEnHeures($s[$cle]), $this->heures);
            $cellules = [Cell::fromValue($l['libelle'])];
            if ($avecDetail) {
                $cellules[] = Cell::fromValue($l['detail'] ?? '—');
            }
            $cellules = [...$cellules,
                $h('minutes_total'), $h('minutes_conge'), $h('minutes_maladie'), $h('minutes_dispo_epi'),
                $h('minutes_formation'), $h('minutes_supervision'), $h('minutes_activite_interne'), $h('minutes_dispo_client'),
                $h('minutes_prestations_facturables'),
                Cell::fromValue((float) $s['nb_prestations'], $this->nombre),
                $this->ratioCellule($s['ratio'], $this->pourcent),
                Cell::fromValue($this->ouiNon($s['objectif_atteint'] ?? null)),
                $this->ratioCellule($s['ratio_brut'], $this->pourcent),
            ];
            $writer->addRow(new Row($cellules));
        }
    }

    /**
     * Feuille tabulaire simple (en-tête + lignes).
     *
     * @param  list<string>  $entete
     * @param  list<list<Cell|scalar|null>>  $lignes
     * @param  list<int>  $largeurs
     */
    private function feuilleTableau(Writer $writer, string $nom, array $entete, array $lignes, array $largeurs): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName($this->nomFeuille($nom));
        foreach ($largeurs as $i => $largeur) {
            $writer->getOptions()->setColumnWidth($largeur, $i + 1);
        }
        $writer->addRow(Row::fromValues($entete, $this->enTete));
        foreach ($lignes as $l) {
            $writer->addRow(new Row(array_map(fn ($v) => $v instanceof Cell ? $v : Cell::fromValue($v), $l)));
        }
    }

    // ------------------------------------------------------------------ outils

    /** @return array{0: Writer, 1: string} */
    private function ouvrir(User $auteur): array
    {
        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 14;
        $writer = new Writer($options);
        $writer->setCreator($auteur->name);
        $chemin = tempnam(sys_get_temp_dir(), 'epi-export-');
        $writer->openToFile($chemin);

        return [$writer, $chemin];
    }

    private function heuresCellule(int|string $minutes): Cell
    {
        return Cell::fromValue($this->minutesEnHeures($minutes), $this->heures);
    }

    private function nombreCellule(int|string $valeur): Cell
    {
        return Cell::fromValue((float) $valeur, $this->nombre);
    }

    /** Ratio « 0.8367 » → cellule en pourcentage ; NULL (non calculable) → cellule vide. */
    private function ratioCellule(?string $ratio, Style $style): Cell
    {
        return $ratio === null ? Cell::fromValue(null) : Cell::fromValue((float) $ratio, $style);
    }

    private function ouiNon(?bool $valeur): string
    {
        return match ($valeur) {
            true => __('epi.export.meta.oui'),
            false => __('epi.export.meta.non'),
            default => __('epi.export.meta.non_calculable'),
        };
    }

    /** Minutes (entier ou décimal en chaîne) → heures décimales arrondies au centième. */
    private function minutesEnHeures(int|string|null $minutes): float
    {
        return round(((float) ($minutes ?? 0)) / 60, 2);
    }

    /** Excel limite le nom d'une feuille à 31 caractères et interdit : \ / ? * [ ] */
    private function nomFeuille(string $nom): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nom), 0, 31);
    }

    private function nomMois(int $annee, int $mois): string
    {
        return ucfirst(CarbonImmutable::create($annee, $mois, 1)->locale(app()->getLocale())->isoFormat('MMMM YYYY'));
    }

    private function date(CarbonInterface $date): string
    {
        return $date->copy()->locale(app()->getLocale())->isoFormat('L');
    }
}
