<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChampsTraduits;
use App\Services\DetailService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtres de l'écran « Détail » : périmètre, période libre (au plus deux ans),
 * types de suivi et types de prestation. Le périmètre est ensuite soumis aux policies.
 */
class DetailRequest extends FormRequest
{
    use ChampsTraduits;

    public const JOURS_MAX = 731;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'perimetre' => ['required', Rule::in(DetailService::PERIMETRES)],
            'id' => ['required_unless:perimetre,epi,interne', 'nullable', 'integer', 'min:1'],
            'du' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01'],
            'au' => ['required', 'date_format:Y-m-d', 'after_or_equal:du', 'before_or_equal:'.now()->addYear()->toDateString()],
            'types_suivi' => ['sometimes', 'array', 'max:50'],
            'types_suivi.*' => ['integer', Rule::exists('categories_suivi', 'id')],
            'types_prestation' => ['sometimes', 'array', 'max:50'],
            'types_prestation.*' => ['string', 'max:150'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['du', 'au'])) {
                    return;
                }
                $jours = CarbonImmutable::parse($this->input('du'))->diffInDays(CarbonImmutable::parse($this->input('au')));
                if ($jours > self::JOURS_MAX) {
                    $validator->errors()->add('au', __('epi.detail.periode_trop_longue', ['max' => self::JOURS_MAX]));
                }
            },
        ];
    }

    public function du(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('du'));
    }

    public function au(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('au'));
    }

    /** @return list<int> */
    public function typesSuivi(): array
    {
        return array_values(array_map('intval', $this->validated('types_suivi') ?? []));
    }

    /** @return list<string> */
    public function typesPrestation(): array
    {
        return array_values(array_map('strval', $this->validated('types_prestation') ?? []));
    }
}
