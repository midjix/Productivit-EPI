<?php

namespace App\Etl;

/**
 * Moteur de calcul de l'efficacité d'un salarié sur un mois — objet-valeur PUR
 * (aucune dépendance à la base), donc testable unitairement contre le jeu d'essai
 * de la Fondation (« Données app EPI.xlsx », exemple avril 2026).
 *
 * Cascade validée avec la Fondation :
 *   total        = somme des minutes DSK des comptes effectifs
 *   dispo EPI    = total − congés − maladie
 *   dispo client = dispo EPI − formation − supervision − activité interne
 *   efficacité   = prestations facturables / dispo client   (NULL si dispo client ≤ 0)
 *   brute        = (facturables + activité interne) / total (NULL si total = 0)
 *
 * Les minutes DSK sont entières ; les prestations sont des décimaux exacts (bcmath),
 * jamais des flottants : 0,25 forfait × 30 min = 7,50 min, sans erreur d'arrondi.
 */
final readonly class CalculEfficacite
{
    private const PRECISION = 2;

    private const PRECISION_RATIO = 4;

    public function __construct(
        public int $minutesNormales = 0,
        public int $minutesConge = 0,
        public int $minutesMaladie = 0,
        public int $minutesFormation = 0,
        public int $minutesSupervision = 0,
        public int $minutesDeplacement = 0,
        public int $minutesAutres = 0,
        public string $nbPrestations = '0.00',
        public string $minutesFacturables = '0.00',
        public string $minutesActiviteInterne = '0.00',
    ) {}

    public function minutesTotal(): int
    {
        return $this->minutesNormales + $this->minutesConge + $this->minutesMaladie
            + $this->minutesFormation + $this->minutesSupervision + $this->minutesDeplacement + $this->minutesAutres;
    }

    public function minutesDispoEpi(): int
    {
        return $this->minutesTotal() - $this->minutesConge - $this->minutesMaladie;
    }

    public function minutesDispoClient(): string
    {
        $base = (string) ($this->minutesDispoEpi() - $this->minutesFormation - $this->minutesSupervision);

        return bcsub($base, $this->minutesActiviteInterne, self::PRECISION);
    }

    /** Efficacité réelle : facturables / dispo client. */
    public function ratio(): ?string
    {
        $dispoClient = $this->minutesDispoClient();

        return bccomp($dispoClient, '0', self::PRECISION) <= 0
            ? null
            : bcdiv($this->minutesFacturables, $dispoClient, self::PRECISION_RATIO);
    }

    /** Efficacité brute : (facturables + activité interne) / total. */
    public function ratioBrut(): ?string
    {
        $total = $this->minutesTotal();

        return $total <= 0
            ? null
            : bcdiv(bcadd($this->minutesFacturables, $this->minutesActiviteInterne, self::PRECISION), (string) $total, self::PRECISION_RATIO);
    }

    /** Colonnes de `statistiques_mensuelles`. @return array<string, int|string|null> */
    public function attributs(): array
    {
        return [
            'minutes_total' => $this->minutesTotal(),
            'minutes_normales' => $this->minutesNormales,
            'minutes_conge' => $this->minutesConge,
            'minutes_maladie' => $this->minutesMaladie,
            'minutes_formation' => $this->minutesFormation,
            'minutes_supervision' => $this->minutesSupervision,
            'minutes_deplacement' => $this->minutesDeplacement,
            'minutes_autres' => $this->minutesAutres,
            'nb_prestations' => $this->nbPrestations,
            'minutes_prestations_facturables' => $this->minutesFacturables,
            'minutes_activite_interne' => $this->minutesActiviteInterne,
            'minutes_dispo_epi' => $this->minutesDispoEpi(),
            'minutes_dispo_client' => $this->minutesDispoClient(),
            'ratio' => $this->ratio(),
            'ratio_brut' => $this->ratioBrut(),
        ];
    }
}
