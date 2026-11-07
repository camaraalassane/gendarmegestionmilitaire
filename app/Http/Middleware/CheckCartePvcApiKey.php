<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCartePvcApiKey
{
    /**
     * Valide l'accès à l'API Cartes PVC via clé secrète.
     * Accepte la clé dans :
     * 1. Le header HTTP "X-API-KEY" ou "X-Api-Key"
     * 2. Le Bearer token "Authorization: Bearer <cle>"
     * 3. Le paramètre d'URL "?api_key=<cle>"
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = env('CARTE_PVC_API_KEY', 'fama-pvc-card-secret-key-2026');

        $providedKey = $request->header('X-API-KEY')
                    ?? $request->header('X-Api-Key')
                    ?? $request->bearerToken()
                    ?? $request->query('api_key');

        if (empty($expectedKey)) {
            return $next($request);
        }

        if (!$providedKey || !hash_equals((string) $expectedKey, (string) $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé : Clé API invalide ou manquante. Veuillez fournir le header X-API-KEY.',
                'error_code' => 'UNAUTHORIZED_API_KEY',
                'hint' => 'Fournissez le header "X-API-KEY: votre_cle" ou "?api_key=votre_cle"',
            ], 401);
        }

        return $next($request);
    }
}
