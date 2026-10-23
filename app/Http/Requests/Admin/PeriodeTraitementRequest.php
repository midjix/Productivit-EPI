<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Déclenchement d'un traitement (import manuel, recalcul) sur une période.
 * `source` n'est lu que par l'import. Bornes : jamais dans le futur, jamais avant 2020
 * (début des données EpiLogg exploitables), `du` ≤ `au`.
 */
class PeriodeTraitementRequest extends FormRequest
{
    use ChampsTraduits;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'in:dsk,epilogg,all'],
            'du' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:today'],
            'au' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today', Rule::when($this->filled('du'), ['after_or_equal:du'])],
        ];
    }

    public function source(): string
    {
        return (string) ($this->validated('source') ?? 'all');
    }

    public function du(): ?CarbonImmutable
    {
        $du = $this->validated('du');

        return $du === null ? null : CarbonImmutable::parse($du);
    }

    public function au(): ?CarbonImmutable
    {
        $au = $this->validated('au');

        return $au === null ? null : CarbonImmutable::parse($au);
    }
}
