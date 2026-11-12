<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Alerte;
use App\Models\Grade;
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
     * Affiche le tableau de bord.
     */
    public function index()
    {
        $alertesNonVues = Alerte::where('est_vue', false)
            ->with(['militaire' => fn($q) => $q->select('id', 'nom', 'prenom', 'matricule')])
            ->orderBy('date_echeance')
            ->take(20)
            ->get()
            ->map(fn($alerte) => [
                'id' => $alerte->id,
                'message' => $alerte->message,
                'type_alerte' => $alerte->type_alerte,
                'date_echeance' => $alerte->date_echeance,
                'est_vue' => $alerte->est_vue,
                'militaire' => $alerte->militaire ? [
                    'id' => $alerte->militaire->id,
                    'nom' => $alerte->militaire->nom,
                    'prenom' => $alerte->militaire->prenom,
                    'matricule' => $alerte->militaire->matricule,
                ] : null,
            ]);

        $stats = Cache::remember('dashboard:statistiques', 3600, function () {
            $retraites = $this->getRetraitesBoth();
            $proposables = $this->getProposablesBoth();

            return [
                'total_retraites_n' => $retraites['retraitesN']['total'] ?? 0,
                'total_retraites_n1' => $retraites['retraitesN1']['total'] ?? 0,
                'total_proposables_n' => $proposables['proposablesN']['total'] ?? 0,
                'total_proposables_n1' => $proposables['proposablesN1']['total'] ?? 0,
            ];
        });

        $statistiques = Cache::remember('dashboard:statistiques_cards', 120, function () use ($stats) {
            return [
                'total_militaires' => Militaire::count(),
                'militaires_actifs' => Militaire::where('statut', 'LIKE', 'actif%')->count(),
                'total_retraites' => Militaire::where('statut', 'LIKE', 'retrait%')->count(),
                'total_decedes' => Militaire::where(function($q) {
                    $q->where('statut', 'LIKE', 'd%c%d%')->orWhere('statut', 'LIKE', 'mort%');
                })->count(),
                'total_retraites_n' => $stats['total_retraites_n'] ?? 0,
                'total_retraites_n1' => $stats['total_retraites_n1'] ?? 0,
                'total_proposables_n' => $stats['total_proposables_n'] ?? 0,
                'total_proposables_n1' => $stats['total_proposables_n1'] ?? 0,
            ];
        });

        // Calcul dynamique en temps réel pour une mise à jour instantanée sans attendre l'expiration du cache
        $statistiques['alertes_non_vues'] = Alerte::where('est_vue', false)->count();

        $grades = Cache::remember('dashboard:grades_distribution', 3600, function () {
            return Grade::withCount(['militaires' => fn($q) => $q->where('statut', 'actif')])
                ->get(['id', 'nom_grade', 'type_grade'])
                ->map(fn($g) => [
                    'id' => $g->id,
                    'nom_grade' => $g->nom_grade,
                    'type_grade' => $g->type_grade,
                    'militaires_count' => $g->militaires_count,
                ]);
        });

        $initialSection = $this->getCachedSection('retraitesN');

        return Inertia::render('Dashboard', [
            'alertesNonVues' => $alertesNonVues,
            'statistiques' => $statistiques,
            'grades' => $grades,
            'initialSection' => $initialSection,
        ]);
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
        return Cache::remember("dashboard:section:{$section}", 3600, function () use ($section) {
            return $this->buildSectionData($section);
        });
    }

    /**
     * Calcule et met en cache l'ensemble des départs en retraite (N et N+1) en une seule passe optimisée.
     */
    public function getRetraitesBoth(): array
    {
        return Cache::remember('dashboard:retraites_both', 3600, function () {
            $aujourdhui = Carbon::now();
            $anneeCourante = (int) $aujourdhui->year;
            $anneeN1 = $anneeCourante + 1;

            $gradesRetraites = Grade::all(['nom_grade', 'code_grade', 'type_grade', 'retraite_obligatoire']);
            $gradeMap = [];
            foreach ($gradesRetraites as $g) {
                if ($g->retraite_obligatoire !== null) {
                    $age = (int) $g->retraite_obligatoire;
                    if ($g->nom_grade) $gradeMap[trim($g->nom_grade)] = $age;
                    if ($g->code_grade) $gradeMap[trim($g->code_grade)] = $age;
                }
            }

            $militaires = Militaire::where(function($q) {
                    $q->where('statut', 'LIKE', 'actif%')
                      ->orWhere('statut', 'LIKE', 'retrait%');
                })
                ->whereNotNull('date_naissance')
                ->whereNotNull('grade_actuel')
                ->select('id', 'nom', 'prenom', 'matricule', 'grade_actuel', 'statut', 'date_naissance')
                ->get();

            $retN = [];
            $retN1 = [];

            foreach ($militaires as $militaire) {
                $gradeName = trim($militaire->grade_actuel);
                $ageRetraite = $gradeMap[$gradeName] ?? Grade::getRetraiteForGrade($gradeName);
                if (!$ageRetraite) continue;

                $birthYear = (int) substr($militaire->date_naissance, 0, 4);
                if (!$birthYear) continue;

                $anneeRetraite = $birthYear + $ageRetraite;
                $dateRetraite = $militaire->calculerDateRetraite();
                if (!$dateRetraite) continue;

                $estActif = str_starts_with(mb_strtolower($militaire->statut ?? ''), 'actif');

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
        return Cache::remember('dashboard:proposables_both', 3600, function () {
            $aujourdhui = Carbon::now();
            $anneeCourante = (int) $aujourdhui->year;
            $anneeN1 = $anneeCourante + 1;
            $datePropN = Carbon::create($anneeCourante, 12, 31, 23, 59, 59);
            $datePropN1 = Carbon::create($anneeN1, 12, 31, 23, 59, 59);

            $militairesActifs = Militaire::where('statut', 'actif')
                ->select('id', 'nom', 'prenom', 'matricule', 'grade_actuel', 'date_naissance', 'date_entree_service', 'date_derniere_promotion', 'antecedent_judiciaire', 'antecedent_disciplinaire')
                ->with(['certificats' => fn($q) => $q->select('certificats.id', 'certificats.nom_certificat', 'certificats.niveau_certificat')])
                ->get();

            $propN = [];
            $propN1 = [];
            $exclusIds = [];

            // Année N
            foreach ($militairesActifs as $m) {
                $gradeCible = $this->getGradeCible($m, $anneeCourante);
                if (!$gradeCible) continue;

                $dateAnciennete = $this->getDateAncienneteReelle($m, $gradeCible);
                if (!$dateAnciennete || $dateAnciennete > $datePropN) continue;

                $propN[] = [
                    'id' => $m->id,
                    'matricule' => $m->matricule,
                    'nom' => $m->nom,
                    'prenom' => $m->prenom,
                    'nom_complet' => $m->nom . ' ' . $m->prenom,
                    'grade_actuel' => $m->grade_actuel,
                    'grade_cible' => $gradeCible,
                    'date_proposition' => $datePropN->format('Y-m-d'),
                    'date_proposition_formatted' => $datePropN->format('d/m/Y'),
                    'date_anciennete' => $dateAnciennete->format('Y-m-d'),
                    'date_anciennete_formatted' => $dateAnciennete->format('d/m/Y'),
                    'anciennete_grade_formatted' => $this->getAncienneteGradeFormatted($m, $datePropN),
                ];
                $exclusIds[$m->id] = true;
            }

            // Année N+1 (exclut les militaires déjà éligibles en N)
            foreach ($militairesActifs as $m) {
                if (isset($exclusIds[$m->id])) continue;

                $gradeCible = $this->getGradeCible($m, $anneeN1);
                if (!$gradeCible) continue;

                $dateAnciennete = $this->getDateAncienneteReelle($m, $gradeCible);
                if (!$dateAnciennete || $dateAnciennete > $datePropN1) continue;

                $propN1[] = [
                    'id' => $m->id,
                    'matricule' => $m->matricule,
                    'nom' => $m->nom,
                    'prenom' => $m->prenom,
                    'nom_complet' => $m->nom . ' ' . $m->prenom,
                    'grade_actuel' => $m->grade_actuel,
                    'grade_cible' => $gradeCible,
                    'date_proposition' => $datePropN1->format('Y-m-d'),
                    'date_proposition_formatted' => $datePropN1->format('d/m/Y'),
                    'date_anciennete' => $dateAnciennete->format('Y-m-d'),
                    'date_anciennete_formatted' => $dateAnciennete->format('d/m/Y'),
                    'anciennete_grade_formatted' => $this->getAncienneteGradeFormatted($m, $datePropN1),
                ];
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
