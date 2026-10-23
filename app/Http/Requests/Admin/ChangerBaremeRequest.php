<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nouvelle règle de la matrice de conversion pour un tarif.
 * La cohérence temporelle (date postérieure à la règle en vigueur) est vérifiée
 * par MatriceConversionService, dans la transaction.
 */
class ChangerBaremeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.now()->addYear()->toDateString()],
            'commentaire' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
