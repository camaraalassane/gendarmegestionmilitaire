<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Models\Contrat;
use App\Models\Militaire;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AlerteController extends Controller
{
    /**
     * Afficher la liste des alertes.
     */
    public function index(Request $request)
    {
        $query = Alerte::with('militaire');

        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type_alerte', $request->type);
        }

        // Filtre par statut (vue/non vue)
        if ($request->filled('statut')) {
            if ($request->statut === 'vue') {
                $query->where('est_vue', true);
            } elseif ($request->statut === 'non_vue') {
                $query->where('est_vue', false);
            }
        }

        // Recherche multi-colonnes améliorée
        if ($request->filled('search')) {
            $search = trim($request->search);
            $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
            $searchPattern = '%' . addcslashes($search, '%_\\') . '%';
            
            // Conversion éventuelle de format de date JJ/MM/AAAA ou reconnaissance AAAA-MM-JJ
            $dateConverted = null;
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $search, $matches)) {
                $dateConverted = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $search)) {
                $dateConverted = $search;
            }

            $query->where(function($q) use ($search, $searchPattern, $likeOp, $dateConverted) {
                // Recherche directe et instantanée par ID (entier)
                if (ctype_digit($search)) {
                    $searchId = (int) $search;
                    $q->where('alertes.id', $searchId)
                      ->orWhere('alertes.militaire_id', $searchId);
                }

                // 1. Recherche par Militaire (recherche full-text indexée, nom, prénom, matricule)
                $q->orWhereHas('militaire', function($mQ) use ($searchPattern, $likeOp) {
                    $mQ->where('recherche', $likeOp, $searchPattern)
                       ->orWhere('nom', $likeOp, $searchPattern)
                       ->orWhere('prenom', $likeOp, $searchPattern)
                       ->orWhere('matricule', $likeOp, $searchPattern);
                })
                // 2. Recherche par Type d'alerte (promotion, formation, contrat, mutation, retraite)
                ->orWhere('type_alerte', $likeOp, $searchPattern)
                // 3. Recherche par Message / Description
                ->orWhere('message', $likeOp, $searchPattern);

                if ($dateConverted) {
                    $q->orWhereDate('date_echeance', $dateConverted);
                }
            });
        }

        $alertes = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($alerte) => [
                'id' => $alerte->id,
                'type_alerte' => $alerte->type_alerte,
                'message' => $alerte->message,
                'date_echeance' => $alerte->date_echeance?->format('Y-m-d'),
                'date_echeance_formatted' => $alerte->date_echeance?->format('d/m/Y'),
                'est_vue' => $alerte->est_vue,
                'created_at' => $alerte->created_at?->format('d/m/Y H:i'),
                'militaire' => $alerte->militaire ? [
                    'id' => $alerte->militaire->id,
                    'nom' => $alerte->militaire->nom,
                    'prenom' => $alerte->militaire->prenom,
                    'matricule' => $alerte->militaire->matricule,
                    'grade_actuel' => $alerte->militaire->grade_actuel,
                ] : null,
            ]);

        // Statistiques globales calculées en direct pour garantir des chiffres 100% dynamiques et synchronisés
        $countsByType = Alerte::selectRaw('type_alerte, count(*) as count')->groupBy('type_alerte')->pluck('count', 'type_alerte');
        $statistiques = [
            'total' => Alerte::count(),
            'non_vues' => Alerte::where('est_vue', false)->count(),
            'vues' => Alerte::where('est_vue', true)->count(),
            'promotions' => (int) ($countsByType['promotion'] ?? 0),
            'formations' => (int) ($countsByType['formation'] ?? 0),
            'retraites' => (int) ($countsByType['retraite'] ?? 0),
            'contrats' => (int) ($countsByType['contrat'] ?? 0),
            'mutations' => (int) ($countsByType['mutation'] ?? 0),
        ];

        return Inertia::render('alertes/index', [
            'alertes' => $alertes,
            'statistiques' => $statistiques,
            'filters' => $request->only(['search', 'type', 'statut']),
        ]);
    }

    /**
     * Marquer une alerte comme vue (archivée)
     */
    public function marquerVue(Alerte $alerte)
    {
        $alerte->update(['est_vue' => true]);

        Cache::forget('alertes:non_vues_count');
        Cache::forget('alertes:non_vues_count_gendarme');
        Cache::forget('dashboard:statistiques_cards');
        Cache::forget('alertes:statistiques_globales');

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Alerte marquée comme vue avec succès.');
    }

    /**
     * Marquer toutes les alertes non vues comme vues
     */
    public function marquerToutVue()
    {
        $alertes = Alerte::where('est_vue', false);
        $count = $alertes->count();

        if ($count > 0) {
            $alertes->update(['est_vue' => true]);
            $message = "{$count} alerte(s) ont été marquées comme vues avec succès.";
        } else {
            $message = "Aucune alerte à marquer comme vue.";
        }

        Cache::forget('alertes:non_vues_count');
        Cache::forget('alertes:non_vues_count_gendarme');
        Cache::forget('dashboard:statistiques_cards');
        Cache::forget('alertes:statistiques_globales');

        return redirect()->back()->with('success', $message);
    }

    /**
     * GRADES ÉLIGIBLES POUR LES ALERTES DE CONTRAT
     * Sous-officiers jusqu'à 1ère classe uniquement
     */
    private function getGradesEligiblesContrat(): array
    {
        return [
            'Soldat 2',
            'Soldat 1',
            'Caporal',
            'Caporal-chef',
            'Sergent',
            'Sergent-Chef',
            'Adjudant',
            'Adjudant-Chef',
            'Major'
        ];
    }

    /**
     * Vérifier et créer les alertes pour les renouvellements de contrat
     */
    public function checkRenouvellements()
    {
        $gradesEligibles = $this->getGradesEligiblesContrat();

        $militaires = Militaire::whereIn('grade_actuel', $gradesEligibles)->lazyById(500); // lecture par lots : mémoire bornée

        $createdCount = 0;
        $alreadyExists = 0;
        $moins5ans = 0;
        $sansDateEntree = 0;

        foreach ($militaires as $militaire) {
            if (!$militaire->date_entree_service) {
                $sansDateEntree++;
                continue;
            }

            $serviceYears = floor($militaire->date_entree_service->diffInYears(now()));

            if ($serviceYears < 5) {
                $moins5ans++;
                Alerte::where('militaire_id', $militaire->id)
                    ->where('type_alerte', 'contrat')
                    ->delete();
                continue;
            }

            $alerteExiste = Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'contrat')
                ->exists();

            if ($alerteExiste) {
                $alreadyExists++;
            } else {
                Alerte::create([
                    'militaire_id' => $militaire->id,
                    'type_alerte' => 'contrat',
                    'message' => "Renouvellement de contrat requis pour {$militaire->prenom} {$militaire->nom} ({$serviceYears} ans de service) - Grade: {$militaire->grade_actuel}",
                    'date_echeance' => now()->addMonths(6),
                    'est_vue' => false,
                ]);
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $message = "✅ {$createdCount} nouvelle(s) alerte(s) de contrat créée(s) avec succès !";
        } elseif ($alreadyExists > 0) {
            $message = "ℹ️ Tous les militaires éligibles ({$alreadyExists}) ont déjà une alerte de contrat.";
        } else {
            $message = "ℹ️ Aucun militaire éligible trouvé (grade éligible + 5 ans de service).";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Vérifier et créer les alertes pour les mutations (durée d'affectation >= 3 ans)
     */
    public function checkMutations()
    {
        $militaires = Militaire::enActivite()->with(['affectations' => function($q) {
            $q->orderBy('date_mutation', 'desc');
        }])->lazyById(500); // lecture par lots : mémoire bornée
        $createdCount = 0;
        $alreadyExists = 0;

        foreach ($militaires as $militaire) {
            // Récupérer l'affectation ayant la date de mutation la plus récente
            $plusRecenteAff = $militaire->affectations->sortByDesc('date_mutation')->first();
            $dateMutation = $plusRecenteAff?->date_mutation 
                ?? $militaire->date_mutation 
                ?? $militaire->date_entree_service;

            if (!$dateMutation) {
                continue;
            }

            // Synchroniser le militaire si nécessaire avec sa plus récente affectation
            if ($plusRecenteAff && ($militaire->date_mutation != $plusRecenteAff->date_mutation || $militaire->unite != $plusRecenteAff->unite)) {
                $militaire->update([
                    'unite' => $plusRecenteAff->unite,
                    'region' => $plusRecenteAff->region,
                    'date_mutation' => $plusRecenteAff->date_mutation,
                ]);
            }

            $anneesMutation = floor(Carbon::parse($dateMutation)->diffInYears(now()));

            if ($anneesMutation < 3) {
                // Supprimer toute alerte mutation si durée < 3 ans (ex: mutation en 2025)
                Alerte::where('militaire_id', $militaire->id)
                    ->where('type_alerte', 'mutation')
                    ->delete();
                continue;
            }

            // Vérifier si une alerte mutation non vue existe déjà ce mois-ci
            $debutMois = now()->startOfMonth();
            $finMois = now()->endOfMonth();
            $alerteExistanteCeMois = Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'mutation')
                ->where('est_vue', false)
                ->whereBetween('created_at', [$debutMois, $finMois])
                ->exists();

            if ($alerteExistanteCeMois) {
                $alreadyExists++;
            } else {
                $unite = $plusRecenteAff?->unite ?? $militaire->unite ?? 'Unité non précisée';
                $region = $plusRecenteAff?->region ?? $militaire->region ?? '-';
                $dateMutFormatted = Carbon::parse($dateMutation)->format('d/m/Y');

                Alerte::create([
                    'militaire_id' => $militaire->id,
                    'type_alerte' => 'mutation',
                    'message' => "Alerte Mutation : {$militaire->grade_actuel} {$militaire->prenom} {$militaire->nom} en poste depuis {$anneesMutation} ans à {$unite} ({$region}) (dernière mutation le {$dateMutFormatted})",
                    'date_echeance' => now()->endOfMonth(),
                    'est_vue' => false,
                ]);
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $message = "✅ {$createdCount} alerte(s) de mutation créée(s) / rappelées avec succès !";
        } elseif ($alreadyExists > 0) {
            $message = "ℹ️ Tous les militaires avec >= 3 ans de présence ({$alreadyExists}) ont déjà une alerte ce mois-ci.";
        } else {
            $message = "ℹ️ Aucun militaire avec une affectation de 3 ans ou plus trouvé.";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Vérifier les alertes pour un militaire spécifique
     */
    public function verifierAlertesMilitaire(int $militaireId): bool
    {
        $militaire = Militaire::find($militaireId);

        if (!$militaire) {
            return false;
        }

        $gradesEligibles = $this->getGradesEligiblesContrat();

        Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', 'contrat')
            ->delete();

        if (!in_array($militaire->grade_actuel, $gradesEligibles)) {
            return false;
        }

        if (!$militaire->date_entree_service) {
            return false;
        }

        $serviceYears = floor($militaire->date_entree_service->diffInYears(now()));

        if ($serviceYears < 5) {
            return false;
        }

        Alerte::create([
            'militaire_id' => $militaire->id,
            'type_alerte' => 'contrat',
            'message' => "Renouvellement de contrat requis pour {$militaire->prenom} {$militaire->nom} ({$serviceYears} ans de service) - Grade: {$militaire->grade_actuel}",
            'date_echeance' => now()->addMonths(6),
            'est_vue' => false,
        ]);

        return true;
    }

    /**
     * Générer toutes les alertes automatiquement (Promotions, Formations, Mutations, Contrats, Retraites)
     * 100% synchronisées avec le moteur de calcul d'EligibiliteController.
     */
    public function genererAlertes()
    {
        // 1. Basculer automatiquement en 'retraité' les militaires ayant dépassé la limite d'âge de leur grade
        $this->actualiserStatutRetraites();

        // 2. Vider et réinitialiser la table des alertes pour générer à neuf et ultra-rapidement
        Alerte::query()->delete();

        // 3. Récupérer les données officielles d'éligibilité pour une synchronisation 100% exacte
        $eligibiliteController = app(EligibiliteController::class);
        // Lecture en flux depuis la table pré-calculée (chaque type n'est parcouru qu'une fois)
        $data = [
            'promotions' => $eligibiliteController->iterer('promotions'),
            'formations' => $eligibiliteController->iterer('formations'),
            'contrats'   => $eligibiliteController->iterer('contrats'),
            'mutations'  => $eligibiliteController->iterer('mutations'),
            'retraites'  => $eligibiliteController->iterer('retraites'),
        ];

        $now = now();
        $nowStr = $now->toDateTimeString();
        $today = $now->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $sixMonthsFuture = $now->copy()->addMonths(6)->toDateString();

        $allAlertes = [];

        // --- A. Alertes Promotions (809) ---
        foreach ($data['promotions'] ?? [] as $p) {
            $allAlertes[] = [
                'militaire_id'  => $p['militaire']['id'],
                'type_alerte'   => 'promotion',
                'message'       => $p['message'] . " - Promotion à " . $p['grade_cible'],
                'date_echeance' => $p['date_estimation'] ?? $today,
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- B. Alertes Formations (2393) ---
        foreach ($data['formations'] ?? [] as $f) {
            $allAlertes[] = [
                'militaire_id'  => $f['militaire']['id'],
                'type_alerte'   => 'formation',
                'message'       => $f['message'] . " - Formation " . $f['nom_formation'],
                'date_echeance' => $f['date_estimation'] ?? $today,
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- C. Alertes Contrats (5602) ---
        foreach ($data['contrats'] ?? [] as $c) {
            $allAlertes[] = [
                'militaire_id'  => $c['militaire']['id'],
                'type_alerte'   => 'contrat',
                'message'       => $c['message'],
                'date_echeance' => $c['date_echeance'] ?? $sixMonthsFuture,
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- D. Alertes Mutations (2049) ---
        foreach ($data['mutations'] ?? [] as $m) {
            $allAlertes[] = [
                'militaire_id'  => $m['id'],
                'type_alerte'   => 'mutation',
                'message'       => "Alerte Mutation : {$m['grade_actuel']} {$m['prenom']} {$m['nom']} en poste depuis {$m['duree_annees']} ans à {$m['unite']} ({$m['region']})",
                'date_echeance' => $endOfMonth,
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- E. Alertes Retraites (Strictement l'année en cours N) ---
        foreach ($data['retraites'] ?? [] as $r) {
            $dateFmt = $r['date_retraite_formatted'] ?? Carbon::parse($r['date_retraite'])->format('d/m/Y');
            $mois = (int) ($r['mois_restants'] ?? 0);
            $msg = ($mois > 0)
                ? "Retraite dans {$mois} mois (le {$dateFmt})"
                : "Retraite échue le {$dateFmt} (année {$now->year})";

            $allAlertes[] = [
                'militaire_id'  => $r['militaire']['id'],
                'type_alerte'   => 'retraite',
                'message'       => $msg,
                'date_echeance' => $r['date_retraite'],
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // Insertion en masse par paquets de 1000 pour performance maximale
        if (!empty($allAlertes)) {
            foreach (array_chunk($allAlertes, 1000) as $chunk) {
                Alerte::insert($chunk);
            }
        }

        // 4. Purger les caches statistiques et compteurs
        Cache::forget('alertes:non_vues_count');
        Cache::forget('alertes:non_vues_count_gendarme');
        Cache::forget('dashboard:statistiques_cards');
        Cache::forget('alertes:statistiques_globales');
        Militaire::incrementCacheVersion();
        if (class_exists(\App\Http\Controllers\CarteController::class)) {
            \App\Http\Controllers\CarteController::flush();
        }

        $totalNonVues = Alerte::where('est_vue', false)->count();

        return redirect()->back()->with('success', "Toutes les alertes (Promotions, Formations, Mutations, Contrats, Retraites) ont été générées et synchronisées avec succès ! ({$totalNonVues} alertes actives)");
    }

    /**
     * Bascule automatiquement en 'retraité' les militaires ayant dépassé la limite d'âge
     */
    private function actualiserStatutRetraites(): int
    {
        $currentYear = (int) date('Y');
        $retraitesSql = "
            SELECT m.id 
            FROM militaires m
            JOIN grades g ON m.grade_id = g.id
            WHERE m.statut_code = 1
              AND m.date_naissance IS NOT NULL
              AND g.retraite_obligatoire IS NOT NULL
              AND (m.position_actuelle IS NULL OR LOWER(m.position_actuelle) NOT LIKE '%otage%')
              AND (EXTRACT(YEAR FROM m.date_naissance) + g.retraite_obligatoire) < :currentYear
        ";
        $depassees = DB::select($retraitesSql, ['currentYear' => $currentYear]);
        $ids = array_column($depassees, 'id');

        if (!empty($ids)) {
            foreach (array_chunk($ids, 1000) as $lot) {
                Militaire::whereIn('id', $lot)->update(['statut' => 'retraité', 'statut_code' => 7]);
            }
            Militaire::incrementCacheVersion();
            if (class_exists(\App\Http\Controllers\CarteController::class)) {
                \App\Http\Controllers\CarteController::flush();
            }
            Cache::forget('dashboard:statistiques_cards');
        }

        return count($ids);
    }

    /**
     * Générer les alertes de promotion (requêtes de masse optimisées)
     */
    private function genererAlertesPromotion(): int
    {
        $militaires = Militaire::enActivite()
            ->whereNull('date_deces')
            ->select('id', 'nom', 'prenom', 'grade_id', 'grade_actuel', 'date_grade_actuel', 'date_derniere_promotion', 'date_entree_service')
            ->get();

        $existingAlertes = Alerte::where('type_alerte', 'promotion')
            ->where('est_vue', false)
            ->pluck('militaire_id')
            ->all();
        $existingMap = array_flip($existingAlertes);

        $toInsert = [];
        $now = now();
        $dateEcheance = $now->copy()->addMonths(6);

        foreach ($militaires as $militaire) {
            if (!isset($existingMap[$militaire->id]) && $militaire->aAncienneteGradeMin(3)) {
                $toInsert[] = [
                    'militaire_id' => $militaire->id,
                    'type_alerte' => 'promotion',
                    'message' => "{$militaire->prenom} {$militaire->nom} est éligible pour une promotion",
                    'date_echeance' => $dateEcheance,
                    'est_vue' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                Alerte::insert($chunk);
            }
        }

        return count($toInsert);
    }

    /**
     * Générer les alertes de retraite (requêtes de masse optimisées)
     */
    private function genererAlertesRetraite(): int
    {
        $militaires = Militaire::enActivite()
            ->whereNull('date_deces')
            ->whereNotNull('date_naissance')
            ->select('id', 'nom', 'prenom', 'grade_actuel', 'date_naissance', 'statut')
            ->get();

        $existingAlertes = Alerte::where('type_alerte', 'retraite')
            ->where('est_vue', false)
            ->pluck('militaire_id')
            ->all();
        $existingMap = array_flip($existingAlertes);

        $toInsert = [];
        $now = now();

        foreach ($militaires as $militaire) {
            if (!isset($existingMap[$militaire->id]) && $militaire->estEligibleRetraite()) {
                $dateRetraite = $militaire->calculerDateRetraite();
                $toInsert[] = [
                    'militaire_id' => $militaire->id,
                    'type_alerte' => 'retraite',
                    'message' => "{$militaire->prenom} {$militaire->nom} est éligible à la retraite",
                    'date_echeance' => $dateRetraite,
                    'est_vue' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                Alerte::insert($chunk);
            }
        }

        return count($toInsert);
    }
}
