<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Donnée brute DSK : minutes d'un compte pour un salarié et un jour. */
#[Table('donnees_dsk')]
#[Fillable(['user_id', 'compte_dsk_id', 'date', 'minutes', 'import_id'])]
class DonneeDsk extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'minutes' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteDsk::class, 'compte_dsk_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
