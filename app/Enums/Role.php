<?php

namespace App\Enums;

/**
 * Rôles applicatifs — hiérarchie à héritage de droits :
 * Administrateur ⊃ Coordinateur ⊃ Salarié.
 *
 * Le niveau numérique permet d'exprimer « au moins coordinateur »
 * sans énumérer les rôles supérieurs partout dans le code.
 */
enum Role: string
{
    case Salarie = 'salarie';
    case Coordinateur = 'coordinateur';
    case Administrateur = 'administrateur';

    public function niveau(): int
    {
        return match ($this) {
            self::Salarie => 1,
            self::Coordinateur => 2,
            self::Administrateur => 3,
        };
    }

    /** Vrai si ce rôle possède au moins les droits du rôle donné. */
    public function auMoins(Role $role): bool
    {
        return $this->niveau() >= $role->niveau();
    }

    public function label(): string
    {
        return match ($this) {
            self::Salarie => 'Salarié',
            self::Coordinateur => 'Coordinateur',
            self::Administrateur => 'Administrateur',
        };
    }
}
