<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par jeton (Laravel Sanctum) pour la SPA Vue.js.
 *
 * Contrôleur mince : validation (LoginRequest) + délégation au modèle.
 * Le jeton est généré une fois, affiché une fois ; seule son empreinte
 * SHA-256 est stockée (table personal_access_tokens).
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        // Même message pour « email inconnu » et « mot de passe faux » :
        // on ne révèle pas quelles adresses existent (énumération de comptes).
        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Identifiants incorrects.'),
            ]);
        }

        if (! $user->peutSeConnecter()) {
            abort(403, 'Ce compte est désactivé.');
        }

        $token = $user->createToken($request->string('device_name', 'spa')->toString());

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user->load('service')),
        ], 201);
    }

    /** Révoque uniquement le jeton utilisé par cette requête (les autres appareils restent connectés). */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('service'));
    }
}
