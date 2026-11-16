<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;
use App\Support\Effectif;
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
        return Inertia::render('Carte/Index', $this->donnees() + ['regionInitiale' => request('region')]);
    }

    /** Données de la carte (en cache). Utilisées par l'écran ET par l'export Excel : chiffres identiques. */
    public function donnees(): array
    {
        $cle = 'carte:v' . Cache::get('carte:ver', 1);

        return Cache::remember($cle, self::CACHE_TTL, fn () => $this->construire());
    }

    private function construire(): array
    {
        // 1) Régions + unités
        $regions = Region::with(['unites' => fn ($q) => $q
            ->select('id', 'region_id', 'nom', 'type', 'ville', 'latitude', 'longitude', 'est_operationnelle')
            ->orderBy('nom')])
            ->orderByRaw('COALESCE(ordre, 999)')->orderBy('nom')
            ->get(['id', 'code', 'nom', 'chef_lieu']);

        // 2) Effectif en activité (actifs, détachés, missions, stages) : définition centralisée dans Effectif.php
        //    Les radiés, retraités, déserteurs et décédés sont comptabilisés à part (hors effectif).
        $absent = "(LOWER(COALESCE(position_actuelle,'')) LIKE '%cong%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%miss%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%stag%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%form%')";
        $cas = fn (string $motif) => 'SUM(CASE WHEN ' . Effectif::sqlStatut($motif) . ' THEN 1 ELSE 0 END)';

        $lignes = Militaire::query()
            ->whereRaw(Effectif::sqlActivite())
            ->selectRaw("region_id, unite_id, COUNT(*) AS total,
                SUM(CASE WHEN {$absent} THEN 1 ELSE 0 END) AS absents,
                SUM(CASE WHEN LOWER(COALESCE(sexe,'')) LIKE 'f%' THEN 1 ELSE 0 END) AS femmes,
                SUM(CASE WHEN statut_code = 1 THEN 1 ELSE 0 END) AS n_actifs,
                SUM(CASE WHEN statut_code = 2 THEN 1 ELSE 0 END) AS n_detaches,
                SUM(CASE WHEN statut_code = 3 THEN 1 ELSE 0 END) AS n_missions,
                SUM(CASE WHEN statut_code = 4 THEN 1 ELSE 0 END) AS n_stages")
            ->groupBy('region_id', 'unite_id')
            ->get();

        // 2 bis) Hors effectif : Radiés (8), Déserteurs (6), Décédés (9) et Retraités (7) (comptabilisés séparément)
        $annee = (int) now()->year;
        $debut = now()->setDate($annee, 1, 1)->startOfDay()->format('Y-m-d H:i:s');
        $fin = now()->setDate($annee, 12, 31)->endOfDay()->format('Y-m-d H:i:s');

        $horsEffectif = Militaire::query()
            ->selectRaw("region_id,
                SUM(CASE WHEN statut_code = 8 THEN 1 ELSE 0 END) AS radies,
                SUM(CASE WHEN statut_code = 6 THEN 1 ELSE 0 END) AS deserteurs,
                SUM(CASE WHEN (statut_code = 9 OR date_deces IS NOT NULL)
                          AND (date_deces BETWEEN ? AND ? OR (date_deces IS NULL AND updated_at BETWEEN ? AND ?))
                         THEN 1 ELSE 0 END) AS decedes", [$debut, $fin, $debut, $fin])
            ->groupBy('region_id')
            ->get()
            ->keyBy(fn ($l) => $l->region_id ?? 0);

        // Retraites de l'année : exactement le même calcul (et le même cache) que le KPI du dashboard
        $retraitesN = app(DashboardController::class)->getRetraitesBoth()['retraitesN']['data'] ?? [];
        $retraitesParRegion = [];
        foreach (array_chunk(array_column($retraitesN, 'id'), 500) as $ids) {
            foreach (Militaire::whereIn('id', $ids)->pluck('region_id') as $rid) {
                $retraitesParRegion[$rid ?? 0] = ($retraitesParRegion[$rid ?? 0] ?? 0) + 1;
            }
        }

        $parUnite = $lignes->whereNotNull('unite_id')->keyBy('unite_id');
        $parRegion = $lignes->whereNotNull('region_id')->groupBy('region_id');
        $nonRattaches = (int) $lignes->whereNull('region_id')->whereNull('unite_id')->sum('total');

        // Liste nominative des recrues et militaires actifs sans région ni unité pour correction rapide
        $nonRattachesListe = Militaire::query()
            ->whereRaw(Effectif::sqlActivite())
            ->whereNull('region_id')
            ->where(function ($q) {
                $q->whereNull('unite_id')->orWhere('unite_id', 0);
            })
            ->select('id', 'matricule', 'mle_grm', 'nom', 'prenom', 'grade_actuel', 'statut', 'position_actuelle')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        // 3) Mise en forme pour la vue
        $out = $regions->map(function ($r) use ($parUnite, $parRegion, $horsEffectif, $retraitesParRegion) {
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
            $h = $horsEffectif->get($r->id);

            return [
                'id' => $r->id,
                'code' => $r->code,
                'nom' => $r->nom,
                'chef_lieu' => $r->chef_lieu,
                'effectif' => $total,
                'absents' => (int) $l->sum('absents'),
                'femmes' => (int) $l->sum('femmes'),
                // Détail en activité
                'actifs' => (int) $l->sum('n_actifs'),
                'detaches' => (int) $l->sum('n_detaches'),
                'missions' => (int) $l->sum('n_missions'),
                'stages' => (int) $l->sum('n_stages'),
                // Hors effectif (comptabilisés à part)
                'radies' => (int) ($h->radies ?? 0),
                'retraites' => (int) ($retraitesParRegion[$r->id] ?? 0),
                'deserteurs' => (int) ($h->deserteurs ?? 0),
                'decedes' => (int) ($h->decedes ?? 0),
                'sans_unite' => max(0, $total - (int) $unites->sum('effectif')),
                'unites' => $unites,
            ];
        })->values();

        return [
            'regions' => $out,
            'non_rattaches_liste' => $nonRattachesListe,
            'stats' => [
                'non_rattaches' => $nonRattaches,
                'annee' => $annee,
                'hors_region' => [
                    'actifs' => (int) $lignes->whereNull('region_id')->sum('n_actifs'),
                    'detaches' => (int) $lignes->whereNull('region_id')->sum('n_detaches'),
                    'missions' => (int) $lignes->whereNull('region_id')->sum('n_missions'),
                    'stages' => (int) $lignes->whereNull('region_id')->sum('n_stages'),
                    'radies' => (int) ($horsEffectif->get(0)->radies ?? 0),
                    'retraites' => (int) ($retraitesParRegion[0] ?? 0),
                    'deserteurs' => (int) ($horsEffectif->get(0)->deserteurs ?? 0),
                    'decedes' => (int) ($horsEffectif->get(0)->decedes ?? 0),
                ],
                'generated_at' => now()->format('d/m/Y H:i'),
            ],
        ];
    }
}
