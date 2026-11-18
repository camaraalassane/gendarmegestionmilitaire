<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Support\Effectif;
use App\Models\Alerte;
use App\Models\Grade;
use App\Models\Region;
use App\Models\Position;
use App\Models\Unite;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProposablesAnneeNExport;
use App\Exports\ProposablesAnneeN1Export;
use App\Exports\RetraitesAnneeNExport;
use App\Exports\RetraitesAnneeN1Export;

class DashboardController extends Controller
{
    /**
     * Militaires en service actif. LOWER(TRIM()) rend la comparaison identique sur SQLite et PostgreSQL
     * (PostgreSQL distingue « Actif » de « actif », pas SQLite).
     */
    /** Effectif en activité (actif, détaché, mission, stage) : même définition que la carte, utilise l'index statut_code. */
    private function enActivite()
    {
        return Militaire::query()->whereRaw(Effectif::sqlActivite());
    }

    private function actifs()
    {
        return Militaire::query()->where(function ($q) {
            $q->where('statut_code', 1)
              ->orWhere(function ($sub) {
                  $sub->whereNull('statut_code')->whereRaw("LOWER(TRIM(statut)) = 'actif'");
              });
        });
    }

    /**
     * Affiche le tableau de bord.
     */
    public function index()
    {
        $aujourdhui = Carbon::now();
        $anneeCourante = (int) $aujourdhui->year;

        $v = \App\Models\Militaire::getCacheVersion();

        $statistiques = $this->statistiquesResume($anneeCourante, $v);

        $chartsData = $this->donneesGraphiques($v);

        // Pré-réchauffer les modules lourds (Éligibilités & Avancement) en arrière-plan sans ralentir le dashboard
        // Une seule fois par version de cache (et non à chaque affichage du dashboard)
        if (Cache::add("v{$v}:dashboard:prewarm", 1, 1800)) dispatch(function () {
            try {
                app(\App\Http\Controllers\EligibiliteController::class)->getAllComputedData();
                app(\App\Http\Controllers\AvancementController::class)->buildCandidatesList((int) date('Y'));
            } catch (\Throwable $e) {
                // Silencieux pour préserver la fluidité
            }
        })->afterResponse();

        return Inertia::render('Dashboard', [
            'statistiques' => $statistiques,
            'chartsData' => $chartsData,
        ]);
    }

    /** Indicateurs clés du dashboard (en cache). Utilisés par l'écran ET par l'export Excel. */
    public function statistiquesResume(?int $anneeCourante = null, ?int $v = null): array
    {
        $anneeCourante ??= (int) Carbon::now()->year;
        $v ??= \App\Models\Militaire::getCacheVersion();

        return Cache::remember("v{$v}:dashboard:statistiques_summary", 600, function () use ($anneeCourante) {
            $retraitesBoth = $this->getRetraitesBoth();
            $proposablesBoth = $this->getProposablesBoth();
            $totalRetraitesN = $retraitesBoth['retraitesN']['total'] ?? 0;
            $totalProposablesN = $proposablesBoth['proposablesN']['total'] ?? 0;
            $totalActifs = $this->enActivite()->count();

            $totalDecedesCetteAnnee = Militaire::where(function ($q) {
                $q->where('statut_code', 9)
                  ->orWhereNotNull('date_deces');
            })->where(function ($q) use ($anneeCourante) {
                $debut = Carbon::create($anneeCourante, 1, 1)->startOfDay();
                $fin = Carbon::create($anneeCourante, 12, 31)->endOfDay();
                $q->whereBetween('date_deces', [$debut, $fin]);
            })->count();

            $totalDeserteurs = Militaire::where(function ($q) {
                $q->where('statut_code', 6)
                  ->orWhereRaw("LOWER(motif_radiation) LIKE '%desert%'");
            })->count();

            $actifsStricts = Militaire::where('statut_code', 1)->count();
            $totalDetaches = Militaire::where('statut_code', 2)->count();
            $totalMissions = Militaire::where('statut_code', 3)->count();
            $totalStages = Militaire::where('statut_code', 4)->count();
            $totalRadies = Militaire::where('statut_code', 8)->count();
            $totalNonClasses = Militaire::whereNull('statut_code')->count();

            return [
                'annee' => $anneeCourante,
                'total_militaires' => $totalActifs,
                'militaires_actifs' => $totalActifs,
                'actifs_stricts' => $actifsStricts,
                'detaches' => $totalDetaches,
                'missions' => $totalMissions,
                'stages' => $totalStages,
                'radies' => $totalRadies,
                'non_classes' => $totalNonClasses,
                'proposables_n' => $totalProposablesN,
                'retraites_n' => $totalRetraitesN,
                'decedes_cette_annee' => $totalDecedesCetteAnnee,
                'deserteurs' => $totalDeserteurs,
            ];
        });
    }

    /** Données des graphiques du dashboard (en cache). Utilisées par l'écran ET par l'export Excel. */
    public function donneesGraphiques(?int $v = null): array
    {
        $v ??= \App\Models\Militaire::getCacheVersion();

        return Cache::remember("v{$v}:dashboard:charts_data", 600, function () {
            // 1. STATISTIQUES PAR STATUT (Situation de tous les personnels)
        $statutsMeta = [
            'actif' => ['num' => 1, 'label' => 'Actif (En service)', 'color' => '#059669', 'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
            'détaché' => ['num' => 2, 'label' => 'Détaché', 'color' => '#0284c7', 'bg' => 'bg-sky-50 text-sky-800 border-sky-200'],
            'stage' => ['num' => 4, 'label' => 'En Stage / Formation', 'color' => '#06b6d4', 'bg' => 'bg-cyan-50 text-cyan-800 border-cyan-200'],
            'mission' => ['num' => 3, 'label' => 'En mission', 'color' => '#6366f1', 'bg' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
            'procédure en cours' => ['num' => 5, 'label' => 'Procédure en cours', 'color' => '#d97706', 'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'déserteur' => ['num' => 6, 'label' => 'Déserteur', 'color' => '#ea580c', 'bg' => 'bg-orange-50 text-orange-800 border-orange-200'],
            'retraité' => ['num' => 7, 'label' => 'Retraité', 'color' => '#9333ea', 'bg' => 'bg-purple-50 text-purple-800 border-purple-200'],
            'radié' => ['num' => 8, 'label' => 'Radié', 'color' => '#64748b', 'bg' => 'bg-slate-50 text-slate-800 border-slate-200'],
            'décédé' => ['num' => 9, 'label' => 'Décédé', 'color' => '#e11d48', 'bg' => 'bg-rose-50 text-rose-800 border-rose-200'],
        ];

        // Comptage direct et ultra-rapide par statut_code indexé
        $statutCounts = Militaire::selectRaw('statut_code, COUNT(*) AS c')
            ->groupBy('statut_code')
            ->pluck('c', 'statut_code')
            ->toArray();

        $statutsDistribution = [];
        $totalStatuts = array_sum(array_map('intval', $statutCounts));
        $totalClasse = 0;
        foreach ($statutsMeta as $code => $meta) {
            $cnt = (int) ($statutCounts[$meta['num']] ?? 0);
            $totalClasse += $cnt;
            $statutsDistribution[] = [
                'code' => $code,
                'label' => $meta['label'],
                'value' => $cnt,
                'color' => $meta['color'],
                'bg' => $meta['bg'],
            ];
        }
        // Statuts inconnus ou vides : visibles au lieu de disparaître silencieusement
        if ($totalStatuts - $totalClasse > 0) {
            $statutsDistribution[] = [
                'code' => 'autres',
                'label' => 'Autres / non classés',
                'value' => $totalStatuts - $totalClasse,
                'color' => '#94a3b8',
                'bg' => 'bg-slate-50 text-slate-800 border-slate-200',
            ];
        }

        // 2. STATISTIQUES PAR POSITION (Où se trouvent les personnels ACTIFS sur le terrain)
        $allConfiguredPositions = Position::orderBy('nom')->pluck('nom')->toArray();
        $dbPositions = $this->enActivite()
            ->whereNotNull('position_actuelle')
            ->where('position_actuelle', '!=', '')
            ->selectRaw('position_actuelle, count(*) as count')
            ->groupBy('position_actuelle')
            ->pluck('count', 'position_actuelle')
            ->toArray();

        $positionsDistribution = [];
        $handledPositions = [];

        foreach ($allConfiguredPositions as $posNom) {
            $val = 0;
            foreach ($dbPositions as $dbPos => $c) {
                if (strcasecmp(trim($dbPos), trim($posNom)) === 0) {
                    $val += (int) $c;
                }
            }
            $positionsDistribution[] = [
                'label' => $posNom,
                'value' => (int) $val,
            ];
            $handledPositions[] = mb_strtolower(trim($posNom));
        }

        foreach ($dbPositions as $posNom => $count) {
            if (!in_array(mb_strtolower(trim($posNom)), $handledPositions)) {
                $positionsDistribution[] = [
                    'label' => $posNom,
                    'value' => (int) $count,
                ];
            }
        }

        // Traitement des militaires actifs sans position renseignée
        $actifsSansPositionAvecUnite = $this->enActivite()
            ->where(function ($q) {
                $q->whereNull('position_actuelle')->orWhere('position_actuelle', '');
            })
            ->where(function ($q) {
                $q->whereNotNull('unite_id')->orWhere(function ($sq) {
                    $sq->whereNotNull('unite')->where('unite', '!=', '');
                });
            })->count();

        if ($actifsSansPositionAvecUnite > 0) {
            $found = false;
            foreach ($positionsDistribution as &$pos) {
                if (strcasecmp($pos['label'], "À l'Unité / Au Poste") === 0) {
                    $pos['value'] += $actifsSansPositionAvecUnite;
                    $found = true;
                    break;
                }
            }
            unset($pos);
            if (!$found) {
                $positionsDistribution[] = [
                    'label' => "À l'Unité / Au Poste",
                    'value' => (int) $actifsSansPositionAvecUnite,
                ];
            }
        }

        $actifsEnAttenteAffectation = $this->enActivite()
            ->where(function ($q) {
                $q->whereNull('position_actuelle')->orWhere('position_actuelle', '');
            })
            ->whereNull('unite_id')
            ->where(function ($q) {
                $q->whereNull('unite')->orWhere('unite', '');
            })->count();

        if ($actifsEnAttenteAffectation > 0) {
            $positionsDistribution[] = [
                'label' => "En attente d'affectation (Recrues)",
                'value' => (int) $actifsEnAttenteAffectation,
            ];
        }

        // Tri : d'abord les positions occupées par ordre décroissant, puis alphabétique
        usort($positionsDistribution, function($a, $b) {
            if ($a['value'] !== $b['value']) {
                return $b['value'] <=> $a['value'];
            }
            return strcasecmp($a['label'], $b['label']);
        });

        // 3. STATISTIQUES PAR RÉGION : une seule requête, par région_id (plus d'alias ni de comparaison de textes)
        $regionsDistribution = Region::query()
            ->leftJoin('militaires', function ($j) {
                $j->on('militaires.region_id', '=', 'regions.id')
                  ->whereNull('militaires.deleted_at')
                  ->whereRaw(Effectif::sqlActivite('militaires.statut'));
            })
            ->groupBy('regions.id', 'regions.code', 'regions.nom', 'regions.ordre')
            ->orderByRaw('CASE WHEN regions.ordre IS NULL OR regions.ordre = 0 THEN 999 ELSE regions.ordre END')
            ->orderBy('regions.nom')
            ->selectRaw('regions.code, regions.nom AS label, COUNT(militaires.id) AS value')
            ->get()
            ->map(fn ($r) => ['code' => $r->code, 'label' => $r->label, 'value' => (int) $r->value])
            ->all();

        $actifsSansRegion = $this->enActivite()->whereNull('region_id')->count();
        if ($actifsSansRegion > 0) {
            $regionsDistribution[] = [
                'code' => 'N/A',
                'label' => 'Non assigné / Sans région',
                'value' => (int) $actifsSansRegion,
            ];
        }

        // 4. STATISTIQUES PAR CATÉGORIE STATUTAIRE
        $categoriesList = [
            'Officier Général',
            'Officier Supérieur',
            'Officier Subalterne',
            'Sous-officier Supérieur',
            'Sous-officier Subalterne',
            'Militaire du Rang',
        ];

        $dbCategories = $this->enActivite()
            ->whereNotNull('categorie')
            ->where('categorie', '!=', '')
            ->selectRaw('categorie, count(*) as count')
            ->groupBy('categorie')
            ->pluck('count', 'categorie')
            ->toArray();

        $categoriesDistribution = [];
        foreach ($categoriesList as $cat) {
            $cnt = 0;
            foreach ($dbCategories as $dbCat => $val) {
                if (strcasecmp(trim($dbCat), trim($cat)) === 0) {
                    $cnt += (int) $val;
                }
            }
            $categoriesDistribution[] = [
                'label' => $cat,
                'value' => $cnt,
            ];
        }

        // 5. STATISTIQUES PAR GRADE (Militaires actifs)
        $gradesDistribution = Grade::orderByDesc('ordre')->withCount(['militaires' => fn($q) => $q->whereRaw(Effectif::sqlActivite('militaires.statut'))])
            ->get()
            ->filter(fn($g) => $g->militaires_count > 0)
            ->values()
            ->map(fn($g) => ['label' => $g->nom_grade, 'value' => (int) $g->militaires_count])
            ->toArray();

            return [
                'statuts' => $statutsDistribution,
                'positions' => $positionsDistribution,
                'regions' => $regionsDistribution,
                'categories' => $categoriesDistribution,
                'grades' => $gradesDistribution,
            ];
        });
    }

    /**
     * Charge une section spécifique du tableau de bord depuis le cache ou la calcule.
     */
    public function section(Request $request)
    {
        $section = $request->get('section', 'retraitesN');
        $data = $this->getCachedSection($section);
        return response()->json($data);
    }

    /**
     * Récupère les données d'une section mises en cache.
     */
    public function getCachedSection(string $section): array
    {
        $v = \App\Models\Militaire::getCacheVersion();
        return Cache::remember("v{$v}:dashboard:section:{$section}", 1800, function () use ($section) {
            return $this->buildSectionData($section);
        });
    }

    /**
     * Calcule et met en cache l'ensemble des départs en retraite (N et N+1) en une seule passe optimisée.
     */
    public function getRetraitesBoth(): array
    {
        $v = \App\Models\Militaire::getCacheVersion();
        return Cache::remember("v{$v}:dashboard:retraites_both", 1800, function () {
            $aujourdhui = Carbon::now();
            $anneeCourante = (int) $aujourdhui->year;
            $anneeN1 = $anneeCourante + 1;

            $gradesRetraites = Grade::all(['id', 'nom_grade', 'code_grade', 'type_grade', 'retraite_obligatoire']);
            $gradeMap = [];
            $gradeMapById = [];
            foreach ($gradesRetraites as $g) {
                if ($g->retraite_obligatoire !== null) {
                    $age = (int) $g->retraite_obligatoire;
                    $gradeMapById[$g->id] = $age;
                    if ($g->nom_grade) $gradeMap[trim($g->nom_grade)] = $age;
                    if ($g->code_grade) $gradeMap[trim($g->code_grade)] = $age;
                }
            }

            // Calcul des bornes d'âge pour ne charger que les militaires d'âge éligible (élimine ~75% des recrues/jeunes)
            $minAge = !empty($gradeMapById) ? min($gradeMapById) : 45;
            $maxAge = !empty($gradeMapById) ? max($gradeMapById) : 66;
            $anneeNaissanceMax = $anneeN1 - $minAge + 1;
            $dateNaissanceMax = Carbon::create($anneeNaissanceMax, 12, 31)->toDateString();

            // Récupération par lazyById(500) pour préserver la mémoire vive (RAM constante)
            $militairesQuery = Militaire::where(function($q) {
                    $q->whereIn('statut_code', [1, 2, 3, 4, 7])
                      ->orWhere(function ($sub) {
                          $sub->whereNull('statut_code')
                              ->whereRaw("(LOWER(statut) LIKE 'actif%' OR LOWER(statut) LIKE 'retrait%')");
                      });
                })
                ->whereNotNull('date_naissance')
                ->where('date_naissance', '<=', $dateNaissanceMax)
                ->where(function ($q) {
                    $q->whereNotNull('grade_id')->orWhereNotNull('grade_actuel');
                })
                ->select('id', 'nom', 'prenom', 'matricule', 'grade_id', 'grade_actuel', 'statut', 'statut_code', 'date_naissance', 'position_actuelle', 'observations');

            $retN = [];
            $retN1 = [];

            foreach ($militairesQuery->lazyById(500) as $militaire) {
                // Les militaires en otage sont expressément maintenus en activité dans le fichier et exclus des prévisions de retraite
                if ($militaire->isOtage()) {
                    continue;
                }

                $ageRetraite = ($militaire->grade_id && isset($gradeMapById[$militaire->grade_id]))
                    ? $gradeMapById[$militaire->grade_id]
                    : ($gradeMap[trim($militaire->grade_actuel ?? '')] ?? Grade::getRetraiteForGrade($militaire->grade_actuel));
                if (!$ageRetraite) continue;

                $birthYear = (int) substr($militaire->date_naissance, 0, 4);
                if (!$birthYear) continue;

                $anneeRetraite = $birthYear + $ageRetraite;
                $dateRetraite = $militaire->calculerDateRetraite();
                if (!$dateRetraite) continue;

                $estActif = in_array((int) $militaire->statut_code, [1, 2, 3, 4], true)
                    || str_starts_with(mb_strtolower($militaire->statut ?? ''), 'actif');

                if ($anneeRetraite === $anneeCourante || ($estActif && $anneeRetraite < $anneeCourante)) {
                    $retN[] = [
                        'id' => $militaire->id,
                        'matricule' => $militaire->matricule,
                        'nom' => $militaire->nom,
                        'prenom' => $militaire->prenom,
                        'nom_complet' => $militaire->nom . ' ' . $militaire->prenom,
                        'grade_actuel' => $militaire->grade_actuel,
                        'statut' => $militaire->statut,
                        'date_retraite' => $dateRetraite->format('Y-m-d'),
                        'date_retraite_formatted' => $dateRetraite->format('d/m/Y'),
                    ];
                } elseif ($estActif && $anneeRetraite === $anneeN1) {
                    $retN1[] = [
                        'id' => $militaire->id,
                        'matricule' => $militaire->matricule,
                        'nom' => $militaire->nom,
                        'prenom' => $militaire->prenom,
                        'nom_complet' => $militaire->nom . ' ' . $militaire->prenom,
                        'grade_actuel' => $militaire->grade_actuel,
                        'statut' => $militaire->statut,
                        'date_retraite' => $dateRetraite->format('Y-m-d'),
                        'date_retraite_formatted' => $dateRetraite->format('d/m/Y'),
                    ];
                }
            }

            usort($retN, fn($a, $b) => strcmp($a['grade_actuel'], $b['grade_actuel']) ?: strcmp($a['date_retraite'], $b['date_retraite']));
            usort($retN1, fn($a, $b) => strcmp($a['grade_actuel'], $b['grade_actuel']) ?: strcmp($a['date_retraite'], $b['date_retraite']));

            $columns = [
                ['field' => 'grade_actuel', 'header' => 'Grade actuel', 'sortable' => true],
                ['field' => 'matricule', 'header' => 'Matricule', 'sortable' => true],
                ['field' => 'nom_complet', 'header' => 'Nom et Prénom', 'sortable' => true],
                ['field' => 'date_retraite_formatted', 'header' => 'Date de retraite', 'sortable' => true],
            ];

            return [
                'retraitesN' => [
                    'data' => $retN,
                    'title' => "Retraites - Année {$anneeCourante}",
                    'icon' => 'pi pi-calendar text-red-500',
                    'total' => count($retN),
                    'sortField' => 'date_retraite',
                    'columns' => $columns,
                ],
                'retraitesN1' => [
                    'data' => $retN1,
                    'title' => "Retraites - Année {$anneeN1}",
                    'icon' => 'pi pi-calendar-plus text-orange-500',
                    'total' => count($retN1),
                    'sortField' => 'date_retraite',
                    'columns' => $columns,
                ],
            ];
        });
    }

    /**
     * Calcule et met en cache l'ensemble des proposables (N et N+1) en une seule passe optimisée.
     */
    public function getProposablesBoth(): array
    {
        $v = \App\Models\Militaire::getCacheVersion();
        return Cache::remember("v{$v}:dashboard:proposables_both", 1800, function () {
            $aujourdhui = Carbon::now();
            $anneeCourante = (int) $aujourdhui->year;
            $anneeN1 = $anneeCourante + 1;
            $datePropN = Carbon::create($anneeCourante, 12, 31, 23, 59, 59);
            $datePropN1 = Carbon::create($anneeN1, 12, 31, 23, 59, 59);

            $militairesQuery = $this->actifs()
                ->select('id', 'nom', 'prenom', 'matricule', 'grade_actuel', 'date_naissance', 'date_entree_service', 'date_derniere_promotion', 'antecedent_judiciaire', 'antecedent_disciplinaire')
                ->with(['certificats' => fn($q) => $q->select('certificats.id', 'certificats.nom_certificat', 'certificats.niveau_certificat')]);

            $propN = [];
            $propN1 = [];

            // Évaluation conjointe Année N et Année N+1 en une seule passe par paquets de 500 (mémoire RAM constante)
            foreach ($militairesQuery->lazyById(500) as $m) {
                // 1. Éligible pour l'année N ?
                $gradeCibleN = $this->getGradeCible($m, $anneeCourante);
                if ($gradeCibleN) {
                    $dateAncienneteN = $this->getDateAncienneteReelle($m, $gradeCibleN);
                    if ($dateAncienneteN && $dateAncienneteN <= $datePropN) {
                        $propN[] = [
                            'id' => $m->id,
                            'matricule' => $m->matricule,
                            'nom' => $m->nom,
                            'prenom' => $m->prenom,
                            'nom_complet' => $m->nom . ' ' . $m->prenom,
                            'grade_actuel' => $m->grade_actuel,
                            'grade_cible' => $gradeCibleN,
                            'date_proposition' => $datePropN->format('Y-m-d'),
                            'date_proposition_formatted' => $datePropN->format('d/m/Y'),
                            'date_anciennete' => $dateAncienneteN->format('Y-m-d'),
                            'date_anciennete_formatted' => $dateAncienneteN->format('d/m/Y'),
                            'anciennete_grade_formatted' => $this->getAncienneteGradeFormatted($m, $datePropN),
                        ];
                        // Un militaire éligible en N ne peut pas être listé en N+1
                        continue;
                    }
                }

                // 2. Sinon, éligible pour l'année N+1 ?
                $gradeCibleN1 = $this->getGradeCible($m, $anneeN1);
                if ($gradeCibleN1) {
                    $dateAncienneteN1 = $this->getDateAncienneteReelle($m, $gradeCibleN1);
                    if ($dateAncienneteN1 && $dateAncienneteN1 <= $datePropN1) {
                        $propN1[] = [
                            'id' => $m->id,
                            'matricule' => $m->matricule,
                            'nom' => $m->nom,
                            'prenom' => $m->prenom,
                            'nom_complet' => $m->nom . ' ' . $m->prenom,
                            'grade_actuel' => $m->grade_actuel,
                            'grade_cible' => $gradeCibleN1,
                            'date_proposition' => $datePropN1->format('Y-m-d'),
                            'date_proposition_formatted' => $datePropN1->format('d/m/Y'),
                            'date_anciennete' => $dateAncienneteN1->format('Y-m-d'),
                            'date_anciennete_formatted' => $dateAncienneteN1->format('d/m/Y'),
                            'anciennete_grade_formatted' => $this->getAncienneteGradeFormatted($m, $datePropN1),
                        ];
                    }
                }
            }

            usort($propN, fn($a, $b) => strcmp($a['grade_actuel'], $b['grade_actuel']) ?: strcmp($a['date_anciennete'], $b['date_anciennete']));
            usort($propN1, fn($a, $b) => strcmp($a['grade_actuel'], $b['grade_actuel']) ?: strcmp($a['date_anciennete'], $b['date_anciennete']));

            $columns = [
                ['field' => 'grade_actuel', 'header' => 'Grade actuel', 'sortable' => true],
                ['field' => 'anciennete_grade_formatted', 'header' => 'Ancienneté grade', 'sortable' => true],
                ['field' => 'matricule', 'header' => 'Matricule', 'sortable' => true],
                ['field' => 'nom_complet', 'header' => 'Nom et Prénom', 'sortable' => true],
                ['field' => 'grade_cible', 'header' => 'Grade cible', 'sortable' => true],
                ['field' => 'date_anciennete_formatted', 'header' => 'Date ancienneté', 'sortable' => true],
                ['field' => 'date_proposition_formatted', 'header' => 'Date proposition', 'sortable' => true],
            ];

            return [
                'proposablesN' => [
                    'data' => $propN,
                    'title' => "Proposables - Année {$anneeCourante}",
                    'icon' => 'pi pi-star text-purple-500',
                    'total' => count($propN),
                    'sortField' => 'date_proposition',
                    'columns' => $columns,
                ],
                'proposablesN1' => [
                    'data' => $propN1,
                    'title' => "Proposables - Année {$anneeN1}",
                    'icon' => 'pi pi-star text-indigo-500',
                    'total' => count($propN1),
                    'sortField' => 'date_proposition',
                    'columns' => $columns,
                ],
            ];
        });
    }

    /**
     * Construit les données pour une section demandée.
     */
    private function buildSectionData(string $section): array
    {
        return match ($section) {
            'retraitesN'    => $this->getRetraitesBoth()['retraitesN'] ?? [],
            'retraitesN1'   => $this->getRetraitesBoth()['retraitesN1'] ?? [],
            'proposablesN'  => $this->getProposablesBoth()['proposablesN'] ?? [],
            'proposablesN1' => $this->getProposablesBoth()['proposablesN1'] ?? [],
            default         => abort(400, 'Section invalide'),
        };
    }

    // -------------------------------------------------------------------------
    // Méthodes de calcul
    // -------------------------------------------------------------------------

    /**
     * Retourne la date de proposition (31 décembre de l'année cible)
     */
    private function getDateProposition($anneeCible)
    {
        return Carbon::create($anneeCible, 12, 31, 23, 59, 59);
    }

    /**
     * Calcule l'ancienneté dans le grade au format années/mois.
     */
    private function getAncienneteGradeFormatted($militaire, $dateProposition)
    {
        $dateReference = $dateProposition;
        $dateDernierePromotion = $militaire->date_derniere_promotion;
        $dateEntreeService = $militaire->date_entree_service;
        
        $dateAnciennete = $dateDernierePromotion ?? $dateEntreeService;
        
        if (!$dateAnciennete) {
            return '0 an';
        }
        
        $diff = Carbon::parse($dateAnciennete)->diff($dateReference);
        $years = $diff->y;
        $months = $diff->m;
        
        if ($years > 0 && $months > 0) {
            return "{$years} an" . ($years > 1 ? 's' : '') . " et {$months} mois";
        } elseif ($years > 0) {
            return "{$years} an" . ($years > 1 ? 's' : '');
        } elseif ($months > 0) {
            return "{$months} mois";
        } else {
            return "moins d'1 mois";
        }
    }

    /**
     * Détermine le grade cible pour un militaire à une année donnée.
     * 
     * @param Militaire $militaire
     * @param int $anneeReference Année de référence (2026 pour N, 2027 pour N+1)
     */
    private function getGradeCible($militaire, $anneeReference)
    {
        $grade = $militaire->grade_actuel;
        $age = $militaire->age;
        $certificatsObtenus = $militaire->certificats->pluck('niveau_certificat')->toArray();
        $conditionsBase = !$militaire->antecedent_judiciaire && !$militaire->antecedent_disciplinaire;
        
        // Calcul de l'ancienneté dans le grade au 31 décembre de l'année de référence
        $dateReference = Carbon::create($anneeReference, 12, 31, 23, 59, 59);
        $dateDernierePromotion = $militaire->date_derniere_promotion;
        
        if ($dateDernierePromotion) {
            $ancienneteGrade = $dateDernierePromotion->diffInYears($dateReference);
        } else {
            $ancienneteGrade = 0;
        }
        
        // Ancienneté totale au 31 décembre de l'année de référence
        $dateEntreeService = $militaire->date_entree_service;
        if ($dateEntreeService) {
            $ancienneteTotale = $dateEntreeService->diffInYears($dateReference);
        } else {
            $ancienneteTotale = 0;
        }

        // === PROMOTIONS OFFICIERS ===
        if ($grade == 'Sous-lieutenant' && $ancienneteGrade >= 2) {
            return 'Lieutenant';
        }
        
        if ($grade == 'Lieutenant' && $ancienneteGrade >= 3) {
            return 'Capitaine';
        }
        
        if ($grade == 'Capitaine' && $ancienneteGrade >= 3) {
            return 'Commandant';
        }
        
        if ($grade == 'Commandant' && $ancienneteGrade >= 3) {
            return 'Lieutenant-colonel';
        }
        
        if ($grade == 'Lieutenant-colonel' && $ancienneteGrade >= 3) {
            return 'Colonel';
        }
        
        if ($grade == 'Colonel' && $ancienneteGrade >= 6) {
            return 'Colonel-Major';
        }
        
        // === PROMOTIONS SOUS-OFFICIERS ===
        if ($grade == 'Soldat 1' && in_array('CAT1', $certificatsObtenus) && $conditionsBase && $ancienneteTotale >= 5) {
            return 'Caporal';
        }
        
        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && in_array('CAT2', $certificatsObtenus) && $conditionsBase) {
            return 'Sergent';
        }
        
        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && !in_array('CAT2', $certificatsObtenus) && $age >= 47 && $ancienneteGrade >= 3 && $conditionsBase) {
            return 'Caporal-chef';
        }
        
        if ($grade == 'Sergent' && $conditionsBase && $ancienneteGrade >= 2 && $ancienneteTotale >= 5) {
            return 'Sergent-Chef';
        }
        
        if ($grade == 'Sergent-Chef' && $conditionsBase && $ancienneteGrade >= 3) {
            return 'Adjudant';
        }
        
        if ($grade == 'Adjudant' && $conditionsBase && $ancienneteGrade >= 2) {
            return 'Adjudant-Chef';
        }
        
        if ($grade == 'Adjudant-Chef' && in_array('CIA', $certificatsObtenus) && in_array('BA1', $certificatsObtenus) && in_array('BA2', $certificatsObtenus) && $age >= 45 && $conditionsBase && $ancienneteGrade >= 2) {
            return 'Adjudant-Chef Major';
        }
        
        // Passage sous-officier → officier
        if (in_array('BA2', $certificatsObtenus)) {
            if (($grade == 'Adjudant-Chef' && $age <= 45 && $ancienneteTotale >= 15 && $ancienneteGrade >= 2)
                || ($grade == 'Adjudant-Chef major' && $ancienneteGrade >= 2)) {
                return 'Sous-lieutenant';
            }
        }
        
        return null;
    }

    /**
     * Calcule la date réelle où l'ancienneté est atteinte pour une promotion.
     */
    private function getDateAncienneteReelle($militaire, $gradeCible)
    {
        $gradeActuel = $militaire->grade_actuel;
        $dateDernierePromotion = $militaire->date_derniere_promotion;
        $dateEntreeService = $militaire->date_entree_service;
        
        // Lieutenant → Capitaine (3 ans)
        if ($gradeActuel == 'Lieutenant' && $gradeCible == 'Capitaine') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Sous-lieutenant → Lieutenant (2 ans)
        if ($gradeActuel == 'Sous-lieutenant' && $gradeCible == 'Lieutenant') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(2);
            }
        }
        
        // Capitaine → Commandant (3 ans)
        if ($gradeActuel == 'Capitaine' && $gradeCible == 'Commandant') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Commandant → Lieutenant-colonel (3 ans)
        if ($gradeActuel == 'Commandant' && $gradeCible == 'Lieutenant-colonel') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Lieutenant-colonel → Colonel (3 ans)
        if ($gradeActuel == 'Lieutenant-colonel' && $gradeCible == 'Colonel') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Colonel → Colonel-Major (6 ans)
        if ($gradeActuel == 'Colonel' && $gradeCible == 'Colonel-Major') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(6);
            }
        }
        
        // Soldat 1 → Caporal (5 ans après entrée)
        if ($gradeActuel == 'Soldat 1' && $gradeCible == 'Caporal') {
            if ($dateEntreeService) {
                return Carbon::parse($dateEntreeService)->addYears(5);
            }
        }
        
        // Caporal → Sergent (3 ans après CAT1)
        if ($gradeActuel == 'Caporal' && $gradeCible == 'Sergent') {
            $certifCAT1 = $militaire->certificats->where('niveau_certificat', 'CAT1')->first();
            if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                return Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
            }
        }
        
        // Caporal → Caporal-chef (3 ans)
        if ($gradeActuel == 'Caporal' && $gradeCible == 'Caporal-chef') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Sergent → Sergent-Chef (2 ans et 5 ans de service)
        if ($gradeActuel == 'Sergent' && $gradeCible == 'Sergent-Chef') {
            if ($dateDernierePromotion && $dateEntreeService) {
                $date2ansGrade = Carbon::parse($dateDernierePromotion)->addYears(2);
                $date5ansService = Carbon::parse($dateEntreeService)->addYears(5);
                return $date2ansGrade->max($date5ansService);
            }
        }
        
        // Sergent-Chef → Adjudant (3 ans)
        if ($gradeActuel == 'Sergent-Chef' && $gradeCible == 'Adjudant') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(3);
            }
        }
        
        // Adjudant → Adjudant-Chef (2 ans)
        if ($gradeActuel == 'Adjudant' && $gradeCible == 'Adjudant-Chef') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(2);
            }
        }
        
        // Adjudant-Chef → Adjudant-Chef Major (2 ans et 45 ans d'âge)
        if ($gradeActuel == 'Adjudant-Chef' && $gradeCible == 'Adjudant-Chef Major') {
            $dateNaissance = $militaire->date_naissance;
            if ($dateDernierePromotion && $dateNaissance) {
                $date2ansGrade = Carbon::parse($dateDernierePromotion)->addYears(2);
                $date45ansAge = Carbon::parse($dateNaissance)->addYears(45);
                return $date2ansGrade->max($date45ansAge);
            }
        }
        
        // Adjudant-Chef → Sous-lieutenant (2 ans)
        if ($gradeActuel == 'Adjudant-Chef' && $gradeCible == 'Sous-lieutenant') {
            if ($dateDernierePromotion) {
                return Carbon::parse($dateDernierePromotion)->addYears(2);
            }
        }
        
        return null;
    }

    // --- Méthodes d'export ---
    
    public function exportProposablesAnneeN()
    {
        $section = $this->getCachedSection('proposablesN');
        $result = ['annee' => Carbon::now()->year, 'proposables' => $section['data'], 'total' => $section['total']];
        return Excel::download(new ProposablesAnneeNExport($result), 'proposables_annee_' . $result['annee'] . '_' . Carbon::now()->format('Y_m_d') . '.xlsx');
    }

    public function exportProposablesAnneeN1()
    {
        $section = $this->getCachedSection('proposablesN1');
        $result = ['annee' => Carbon::now()->year + 1, 'proposables' => $section['data'], 'total' => $section['total']];
        return Excel::download(new ProposablesAnneeN1Export($result), 'proposables_annee_' . $result['annee'] . '_' . Carbon::now()->format('Y_m_d') . '.xlsx');
    }

    public function exportRetraitesAnneeN()
    {
        $section = $this->getCachedSection('retraitesN');
        $result = ['annee' => Carbon::now()->year, 'retraites' => $section['data'], 'total' => $section['total']];
        return Excel::download(new RetraitesAnneeNExport($result), 'retraites_annee_' . $result['annee'] . '_' . Carbon::now()->format('Y_m_d') . '.xlsx');
    }

    public function exportRetraitesAnneeN1()
    {
        $section = $this->getCachedSection('retraitesN1');
        $result = ['annee' => Carbon::now()->year + 1, 'retraites' => $section['data'], 'total' => $section['total']];
        return Excel::download(new RetraitesAnneeN1Export($result), 'retraites_annee_' . $result['annee'] . '_' . Carbon::now()->format('Y_m_d') . '.xlsx');
    }

    public function marquerAlerteVue(Alerte $alerte)
    {
        $alerte->update(['est_vue' => true]);

        Cache::forget('alertes:non_vues_count');
        Cache::forget('alertes:non_vues_count_gendarme');
        Cache::forget('dashboard:statistiques_cards');
        Cache::forget('alertes:statistiques_globales');

        return request()->wantsJson() ? response()->json(['success' => true]) : redirect()->back()->with('success', 'Alerte marquée comme vue.');
    }
}
