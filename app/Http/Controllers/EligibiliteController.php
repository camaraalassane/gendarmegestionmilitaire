<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Alerte;
use App\Models\Grade;
use App\Exports\EligibilitesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Inertia\Inertia;

class EligibiliteController extends Controller
{
    /**
     * Affiche la page des éligibilités pré-chargée avec la première page et les statistiques complètes.
     */
    public function index()
    {
        $formationsListe = $this->getFormationsListe();
        $gradesListe = $this->getGradesListe();

        $allComputed = $this->getAllComputedData();

        $initialPromotions = array_slice($allComputed['promotions'] ?? [], 0, 30);
        $initialTotal = count($allComputed['promotions'] ?? []);

        return Inertia::render('eligibilites/index', [
            'formationsListe' => $formationsListe,
            'gradesListe' => $gradesListe,
            'initialType' => 'promotions',
            'initialData' => $initialPromotions,
            'initialTotal' => $initialTotal,
            'initialStatistiques' => $allComputed['statistiques'] ?? [
                'total_promotions' => 0,
                'total_formations' => 0,
                'total_retraites' => 0,
                'total_contrats' => 0,
                'total_mutations' => 0,
            ],
            'initialFormationCounts' => $allComputed['formation_counts'] ?? [],
            'initialGradeCounts' => $allComputed['grade_counts'] ?? [],
        ]);
    }

    /**
     * API: Récupère les éligibilités filtrées avec pagination et compteurs compacts.
     */
    public function getFiltered(Request $request)
    {
        $type = $request->input('type', 'promotions');
        $formation = $request->input('formation', '');
        $grade = $request->input('grade', '');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max((int) $request->input('per_page', 30), 5), 100);

        $allComputed = $this->getAllComputedData();

        $targetKey = in_array($type, ['promotions', 'formations', 'retraites', 'contrats', 'mutations']) ? $type : 'promotions';
        $items = $allComputed[$targetKey] ?? [];

        // Filtre par grade
        if (!empty($grade)) {
            $items = array_values(array_filter($items, function ($item) use ($grade) {
                return ($item['militaire']['grade_actuel'] ?? $item['grade_actuel'] ?? '') === $grade;
            }));
        }

        // Filtre par formation (uniquement pertinent pour le type 'formations')
        if (!empty($formation) && $targetKey === 'formations') {
            $items = array_values(array_filter($items, function ($item) use ($formation) {
                return ($item['formation'] ?? '') === $formation;
            }));
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($items, $offset, $perPage);

        return response()->json([
            'data' => $paginated,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $perPage)),
            'statistiques' => $allComputed['statistiques'] ?? [
                'total_promotions' => 0,
                'total_formations' => 0,
                'total_retraites' => 0,
                'total_contrats' => 0,
                'total_mutations' => 0,
            ],
            'formation_counts' => $allComputed['formation_counts'] ?? [],
            'grade_counts' => $allComputed['grade_counts'] ?? [],
        ]);
    }

    /**
     * Récupère toutes les données d'éligibilités calculées et mises en cache.
     */
    public function getAllComputedData(): array
    {
        $v = \App\Models\Militaire::getCacheVersion();
        return Cache::remember("v{$v}:eligibilites:all_computed_data", 1800, function () {
            return $this->computeAll();
        });
    }

    /**
     * Calcule l'ensemble des éligibilités de manière ultra-optimisée.
     */
    private function computeAll(): array
    {
        $refDate = Carbon::create(Carbon::now()->year, 12, 31, 23, 59, 59);
        $refYear = $refDate->year;
        $refYearStr = (string) $refYear;
        $datePropStr = $refDate->format('Y-m-d');
        $now = Carbon::now();

        $militaires = Militaire::enActivite()
            ->with([
                'certificats' => function ($q) {
                    $q->select('certificats.id', 'niveau_certificat');
                },
                'contratActif:id,militaire_id,statut,date_fin,date_debut',
                'affectations' => function ($q) {
                    $q->orderBy('date_mutation', 'desc');
                },
            ])
            ->select([
                'id', 'matricule', 'nom', 'prenom', 'grade_id', 'grade_actuel', 'grade_ordre',
                'date_entree_service', 'date_derniere_promotion',
                'statut', 'statut_code', 'a_permis_conduire', 'antecedent_judiciaire',
                'antecedent_disciplinaire', 'date_naissance',
                'unite', 'region', 'date_mutation'
            ])
            ->get();

        $militaireIds = $militaires->pluck('id')->all();
        $alertesContrat = Alerte::whereIn('militaire_id', $militaireIds)
            ->where('type_alerte', 'contrat')
            ->where('est_vue', false)
            ->pluck('message', 'militaire_id')
            ->all();

        $allPromotions = [];
        $allFormations = [];
        $allRetraites = [];
        $allContrats = [];
        $allMutations = [];
        $formationCounts = [];
        $gradeCounts = [];

        foreach ($militaires as $m) {
            $grade = $m->grade_actuel;
            $hasAntecedents = ($m->antecedent_judiciaire || $m->antecedent_disciplinaire);
            $conditionsBase = !$hasAntecedents;

            $dNaissance = $m->date_naissance;
            $dEntree = $m->date_entree_service;
            $dPromo = $m->date_derniere_promotion;

            $certs = null;

            // 1. PROMOTIONS
            $gradeCible = null;
            $dateAnciennete = null;
            $promoDetail = '';

            switch ($grade) {
                case 'Soldat 1':
                    if ($conditionsBase) {
                        $anc = $dEntree ? $dEntree->diffInYears($refDate) : 0;
                        if ($anc >= 5) {
                            $certs = $m->certificats->pluck('niveau_certificat')->all();
                            if (in_array('CAT1', $certs, true)) {
                                $gradeCible = 'Caporal';
                                $dateAnciennete = $dEntree->copy()->addYears(5);
                            }
                        }
                    }
                    break;

                case 'Caporal':
                    if ($conditionsBase) {
                        $certs = $m->certificats->pluck('niveau_certificat')->all();
                        $hasCAT1 = in_array('CAT1', $certs, true);
                        if ($hasCAT1) {
                            $hasCAT2 = in_array('CAT2', $certs, true);
                            if ($hasCAT2) {
                                $gradeCible = 'Sergent';
                                $c1 = $m->certificats->firstWhere('niveau_certificat', 'CAT1');
                                if ($c1 && $c1->pivot->date_obtention) {
                                    $dateAnciennete = Carbon::parse($c1->pivot->date_obtention)->addYears(3);
                                }
                            } else {
                                $age = $dNaissance ? $dNaissance->age : 0;
                                if ($age >= 47) {
                                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                                    if ($ancG >= 3) {
                                        $gradeCible = 'Caporal-chef';
                                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                                        $promoDetail = 'âge ≥ 47 ans';
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'Sergent':
                    if ($conditionsBase) {
                        $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                        if ($ancG >= 2) {
                            $anc = $dEntree ? $dEntree->diffInYears($refDate) : 0;
                            if ($anc >= 5) {
                                $gradeCible = 'Sergent-Chef';
                                $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(2) : ($dEntree ? $dEntree->copy()->addYears(2) : null);
                            }
                        }
                    }
                    break;

                case 'Sergent-Chef':
                    if ($conditionsBase) {
                        $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                        if ($ancG >= 3) {
                            $gradeCible = 'Adjudant';
                            $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                        }
                    }
                    break;

                case 'Adjudant':
                    if ($conditionsBase) {
                        $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                        if ($ancG >= 2) {
                            $gradeCible = 'Adjudant-Chef';
                            $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(2) : null;
                        }
                    }
                    break;

                case 'Sous-lieutenant':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 2) {
                        $gradeCible = 'Lieutenant';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(2) : null;
                    }
                    break;

                case 'Lieutenant':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 3) {
                        $gradeCible = 'Capitaine';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                    }
                    break;

                case 'Capitaine':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 3) {
                        $gradeCible = 'Commandant';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                    }
                    break;

                case 'Commandant':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 3) {
                        $gradeCible = 'Lieutenant-colonel';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                    }
                    break;

                case 'Lieutenant-colonel':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 3) {
                        $gradeCible = 'Colonel';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(3) : null;
                    }
                    break;

                case 'Colonel':
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 6) {
                        $gradeCible = 'Colonel-Major';
                        $dateAnciennete = $dPromo ? $dPromo->copy()->addYears(6) : null;
                    }
                    break;
            }

            if ($gradeCible) {
                if ($dateAnciennete && $dateAnciennete->year <= $refYear) {
                    $msg = "Proposable pour {$refYearStr} (ancienneté atteinte le " . $dateAnciennete->format('d/m/Y') . ")";
                } elseif ($dateAnciennete) {
                    $msg = "Proposable pour {$refYearStr} (ancienneté atteinte le " . $dateAnciennete->format('d/m/Y') . " - dans l'année)";
                } elseif ($promoDetail) {
                    $msg = "Proposable pour {$refYearStr} ({$promoDetail})";
                } else {
                    $msg = "Proposable pour {$refYearStr}";
                }

                $allPromotions[] = [
                    'militaire' => [
                        'id' => $m->id,
                        'matricule' => $m->matricule,
                        'nom' => $m->nom,
                        'prenom' => $m->prenom,
                        'grade_actuel' => $grade,
                    ],
                    'type' => 'Promotion',
                    'grade_cible' => $gradeCible,
                    'message' => $msg,
                    'date_estimation' => $datePropStr,
                    'annee_proposition' => $refYearStr,
                    'date_anciennete' => $dateAnciennete ? $dateAnciennete->format('Y-m-d') : null,
                ];

                $gradeCounts[$grade] = ($gradeCounts[$grade] ?? 0) + 1;
            }

            // 2. FORMATIONS
            $age = null;
            $anc = null;
            $ancG = null;

            // CAT1
            if (($grade === 'Soldat 2' || $grade === 'Soldat 1') && $conditionsBase) {
                $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                if ($ancG >= 5) {
                    if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                    if (!in_array('CAT1', $certs, true)) {
                        $dCond = $dEntree ? $dEntree->copy()->addYears(5) : null;
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'CAT1',
                            'nom_formation' => 'Certificat d\'Aptitude Technique Niveau 1',
                            'message' => "Proposable pour {$refYearStr}" . ($dCond ? " (conditions remplies le " . $dCond->format('d/m/Y') . ")" : ""),
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => $dCond ? $dCond->format('Y-m-d') : null,
                        ];
                        $formationCounts['CAT1'] = ($formationCounts['CAT1'] ?? 0) + 1;
                    }
                }
            }

            // CAT2
            if ($grade === 'Caporal' && $conditionsBase) {
                if ($age === null) $age = $dNaissance ? $dNaissance->age : 0;
                if ($age < 47) {
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if ($ancG >= 3) {
                        if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                        if (!in_array('CAT2', $certs, true) && in_array('CAT1', $certs, true)) {
                            $c1 = $m->certificats->firstWhere('niveau_certificat', 'CAT1');
                            $dCond = ($c1 && $c1->pivot->date_obtention) ? Carbon::parse($c1->pivot->date_obtention)->addYears(3) : null;
                            $allFormations[] = [
                                'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                                'formation' => 'CAT2',
                                'nom_formation' => 'Certificat d\'Aptitude Technique Niveau 2',
                                'message' => "Proposable pour {$refYearStr}" . ($dCond ? " (conditions remplies le " . $dCond->format('d/m/Y') . ")" : ""),
                                'date_estimation' => $datePropStr,
                                'annee_proposition' => $refYearStr,
                                'date_conditions' => $dCond ? $dCond->format('Y-m-d') : null,
                            ];
                            $formationCounts['CAT2'] = ($formationCounts['CAT2'] ?? 0) + 1;
                        }
                    }
                }
            }

            // CIA
            if (in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef'], true) && $conditionsBase && $m->a_permis_conduire) {
                if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                if (!in_array('CIA', $certs, true)) {
                    $allFormations[] = [
                        'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                        'formation' => 'CIA',
                        'nom_formation' => 'Certificat d\'Instruction d\'Armes',
                        'message' => "Proposable pour {$refYearStr} (permis de conduire requis)",
                        'date_estimation' => $datePropStr,
                        'annee_proposition' => $refYearStr,
                        'date_conditions' => null,
                    ];
                    $formationCounts['CIA'] = ($formationCounts['CIA'] ?? 0) + 1;
                }
            }

            // BA1
            if (in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef'], true) && $conditionsBase) {
                $anc = $dEntree ? $dEntree->diffInYears($refDate) : 0;
                if ($anc >= 8) {
                    if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                    if (!in_array('BA1', $certs, true) && in_array('CIA', $certs, true)) {
                        $c = $m->certificats->firstWhere('niveau_certificat', 'CIA');
                        $dCond = ($c && $c->pivot->date_obtention) ? Carbon::parse($c->pivot->date_obtention)->addYears(3) : null;
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'BA1',
                            'nom_formation' => 'Brevet d\'Aptitude Niveau 1',
                            'message' => "Proposable pour {$refYearStr}" . ($dCond ? " (conditions remplies le " . $dCond->format('d/m/Y') . ")" : ""),
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => $dCond ? $dCond->format('Y-m-d') : null,
                        ];
                        $formationCounts['BA1'] = ($formationCounts['BA1'] ?? 0) + 1;
                    }
                }
            }

            // BA2
            if (in_array($grade, ['Adjudant', 'Adjudant-Chef'], true) && $conditionsBase) {
                if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                if (!in_array('BA2', $certs, true) && in_array('BA1', $certs, true)) {
                    $c = $m->certificats->firstWhere('niveau_certificat', 'BA1');
                    $dCond = ($c && $c->pivot->date_obtention) ? Carbon::parse($c->pivot->date_obtention)->addYears(3) : null;
                    $allFormations[] = [
                        'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                        'formation' => 'BA2',
                        'nom_formation' => 'Brevet d\'Aptitude Niveau 2',
                        'message' => "Proposable pour {$refYearStr}" . ($dCond ? " (conditions remplies le " . $dCond->format('d/m/Y') . ")" : ""),
                        'date_estimation' => $datePropStr,
                        'annee_proposition' => $refYearStr,
                        'date_conditions' => $dCond ? $dCond->format('Y-m-d') : null,
                    ];
                    $formationCounts['BA2'] = ($formationCounts['BA2'] ?? 0) + 1;
                }
            }

            // APLI
            if (in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine'], true)) {
                if ($age === null) $age = $dNaissance ? $dNaissance->age : 0;
                if ($age <= 50) {
                    if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                    if (!in_array('APLI', $certs, true) && !in_array('CFCU', $certs, true)) {
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'APLI',
                            'nom_formation' => 'Cour d\'Application',
                            'message' => "Proposable pour {$refYearStr} (âge ≤ 50 ans)",
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => null,
                        ];
                        $formationCounts['APLI'] = ($formationCounts['APLI'] ?? 0) + 1;
                    }
                }
            }

            // CFCU
            if (in_array($grade, ['Lieutenant', 'Capitaine'], true)) {
                if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                if (!in_array('CFCU', $certs, true)) {
                    if ($grade === 'Capitaine' || in_array('APLI', $certs, true)) {
                        $cond = $grade === 'Capitaine' ? 'grade Capitaine' : 'APLI validé';
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'CFCU',
                            'nom_formation' => 'Cour des Futurs Commandants d\'Unité',
                            'message' => "Proposable pour {$refYearStr} ({$cond})",
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => null,
                        ];
                        $formationCounts['CFCU'] = ($formationCounts['CFCU'] ?? 0) + 1;
                    }
                }
            }

            // CEM
            if (in_array($grade, ['Capitaine', 'Commandant'], true)) {
                if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                if (!in_array('CEM', $certs, true)) {
                    $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                    if (($grade === 'Capitaine' && $ancG >= 3) || $grade === 'Commandant') {
                        if ($age === null) $age = $dNaissance ? $dNaissance->age : 0;
                        if ($age <= 45) {
                            $allFormations[] = [
                                'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                                'formation' => 'CEM',
                                'nom_formation' => 'Cour d\'État-Major',
                                'message' => "Proposable pour {$refYearStr} (âge ≤ 45 ans)",
                                'date_estimation' => $datePropStr,
                                'annee_proposition' => $refYearStr,
                                'date_conditions' => null,
                            ];
                            $formationCounts['CEM'] = ($formationCounts['CEM'] ?? 0) + 1;
                        }
                    }
                }
            }

            // Certificat d'État-Major (CERT_EM)
            if ($grade === 'Commandant') {
                if ($age === null) $age = $dNaissance ? $dNaissance->age : 0;
                if ($age > 45) {
                    if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                    if (!in_array('CERT_EM', $certs, true)) {
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'CERT_EM',
                            'nom_formation' => 'Certificat d\'État-Major',
                            'message' => "Proposable pour {$refYearStr} (âge > 45 ans)",
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => null,
                        ];
                        $formationCounts['CERT_EM'] = ($formationCounts['CERT_EM'] ?? 0) + 1;
                    }
                }
            }

            // École de Guerre (ECOLE_GUERRE)
            if (in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major'], true)) {
                $ancG = $dPromo ? $dPromo->diffInYears($refDate) : ($dEntree ? $dEntree->diffInYears($refDate) : 0);
                if ($ancG >= 2) {
                    if ($age === null) $age = $dNaissance ? $dNaissance->age : 0;
                    if ($age <= 53) {
                        if ($certs === null) $certs = $m->certificats->pluck('niveau_certificat')->all();
                        if (!in_array('ECOLE_GUERRE', $certs, true)) {
                            $allFormations[] = [
                                'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                                'formation' => 'ECOLE_GUERRE',
                                'nom_formation' => 'École de Guerre',
                                'message' => "Proposable pour {$refYearStr} (âge ≤ 53 ans)",
                                'date_estimation' => $datePropStr,
                                'annee_proposition' => $refYearStr,
                                'date_conditions' => null,
                            ];
                            $formationCounts['ECOLE_GUERRE'] = ($formationCounts['ECOLE_GUERRE'] ?? 0) + 1;
                        }
                    }
                }
            }

            // 3. RETRAITES
            $dRetraite = $m->calculerDateRetraite();
            if ($dRetraite) {
                $diffJours = $now->diffInDays($dRetraite, false);
                $moisRestants = floor($diffJours / 30);
                if ($moisRestants <= 12 && $diffJours >= 0) {
                    $allRetraites[] = [
                        'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                        'date_retraite' => $dRetraite->format('Y-m-d'),
                        'date_retraite_formatted' => $dRetraite->format('d/m/Y'),
                        'mois_restants' => $moisRestants,
                    ];
                }
            }

            // 4. CONTRATS
            $hasContratAlerte = isset($alertesContrat[$m->id]);
            $isEligibleContrat = $hasContratAlerte;

            if (!$isEligibleContrat && $dEntree && $conditionsBase && $m->statut === 'actif') {
                $anneesServiceContrat = (int) floor($dEntree->diffInYears($now));
                $gradesContrats = [
                    'Soldat 2', 'Soldat 1', 'Caporal', 'Caporal-chef',
                    'Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef', 'Major',
                    'Sapeur 1ère classe'
                ];
                if ($anneesServiceContrat >= 5 && in_array($grade, $gradesContrats)) {
                    $cActif = $m->contratActif;
                    // Éligible au renouvellement si sans contrat actif ou contrat finissant dans <= 6 mois (ou expiré)
                    if (!$cActif || !$cActif->date_fin || !$cActif->date_fin->isFuture() || $cActif->date_fin->diffInDays($now) <= 180) {
                        $isEligibleContrat = true;
                    }
                }
            }

            if ($isEligibleContrat) {
                $contratActif = $m->contratActif;
                $anneesService = $dEntree ? (int) floor($dEntree->diffInYears($now)) : 0;
                $msgContrat = $alertesContrat[$m->id] ?? "Renouvellement de contrat requis pour {$m->prenom} {$m->nom} ({$anneesService} ans de service) - Grade: {$grade}";
                $allContrats[] = [
                    'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                    'annees_service' => $anneesService,
                    'statut_contrat' => $contratActif ? 'actif' : 'sans contrat',
                    'date_echeance' => $contratActif?->date_fin?->format('Y-m-d'),
                    'message' => $msgContrat,
                ];
            }

            // 5. MUTATIONS (>= 3 ans d'affectation calculée sur la date de mutation la plus RÉCENTE)
            $plusRecenteAff = $m->relationLoaded('affectations') && $m->affectations->isNotEmpty()
                ? $m->affectations->first()
                : null;

            $dateMutation = $plusRecenteAff?->date_mutation 
                ?? $m->date_mutation 
                ?? $m->date_entree_service;

            $uniteActuelle = $plusRecenteAff?->unite ?? $m->unite ?? 'Unité non précisée';
            $regionActuelle = $plusRecenteAff?->region ?? $m->region ?? '-';

            if ($dateMutation) {
                $dMutationCarbon = $dateMutation instanceof Carbon ? $dateMutation : Carbon::parse($dateMutation);
                $duree = floor($dMutationCarbon->diffInYears($now));
                if ($duree >= 3) {
                    $allMutations[] = [
                        'id' => $m->id,
                        'matricule' => $m->matricule,
                        'nom' => $m->nom,
                        'prenom' => $m->prenom,
                        'grade_actuel' => $grade,
                        'unite' => $uniteActuelle,
                        'region' => $regionActuelle,
                        'date_mutation' => $dMutationCarbon->format('d/m/Y'),
                        'duree_annees' => $duree,
                        'conditions_remplies' => "En poste depuis {$duree} ans (>= 3 ans)",
                        'statut_eligibilite' => 'Éligible à la mutation'
                    ];
                }
            }
        }

        // Tri
        usort($allPromotions, fn($a, $b) => strcmp($a['date_estimation'] ?? '', $b['date_estimation'] ?? ''));
        usort($allFormations, fn($a, $b) => strcmp($a['date_estimation'] ?? '', $b['date_estimation'] ?? ''));
        usort($allRetraites, fn($a, $b) => $a['date_retraite'] <=> $b['date_retraite']);
        usort($allContrats, fn($a, $b) => $b['annees_service'] <=> $a['annees_service']);
        usort($allMutations, fn($a, $b) => ($b['duree_annees'] ?? 0) <=> ($a['duree_annees'] ?? 0));

        return [
            'promotions' => $allPromotions,
            'formations' => $allFormations,
            'retraites' => $allRetraites,
            'contrats' => $allContrats,
            'mutations' => $allMutations,
            'statistiques' => [
                'total_promotions' => count($allPromotions),
                'total_formations' => count($allFormations),
                'total_retraites' => count($allRetraites),
                'total_contrats' => count($allContrats),
                'total_mutations' => count($allMutations),
            ],
            'formation_counts' => $formationCounts,
            'grade_counts' => $gradeCounts,
        ];
    }

    /**
     * Récupère la liste unique de toutes les formations.
     */
    private function getFormationsListe(): array
    {
        return [
            ['id' => 'BE', 'nom' => 'BE (Brevet Élémentaire)'],
            ['id' => 'CT1', 'nom' => 'CT1 (Certificat Technique N1)'],
            ['id' => 'CAT1', 'nom' => 'CAT1 (Certificat Technique Niveau 1)'],
            ['id' => 'CAT2', 'nom' => 'CAT2 (Certificat Technique Niveau 2)'],
            ['id' => 'CIA', 'nom' => 'CIA (Certificat Interarmes)'],
            ['id' => 'BA1', 'nom' => 'BA1 (Brevet d\'Arme N1)'],
            ['id' => 'BA2', 'nom' => 'BA2 (Brevet d\'Arme N2)'],
            ['id' => 'APLI', 'nom' => 'APLI (Cour d\'Application)'],
            ['id' => 'CFCU', 'nom' => 'CFCU (Cour des Futurs Commandants)'],
            ['id' => 'CERT_EM', 'nom' => 'Certificat d\'État-Major'],
            ['id' => 'ECOLE_GUERRE', 'nom' => 'École de Guerre'],
        ];
    }

    /**
     * Récupère la liste unique de tous les grades.
     */
    private function getGradesListe(): \Illuminate\Support\Collection
    {
        return Grade::getAllCached()->map(fn($g) => [
            'id' => $g->nom_grade,
            'nom' => $g->nom_grade
        ]);
    }

    /**
     * Exporte les éligibilités vers Excel.
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'all');
        $formation = $request->input('formation', '');
        $grade = $request->input('grade', '');
        $exportAll = filter_var($request->input('export_all', false), FILTER_VALIDATE_BOOLEAN) || ($type === 'all');

        $allComputed = $this->getAllComputedData();

        $exportData = [];
        $categories = $exportAll ? ['promotions', 'formations', 'retraites', 'contrats', 'mutations'] : ([$type] ?: ['promotions']);

        foreach ($categories as $cat) {
            $catItems = $allComputed[$cat] ?? [];
            if (!empty($grade)) {
                $catItems = array_values(array_filter($catItems, fn($it) => ($it['militaire']['grade_actuel'] ?? $it['grade_actuel'] ?? '') === $grade));
            }
            if (!empty($formation) && $cat === 'formations') {
                $catItems = array_values(array_filter($catItems, fn($it) => ($it['formation'] ?? '') === $formation));
            }
            $exportData[$cat] = $catItems;
        }

        $totalItems = 0;
        foreach ($exportData as $items) {
            $totalItems += count($items);
        }

        if ($totalItems === 0) {
            return redirect()->back()->with('error', 'Aucune donnée à exporter.');
        }

        $typeName = $type ?: 'all';
        $fileName = "eligibilites_{$typeName}_" . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new EligibilitesExport($exportData, $type), $fileName);
    }
}
