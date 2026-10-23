<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Mot de passe initial d'un salarié créé par l'import DSK (mot de passe aléatoire
 * inconnu de tous tant que l'Administrateur n'en a pas défini un).
 */
class DefinirMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }
}
