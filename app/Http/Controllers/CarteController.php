<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Region;
use App\Models\Unite;
use App\Models\Theatre;
use App\Models\Grade;
use App\Services\RegionGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
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
            ->select('id', 'region_id', 'nom', 'code', 'type', 'type_categorie', 'ville', 'latitude', 'longitude', 'est_operationnelle')
            ->orderBy('nom')])
            ->orderByRaw('COALESCE(ordre, 999)')->orderBy('nom')
            ->get(['id', 'code', 'nom', 'chef_lieu', 'latitude', 'longitude']);

        // 2) Effectif en activité (actifs, détachés, missions, stages) : définition centralisée dans Effectif.php
        //    Les radiés, retraités, déserteurs et décédés sont comptabilisés à part (hors effectif).
        $absent = "(LOWER(COALESCE(position_actuelle,'')) LIKE '%cong%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%miss%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%stag%'
                 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%form%')";
        $cas = fn (string $motif) => 'SUM(CASE WHEN ' . Effectif::sqlStatut($motif) . ' THEN 1 ELSE 0 END)';

        $grmId = Effectif::idOrganismeGrm();
        $sqlDetache = "(COALESCE(statut_code, 0) = 2 OR COALESCE(position_id, 0) = 26 OR (organisme_detachement_id IS NOT NULL AND organisme_detachement_id != {$grmId}) OR LOWER(COALESCE(position_actuelle,'')) LIKE '%détach%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%detach%')";
        $sqlMission = "(COALESCE(position_id, 0) = 29 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%miss%' OR COALESCE(statut_code, 0) = 3)";
        $sqlStage = "(COALESCE(position_id, 0) = 28 OR LOWER(COALESCE(position_actuelle,'')) LIKE '%stag%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%format%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%cours-sup%' OR LOWER(COALESCE(position_actuelle,'')) LIKE '%ecole%' OR COALESCE(statut_code, 0) = 4)";
        $sqlActifStrict = "(NOT ({$sqlDetache}) AND NOT ({$sqlMission}) AND NOT ({$sqlStage}))";

        $lignes = Militaire::query()
            ->whereRaw(Effectif::sqlActivite())
            ->selectRaw("region_id, unite_id, COUNT(*) AS total,
                SUM(CASE WHEN {$absent} THEN 1 ELSE 0 END) AS absents,
                SUM(CASE WHEN LOWER(COALESCE(sexe,'')) LIKE 'f%' THEN 1 ELSE 0 END) AS femmes,
                SUM(CASE WHEN {$sqlActifStrict} THEN 1 ELSE 0 END) AS n_actifs,
                SUM(CASE WHEN {$sqlDetache} THEN 1 ELSE 0 END) AS n_detaches,
                SUM(CASE WHEN {$sqlMission} THEN 1 ELSE 0 END) AS n_missions,
                SUM(CASE WHEN {$sqlStage} THEN 1 ELSE 0 END) AS n_stages")
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
                          AND date_deces BETWEEN ? AND ?
                         THEN 1 ELSE 0 END) AS decedes", [$debut, $fin])
            ->groupBy('region_id')
            ->get()
            ->keyBy(fn ($l) => $l->region_id ?? 0);

        // Retraites de l'année : calcul direct et groupé par région en SQL
        $retraitesParRegion = \Illuminate\Support\Facades\DB::table('militaires')
            ->join('grades', 'militaires.grade_id', '=', 'grades.id')
            ->whereNotNull('militaires.date_naissance')
            ->whereNotNull('grades.retraite_obligatoire')
            ->where('militaires.est_disparu', 0)
            ->whereNull('militaires.deleted_at')
            ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$annee})")
            ->groupBy('militaires.region_id')
            ->selectRaw('militaires.region_id, count(*) as cnt')
            ->pluck('cnt', 'region_id')
            ->toArray();

        $parUnite = $lignes->whereNotNull('unite_id')->keyBy('unite_id');
        $parRegion = $lignes->whereNotNull('region_id')->groupBy('region_id');
        $nonRattaches = (int) $lignes->whereNull('region_id')->whereNull('unite_id')->sum('n_actifs');

        $grmId = Effectif::idOrganismeGrm();
        // Vraies recrues ou militaires actifs sans unité/région et non détachés dans un organisme extérieur
        $nonRattachesListe = Militaire::query()
            ->whereRaw(Effectif::sqlActivite())
            ->whereNull('region_id')
            ->where(function ($q) {
                $q->whereNull('unite_id')->orWhere('unite_id', 0);
            })
            ->where(function ($q) use ($grmId) {
                $q->whereNull('organisme_detachement_id')
                  ->orWhere('organisme_detachement_id', $grmId);
            })
            ->where(function ($q) {
                $q->where('position_actuelle', '!=', 'En détachement')
                  ->where('position_id', '!=', 26)
                  ->where('statut', '!=', 'détaché')
                  ->where('statut_code', '!=', 2);
            })
            ->select('id', 'matricule', 'mle_grm', 'nom', 'prenom', 'grade_actuel', 'statut', 'position_actuelle')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        // Organismes et structures de détachement (SPA / Ministères / Présidence...) classés à part
        $organismesDetachement = \App\Models\OrganismeDetachement::where('code', '!=', 'GRM')
            ->where('est_actif', true)
            ->withCount(['militaires' => function ($q) {
                $q->whereNull('deleted_at')
                  ->where(function ($sq) {
                      $sq->where('position_actuelle', 'En détachement')
                         ->orWhere('position_id', 26)
                         ->orWhere('statut', 'détaché')
                         ->orWhere('statut_code', 2);
                  });
            }])
            ->orderByDesc('militaires_count')
            ->orderBy('nom')
            ->get()
            ->filter(fn ($o) => $o->militaires_count > 0)
            ->map(fn ($o) => [
                'id' => $o->id,
                'code' => $o->code,
                'nom' => $o->nom,
                'effectif' => (int) $o->militaires_count,
            ])
            ->values();

        $totalDetaches = (int) $organismesDetachement->sum('effectif');

        // 3) Mise en forme pour la vue
        $out = $regions->map(function ($r) use ($parUnite, $parRegion, $horsEffectif, $retraitesParRegion) {
            $l = $parRegion->get($r->id, collect());
            $unites = $r->unites->map(fn ($u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'code' => $u->code,
                'type' => $u->type,
                'type_categorie' => (int) ($u->type_categorie ?? 1),
                'is_detachement' => (int) ($u->type_categorie ?? 1) === 2,
                'region_id' => $r->id,
                'region_code' => $r->code,
                'region_nom' => $r->nom,
                'ville' => $u->ville,
                'lat' => $u->latitude !== null ? (float) $u->latitude : null,
                'lon' => $u->longitude !== null ? (float) $u->longitude : null,
                'operationnelle' => (bool) $u->est_operationnelle,
                'effectif' => (int) ($parUnite->get($u->id)->total ?? 0),
            ])->values();

            $effGend = (int) $unites->where('type_categorie', 1)->sum('effectif');
            $effDet = (int) $unites->where('type_categorie', 2)->sum('effectif');
            $total = (int) $l->sum('total');
            $h = $horsEffectif->get($r->id);

            return [
                'id' => $r->id,
                'code' => $r->code,
                'nom' => $r->nom,
                'chef_lieu' => $r->chef_lieu,
                'chef_lat' => $r->latitude !== null ? (float) $r->latitude : null,
                'chef_lon' => $r->longitude !== null ? (float) $r->longitude : null,
                'effectif' => $total,
                'effectif_gendarmerie' => $effGend,
                'effectif_detachement' => $effDet,
                'absents' => (int) $l->sum('absents'),
                'hommes' => max(0, $total - (int) $l->sum('femmes')),
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

        // 4) Théâtres d'opérations souverains (agrégations SQL directes pour un chargement instantané)
        $regionsMap = $regions->keyBy('code');
        $theatres = Theatre::with('unitesAppui')->orderBy('id')->get()->map(function ($th) use ($regionsMap, $regions) {
            $q = \Illuminate\Support\Facades\DB::table('militaires')
                ->whereRaw(Effectif::sqlActivite())
                ->whereNull('deleted_at')
                ->where('theatre_id', $th->id);

            $st = $q->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN statut_code = 1 THEN 1 ELSE 0 END) as n_actifs,
                SUM(CASE WHEN est_blesse_guerre = 1 THEN 1 ELSE 0 END) as n_blesses,
                SUM(CASE WHEN grade_ordre >= 11 THEN 1 ELSE 0 END) as n_officiers,
                SUM(CASE WHEN grade_ordre BETWEEN 5 AND 10 THEN 1 ELSE 0 END) as n_sous_officiers,
                SUM(CASE WHEN grade_ordre < 5 THEN 1 ELSE 0 END) as n_mdr
            ")->first();

            $regionsCodes = is_array($th->regions_codes) ? $th->regions_codes : [];
            $regionsNoms = array_filter(array_map(fn($c) => $regionsMap->get($c)?->nom, $regionsCodes));

            $unitesAppui = ($th->unitesAppui ?? collect())->map(function ($ua) {
                return [
                    'id' => $ua->id,
                    'theatre_id' => $ua->theatre_id,
                    'code' => $ua->code,
                    'nom' => $ua->nom,
                    'effectif' => (int) $ua->effectif,
                    'chef_detachement' => $ua->chef_detachement,
                    'couleur' => $ua->couleur ?: '#0284c7',
                    'observations' => $ua->observations,
                ];
            })->values();

            $effectifGendarmerie = (int) ($st->total ?? 0);
            $effectifAppui = (int) $unitesAppui->sum('effectif');
            $effectifGlobal = $effectifGendarmerie + $effectifAppui;

            return [
                'id' => $th->id,
                'code' => $th->code,
                'nom' => $th->nom,
                'description' => $th->description,
                'pc_lieu' => $th->pc_lieu,
                'lat' => $th->latitude !== null ? (float) $th->latitude : null,
                'lon' => $th->longitude !== null ? (float) $th->longitude : null,
                'regions_codes' => $regionsCodes,
                'regions_noms' => array_values($regionsNoms),
                'couleur' => $th->couleur ?: '#ea580c',
                'statut' => $th->statut ?: 'actif',
                'effectif' => $effectifGendarmerie,
                'effectif_gendarmerie' => $effectifGendarmerie,
                'effectif_appui' => $effectifAppui,
                'effectif_global' => $effectifGlobal,
                'unites_appui' => $unitesAppui,
                'actifs' => (int) ($st->n_actifs ?? 0),
                'absents' => max(0, $effectifGendarmerie - (int) ($st->n_actifs ?? 0)),
                'blesses' => (int) ($st->n_blesses ?? 0),
                'officiers' => (int) ($st->n_officiers ?? 0),
                'sous_officiers' => (int) ($st->n_sous_officiers ?? 0),
                'mdr' => (int) ($st->n_mdr ?? 0),
                'militaires' => [], // Chargé à la demande à l'ouverture de la modale
            ];
        })->values();

        return [
            'regions' => $out,
            'theatres' => $theatres,
            'organismes_detachement' => $organismesDetachement,
            'non_rattaches_liste' => $nonRattachesListe,
            'stats' => [
                'non_rattaches' => $nonRattaches,
                'total_detaches' => (int) $out->sum('effectif_detachement'),
                'total_gendarmerie' => (int) $out->sum('effectif_gendarmerie'),
                'total_global' => (int) $out->sum('effectif'),
                'annee' => $annee,
                'total_theatres' => $theatres->count(),
                'effectif_theatres' => (int) $theatres->sum('effectif_gendarmerie'),
                'effectif_theatres_gendarmerie' => (int) $theatres->sum('effectif_gendarmerie'),
                'effectif_theatres_appui' => (int) $theatres->sum('effectif_appui'),
                'effectif_theatres_global' => (int) $theatres->sum('effectif_global'),
                'hors_region' => [
                    'actifs' => (int) $lignes->whereNull('region_id')->sum('n_actifs'),
                    'detaches' => $totalDetaches,
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

    /**
     * Endpoint API JSON : renvoie la liste détaillée des militaires pour le modal
     * GET /carte/militaires-statut
     */
    public function militairesStatut(Request $request)
    {
        $type = $request->query('type', 'actifs');
        $region = $request->query('region');
        $organismeId = $request->query('organisme_id') ? (int) $request->query('organisme_id') : null;

        $info = self::buildQueryStatut($type, $region, $organismeId);
        $query = $info['query'];
        $retraitesMap = $info['retraitesMap'];

        $limit = 200;
        $total = (clone $query)->count();

        $regionsMap = Region::pluck('nom', 'id')->all();
        $unitesMap = Unite::pluck('nom', 'id')->all();
        $gradesMap = Grade::pluck('nom_grade', 'id')->all();
        $orgsMap = \App\Models\OrganismeDetachement::pluck('nom', 'id')->all();

        $militaires = (clone $query)->limit($limit)->get()->map(function ($m) use ($retraitesMap, $regionsMap, $unitesMap, $gradesMap, $orgsMap) {
            $dateRetraite = $retraitesMap[$m->id]['date_retraite_formatted']
                ?? ($m->calculerDateRetraite()?->format('d/m/Y') ?? '-');

            $orgNom = $orgsMap[$m->organisme_detachement_id] ?? null;
            $uniteNom = ($m->organisme_detachement_id && $orgNom)
                ? $orgNom
                : ($unitesMap[$m->unite_id] ?? (is_string($m->unite) ? $m->unite : '-'));

            return [
                'id' => $m->id,
                'matricule' => $m->matricule,
                'mle_grm' => $m->mle_grm,
                'nom' => $m->nom,
                'prenom' => $m->prenom,
                'grade' => $gradesMap[$m->grade_id] ?? $m->grade_actuel ?? '-',
                'region_nom' => $regionsMap[$m->region_id] ?? (is_string($m->region) ? $m->region : ($orgNom ? 'Détachement SPA' : '-')),
                'unite_nom' => $uniteNom,
                'organisme_nom' => $orgNom,
                'statut' => $m->statut ?? '-',
                'position' => $m->position_actuelle ?? '-',
                'date_naissance' => $m->date_naissance ? $m->date_naissance->format('d/m/Y') : '-',
                'date_retraite' => $dateRetraite,
            ];
        })->all();

        return response()->json([
            'type' => $info['type'],
            'titre' => $info['titre'],
            'perimetre' => $info['perimetre'],
            'region_code' => $info['region_code'],
            'count' => $total,
            'is_limited' => $total > $limit,
            'limit' => $limit,
            'militaires' => $militaires,
        ]);
    }

    /**
     * Construit la requête Eloquent et les métadonnées pour un statut et un périmètre donné
     */
    public static function buildQueryStatut(string $type, ?string $regionCode = null, ?int $organismeId = null): array
    {
        $region = null;
        if ($regionCode && $regionCode !== 'national' && $regionCode !== 'all') {
            $region = is_numeric($regionCode)
                ? Region::find((int) $regionCode)
                : Region::where('code', $regionCode)->first();
        }

        $perimetre = $region 
            ? (str_starts_with($region->nom, 'Région') ? "{$region->nom} ({$region->code})" : "Région de {$region->nom} ({$region->code})")
            : "Mali entier (Toutes régions)";

        if ($organismeId) {
            $org = \App\Models\OrganismeDetachement::find($organismeId);
            if ($org) {
                $perimetre = "Structure : {$org->nom} [{$org->code}]";
            }
        }

        $retraitesMap = [];

        if ($type === 'retraites') {
            $dash = app(DashboardController::class);
            $retraitesData = $dash->getRetraitesBoth()['retraitesN']['data'] ?? [];
            $ids = array_column($retraitesData, 'id');

            foreach ($retraitesData as $rd) {
                $retraitesMap[$rd['id']] = $rd;
            }

            $query = Militaire::with(['region', 'unite', 'grade'])->whereIn('id', $ids ?: [0]);
            if ($region) {
                $query->where('region_id', $region->id);
            }
            $query->orderBy('nom')->orderBy('prenom');

            return [
                'type' => $type,
                'titre' => 'Départs à la retraite ' . now()->year,
                'perimetre' => $perimetre,
                'region_code' => $region?->code,
                'query' => $query,
                'retraitesMap' => $retraitesMap,
            ];
        }

        $query = Militaire::with(['region', 'unite', 'grade']);
        if ($region) {
            $query->where('region_id', $region->id);
        }
        if ($organismeId) {
            $query->where('organisme_detachement_id', $organismeId);
        }

        $titre = match ($type) {
            'actifs' => 'Militaires actifs au poste',
            'detaches' => ($organismeId && isset($org)) ? "Militaires détachés : {$org->nom} [{$org->code}]" : 'Militaires en détachement',
            'missions' => 'Militaires en mission',
            'stages' => 'Militaires en stage / formation',
            'radies' => 'Personnels radiés des cadres',
            'deserteurs' => 'Militaires déserteurs',
            'decedes' => 'Militaires décédés en ' . now()->year,
            default => 'Militaires'
        };

        match ($type) {
            'actifs' => Effectif::appliquerFiltreActifsStricts($query),
            'detaches' => Effectif::appliquerFiltreDetaches($query),
            'missions' => Effectif::appliquerFiltreMissions($query),
            'stages' => Effectif::appliquerFiltreStages($query),
            'radies' => $query->where('statut_code', 8),
            'deserteurs' => $query->where('statut_code', 6),
            'decedes' => (function () use ($query) {
                $annee = (int) now()->year;
                $debut = now()->setDate($annee, 1, 1)->startOfDay()->format('Y-m-d H:i:s');
                $fin = now()->setDate($annee, 12, 31)->endOfDay()->format('Y-m-d H:i:s');
                $query->where(function ($q) use ($debut, $fin) {
                    $q->where(function ($sub) {
                        $sub->where('statut_code', 9)
                            ->orWhereNotNull('date_deces');
                    })->whereBetween('date_deces', [$debut, $fin]);
                });
            })(),
            default => null,
        };

        $query->orderBy('nom')->orderBy('prenom');

        return [
            'type' => $type,
            'titre' => $titre,
            'perimetre' => $perimetre,
            'region_code' => $region?->code,
            'query' => $query,
            'retraitesMap' => $retraitesMap,
        ];
    }

    /**
     * Met à jour les coordonnées géographiques (GPS) et la ville d'une unité depuis la carte
     */
    public function updateCoordinates(Request $request, Unite $unite)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'ville' => 'nullable|string|max:255',
            'region_id' => 'nullable|exists:regions,id',
        ], [
            'latitude.numeric' => 'La latitude doit être un nombre décimal valide (ex: 13.4333).',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre décimal valide (ex: -6.2667).',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
        ]);

        $regionId = !empty($validated['region_id']) ? (int) $validated['region_id'] : $unite->region_id;
        $region = Region::find($regionId);

        if (!empty($validated['latitude']) && !empty($validated['longitude']) && $region) {
            $lat = (float) $validated['latitude'];
            $lon = (float) $validated['longitude'];

            if (!RegionGeo::contient($region->code, $lat, $lon)) {
                $autreRegionCode = RegionGeo::trouverRegion($lat, $lon);
                $precision = '';
                if ($autreRegionCode) {
                    $autreNom = Region::where('code', $autreRegionCode)->value('nom') ?? $autreRegionCode;
                    $precision = " (ces coordonnées se situent en réalité dans la région de {$autreNom})";
                } else {
                    $precision = " (ces coordonnées semblent être en dehors des frontières de la région)";
                }
                throw ValidationException::withMessages([
                    'latitude' => "Attention : les coordonnées ({$lat}, {$lon}) ne se trouvent pas dans la région {$region->nom}{$precision}.",
                ]);
            }
        }

        $unite->update([
            'latitude' => !empty($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => !empty($validated['longitude']) ? (float) $validated['longitude'] : null,
            'ville' => !empty($validated['ville']) ? trim($validated['ville']) : null,
            'region_id' => $regionId,
        ]);

        self::flush();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Coordonnées de l'unité « {$unite->nom} » enregistrées avec succès.",
                'unite' => $unite->fresh(),
            ]);
        }

        return redirect()->back()->with('success', "Coordonnées de l'unité « {$unite->nom} » enregistrées avec succès.");
    }

    /**
     * Met à jour les coordonnées géographiques (GPS) du PC d'un théâtre depuis la carte
     */
    public function updateTheatreCoordinates(Request $request, Theatre $theatre)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pc_lieu' => 'nullable|string|max:255',
        ], [
            'latitude.numeric' => 'La latitude doit être un nombre décimal valide.',
            'longitude.numeric' => 'La longitude doit être un nombre décimal valide.',
        ]);

        $theatre->update([
            'latitude' => !empty($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => !empty($validated['longitude']) ? (float) $validated['longitude'] : null,
            'pc_lieu' => !empty($validated['pc_lieu']) ? trim($validated['pc_lieu']) : null,
        ]);

        self::flush();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Coordonnées du PC du théâtre « {$theatre->nom} » actualisées avec succès.",
                'theatre' => $theatre->fresh(),
            ]);
        }

        return redirect()->back()->with('success', "Coordonnées du PC du théâtre « {$theatre->nom} » actualisées avec succès.");
    }

    /**
     * Charge à la demande les militaires déployés sur un théâtre d'opérations spécifique (pour la modale).
     */
    public function theatreMilitaires(Theatre $theatre)
    {
        $regions = Region::all();
        $q = Militaire::enActivite()
            ->with(['grade', 'unite', 'region', 'registresOperationnels'])
            ->where('theatre_id', $theatre->id);

        $militaires = $q->get();
        $militairesTries = $militaires->sortByDesc(fn ($m) => (int) ($m->grade?->ordre ?? $m->grade_ordre ?? 0))->values();

        return response()->json($militairesTries->map(function ($m) use ($regions) {
            $regBlesse = $m->registresOperationnels?->where('type_registre_code', 1)->first();
            $estBlesse = (bool) ($m->est_blesse_guerre || $regBlesse);
            $regObj = $m->region_id ? $regions->firstWhere('id', $m->region_id) : null;

            return [
                'id' => $m->id,
                'matricule' => $m->matricule,
                'mle_grm' => $m->mle_grm,
                'nom' => $m->nom,
                'prenom' => $m->prenom,
                'nom_complet' => $m->nom . ' ' . $m->prenom,
                'grade' => $m->grade?->nom_grade ?? $m->grade_actuel ?? 'Soldat 2',
                'grade_code' => $m->grade?->code_grade ?? '',
                'grade_ordre' => (int) ($m->grade?->ordre ?? $m->grade_ordre ?? 0),
                'categorie_statutaire' => $m->grade?->categorie_statutaire ?? $m->grade?->type_grade ?? 'Militaire',
                'unite_nom' => $m->unite?->nom ?? (is_string($m->unite) ? $m->unite : '-'),
                'region_nom' => $regObj?->nom ?? (is_string($m->region) ? $m->region : '-'),
                'region_code' => $regObj?->code ?? '',
                'statut' => $m->statut ?? 'Actif',
                'est_blesse_guerre' => $estBlesse,
                'circonstance_blessure' => $estBlesse ? ($regBlesse?->circonstances ?: 'Blessé au combat en opération') : null,
                'nature_pathologie' => $regBlesse?->nature_pathologie ?? null,
                'date_blessure' => $regBlesse?->date_evenement?->format('d/m/Y') ?? null,
            ];
        }));
    }

    /**
     * Supprime une unité d'appui interarmées depuis la vue cartographique.
     */
    public function destroyTheatreAppui(Request $request, \App\Models\TheatreUniteAppui $uniteAppui)
    {
        $code = $uniteAppui->code;
        $uniteAppui->delete();
        static::flush();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Unité d’appui « {$code} » supprimée avec succès."]);
        }

        return redirect()->back()->with('success', "Unité d’appui « {$code} » supprimée avec succès.");
    }

    /**
     * Enregistre une unité d'appui interarmées directement depuis la vue cartographique.
     */
    public function storeTheatreAppui(Request $request, Theatre $theatre)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'nom' => 'required|string|max:255',
            'effectif' => 'required|integer|min:0',
            'chef_detachement' => 'nullable|string|max:255',
            'couleur' => 'nullable|string|max:20',
            'observations' => 'nullable|string|max:255',
        ]);

        $theatre->unitesAppui()->create($validated);
        static::flush();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Unité d'appui ajoutée au théâtre avec succès."]);
        }

        return redirect()->back()->with('success', "Unité d'appui ajoutée au théâtre avec succès.");
    }
}
