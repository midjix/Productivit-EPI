<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategorieHeure;
use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Qualification d'un compteur DSK : catégorie d'heures, prise en compte dans le total, activité. */
class QualifierCompteDskRequest extends FormRequest
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
            'categorie' => ['required_without_all:compte_effectif,actif', Rule::enum(CategorieHeure::class)],
            'compte_effectif' => ['required_without_all:categorie,actif', 'boolean'],
            'actif' => ['required_without_all:categorie,compte_effectif', 'boolean'],
        ];
    }
}
