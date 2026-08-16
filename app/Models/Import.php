<?php

namespace App\Models;

use App\Enums\SourceImport;
use App\Enums\StatutImport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'une exécution de l'ETL (import DSK ou EpiLogg). */
#[Fillable(['source', 'statut', 'periode_debut', 'periode_fin', 'nb_lignes', 'message', 'user_id', 'demarre_le', 'termine_le'])]
class Import extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => SourceImport::class,
            'statut' => StatutImport::class,
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'nb_lignes' => 'integer',
            'demarre_le' => 'datetime',
            'termine_le' => 'datetime',
        ];
    }

    /** Déclencheur humain (NULL = planificateur CRON). */
    public function declencheur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
