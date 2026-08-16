<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Organigramme : Follow Up, AISP, PSY, Administration… (aligné sur les départements DSK). */
#[Fillable(['code', 'nom', 'actif'])]
class Service extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categoriesSuivi(): HasMany
    {
        return $this->hasMany(CategorieSuivi::class);
    }
}
