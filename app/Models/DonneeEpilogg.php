<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donnée brute EpiLogg : prestation(s) d'un forfait réalisée(s) par un salarié un jour donné.
 * Aucune donnée sur le bénéficiaire (minimisation RGPD).
 */
#[Table('donnees_epilogg')]
#[Fillable(['user_id', 'categorie_suivi_id', 'date', 'quantite', 'reference_externe', 'import_id'])]
class DonneeEpilogg extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantite' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categorieSuivi(): BelongsTo
    {
        return $this->belongsTo(CategorieSuivi::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
