<?php

namespace Tests\Unit\Models;

use App\Models\Tarif;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Normalisation du `rate_name` EpiLogg (« T 8.1 », nullable) en code de tarif. */
class TarifTest extends TestCase
{
    #[Test]
    #[DataProvider('rateNames')]
    public function normalise_le_rate_name_epilogg(?string $rateName, string $attendu): void
    {
        $this->assertSame($attendu, Tarif::codeDepuisRateName($rateName));
    }

    /** @return array<string, array{?string, string}> */
    public static function rateNames(): array
    {
        return [
            'format EpiLogg standard' => ['T 8.1', '8.1'],
            'sans préfixe' => ['9.1', '9.1'],
            'espaces parasites' => ['  T  8.2 ', '8.2'],
            'minuscule' => ['t 9.1', '9.1'],
            'NULL => activité interne' => [null, Tarif::CODE_AUTRE],
            'vide => activité interne' => ['', Tarif::CODE_AUTRE],
            'texte sans code => activité interne' => ['Comité des Fêtes', Tarif::CODE_AUTRE],
        ];
    }
}
