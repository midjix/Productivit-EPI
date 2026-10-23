<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue des réponses de l'API (messages d'erreur, validation, libellés) d'après
 * l'en-tête `Accept-Language` envoyé par la SPA : français (défaut), anglais, allemand.
 * La Fondation est luxembourgeoise : les trois langues administratives usuelles.
 */
class SetLocale
{
    public const LANGUES = ['fr', 'en', 'de'];

    public function handle(Request $request, Closure $next): Response
    {
        $langue = $request->getPreferredLanguage(self::LANGUES) ?? config('app.locale');

        app()->setLocale($langue);

        $reponse = $next($request);
        $reponse->headers->set('Content-Language', $langue);

        return $reponse;
    }
}
