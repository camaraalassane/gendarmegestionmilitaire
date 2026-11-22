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

        $militaires = Militaire::whereIn('grade_actuel', $gradesEligibles)->get();

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
        }])->get();
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
     */
    public function genererAlertes()
    {
        // 1. Basculer automatiquement en 'retraité' les militaires ayant dépassé la limite d'âge de leur grade
        $this->actualiserStatutRetraites();

        // 2. Vider et réinitialiser la table des alertes pour générer à neuf et ultra-rapidement
        Alerte::query()->delete();

        // 3. Génération ultra-rapide et vectorisée de toutes les alertes (Promotions, Formations, Retraites, Contrats, Mutations)
        $now = now();
        $nowStr = $now->toDateTimeString();
        $currentYear = (int) $now->year;
        $today = $now->toDateString();
        $dateProposition = Carbon::create($currentYear, 12, 31, 23, 59, 59);
        $datePropStr = $dateProposition->toDateString();
        $anneeProp = (string) $currentYear;
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $sixMonthsFuture = $now->copy()->addMonths(6)->toDateString();

        $allAlertes = [];

        // --- A. Alertes Retraites (<= 12 mois) ---
        $retraites = DB::select("
            SELECT m.id, m.nom, m.prenom, m.date_naissance, m.grade_actuel, g.retraite_obligatoire
            FROM militaires m
            JOIN grades g ON LOWER(TRIM(m.grade_actuel)) = LOWER(TRIM(g.nom_grade))
            WHERE m.statut_code = 1
              AND m.date_naissance IS NOT NULL
              AND g.retraite_obligatoire IS NOT NULL
              AND (m.position_actuelle IS NULL OR LOWER(m.position_actuelle) NOT LIKE '%otage%')
              AND (EXTRACT(YEAR FROM m.date_naissance) + g.retraite_obligatoire) = :currentYear
        ", ['currentYear' => $currentYear]);

        foreach ($retraites as $r) {
            $dateRetraite = Carbon::parse($r->date_naissance)->addYears($r->retraite_obligatoire);
            $diffJours = Carbon::now()->startOfDay()->diffInDays($dateRetraite);
            $moisRestants = max(0, (int) floor($diffJours / 30));
            $allAlertes[] = [
                'militaire_id'  => $r->id,
                'type_alerte'   => 'retraite',
                'message'       => "Retraite dans {$moisRestants} mois (le " . $dateRetraite->format('d/m/Y') . ")",
                'date_echeance' => $dateRetraite->toDateString(),
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- B. Alertes Mutations (>= 3 ans en poste) ---
        $mutations = DB::select("
            SELECT m.id, m.nom, m.prenom, m.grade_actuel, m.unite, m.region,
                   COALESCE(
                       (SELECT a.date_mutation FROM affectations a WHERE a.militaire_id = m.id AND a.date_mutation IS NOT NULL ORDER BY a.date_mutation DESC LIMIT 1),
                       m.date_mutation,
                       m.date_entree_service
                   ) as effective_date_mutation,
                   COALESCE(
                       (SELECT a.unite FROM affectations a WHERE a.militaire_id = m.id AND a.date_mutation IS NOT NULL ORDER BY a.date_mutation DESC LIMIT 1),
                       m.unite
                   ) as effective_unite,
                   COALESCE(
                       (SELECT a.region FROM affectations a WHERE a.militaire_id = m.id AND a.date_mutation IS NOT NULL ORDER BY a.date_mutation DESC LIMIT 1),
                       m.region
                   ) as effective_region
            FROM militaires m
            WHERE m.statut_code = 1
        ");

        foreach ($mutations as $m) {
            if (!$m->effective_date_mutation) continue;
            $d = substr($m->effective_date_mutation, 0, 10);
            $years = (int) floor((strtotime($today) - strtotime($d)) / (365.25 * 86400));
            if ($years >= 3) {
                $dFormatted = date('d/m/Y', strtotime($d));
                $unite = $m->effective_unite ?: 'Unité non précisée';
                $region = $m->effective_region ?: '-';
                $allAlertes[] = [
                    'militaire_id'  => $m->id,
                    'type_alerte'   => 'mutation',
                    'message'       => "Alerte Mutation : {$m->grade_actuel} {$m->prenom} {$m->nom} en poste depuis {$years} ans à {$unite} ({$region}) (dernière mutation le {$dFormatted})",
                    'date_echeance' => $endOfMonth,
                    'est_vue'       => false,
                    'created_at'    => $nowStr,
                    'updated_at'    => $nowStr,
                ];
            }
        }

        // --- C. Alertes Contrats ---
        $contratsActifs = DB::table('contrats')
            ->where('statut', 'actif')
            ->select('militaire_id', 'date_fin', 'date_debut')
            ->orderBy('date_debut', 'desc')
            ->get()
            ->keyBy('militaire_id');

        $gradesContrat = [
            'Soldat 2', 'Soldat 1', 'Caporal', 'Caporal-chef',
            'Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef', 'Major',
            'Sapeur 1ère classe'
        ];

        $militairesContrat = DB::table('militaires')
            ->where('statut_code', 1)
            ->whereIn('grade_actuel', $gradesContrat)
            ->select('id', 'nom', 'prenom', 'grade_actuel', 'date_entree_service')
            ->get();

        foreach ($militairesContrat as $m) {
            $c = $contratsActifs[$m->id] ?? null;
            if ($c && $c->date_fin) {
                $diffDays = (strtotime($c->date_fin) - strtotime($today)) / 86400;
                if ($diffDays > 180) {
                    continue; // Contrat actif sécurisé pour plus de 6 mois
                }
            }

            $serviceYears = null;
            if ($m->date_entree_service) {
                $serviceYears = (int) floor((strtotime($today) - strtotime($m->date_entree_service)) / (365.25 * 86400));
            }

            if ($serviceYears !== null && $serviceYears < 5 && !$c) {
                continue;
            }

            $message = ($serviceYears !== null && $serviceYears > 0)
                ? "Renouvellement de contrat requis pour {$m->prenom} {$m->nom} ({$serviceYears} ans de service) - Grade: {$m->grade_actuel}"
                : "Régularisation / Renouvellement de contrat requis pour {$m->prenom} {$m->nom} (sans contrat actif) - Grade: {$m->grade_actuel}";

            $allAlertes[] = [
                'militaire_id'  => $m->id,
                'type_alerte'   => 'contrat',
                'message'       => $message,
                'date_echeance' => $c?->date_fin ?? $sixMonthsFuture,
                'est_vue'       => false,
                'created_at'    => $nowStr,
                'updated_at'    => $nowStr,
            ];
        }

        // --- D. Alertes Promotions & Formations ---
        $certifs = DB::table('certificat_militaire')
            ->join('certificats', 'certificat_militaire.certificat_id', '=', 'certificats.id')
            ->select('certificat_militaire.militaire_id', 'certificats.niveau_certificat', 'certificat_militaire.date_obtention')
            ->get();

        $certifsByMilitaire = [];
        $certifsDates = [];
        foreach ($certifs as $c) {
            $certifsByMilitaire[$c->militaire_id][] = $c->niveau_certificat;
            if ($c->date_obtention) {
                $certifsDates[$c->militaire_id][$c->niveau_certificat] = $c->date_obtention;
            }
        }

        $militairesActive = DB::table('militaires')
            ->where('statut_code', 1)
            ->select('id', 'nom', 'prenom', 'grade_actuel', 'date_naissance', 'date_entree_service', 'date_derniere_promotion', 'a_permis_conduire', 'antecedent_judiciaire', 'antecedent_disciplinaire')
            ->get();

        foreach ($militairesActive as $m) {
            $grade = $m->grade_actuel;
            if ($grade === 'Sapeur 1ère classe') $grade = 'Soldat 1';
            elseif ($grade === 'Commissaire') $grade = 'Lieutenant';

            $age = $m->date_naissance ? (int) floor((strtotime($today) - strtotime($m->date_naissance)) / (365.25 * 86400)) : 0;
            $refProm = $m->date_derniere_promotion ?? $m->date_entree_service;
            $ancienneteGrade = $refProm ? (int) floor((strtotime($today) - strtotime($refProm)) / (365.25 * 86400)) : 0;
            $anciennete = $m->date_entree_service ? (int) floor((strtotime($today) - strtotime($m->date_entree_service)) / (365.25 * 86400)) : 0;

            $conditionsBase = !$m->antecedent_judiciaire && !$m->antecedent_disciplinaire;
            $cList = $certifsByMilitaire[$m->id] ?? [];
            $hasCAT1 = in_array('CAT1', $cList);
            $hasCAT2 = in_array('CAT2', $cList);
            $hasCIA = in_array('CIA', $cList);
            $hasBA1 = in_array('BA1', $cList);
            $hasBA2 = in_array('BA2', $cList);
            $hasAPLI = in_array('APLI', $cList);
            $hasCFCU = in_array('CFCU', $cList);
            $hasCEM = in_array('CEM', $cList);
            $hasCERT_EM = in_array('CERT_EM', $cList);
            $hasECOLE_GUERRE = in_array('ECOLE_GUERRE', $cList);

            // Promotion helper
            $addProm = function($cible, $annees) use (&$allAlertes, $m, $refProm, $anneeProp, $datePropStr, $nowStr) {
                $dateAnc = $refProm ? date('d/m/Y', strtotime("+{$annees} years", strtotime($refProm))) : 'conditions remplies';
                $allAlertes[] = [
                    'militaire_id'  => $m->id,
                    'type_alerte'   => 'promotion',
                    'message'       => "Proposable pour {$anneeProp} (ancienneté atteinte le {$dateAnc}) - Promotion à {$cible}",
                    'date_echeance' => $datePropStr,
                    'est_vue'       => false,
                    'created_at'    => $nowStr,
                    'updated_at'    => $nowStr,
                ];
            };

            // Formation helper
            $addForm = function($formKey, $nomForm, $dateCond = null) use (&$allAlertes, $m, $anneeProp, $datePropStr, $nowStr) {
                $dateCondTxt = $dateCond ? date('d/m/Y', strtotime($dateCond)) : 'conditions remplies';
                $allAlertes[] = [
                    'militaire_id'  => $m->id,
                    'type_alerte'   => 'formation',
                    'message'       => "Proposable pour {$anneeProp} (conditions remplies le {$dateCondTxt}) - Formation {$nomForm}",
                    'date_echeance' => $datePropStr,
                    'est_vue'       => false,
                    'created_at'    => $nowStr,
                    'updated_at'    => $nowStr,
                ];
            };

            // Règles de promotion
            if ($grade == 'Soldat 1' && $hasCAT1 && $conditionsBase && $anciennete >= 5) {
                $addProm('Caporal', 5);
            }
            if ($grade == 'Caporal' && $hasCAT1 && $hasCAT2 && $conditionsBase) {
                $addProm('Sergent', 3);
            }
            if ($grade == 'Caporal' && $hasCAT1 && !$hasCAT2 && $age >= 47 && $ancienneteGrade >= 3 && $conditionsBase) {
                $addProm('Caporal-chef', 3);
            }
            if ($grade == 'Sergent' && $conditionsBase && $ancienneteGrade >= 2 && $anciennete >= 5) {
                $addProm('Sergent-Chef', 2);
            }
            if ($grade == 'Sergent-Chef' && $conditionsBase && $ancienneteGrade >= 3) {
                $addProm('Adjudant', 3);
            }
            if ($grade == 'Adjudant' && $conditionsBase && $ancienneteGrade >= 2) {
                $addProm('Adjudant-Chef', 2);
            }
            if ($grade == 'Sous-lieutenant' && $ancienneteGrade >= 2) {
                $addProm('Lieutenant', 2);
            }
            if ($grade == 'Lieutenant' && $ancienneteGrade >= 3) {
                $addProm('Capitaine', 3);
            }
            if ($grade == 'Capitaine' && $ancienneteGrade >= 3) {
                $addProm('Commandant', 3);
            }
            if ($grade == 'Commandant' && $ancienneteGrade >= 3) {
                $addProm('Lieutenant-colonel', 3);
            }
            if ($grade == 'Lieutenant-colonel' && $ancienneteGrade >= 3) {
                $addProm('Colonel', 3);
            }
            if ($grade == 'Colonel' && $ancienneteGrade >= 6) {
                $addProm('Colonel-Major', 6);
            }

            // Règles de formation
            if (!$hasAPLI && in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine']) && !$hasCFCU && $age <= 50) {
                $addForm('APLI', "Cour d'Application");
            }
            if (!$hasCFCU && in_array($grade, ['Lieutenant', 'Capitaine']) && ($grade == 'Capitaine' || $hasAPLI)) {
                $addForm('CFCU', "Cour des Futurs Commandants d'Unité");
            }
            if (!$hasCAT1 && in_array($grade, ['Soldat 2', 'Soldat 1']) && $ancienneteGrade >= 5 && $conditionsBase) {
                $dEntree = $m->date_entree_service ? date('Y-m-d', strtotime('+5 years', strtotime($m->date_entree_service))) : null;
                $addForm('CAT1', "Certificat d'Aptitude Technique Niveau 1", $dEntree);
            }
            if (!$hasCAT2 && $grade == 'Caporal' && $age < 47 && $ancienneteGrade >= 3 && $conditionsBase && $hasCAT1) {
                $dObt = $certifsDates[$m->id]['CAT1'] ?? null;
                $dCond = $dObt ? date('Y-m-d', strtotime('+3 years', strtotime($dObt))) : null;
                $addForm('CAT2', "Certificat d'Aptitude Technique Niveau 2", $dCond);
            }
            if (!$hasCIA && in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && $conditionsBase && $m->a_permis_conduire) {
                $addForm('CIA', "Certificat Interarmes (CIA)");
            }
            if (!$hasBA1 && in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && $hasCIA && $conditionsBase && $anciennete >= 8) {
                $dObt = $certifsDates[$m->id]['CIA'] ?? null;
                $dCond = $dObt ? date('Y-m-d', strtotime('+3 years', strtotime($dObt))) : null;
                $addForm('BA1', "Brevet d'Arme N1", $dCond);
            }
            if (!$hasBA2 && in_array($grade, ['Adjudant', 'Adjudant-Chef']) && $hasBA1 && $conditionsBase) {
                $dObt = $certifsDates[$m->id]['BA1'] ?? null;
                $dCond = $dObt ? date('Y-m-d', strtotime('+3 years', strtotime($dObt))) : null;
                $addForm('BA2', "Brevet d'Arme N2", $dCond);
            }
            if (!$hasCEM && in_array($grade, ['Capitaine', 'Commandant']) && (($grade === 'Capitaine' && $ancienneteGrade >= 3) || $grade === 'Commandant') && $age <= 45) {
                $addForm('CEM', "Cour d'État-Major");
            }
            if (!$hasCERT_EM && $grade == 'Commandant' && $age > 45) {
                $addForm('CERT_EM', "Certificat d'État-Major");
            }
            if (!$hasECOLE_GUERRE && in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major']) && $ancienneteGrade >= 2 && $age <= 53) {
                $addForm('ECOLE_GUERRE', "École de Guerre");
            }
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
            JOIN grades g ON LOWER(TRIM(m.grade_actuel)) = LOWER(TRIM(g.nom_grade))
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
