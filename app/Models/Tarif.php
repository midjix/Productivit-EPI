<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarif = nature du service aux yeux de l'État (8.1, 8.2, 9.1) ou « AUTRE »
 * pour les activités internes (comités, coordination, formation…).
 *
 * C'est le tarif qui porte la valeur en minutes d'un forfait, via la matrice
 * historisée `regles_conversion` : 8.1 = 60 min, 8.2 = 60 min, 9.1 = 30 min,
 * AUTRE = 60 min (barème 2026, fichier « Données app EPI.xlsx »).
 * Plusieurs types de suivi partagent un même tarif (8.1 AISP, 8.1 Follow Up…).
 */
#[Fillable(['code', 'libelle', 'facturable', 'actif'])]
class Tarif extends Model
{
    use HasFactory;

    /** Code du tarif attribué aux types de suivi sans rate_name EpiLogg. */
    public const CODE_AUTRE = 'AUTRE';

    protected function casts(): array
    {
        return [
            'facturable' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function categoriesSuivi(): HasMany
    {
        return $this->hasMany(CategorieSuivi::class);
    }

    public function reglesConversion(): HasMany
    {
        return $this->hasMany(RegleConversion::class);
    }

    /**
     * Règle de conversion applicable à une date donnée (barème de l'époque).
     * Cœur de l'historisation : le même tarif peut valoir 60 min en 2025 et 45 min en 2026.
     */
    public function regleEnVigueurLe(CarbonInterface|string $date): ?RegleConversion
    {
        return $this->reglesConversion()->enVigueurLe($date)->first();
    }

    /** @param  Builder<Tarif>  $query */
    public function scopeFacturables(Builder $query): void
    {
        $query->where('facturable', true);
    }

    /**
     * Normalise le `rate_name` EpiLogg (« T 8.1 », « 8.1 », NULL) en code de tarif.
     * NULL ou non reconnu => AUTRE (activité interne).
     */
    public static function codeDepuisRateName(?string $rateName): string
    {
        if ($rateName === null) {
            return self::CODE_AUTRE;
        }

        return preg_match('/(\d+\.\d+)/', $rateName, $m) ? $m[1] : self::CODE_AUTRE;
    }
}
