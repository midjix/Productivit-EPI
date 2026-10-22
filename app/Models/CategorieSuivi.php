<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de suivi EpiLogg : « 8.1 AISP », « 8.1 Follow Up », « 9.1 Service Psychologique »,
 * « Comité des Fêtes », « Coordination d'équipe »…
 *
 * Côté EpiLogg : table `attend_types` — `name` (libellé), `rate_name` (« T 8.1 », nullable),
 * `minute_by_package` (minutes), `deleted_at` (archivage). Le rapprochement se fait sur
 * `epilogg_attend_type_id` (stable), pas sur le libellé (modifiable).
 *
 * Le type de suivi ne porte PAS le barème : il pointe vers son `Tarif` (8.1, 8.2, 9.1, AUTRE),
 * qui porte la matrice de conversion historisée.
 */
#[Table('categories_suivi')]
#[Fillable(['tarif_id', 'epilogg_attend_type_id', 'libelle', 'service_id', 'actif'])]
class CategorieSuivi extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function donneesEpilogg(): HasMany
    {
        return $this->hasMany(DonneeEpilogg::class);
    }

    /** Barème applicable à une date : délégué au tarif. NULL si type non qualifié. */
    public function regleEnVigueurLe(CarbonInterface|string $date): ?RegleConversion
    {
        return $this->tarif?->regleEnVigueurLe($date);
    }

    /** Vrai si les forfaits de ce type comptent comme prestations facturables. */
    public function estFacturable(): bool
    {
        return (bool) $this->tarif?->facturable;
    }

    /** @param  Builder<CategorieSuivi>  $query */
    public function scopeActifs(Builder $query): void
    {
        $query->where('actif', true);
    }

    /** Types importés d'EpiLogg mais pas encore rattachés à un tarif par l'Administrateur. */
    public function scopeNonQualifies(Builder $query): void
    {
        $query->whereNull('tarif_id');
    }
}
