<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CarteController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes ; invalidé aussitôt par flush() à chaque modification

    /** Invalide la carte (à appeler quand un militaire, une région ou une unité change). */
    public static function flush(): void
    {
        Cache::forever('carte:ver', (int) Cache::get('carte:ver', 1) + 1);
    }

    public function index()
    {
        $cle = 'carte:v' . Cache::get('carte:ver', 1);

        return Inertia::render('Carte/Index', Cache::remember($cle, self::CACHE_TTL, fn () => $this->construire()));
    }

    private function construire(): array
    {
        // 1) Régions + unités : 2 requêtes
        $regions = Region::with(['unites' => fn ($q) => $q
            ->select('id', 'region_id', 'nom', 'type', 'ville', 'latitude', 'longitude', 'est_operationnelle')
            ->orderBy('nom')])
            ->orderByRaw('COALESCE(ordre, 999)')->orderBy('nom')
            ->get(['id', 'code', 'nom', 'chef_lieu']);

        // 2) Effectifs : UNE requête d'agrégation (plus de boucle PHP militaire × région × unité)
        $retire = "(LOWER(COALESCE(statut,'')) LIKE 'retrait%' OR LOWER(COALESCE(statut,'')) LIKE 'radi%' OR LOWER(COALESCE(statut,'')) LIKE 'd_c_s%')";
        $absent = "(LOWER(COALESCE(statut,'')) LIKE '%cong%' OR LOWER(COALESCE(statut,'')) LIKE '%miss%'
                 OR LOWER(COALESCE(statut,'')) LIKE '%stag%' OR LOWER(COALESCE(statut,'')) LIKE '%malad%'
                 OR LOWER(COALESCE(statut,'')) LIKE '%susp%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%cong%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%miss%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%stag%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%form%')";

        $lignes = Militaire::query()                    // SoftDeletes exclut déjà les supprimés
            ->whereRaw("NOT {$retire}")
            ->selectRaw("region_id, unite_id, COUNT(*) AS total,
                SUM(CASE WHEN {$absent} THEN 1 ELSE 0 END) AS absents,
                SUM(CASE WHEN LOWER(COALESCE(sexe,'')) LIKE 'f%' THEN 1 ELSE 0 END) AS femmes")
            ->groupBy('region_id', 'unite_id')
            ->get();

        $parUnite = $lignes->whereNotNull('unite_id')->keyBy('unite_id');
        $parRegion = $lignes->whereNotNull('region_id')->groupBy('region_id');
        $nonRattaches = (int) $lignes->whereNull('region_id')->whereNull('unite_id')->sum('total');

        // 3) Mise en forme pour la vue
        $out = $regions->map(function ($r) use ($parUnite, $parRegion) {
            $l = $parRegion->get($r->id, collect());
            $unites = $r->unites->map(fn ($u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'type' => $u->type,
                'ville' => $u->ville,
                'lat' => $u->latitude !== null ? (float) $u->latitude : null,
                'lon' => $u->longitude !== null ? (float) $u->longitude : null,
                'operationnelle' => (bool) $u->est_operationnelle,
                'effectif' => (int) ($parUnite->get($u->id)->total ?? 0),
            ])->values();

            $total = (int) $l->sum('total');

            return [
                'id' => $r->id,
                'code' => $r->code,
                'nom' => $r->nom,
                'chef_lieu' => $r->chef_lieu,
                'effectif' => $total,
                'absents' => (int) $l->sum('absents'),
                'femmes' => (int) $l->sum('femmes'),
                'sans_unite' => max(0, $total - (int) $unites->sum('effectif')),
                'unites' => $unites,
            ];
        })->values();

        return [
            'regions' => $out,
            'stats' => [
                'non_rattaches' => $nonRattaches,
                'generated_at' => now()->format('d/m/Y H:i'),
            ],
        ];
    }
}
