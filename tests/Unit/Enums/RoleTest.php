<?php

namespace Tests\Unit\Enums;

use App\Enums\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Héritage de droits : Administrateur ⊃ Coordinateur ⊃ Salarié.
 * Étend Tests\TestCase : label() lit les traductions (conteneur nécessaire).
 */
class RoleTest extends TestCase
{
    #[Test]
    public function les_niveaux_sont_strictement_croissants(): void
    {
        $this->assertLessThan(Role::Coordinateur->niveau(), Role::Salarie->niveau());
        $this->assertLessThan(Role::Administrateur->niveau(), Role::Coordinateur->niveau());
    }

    #[Test]
    #[DataProvider('heritage')]
    public function au_moins_respecte_l_heritage(Role $possede, Role $requis, bool $attendu): void
    {
        $this->assertSame($attendu, $possede->auMoins($requis));
    }

    /** @return array<string, array{Role, Role, bool}> */
    public static function heritage(): array
    {
        return [
            'salarié a les droits salarié' => [Role::Salarie, Role::Salarie, true],
            'salarié n\'a pas les droits coordinateur' => [Role::Salarie, Role::Coordinateur, false],
            'salarié n\'a pas les droits administrateur' => [Role::Salarie, Role::Administrateur, false],
            'coordinateur a les droits salarié' => [Role::Coordinateur, Role::Salarie, true],
            'coordinateur n\'a pas les droits administrateur' => [Role::Coordinateur, Role::Administrateur, false],
            'administrateur a tous les droits' => [Role::Administrateur, Role::Salarie, true],
            'administrateur a les droits coordinateur' => [Role::Administrateur, Role::Coordinateur, true],
        ];
    }

    #[Test]
    public function chaque_role_a_un_libelle(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotEmpty($role->label());
        }
    }
}
