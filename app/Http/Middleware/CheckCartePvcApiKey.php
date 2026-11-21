<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCartePvcApiKey
{
    /**
     * Valide l'accès à l'API Cartes PVC via clé secrète forte.
     * Sécurité durcie (audit) :
     * - Lecture via config() pour supporter config:cache
     * - Échec fermé : si la clé n'est pas configurée, accès strictement refusé (403)
     * - Clé passée EXCLUSIVEMENT par header HTTP sécurisé (pas de ?api_key dans l'URL pour éviter les fuites dans les logs)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.carte_pvc.key');

        // Échec fermé : si aucune clé n'est configurée dans l'environnement, l'accès est totalement verrouillé
        if (empty($expectedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Service API Cartes PVC désactivé : Clé API non configurée sur le serveur (définir CARTE_PVC_API_KEY).',
                'error_code' => 'API_KEY_NOT_CONFIGURED',
            ], 403);
        }

        // Récupération stricte depuis les headers uniquement (pas de paramètre GET d'URL)
        $providedKey = $request->header('X-API-KEY')
                    ?? $request->header('X-Api-Key')
                    ?? $request->bearerToken();

        if (!$providedKey || !hash_equals((string) $expectedKey, (string) $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé : Clé API invalide ou absente du header X-API-KEY.',
                'error_code' => 'UNAUTHORIZED_API_KEY',
            ], 401);
        }

        // Contrôle de liste blanche d'IP (si configurée)
        $allowedIps = array_filter(array_map('trim', explode(',', (string) config('services.carte_pvc.allowed_ips'))));
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            \Illuminate\Support\Facades\Log::channel('audit')->warning('api.carte_pvc.ip_blocked', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : Adresse IP non autorisée.',
                'error_code' => 'IP_NOT_ALLOWED',
            ], 403);
        }

        // Journalisation de sécurité dans le canal d'audit
        \Illuminate\Support\Facades\Log::channel('audit')->info('api.carte_pvc', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'key_id' => substr(hash('sha256', (string) $providedKey), 0, 8),
        ]);

        return $next($request);
    }
}
