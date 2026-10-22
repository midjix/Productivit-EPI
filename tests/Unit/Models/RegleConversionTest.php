<?php

namespace Tests\Unit\Models;

use App\Models\RegleConversion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Logique d'intervalle de validité d'une règle, sans requête en base.
 * Hérite du TestCase Laravel (et non PHPUnit) : les casts `date` d'Eloquent
 * ont besoin de l'application démarrée, même sans toucher à la base.
 */
class RegleConversionTest extends TestCase
{
    #[Test]
    #[DataProvider('dates')]
    public function couvre_une_date_selon_son_intervalle(?string $fin, string $date, bool $attendu): void
    {
        $regle = new RegleConversion(['minutes' => 60, 'date_debut_validite' => '2026-01-01', 'date_fin_validite' => $fin]);

        $this->assertSame($attendu, $regle->couvre($date));
    }

    /** @return array<string, array{?string, string, bool}> */
    public static function dates(): array
    {
        return [
            'veille du début, règle ouverte' => [null, '2025-12-31', false],
            'jour du début, règle ouverte' => [null, '2026-01-01', true],
            'loin après, règle ouverte' => [null, '2030-06-15', true],
            'dans l\'intervalle, règle close' => ['2026-06-30', '2026-03-15', true],
            'dernier jour, règle close' => ['2026-06-30', '2026-06-30', true],
            'lendemain de la fin, règle close' => ['2026-06-30', '2026-07-01', false],
        ];
    }

    #[Test]
    public function est_ouverte_si_aucune_date_de_fin(): void
    {
        $this->assertTrue((new RegleConversion(['date_fin_validite' => null]))->estOuverte());
        $this->assertFalse((new RegleConversion(['date_fin_validite' => '2026-06-30']))->estOuverte());
    }
}
