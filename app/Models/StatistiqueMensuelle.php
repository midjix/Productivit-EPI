<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cache mensuel consolidé (salarié × mois) lu par l'API de consultation.
 *
 * Indicateurs validés avec la Fondation (« Données app EPI.xlsx ») :
 *   minutes_total        = « Total hrs dispo » (somme des comptes DSK effectifs)
 *   minutes_dispo_epi    = total - congés - maladie
 *   minutes_dispo_client = dispo EPI - formation - supervision - activité interne
 *   ratio (efficacité réelle) = prestations facturables / dispo client
 *   ratio_brut                = (facturables + activité interne) / total
 *
 * Les heures DSK sont des minutes entières ; les prestations sont décimales
 * (quantité de forfaits décimale × barème). L'objectif d'efficacité (0,75) est
 * un paramètre de configuration (config/epi.php), pas une donnée du cache.
 */
#[Table('statistiques_mensuelles')]
#[Fillable([
    'user_id', 'annee', 'mois',
    'minutes_total', 'minutes_normales', 'minutes_conge', 'minutes_maladie',
    'minutes_formation', 'minutes_supervision', 'minutes_deplacement', 'minutes_autres',
    'nb_prestations', 'minutes_prestations_facturables', 'minutes_activite_interne',
    'minutes_dispo_epi', 'minutes_dispo_client',
    'ratio', 'ratio_brut', 'calcule_le',
])]
class StatistiqueMensuelle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'mois' => 'integer',
            'minutes_total' => 'integer',
            'minutes_normales' => 'integer',
            'minutes_conge' => 'integer',
            'minutes_maladie' => 'integer',
            'minutes_formation' => 'integer',
            'minutes_supervision' => 'integer',
            'minutes_deplacement' => 'integer',
            'minutes_autres' => 'integer',
            'nb_prestations' => 'decimal:2',
            'minutes_prestations_facturables' => 'decimal:2',
            'minutes_activite_interne' => 'decimal:2',
            'minutes_dispo_epi' => 'integer',
            'minutes_dispo_client' => 'decimal:2',
            'ratio' => 'decimal:4',
            'ratio_brut' => 'decimal:4',
            'calcule_le' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @param  Builder<StatistiqueMensuelle>  $query */
    public function scopePeriode(Builder $query, int $annee, ?int $mois = null): void
    {
        $query->where('annee', $annee);

        if ($mois !== null) {
            $query->where('mois', $mois);
        }
    }

    /** Vrai si l'efficacité réelle atteint l'objectif fixé par la Fondation. */
    public function atteintObjectif(): ?bool
    {
        if ($this->ratio === null) {
            return null;
        }

        return (float) $this->ratio >= (float) config('epi.objectif_efficacite');
    }
}
