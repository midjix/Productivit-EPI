<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Http\Requests\Concerns\ChampsTraduits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Affectations d'un utilisateur : rôle, service, activité, email (clé d'appariement EpiLogg).
 * Garde-fou : un Administrateur ne peut ni se rétrograder ni se désactiver lui-même
 * (sinon plus personne ne pourrait administrer l'application).
 */
class ModifierUtilisateurRequest extends FormRequest
{
    use ChampsTraduits;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var User $cible */
        $cible = $this->route('utilisateur');

        return [
            'role' => ['sometimes', Rule::enum(Role::class)],
            'service_id' => ['sometimes', 'nullable', 'integer', Rule::exists('services', 'id')],
            'actif' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($cible->id)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $cible */
                $cible = $this->route('utilisateur');

                if (! $cible->is($this->user())) {
                    return;
                }

                if ($this->has('role') && $this->input('role') !== Role::Administrateur->value) {
                    $validator->errors()->add('role', __('epi.admin.propre_role'));
                }

                if ($this->has('actif') && ! $this->boolean('actif')) {
                    $validator->errors()->add('actif', __('epi.admin.propre_compte'));
                }
            },
        ];
    }
}
