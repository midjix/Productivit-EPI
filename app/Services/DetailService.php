<?php

namespace App\Services;

use App\Enums\CategorieHeure;
use App\Etl\Bareme;
use App\Etl\CalculEfficacite;
use App\Models\DonneeDsk;
use App\Models\DonneeEpilogg;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Niveau de détail de l'écran « utilisateur + admin » du fichier de la Fondation :
 * sur une période libre et un périmètre (salarié, service, tarif, Total EPI, activité
 * interne), les heures par type de suivi, par tarif, par service, par type de prestation
 * et par compteur DSK, plus les indicateurs de synthèse (cascade d'efficacité).
 *
 * Contrairement à l'API de consultation, ce calcul part des FLUX BRUTS (le cache mensuel
 * n'a pas ce grain), avec le barème en vigueur à la date de chaque prestation.
 * Les filtres (types de suivi, types de prestation) ne portent que sur les prestations.
 */
class DetailService
{
    public const PERIMETRES = ['salarie', 'service', 'tarif', 'epi', 'interne'];

    /**
     * @param  list<int>  $typesSuivi  identifiants de catégories de suivi (vide = tous)
     * @param  list<string>  $typesPrestation  libellés de types de prestation (vide = tous)
     * @return array<string, mixed>
     */
    public function calculer(string $perimetre, ?int $id, CarbonInterface $du, CarbonInterface $au, array $typesSuivi = [], array $typesPrestation = []): array
    {
        $bornes = [$du->toDateString(), $au->toDateString()];
        $bareme = new Bareme;

        // ---- Prestations du périmètre (numérateur), valorisées au barème de leur date ----
        $prestations = DonneeEpilogg::query()
            ->join('categories_suivi as cs', 'cs.id', '=', 'donnees_epilogg.categorie_suivi_id')
            ->leftJoin('tarifs', 'tarifs.id', '=', 'cs.tarif_id')
            ->leftJoin('services', 'services.id', '=', 'cs.service_id')
            ->whereBetween('donnees_epilogg.date', $bornes)
            ->when($perimetre === 'salarie', fn ($q) => $q->where('donnees_epilogg.user_id', $id))
            ->when($perimetre === 'service', fn ($q) => $q->whereIn('donnees_epilogg.user_id', User::query()->where('service_id', $id)->select('id')))
            ->when($perimetre === 'tarif', fn ($q) => $q->where('cs.tarif_id', $id))
            ->when($perimetre === 'interne', fn ($q) => $q->where('tarifs.facturable', false))
            ->when($typesSuivi !== [], fn ($q) => $q->whereIn('cs.id', $typesSuivi))
            ->when($typesPrestation !== [], fn ($q) => $q->whereIn('donnees_epilogg.type_prestation', $typesPrestation))
            ->select([
                'donnees_epilogg.user_id', 'donnees_epilogg.date', 'donnees_epilogg.quantite', 'donnees_epilogg.type_prestation',
                'cs.id as categorie_id', 'cs.libelle as categorie_libelle', 'cs.tarif_id',
                'tarifs.code as tarif_code', 'tarifs.libelle as tarif_libelle', 'tarifs.facturable',
                'services.id as service_id', 'services.code as service_code', 'services.nom as service_nom',
            ])
            ->get();

        $parType = [];
        $parTarif = [];
        $parService = [];
        $parPrestation = [];
        $nb = '0.00';
        $facturables = '0.00';
        $internes = '0.00';
        $nonValorises = '0.00';
        $utilisateursPrestations = [];

        $ajouter = function (array &$cible, string|int $cle, array $base, string $quantite, ?string $minutes) {
            $cible[$cle] ??= $base + ['nb_forfaits' => '0.00', 'minutes' => '0.00'];
            $cible[$cle]['nb_forfaits'] = bcadd($cible[$cle]['nb_forfaits'], $quantite, 2);
            $cible[$cle]['minutes'] = bcadd($cible[$cle]['minutes'], $minutes ?? '0.00', 2);
        };

        foreach ($prestations as $p) {
            $quantite = (string) $p->quantite;
            $minutes = $p->tarif_id === null ? null : $bareme->valoriser((int) $p->tarif_id, $p->date, $quantite);
            $utilisateursPrestations[(int) $p->user_id] = true;
            $nb = bcadd($nb, $quantite, 2);

            if ($minutes === null) {
                $nonValorises = bcadd($nonValorises, $quantite, 2);
            } elseif ($p->facturable) {
                $facturables = bcadd($facturables, $minutes, 2);
            } else {
                $internes = bcadd($internes, $minutes, 2);
            }

            $ajouter($parType, (int) $p->categorie_id, [
                'id' => (int) $p->categorie_id, 'libelle' => $p->categorie_libelle,
                'tarif_code' => $p->tarif_code, 'facturable' => (bool) $p->facturable, 'service' => $p->service_nom,
            ], $quantite, $minutes);
            $ajouter($parTarif, $p->tarif_code ?? '', [
                'code' => $p->tarif_code, 'libelle' => $p->tarif_libelle, 'facturable' => (bool) $p->facturable,
            ], $quantite, $minutes);
            $ajouter($parService, $p->service_id === null ? 0 : (int) $p->service_id, [
                'id' => $p->service_id === null ? null : (int) $p->service_id, 'code' => $p->service_code, 'nom' => $p->service_nom,
            ], $quantite, $minutes);
            $ajouter($parPrestation, $p->type_prestation ?? '', ['libelle' => $p->type_prestation], $quantite, $minutes);
        }

        // ---- Heures DSK (dénominateur) : les salariés du périmètre ----
        $userIds = match ($perimetre) {
            'salarie' => [$id],
            'service' => User::query()->where('service_id', $id)->pluck('id')->all(),
            'epi' => null, // tous
            default => array_keys($utilisateursPrestations), // tarif, interne : les salariés ayant réalisé ces prestations
        };

        $comptes = DonneeDsk::query()
            ->join('comptes_dsk', 'comptes_dsk.id', '=', 'donnees_dsk.compte_dsk_id')
            ->whereBetween('donnees_dsk.date', $bornes)
            ->when($userIds !== null, fn ($q) => $q->whereIn('donnees_dsk.user_id', $userIds === [] ? [0] : $userIds))
            ->groupBy('comptes_dsk.id', 'comptes_dsk.no_account', 'comptes_dsk.libelle', 'comptes_dsk.categorie', 'comptes_dsk.compte_effectif')
            ->selectRaw('comptes_dsk.id, comptes_dsk.no_account, comptes_dsk.libelle, comptes_dsk.categorie, comptes_dsk.compte_effectif, SUM(donnees_dsk.minutes) AS minutes, COUNT(DISTINCT donnees_dsk.user_id) AS nb_salaries')
            ->orderBy('comptes_dsk.no_account')
            ->get();

        $parCategorie = [];
        foreach ($comptes as $c) {
            if ($c->compte_effectif) {
                $parCategorie[$c->categorie] = ($parCategorie[$c->categorie] ?? 0) + (int) $c->minutes;
            }
        }

        $args = [];
        foreach (CategorieHeure::cases() as $categorie) {
            $arg = lcfirst(str_replace('_', '', ucwords($categorie->colonneStatistique(), '_')));
            $args[$arg] = max(0, (int) ($parCategorie[$categorie->value] ?? 0));
        }

        $calcul = new CalculEfficacite(...$args, nbPrestations: $nb, minutesFacturables: $facturables, minutesActiviteInterne: $internes);
        $ratio = $calcul->ratio();
        $objectif = number_format((float) config('epi.objectif_efficacite'), 4, '.', '');

        $nbSalariesDsk = DonneeDsk::query()->whereBetween('date', $bornes)
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds === [] ? [0] : $userIds))
            ->distinct()->count('user_id');

        return [
            'synthese' => $calcul->attributs() + [
                'nb_salaries' => max($nbSalariesDsk, count($utilisateursPrestations)),
                'forfaits_non_valorises' => $nonValorises,
                'objectif' => $objectif,
                'objectif_atteint' => $ratio === null ? null : bccomp($ratio, $objectif, 4) >= 0,
            ],
            'types_suivi' => $this->trier($parType, 'libelle'),
            'tarifs' => $this->trier($parTarif, 'code'),
            'services' => $this->trier($parService, 'nom'),
            'types_prestation' => $this->trier($parPrestation, 'libelle'),
            'comptes_dsk' => $comptes->map(fn ($c) => [
                'id' => (int) $c->id,
                'no_account' => (int) $c->no_account,
                'libelle' => $c->libelle,
                'categorie' => $c->categorie,
                'categorie_label' => CategorieHeure::from($c->categorie)->label(),
                'compte_effectif' => (bool) $c->compte_effectif,
                'minutes' => (int) $c->minutes,
                'nb_salaries' => (int) $c->nb_salaries,
            ])->values()->all(),
        ];
    }

    /**
     * Tri naturel sur une clé ; les lignes sans valeur (type non qualifié, hors service) en dernier.
     *
     * @param  array<int|string, array<string, mixed>>  $lignes
     */
    private function trier(array $lignes, string $cle): array
    {
        return Collection::make($lignes)
            ->sortBy(fn (array $l) => $l[$cle] === null ? "\u{10FFFF}" : (string) $l[$cle], SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
