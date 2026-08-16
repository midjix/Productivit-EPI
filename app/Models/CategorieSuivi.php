<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Type d'intervention EpiLogg (forfait) : 8.1 AISP, 9.1 Service Psychologique… */
#[Table('categories_suivi')]
#[Fillable(['code', 'libelle', 'service_id', 'actif'])]
class CategorieSuivi extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reglesConversion(): HasMany
    {
        return $this->hasMany(RegleConversion::class);
    }

    public function donneesEpilogg(): HasMany
    {
        return $this->hasMany(DonneeEpilogg::class);
    }

    /**
     * Règle de conversion applicable à une date donnée (barème de l'époque).
     * Cœur de l'historisation : le même forfait peut valoir 60 min en 2024 et 45 min en 2026.
     */
    public function regleEnVigueurLe(CarbonInterface|string $date): ?RegleConversion
    {
        return $this->reglesConversion()->enVigueurLe($date)->first();
    }
}
