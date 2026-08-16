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
 * Toutes les durées sont en minutes ; le ratio = minutes_prestations / minutes_effectives.
 */
#[Table('statistiques_mensuelles')]
#[Fillable([
    'user_id', 'annee', 'mois',
    'minutes_effectives', 'minutes_normales', 'minutes_formation', 'minutes_conge', 'minutes_maladie', 'minutes_deplacement',
    'nb_prestations', 'minutes_prestations', 'ratio', 'calcule_le',
])]
class StatistiqueMensuelle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'mois' => 'integer',
            'minutes_effectives' => 'integer',
            'minutes_normales' => 'integer',
            'minutes_formation' => 'integer',
            'minutes_conge' => 'integer',
            'minutes_maladie' => 'integer',
            'minutes_deplacement' => 'integer',
            'nb_prestations' => 'integer',
            'minutes_prestations' => 'integer',
            'ratio' => 'decimal:4',
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
}
