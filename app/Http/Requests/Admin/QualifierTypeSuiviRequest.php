<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Qualification d'un type de suivi EpiLogg : tarif (porte le barème), service de rattachement, activité. */
class QualifierTypeSuiviRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tarif_id' => ['required_without_all:service_id,actif', 'integer', Rule::exists('tarifs', 'id')->where('actif', true)],
            'service_id' => ['sometimes', 'nullable', 'integer', Rule::exists('services', 'id')],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
