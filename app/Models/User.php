<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Entité « Utilisateur » du diagramme de classes.
 *
 * Le nom `User` est conservé (convention Laravel : authentification, factories,
 * policies s'appuient dessus) ; le vocabulaire métier reste « Utilisateur / Salarié ».
 * Pivots de jointure des imports (il n'existe AUCUN identifiant commun entre les sources) :
 *  - DSK     : `dsk_no_pers` (noPers) / `matricule` (codePers) ;
 *  - EpiLogg : `epilogg_user_id` (users.id EpiLogg), apparié sur l'adresse `email`
 *              (recommandation du prestataire CBC, 26/08/2026).
 */
#[Fillable([
    'name', 'email', 'password',
    'matricule', 'dsk_no_pers', 'epilogg_user_id', 'service_id', 'role', 'actif', 'date_entree', 'date_sortie',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'actif' => 'boolean',
            'date_entree' => 'date',
            'date_sortie' => 'date',
        ];
    }

    // ---- Relations -----------------------------------------------------

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function donneesDsk(): HasMany
    {
        return $this->hasMany(DonneeDsk::class);
    }

    public function donneesEpilogg(): HasMany
    {
        return $this->hasMany(DonneeEpilogg::class);
    }

    public function statistiquesMensuelles(): HasMany
    {
        return $this->hasMany(StatistiqueMensuelle::class);
    }

    // ---- Rôles (héritage de droits) -------------------------------------

    /** Vrai si l'utilisateur possède au moins les droits du rôle donné. */
    public function aRole(Role $role): bool
    {
        return $this->role->auMoins($role);
    }

    public function estAdministrateur(): bool
    {
        return $this->aRole(Role::Administrateur);
    }

    public function estCoordinateur(): bool
    {
        return $this->aRole(Role::Coordinateur);
    }

    /**
     * Un compte peut se connecter s'il est actif et que sa date de sortie
     * n'est pas passée (un salarié parti garde ses données, pas son accès).
     */
    public function peutSeConnecter(): bool
    {
        return $this->actif && ($this->date_sortie === null || $this->date_sortie->isFuture());
    }

    // ---- Scopes ----------------------------------------------------------

    /** @param  Builder<User>  $query */
    public function scopeActifs(Builder $query): void
    {
        $query->where('actif', true);
    }
}
