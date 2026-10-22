<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donnée brute EpiLogg : une prestation (ligne `benefits`) réalisée par un salarié un jour donné.
 *
 * `quantite` = nombre de forfaits saisis par le salarié, DÉCIMAL (ex. 1,5 forfait 9.1 = 45 min).
 * `type_prestation` = libellé descriptif EpiLogg (entretien, rapport, déplacement…), filtre d'affichage.
 * Aucune donnée sur le bénéficiaire (minimisation RGPD).
 */
#[Table('donnees_epilogg')]
#[Fillable(['user_id', 'categorie_suivi_id', 'type_prestation', 'date', 'quantite', 'reference_externe', 'import_id'])]
class DonneeEpilogg extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantite' => 'decimal:2',
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
