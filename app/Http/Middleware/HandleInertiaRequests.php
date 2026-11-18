<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Alerte;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Données partagées avec toutes les vues Inertia
        $sharedData = [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'duplicates' => fn () => $request->session()->get('duplicates'),
            'import_summary' => fn () => $request->session()->get('import_summary'),
        ];

        // Ajouter le compteur d'alertes si l'utilisateur est connecté (mis en cache 120s pour la rapidité)
        if ($request->user()) {
            $sharedData['alertesCount'] = Cache::remember('alertes:non_vues_count_gendarme', 120, function () {
                return Alerte::where('est_vue', false)->count();
            });
        }

        return $sharedData;
    }
}