<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Règle de la matrice de conversion forfait → minutes, bornée dans le temps.
 *
 * Une règle n'est JAMAIS modifiée après coup : tout changement de barème passe
 * par la clôture de la règle courante et la création d'une nouvelle (voir EtlService /
 * service de gestion de la matrice). `date_fin_validite` NULL = règle en vigueur.
 */
#[Table('regles_conversion')]
#[Fillable(['categorie_suivi_id', 'minutes', 'date_debut_validite', 'date_fin_validite', 'commentaire', 'created_by'])]
class RegleConversion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'date_debut_validite' => 'date',
            'date_fin_validite' => 'date',
        ];
    }

    public function categorieSuivi(): BelongsTo
    {
        return $this->belongsTo(CategorieSuivi::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Scopes ----------------------------------------------------------

    /**
     * Règles en vigueur à une date donnée :
     * date_debut_validite <= D et (date_fin_validite est NULL ou >= D).
     *
     * @param  Builder<RegleConversion>  $query
     */
    public function scopeEnVigueurLe(Builder $query, CarbonInterface|string $date): void
    {
        $jour = Carbon::parse($date)->toDateString();

        $query->whereDate('date_debut_validite', '<=', $jour)
            ->where(fn (Builder $q) => $q
                ->whereNull('date_fin_validite')
                ->orWhereDate('date_fin_validite', '>=', $jour));
    }

    /** @param  Builder<RegleConversion>  $query */
    public function scopeOuvertes(Builder $query): void
    {
        $query->whereNull('date_fin_validite');
    }

    // ---- Helpers ---------------------------------------------------------

    public function estOuverte(): bool
    {
        return $this->date_fin_validite === null;
    }

    public function couvre(CarbonInterface|string $date): bool
    {
        $jour = Carbon::parse($date)->startOfDay();

        return $this->date_debut_validite->lte($jour)
            && ($this->date_fin_validite === null || $this->date_fin_validite->gte($jour));
    }
}
