<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DefinirMotDePasseRequest;
use App\Http\Requests\Admin\ModifierUtilisateurRequest;
use App\Http\Resources\Admin\UtilisateurAdminResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Cas d'utilisation « Gérer l'organigramme et les affectations » : rôles, services,
 * activité, email (clé d'appariement EpiLogg) et mot de passe initial.
 * Les identités viennent de l'import DSK ; l'Administrateur ne crée pas d'utilisateur à la main.
 */
class UtilisateurController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'service_id' => ['sometimes', 'integer'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'actif' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $utilisateurs = User::query()
            ->with('service:id,code,nom')
            ->when(isset($filtres['service_id']), fn ($q) => $q->where('service_id', $filtres['service_id']))
            ->when(isset($filtres['role']), fn ($q) => $q->where('role', $filtres['role']))
            ->when(isset($filtres['actif']), fn ($q) => $q->where('actif', $request->boolean('actif')))
            ->when($filtres['q'] ?? null, fn ($q, $texte) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$texte}%")
                ->orWhere('email', 'like', "%{$texte}%")))
            ->orderBy('name')
            ->paginate(25);

        return UtilisateurAdminResource::collection($utilisateurs);
    }

    public function update(ModifierUtilisateurRequest $request, User $utilisateur): UtilisateurAdminResource
    {
        $utilisateur->fill($request->validated())->save();

        // Un compte désactivé perd immédiatement ses sessions ouvertes.
        if ($request->has('actif') && ! $utilisateur->actif) {
            $utilisateur->tokens()->delete();
        }

        return new UtilisateurAdminResource($utilisateur->load('service:id,code,nom'));
    }

    /** Mot de passe initial (ou réinitialisation) ; toutes les sessions existantes sont révoquées. */
    public function definirMotDePasse(DefinirMotDePasseRequest $request, User $utilisateur): JsonResponse
    {
        $utilisateur->forceFill(['password' => $request->validated('password')])->save();
        $utilisateur->tokens()->delete();

        return response()->json(status: 204);
    }
}
