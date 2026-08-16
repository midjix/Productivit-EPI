<?php

namespace App\Models;

use App\Enums\CategorieHeure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compteur DSK (noAccount) et sa qualification métier par l'Administrateur :
 * catégorie d'heures + prise en compte (ou non) dans les heures effectives.
 */
#[Table('comptes_dsk')]
#[Fillable(['no_account', 'libelle', 'type_dsk', 'categorie', 'compte_effectif', 'actif'])]
class CompteDsk extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'no_account' => 'integer',
            'type_dsk' => 'integer',
            'categorie' => CategorieHeure::class,
            'compte_effectif' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function donnees(): HasMany
    {
        return $this->hasMany(DonneeDsk::class);
    }

    /** @param  Builder<CompteDsk>  $query */
    public function scopeEffectifs(Builder $query): void
    {
        $query->where('compte_effectif', true);
    }
}
