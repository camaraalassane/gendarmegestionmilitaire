<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Alerte;
use App\Models\Grade;
use App\Exports\EligibilitesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('eligibilites/index', [
            'formationsListe' => $formationsListe,
            'gradesListe' => $gradesListe,
            'initialType' => 'promotions',
            'initialData' => Inertia::defer(fn () => $this->page('promotions', null, null, 1, 30)[0]),
            'initialTotal' => Inertia::defer(fn () => $this->meta()['statistiques']['total_promotions'] ?? 0),
            'initialStatistiques' => Inertia::defer(fn () => $this->meta()['statistiques']),
            'initialFormationCounts' => Inertia::defer(fn () => $this->meta()['formation_counts']),
            'initialGradeCounts' => Inertia::defer(fn () => $this->meta()['grade_counts']),
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

        $targetKey = in_array($type, self::TYPES, true) ? $type : 'promotions';
        [$paginated, $total] = $this->page($targetKey, $grade, $formation, $page, $perPage);
        $meta = $this->meta();

        return response()->json([
            'data' => $paginated,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) max(1, ceil($total / $perPage)),
            'statistiques' => $meta['statistiques'],
            'formation_counts' => $meta['formation_counts'],
            'grade_counts' => $meta['grade_counts'],
        ]);
    }

    /**
     * Calcule l'ensemble des éligibilités de manière ultra-optimisée par lots de 2 000.
     */
    public function computeAll(int $version): array
    {
        $now = Carbon::now();
        $refYear = (int) $now->year;
        $refYearStr = (string) $refYear;
        $refY = $refYear;
        $refMd = 1231;

        $nowY = (int) $now->year;
        $nowMd = (int) $now->format('md');
        $nowYmd = $now->format('Y-m-d');
        $datePropStr = "{$refYear}-12-31";

        $fastDiffYears = static function (?string $dateStr, int $targetY, int $targetMd): int {
            if (!$dateStr || strlen($dateStr) < 10) return 0;
            $y = (int) substr($dateStr, 0, 4);
            $md = (int) (substr($dateStr, 5, 2) . substr($dateStr, 8, 2));
            $diff = $targetY - $y;
            if ($targetMd < $md) {
                $diff--;
            }
            return max(0, $diff);
        };

        // 4. Grades de retraite
        $gradesRetraite = DB::table('grades')->whereNotNull('retraite_obligatoire')->pluck('retraite_obligatoire', 'nom_grade')->toArray();

        $totaux = ['promotions' => 0, 'formations' => 0, 'retraites' => 0, 'contrats' => 0, 'mutations' => 0];

        $allPromotions = [];
        $allFormations = [];
        $allRetraites = [];
        $allContrats = [];
        $allMutations = [];
        $formationCounts = [];
        $gradeCounts = [];

        $gradesContrats = [
            'Soldat 2' => true, 'Soldat 1' => true, 'Caporal' => true, 'Caporal-chef' => true,
            'Sergent' => true, 'Sergent-Chef' => true, 'Adjudant' => true, 'Adjudant-Chef' => true, 'Major' => true,
            'Sapeur 1ère classe' => true
        ];

        // Lecture par lots de 2 000 militaires (keyset sur l'id) : la mémoire ne dépend plus du nombre de militaires.
        // Certificats, contrats et affectations ne sont chargés que pour les militaires du lot.
        $dernierId = 0;
        while (true) {
            $lot = DB::table('militaires')
                ->whereRaw(\App\Support\Effectif::sqlActivite('statut'))
                ->whereNull('deleted_at')
                ->where('id', '>', $dernierId)
                ->select([
                    'id', 'matricule', 'nom', 'prenom', 'grade_id', 'grade_actuel', 'grade_ordre',
                    'date_entree_service', 'date_derniere_promotion',
                    'statut', 'statut_code', 'position_actuelle', 'a_permis_conduire', 'antecedent_judiciaire',
                    'antecedent_disciplinaire', 'date_naissance',
                    'unite', 'region', 'date_mutation'
                ])
                ->orderBy('id')
                ->limit(2000)
                ->get();

            if ($lot->isEmpty()) {
                break;
            }
            $dernierId = $lot->last()->id;
            $ids = $lot->pluck('id')->all();

            $certsMap = [];
            $rawCerts = DB::table('certificat_militaire')
                ->join('certificats', 'certificats.id', '=', 'certificat_militaire.certificat_id')
                ->whereIn('certificat_militaire.militaire_id', $ids)
                ->select('certificat_militaire.militaire_id', 'certificats.niveau_certificat', 'certificat_militaire.date_obtention')
                ->get();
            foreach ($rawCerts as $c) {
                $certsMap[$c->militaire_id][$c->niveau_certificat] = $c->date_obtention ? substr((string)$c->date_obtention, 0, 10) : true;
            }

            $contratsMap = DB::table('contrats')
                ->where('statut', 'actif')
                ->whereIn('militaire_id', $ids)
                ->select('id', 'militaire_id', 'date_fin', 'date_debut')
                ->get()
                ->keyBy('militaire_id')
                ->all();

            $latestAffectations = [];
            $affectationRows = DB::table('affectations')
                ->whereIn('militaire_id', $ids)
                ->select('militaire_id', 'unite', 'region', 'date_mutation')
                ->orderBy('date_mutation', 'desc')
                ->get();
            foreach ($affectationRows as $aff) {
                if (!isset($latestAffectations[$aff->militaire_id])) {
                    $latestAffectations[$aff->militaire_id] = $aff;
                }
            }

            foreach ($lot as $m) {
            $grade = $m->grade_actuel;
            $hasAntecedents = ($m->antecedent_judiciaire || $m->antecedent_disciplinaire);
            $conditionsBase = !$hasAntecedents;

            $dNaissanceStr = $m->date_naissance;
            $dEntreeStr = $m->date_entree_service;
            $dPromoStr = $m->date_derniere_promotion;
            $dMutationRaw = $m->date_mutation;

            $mCerts = $certsMap[$m->id] ?? [];

            // 1. PROMOTIONS
            $gradeCible = null;
            $dateAncienneteStr = null;
            $promoDetail = '';

            switch ($grade) {
                case 'Soldat 1':
                    if ($conditionsBase) {
                        $anc = $fastDiffYears($dEntreeStr, $refY, $refMd);
                        if ($anc >= 5 && isset($mCerts['CAT1'])) {
                            $gradeCible = 'Caporal';
                            $dateAncienneteStr = sprintf('%04d-%s', (int)substr($dEntreeStr, 0, 4) + 5, substr($dEntreeStr, 5, 5));
                        }
                    }
                    break;

                case 'Caporal':
                    if ($conditionsBase) {
                        if (isset($mCerts['CAT1'])) {
                            if (isset($mCerts['CAT2'])) {
                                $gradeCible = 'Sergent';
                                $dObt = is_string($mCerts['CAT1']) ? $mCerts['CAT1'] : null;
                                if ($dObt) {
                                    $dateAncienneteStr = sprintf('%04d-%s', (int)substr($dObt, 0, 4) + 3, substr($dObt, 5, 5));
                                }
                            } else {
                                $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
                                if ($age >= 47) {
                                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                                    if ($ancG >= 3) {
                                        $gradeCible = 'Caporal-chef';
                                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                                        $promoDetail = 'âge ≥ 47 ans';
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'Sergent':
                    if ($conditionsBase) {
                        $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                        if ($ancG >= 2) {
                            $anc = $fastDiffYears($dEntreeStr, $refY, $refMd);
                            if ($anc >= 5) {
                                $gradeCible = 'Sergent-Chef';
                                $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : ($dEntreeStr ? sprintf('%04d-%s', (int)substr($dEntreeStr, 0, 4) + 2, substr($dEntreeStr, 5, 5)) : null);
                            }
                        }
                    }
                    break;

                case 'Sergent-Chef':
                    if ($conditionsBase) {
                        $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                        if ($ancG >= 3) {
                            $gradeCible = 'Adjudant';
                            $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                        }
                    }
                    break;

                case 'Adjudant':
                    if ($conditionsBase) {
                        $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                        if ($ancG >= 2) {
                            $gradeCible = 'Adjudant-Chef';
                            $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : null;
                        }
                    }
                    break;

                case 'Sous-lieutenant':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 2) {
                        $gradeCible = 'Lieutenant';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;

                case 'Lieutenant':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 3) {
                        $gradeCible = 'Capitaine';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;

                case 'Capitaine':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 3) {
                        $gradeCible = 'Commandant';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;

                case 'Commandant':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 3) {
                        $gradeCible = 'Lieutenant-colonel';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;

                case 'Lieutenant-colonel':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 3) {
                        $gradeCible = 'Colonel';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;

                case 'Colonel':
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 6) {
                        $gradeCible = 'Colonel-Major';
                        $dateAncienneteStr = $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 6, substr($dPromoStr, 5, 5)) : null;
                    }
                    break;
            }

            if ($gradeCible) {
                $ancYear = $dateAncienneteStr ? (int)substr($dateAncienneteStr, 0, 4) : 0;
                $ancFormatted = $dateAncienneteStr ? sprintf('%02d/%02d/%04d', (int)substr($dateAncienneteStr, 8, 2), (int)substr($dateAncienneteStr, 5, 2), (int)substr($dateAncienneteStr, 0, 4)) : '';
                if ($dateAncienneteStr && $ancYear <= $refYear) {
                    $msg = "Proposable pour {$refYearStr} (ancienneté atteinte le {$ancFormatted})";
                } elseif ($dateAncienneteStr) {
                    $msg = "Proposable pour {$refYearStr} (ancienneté atteinte le {$ancFormatted} - dans l'année)";
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
                    'date_anciennete' => $dateAncienneteStr,
                ];
                $gradeCounts[$grade] = ($gradeCounts[$grade] ?? 0) + 1;
            }

            // 2. FORMATIONS
            $age = null;
            $ancG = null;

            if (($grade === 'Soldat 2' || $grade === 'Soldat 1') && $conditionsBase) {
                $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                if ($ancG >= 5) {
                    if (!isset($mCerts['CAT1'])) {
                        $dCondStr = $dEntreeStr ? sprintf('%04d-%s', (int)substr($dEntreeStr, 0, 4) + 5, substr($dEntreeStr, 5, 5)) : null;
                        $dCondFormatted = $dCondStr ? sprintf('%02d/%02d/%04d', (int)substr($dCondStr, 8, 2), (int)substr($dCondStr, 5, 2), (int)substr($dCondStr, 0, 4)) : '';
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'CAT1',
                            'nom_formation' => 'Certificat d\'Aptitude Technique Niveau 1',
                            'message' => "Proposable pour {$refYearStr}" . ($dCondStr ? " (conditions remplies le {$dCondFormatted})" : ""),
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => $dCondStr,
                        ];
                        $formationCounts['CAT1'] = ($formationCounts['CAT1'] ?? 0) + 1;
                    }
                }
            }

            if ($grade === 'Caporal' && $conditionsBase) {
                if ($age === null) $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
                if ($age < 47) {
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if ($ancG >= 3) {
                        if (!isset($mCerts['CAT2']) && isset($mCerts['CAT1'])) {
                            $dObt = is_string($mCerts['CAT1']) ? $mCerts['CAT1'] : null;
                            $dCondStr = $dObt ? sprintf('%04d-%s', (int)substr($dObt, 0, 4) + 3, substr($dObt, 5, 5)) : null;
                            $dCondFormatted = $dCondStr ? sprintf('%02d/%02d/%04d', (int)substr($dCondStr, 8, 2), (int)substr($dCondStr, 5, 2), (int)substr($dCondStr, 0, 4)) : '';
                            $allFormations[] = [
                                'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                                'formation' => 'CAT2',
                                'nom_formation' => 'Certificat d\'Aptitude Technique Niveau 2',
                                'message' => "Proposable pour {$refYearStr}" . ($dCondStr ? " (conditions remplies le {$dCondFormatted})" : ""),
                                'date_estimation' => $datePropStr,
                                'annee_proposition' => $refYearStr,
                                'date_conditions' => $dCondStr,
                            ];
                            $formationCounts['CAT2'] = ($formationCounts['CAT2'] ?? 0) + 1;
                        }
                    }
                }
            }

            if (in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef'], true) && $conditionsBase && $m->a_permis_conduire) {
                if (!isset($mCerts['CIA'])) {
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

            if (in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef'], true) && $conditionsBase) {
                $anc = $fastDiffYears($dEntreeStr, $refY, $refMd);
                if ($anc >= 8) {
                    if (!isset($mCerts['BA1']) && isset($mCerts['CIA'])) {
                        $dObt = is_string($mCerts['CIA']) ? $mCerts['CIA'] : null;
                        $dCondStr = $dObt ? sprintf('%04d-%s', (int)substr($dObt, 0, 4) + 3, substr($dObt, 5, 5)) : null;
                        $dCondFormatted = $dCondStr ? sprintf('%02d/%02d/%04d', (int)substr($dCondStr, 8, 2), (int)substr($dCondStr, 5, 2), (int)substr($dCondStr, 0, 4)) : '';
                        $allFormations[] = [
                            'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                            'formation' => 'BA1',
                            'nom_formation' => 'Brevet d\'Aptitude Niveau 1',
                            'message' => "Proposable pour {$refYearStr}" . ($dCondStr ? " (conditions remplies le {$dCondFormatted})" : ""),
                            'date_estimation' => $datePropStr,
                            'annee_proposition' => $refYearStr,
                            'date_conditions' => $dCondStr,
                        ];
                        $formationCounts['BA1'] = ($formationCounts['BA1'] ?? 0) + 1;
                    }
                }
            }

            if (in_array($grade, ['Adjudant', 'Adjudant-Chef'], true) && $conditionsBase) {
                if (!isset($mCerts['BA2']) && isset($mCerts['BA1'])) {
                    $dObt = is_string($mCerts['BA1']) ? $mCerts['BA1'] : null;
                    $dCondStr = $dObt ? sprintf('%04d-%s', (int)substr($dObt, 0, 4) + 3, substr($dObt, 5, 5)) : null;
                    $dCondFormatted = $dCondStr ? sprintf('%02d/%02d/%04d', (int)substr($dCondStr, 8, 2), (int)substr($dCondStr, 5, 2), (int)substr($dCondStr, 0, 4)) : '';
                    $allFormations[] = [
                        'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                        'formation' => 'BA2',
                        'nom_formation' => 'Brevet d\'Aptitude Niveau 2',
                        'message' => "Proposable pour {$refYearStr}" . ($dCondStr ? " (conditions remplies le {$dCondFormatted})" : ""),
                        'date_estimation' => $datePropStr,
                        'annee_proposition' => $refYearStr,
                        'date_conditions' => $dCondStr,
                    ];
                    $formationCounts['BA2'] = ($formationCounts['BA2'] ?? 0) + 1;
                }
            }

            if (in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine'], true)) {
                if ($age === null) $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
                if ($age <= 50) {
                    if (!isset($mCerts['APLI']) && !isset($mCerts['CFCU'])) {
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

            if (in_array($grade, ['Lieutenant', 'Capitaine'], true)) {
                if (!isset($mCerts['CFCU'])) {
                    if ($grade === 'Capitaine' || isset($mCerts['APLI'])) {
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

            if (in_array($grade, ['Capitaine', 'Commandant'], true)) {
                if (!isset($mCerts['CEM'])) {
                    $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                    if (($grade === 'Capitaine' && $ancG >= 3) || $grade === 'Commandant') {
                        if ($age === null) $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
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

            if ($grade === 'Commandant') {
                if ($age === null) $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
                if ($age > 45) {
                    if (!isset($mCerts['CERT_EM'])) {
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

            if (in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major'], true)) {
                $ancG = $dPromoStr ? $fastDiffYears($dPromoStr, $refY, $refMd) : $fastDiffYears($dEntreeStr, $refY, $refMd);
                if ($ancG >= 2) {
                    if ($age === null) $age = $fastDiffYears($dNaissanceStr, $nowY, $nowMd);
                    if ($age <= 53) {
                        if (!isset($mCerts['ECOLE_GUERRE'])) {
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
            $isOtage = str_contains(mb_strtolower((string) $m->position_actuelle), 'otage');
            if (!$isOtage && $dNaissanceStr && $grade) {
                $ageRetraite = $gradesRetraite[$grade] ?? null;
                if ($ageRetraite) {
                    $anneeRetraite = (int)substr($dNaissanceStr, 0, 4) + $ageRetraite;
                    if ($anneeRetraite === $nowY || $anneeRetraite === $nowY + 1) {
                        $dRetraite = Carbon::parse($dNaissanceStr)->addYears($ageRetraite);
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
                }
            }

            // 4. CONTRATS (Tous les grades contractuels éligibles au renouvellement ou régularisation)
            $cActif = $contratsMap[$m->id] ?? null;
            $isEligibleContrat = false;

            if ($m->statut_code >= 1 && $m->statut_code <= 4) {
                if (isset($gradesContrats[$grade])) {
                    $cFinStr = $cActif?->date_fin ? substr((string)$cActif->date_fin, 0, 10) : null;
                    if (!$cActif || !$cFinStr || $cFinStr <= $nowYmd || ($fastDiffYears($nowYmd, (int)substr($cFinStr, 0, 4), (int)substr($cFinStr, 5, 2) . (int)substr($cFinStr, 8, 2)) === 0)) {
                        $anneesServiceContrat = $fastDiffYears($dEntreeStr, $nowY, $nowMd);
                        if (!$dEntreeStr || $anneesServiceContrat >= 5) {
                            $isEligibleContrat = true;
                        }
                    }
                }
            }

            if ($isEligibleContrat) {
                $cFinStr = $cActif?->date_fin ? substr((string)$cActif->date_fin, 0, 10) : null;
                $anneesService = $fastDiffYears($dEntreeStr, $nowY, $nowMd);
                $msgContrat = ($anneesService > 0 
                    ? "Renouvellement de contrat requis pour {$m->prenom} {$m->nom} ({$anneesService} ans de service) - Grade: {$grade}"
                    : "Régularisation / Renouvellement de contrat requis pour {$m->prenom} {$m->nom} (sans contrat actif) - Grade: {$grade}");
                $allContrats[] = [
                    'militaire' => ['id' => $m->id, 'matricule' => $m->matricule, 'nom' => $m->nom, 'prenom' => $m->prenom, 'grade_actuel' => $grade],
                    'annees_service' => $anneesService,
                    'statut_contrat' => $cActif ? 'actif' : 'sans contrat',
                    'date_echeance' => $cFinStr,
                    'message' => $msgContrat,
                ];
            }

            // 5. MUTATIONS
            $aff = $latestAffectations[$m->id] ?? null;
            $dateMutationStr = $aff?->date_mutation ? substr((string)$aff->date_mutation, 0, 10) : ($dMutationRaw ? substr((string)$dMutationRaw, 0, 10) : ($dEntreeStr ? substr((string)$dEntreeStr, 0, 10) : null));
            $uniteActuelle = $aff?->unite ?? $m->unite ?? 'Unité non précisée';
            $regionActuelle = $aff?->region ?? $m->region ?? '-';

            if ($dateMutationStr) {
                $duree = $fastDiffYears($dateMutationStr, $nowY, $nowMd);
                if ($duree >= 3) {
                    $dMutFormatted = sprintf('%02d/%02d/%04d', (int)substr($dateMutationStr, 8, 2), (int)substr($dateMutationStr, 5, 2), (int)substr($dateMutationStr, 0, 4));
                    $allMutations[] = [
                        'id' => $m->id,
                        'matricule' => $m->matricule,
                        'nom' => $m->nom,
                        'prenom' => $m->prenom,
                        'grade_actuel' => $grade,
                        'unite' => $uniteActuelle,
                        'region' => $regionActuelle,
                        'date_mutation' => $dMutFormatted,
                        'duree_annees' => $duree,
                        'conditions_remplies' => "En poste depuis {$duree} ans (>= 3 ans)",
                        'statut_eligibilite' => 'Éligible à la mutation'
                    ];
                }
            }
        }

            $this->viderVersTable('promotions', $allPromotions, $version, $totaux);
            $this->viderVersTable('formations', $allFormations, $version, $totaux);
            $this->viderVersTable('retraites', $allRetraites, $version, $totaux);
            $this->viderVersTable('contrats', $allContrats, $version, $totaux);
            $this->viderVersTable('mutations', $allMutations, $version, $totaux);
        } // fin while lots

        return [
            'statistiques' => [
                'total_promotions' => $totaux['promotions'],
                'total_formations' => $totaux['formations'],
                'total_retraites' => $totaux['retraites'],
                'total_contrats' => $totaux['contrats'],
                'total_mutations' => $totaux['mutations'],
            ],
            'formation_counts' => $formationCounts,
            'grade_counts' => $gradeCounts,
        ];
    }

    /**
     * Écrit un lot d'éligibilités dans la table pré-calculée puis vide le tableau mémoire.
     * Clé de tri : promotions/formations = 0 (ordre d'id) ; retraites = date de retraite (AAAAMMJJ) ;
     * contrats = -années de service ; mutations = -durée en années (même ordre que l'ancien usort).
     */
    private function viderVersTable(string $type, array &$items, int $version, array &$totaux): void
    {
        if (!$items) {
            return;
        }
        $rows = [];
        foreach ($items as $it) {
            $mil = $it['militaire'] ?? $it;
            $tri = 0;
            if ($type === 'retraites') {
                $tri = (int) str_replace('-', '', (string) ($it['date_retraite'] ?? '0'));
            } elseif ($type === 'contrats') {
                $tri = -((int) ($it['annees_service'] ?? 0));
            } elseif ($type === 'mutations') {
                $tri = -((int) ($it['duree_annees'] ?? 0));
            }
            $rows[] = [
                'version' => $version,
                'type' => $type,
                'militaire_id' => (int) ($mil['id'] ?? 0),
                'grade_actuel' => $mil['grade_actuel'] ?? null,
                'formation' => $type === 'formations' ? ($it['formation'] ?? null) : null,
                'tri' => $tri,
                'payload' => json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }
        foreach (array_chunk($rows, 500) as $part) {
            DB::table('eligibilites_calculees')->insert($part);
        }
        $totaux[$type] += count($items);
        $items = [];
    }

    /**
     * S'assure que la table pré-calculée correspond à la version actuelle des données.
     * Si la version actuelle n'est pas encore prête :
     * 1. Déclenche la reconstruction en arrière-plan (job asynchrone sans bloquer l'utilisateur).
     * 2. Retourne immédiatement la dernière version disponible en base (zéro seconde d'attente).
     * 3. Ne fait le calcul synchrone qu'au tout premier démarrage si aucune version précédente n'existe.
     */
    public function assurerCalcul(): int
    {
        $v = (int) \App\Models\Militaire::getCacheVersion();
        $prete = $this->versionPrete(); // lue dans la BASE : survit à cache:clear et aux redéploiements

        if ($prete === $v) {
            return $v;
        }

        // Reconstruction en arrière-plan (file d'attente) : un seul job à la fois
        if (Cache::add('eligibilites:rebuild:lock', 1, 600)) {
            \App\Jobs\RebuildEligibilitesTableJob::dispatch($v);
        }

        // Une version précédente existe : on la sert tout de suite, sans faire attendre l'utilisateur
        if ($prete > 0) {
            return $prete;
        }

        // Aucune version disponible (toute première utilisation) : calcul initial synchrone de secours
        DB::table('eligibilites_calculees')->where('version', $v)->delete();
        $this->finaliserVersion($v, $this->computeAll($v));
        Cache::forget('eligibilites:rebuild:lock');

        return $v;
    }

    /** Dernière version COMPLÈTE : celle dont la ligne de fin (type 'meta') a été écrite. 0 si aucune. */
    public function versionPrete(): int
    {
        return (int) DB::table('eligibilites_calculees')->where('type', 'meta')->max('version');
    }

    /**
     * Marque une version comme complète en écrivant sa ligne 'meta' EN DERNIER, puis supprime les anciennes versions.
     * Tant que cette ligne n'existe pas, la version en cours de construction n'est jamais lue.
     */
    public function finaliserVersion(int $v, array $meta): void
    {
        DB::table('eligibilites_calculees')->insert([
            'version' => $v, 'type' => 'meta', 'militaire_id' => 0, 'grade_actuel' => null,
            'formation' => null, 'tri' => 0,
            'payload' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
        do {
            $n = DB::table('eligibilites_calculees')->where('version', '<>', $v)->limit(5000)->delete();
        } while ($n > 0);
    }

    /** Statistiques et compteurs (petit tableau), lus dans la ligne 'meta' de la version servie. */
    public function meta(): array
    {
        $v = $this->assurerCalcul();
        $vide = [
            'statistiques' => ['total_promotions' => 0, 'total_formations' => 0, 'total_retraites' => 0, 'total_contrats' => 0, 'total_mutations' => 0],
            'formation_counts' => [],
            'grade_counts' => [],
        ];
        return Cache::remember("eligibilites:meta:v{$v}", 3600, function () use ($v, $vide) {
            $json = DB::table('eligibilites_calculees')->where('type', 'meta')->where('version', $v)->value('payload');
            return $json ? (json_decode($json, true) ?: $vide) : $vide;
        });
    }

    public const TYPES = ['promotions', 'formations', 'retraites', 'contrats', 'mutations'];

    private function requeteType(int $v, string $type, $grade = null, $formation = null)
    {
        $q = DB::table('eligibilites_calculees')->where('version', $v)->where('type', $type);
        if (!empty($grade)) {
            $gradeNom = is_numeric($grade) ? Grade::where('id', (int) $grade)->value('nom_grade') : $grade;
            $q->whereIn('grade_actuel', array_filter([(string) $grade, $gradeNom]));
        }
        if (!empty($formation) && $type === 'formations') {
            $q->where('formation', $formation);
        }
        return $q;
    }

    /** Une page d'éligibilités (SQL) : [items, total]. */
    public function page(string $type, $grade, $formation, int $page, int $perPage): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'promotions';
        $v = $this->assurerCalcul();
        $q = $this->requeteType($v, $type, $grade, $formation);
        $total = (clone $q)->count();
        $items = $q->orderBy('tri')->orderBy('id')
            ->offset(($page - 1) * $perPage)->limit($perPage)
            ->pluck('payload')
            ->map(fn ($p) => json_decode($p, true))
            ->all();
        return [$items, $total];
    }

    /** Parcours en flux d'un type d'éligibilités (mémoire constante côté appelant s'il n'accumule pas). */
    public function iterer(string $type, $grade = null, $formation = null): \Generator
    {
        $v = $this->assurerCalcul();
        foreach ($this->requeteType($v, $type, $grade, $formation)->orderBy('tri')->orderBy('id')->select('id', 'payload')->cursor() as $row) {
            yield json_decode($row->payload, true);
        }
    }

    /**
     * Rétro-compatibilité : si un composant appelle encore getAllComputedData().
     */
    public function getAllComputedData(): array
    {
        $meta = $this->meta();
        return [
            'promotions' => iterator_to_array($this->iterer('promotions'), false),
            'formations' => iterator_to_array($this->iterer('formations'), false),
            'retraites'  => iterator_to_array($this->iterer('retraites'), false),
            'contrats'   => iterator_to_array($this->iterer('contrats'), false),
            'mutations'  => iterator_to_array($this->iterer('mutations'), false),
            'statistiques' => $meta['statistiques'],
            'formation_counts' => $meta['formation_counts'],
            'grade_counts' => $meta['grade_counts'],
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

        $exportData = [];
        $categories = $exportAll ? self::TYPES : [in_array($type, self::TYPES, true) ? $type : 'promotions'];

        foreach ($categories as $cat) {
            $exportData[$cat] = iterator_to_array($this->iterer($cat, $grade ?: null, $formation ?: null), false);
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
