<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;

/** Validation serveur systématique des entrées (AT1) : rien n'atteint le contrôleur sans passer ici. */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
