<?php

namespace Tests\Unit\Enums;

use App\Enums\CategorieHeure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Le lien catégorie d'heures DSK -> colonne du cache est porté par l'énumération :
 * le moteur de calcul ne doit jamais avoir de `switch` à maintenir.
 */
class CategorieHeureTest extends TestCase
{
    #[Test]
    public function chaque_categorie_a_une_colonne_de_cache_distincte(): void
    {
        $colonnes = array_map(fn (CategorieHeure $c) => $c->colonneStatistique(), CategorieHeure::cases());

        $this->assertCount(count(CategorieHeure::cases()), array_unique($colonnes));

        foreach ($colonnes as $colonne) {
            $this->assertStringStartsWith('minutes_', $colonne);
        }
    }

    #[Test]
    public function les_colonnes_correspondent_au_schema_de_statistiques_mensuelles(): void
    {
        $this->assertSame('minutes_normales', CategorieHeure::Normal->colonneStatistique());
        $this->assertSame('minutes_conge', CategorieHeure::Conge->colonneStatistique());
        $this->assertSame('minutes_maladie', CategorieHeure::Maladie->colonneStatistique());
        $this->assertSame('minutes_formation', CategorieHeure::Formation->colonneStatistique());
        $this->assertSame('minutes_supervision', CategorieHeure::Supervision->colonneStatistique());
        $this->assertSame('minutes_deplacement', CategorieHeure::Deplacement->colonneStatistique());
        $this->assertSame('minutes_autres', CategorieHeure::Autre->colonneStatistique());
    }
}
