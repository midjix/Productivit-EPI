<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;

/** Organigramme : le code vient de DSK (clé de synchronisation), seuls le libellé et l'activité se modifient. */
class ModifierServiceRequest extends FormRequest
{
    use ChampsTraduits;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nom' => ['required_without:actif', 'string', 'min:2', 'max:100'],
            'actif' => ['required_without:nom', 'boolean'],
        ];
    }
}
