<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Changement de mot de passe par l'utilisateur lui-même : l'ancien mot de passe
 * est exigé (un jeton volé ne suffit pas à verrouiller le compte), le nouveau
 * suit la même politique que celle imposée par l'Administrateur.
 */
class ChangerMotDePasseRequest extends FormRequest
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
            'mot_de_passe_actuel' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', 'different:mot_de_passe_actuel', Password::defaults()],
        ];
    }
}
