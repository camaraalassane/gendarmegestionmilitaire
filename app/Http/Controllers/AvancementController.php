<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Avancement;
use App\Models\Grade;
use App\Exports\AvancementsExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvancementController extends Controller
{
    /**
     * Affiche le tableau d'avancement avec classement automatique par grade (plus haut grade en premier)
     */
    public function index(Request $request): Response
    {
        // Session : toujours l'année en clôture
        $annee = (int) $request->input('annee', date('Y'));
        $gradeFiltre = $request->input('grade', 'all');
        $search = trim((string) $request->input('search', ''));

        // Récupérer la hiérarchie des grades pour le tri du plus élevé au plus bas
        $gradesHierarchy = Grade::pluck('ordre', 'nom_grade')->all();

        $candidates = $this->buildCandidatesList($annee);

        // Liste des grades uniques présents parmi les candidats, triés du plus élevé au moins élevé
        $availableGrades = collect($candidates)->pluck('grade_actuel')->filter()->unique()->values()->all();
        usort($availableGrades, function ($a, $b) use ($gradesHierarchy) {
            $orderA = $gradesHierarchy[$a] ?? 0;
            $orderB = $gradesHierarchy[$b] ?? 0;
            return $orderB <=> $orderA; // Décroissant : grade le plus élevé en premier
        });

        // Filtrage par grade si demandé
        if (!empty($gradeFiltre) && $gradeFiltre !== 'all') {
            $candidates = array_values(array_filter($candidates, fn($c) => ($c['grade_actuel'] ?? '') === $gradeFiltre));
        }

        // Recherche par nom, prénom ou matricule
        if (!empty($search)) {
            $lowerSearch = mb_strtolower($search);
            $candidates = array_values(array_filter($candidates, function ($c) use ($lowerSearch) {
                return str_contains(mb_strtolower($c['nom'] ?? ''), $lowerSearch)
                    || str_contains(mb_strtolower($c['prenom'] ?? ''), $lowerSearch)
                    || str_contains(mb_strtolower($c['matricule'] ?? ''), $lowerSearch);
            }));
        }

        return Inertia::render('Avancements/Index', [
            'candidates' => $candidates,
            'grades' => $availableGrades,
            'selectedAnnee' => $annee,
            'selectedGrade' => $gradeFiltre,
            'search' => $search,
            'stats' => [
                'total_candidats' => count($candidates),
                'total_grades' => count($availableGrades),
                'total_enregistres' => count(array_filter($candidates, fn($c) => !empty($c['est_enregistre']))),
            ]
        ]);
    }

    /**
     * Sauvegarde en lot des notes du tableau d'avancement
     */
    public function save(Request $request)
    {
        $request->validate([
            'annee' => 'required|integer',
            'items' => 'required|array',
            'items.*.militaire_id' => 'required|exists:militaires,id',
            'items.*.points_fonction_actuelle' => 'nullable|numeric|min:0|max:100',
            'items.*.note_rendement' => 'nullable|numeric|min:0|max:100',
            'items.*.bonus_dernier_emploi' => 'nullable|numeric|min:0|max:100',
            'items.*.points_diplomes' => 'nullable|numeric|min:0|max:100',
            'items.*.points_decorations' => 'nullable|numeric|min:0|max:100',
            'items.*.points_anciennete_grade' => 'nullable|numeric|min:0|max:100',
            'items.*.jours_punition' => 'nullable|integer|min:0',
            'items.*.annees_restantes_retraite' => 'nullable|integer',
            'items.*.malus_limite_age' => 'nullable|numeric|min:0|max:100',
        ]);

        $annee = (int) $request->input('annee', date('Y'));
        $items = $request->input('items', []);

        foreach ($items as $item) {
            $joursPunition = max(0, (int) ($item['jours_punition'] ?? 0));
            $malusPunition = round($joursPunition * 0.1, 2);

            $noteRendement = (float) ($item['note_rendement'] ?? 0);
            $ptsFonction = (float) ($item['points_fonction_actuelle'] ?? 0);
            $bonusEmploi = (float) ($item['bonus_dernier_emploi'] ?? 0);
            $ptsDiplomes = (float) ($item['points_diplomes'] ?? 0);
            $ptsDecos = (float) ($item['points_decorations'] ?? 0);
            $ptsAncGrade = (float) ($item['points_anciennete_grade'] ?? 0);

            // Règle limite d'âge : si reste <= 1 an = -2 pts, si reste 2 ans = -1 pt
            $anneesRestantes = isset($item['annees_restantes_retraite']) ? (int) $item['annees_restantes_retraite'] : null;
            if (isset($item['malus_limite_age'])) {
                $malusLimiteAge = (float) $item['malus_limite_age'];
            } else {
                $malusLimiteAge = ($anneesRestantes !== null && $anneesRestantes <= 1) ? 2.0 : (($anneesRestantes !== null && $anneesRestantes == 2) ? 1.0 : 0.0);
            }

            // Total = Rendement + Fonction + Bonus + Diplômes + Décorations + Ancienneté Grade - Malus Limite Âge - Malus Punition
            $total = round($noteRendement + $ptsFonction + $bonusEmploi + $ptsDiplomes + $ptsDecos + $ptsAncGrade - $malusLimiteAge - $malusPunition, 2);

            Avancement::updateOrCreate(
                [
                    'militaire_id' => $item['militaire_id'],
                    'annee' => $annee,
                ],
                [
                    'grade_actuel' => $item['grade_actuel'] ?? null,
                    'grade_cible' => $item['grade_cible'] ?? null,
                    'fonction_actuelle' => $item['fonction_actuelle'] ?? null,
                    'points_fonction_actuelle' => $ptsFonction,
                    'note_rendement' => $noteRendement,
                    'bonus_dernier_emploi' => $bonusEmploi,
                    'points_diplomes' => $ptsDiplomes,
                    'points_decorations' => $ptsDecos,
                    'points_anciennete_grade' => $ptsAncGrade,
                    'jours_punition' => $joursPunition,
                    'malus_punition' => $malusPunition,
                    'annees_restantes_retraite' => $anneesRestantes,
                    'malus_limite_age' => $malusLimiteAge,
                    'total_points' => max(0, $total),
                ]
            );
        }

        // Recalcul des rangs par grade en base de données
        $this->recalculerRangsEnBase($annee);

        // Vider le cache de l'avancement pour prise en compte immédiate
        Cache::forget("avancements:candidates_list_{$annee}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notes du tableau d\'avancement enregistrées avec succès.'
            ]);
        }

        return redirect()->back()->with('success', 'Notes du tableau d\'avancement enregistrées avec succès.');
    }

    /**
     * Exporte le tableau d'avancement sous format Excel
     */
    public function export(Request $request): BinaryFileResponse
    {
        $annee = (int) $request->input('annee', date('Y'));
        $gradeFiltre = $request->input('grade', 'all');

        $candidates = $this->buildCandidatesList($annee);

        if (!empty($gradeFiltre) && $gradeFiltre !== 'all') {
            $candidates = array_values(array_filter($candidates, fn($c) => ($c['grade_actuel'] ?? '') === $gradeFiltre));
        }

        $fileName = 'Tableau_Avancement_' . $annee;
        if (!empty($gradeFiltre) && $gradeFiltre !== 'all') {
            $fileName .= '_' . str_replace(' ', '_', $gradeFiltre);
        }
        $fileName .= '_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new AvancementsExport($candidates, $annee, $gradeFiltre), $fileName);
    }

    /**
     * Importe un fichier Excel complété avec les notes du tableau d'avancement
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'annee' => 'required|integer',
        ]);

        $annee = (int) $request->input('annee');
        $file = $request->file('file');

        try {
            $data = Excel::toArray([], $file);
            $rows = $data[0] ?? [];

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier Excel est vide.'
                ], 422);
            }

            $updatedCount = 0;
            $skippedCount = 0;

            // Détection de la ligne d'en-tête
            $headerRowIndex = null;
            foreach ($rows as $idx => $row) {
                $rowStr = implode(' ', array_map('strval', $row));
                if (stripos($rowStr, 'Matricule') !== false && stripos($rowStr, 'Nom') !== false) {
                    $headerRowIndex = $idx;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                $headerRowIndex = 3;
            }

            $dataRows = array_slice($rows, $headerRowIndex + 1);

            foreach ($dataRows as $row) {
                if (empty($row) || !is_array($row)) {
                    continue;
                }

                // Ignorer les séparateurs de grade
                $colA = trim((string)($row[0] ?? ''));
                $colB = trim((string)($row[1] ?? ''));
                $colC = trim((string)($row[2] ?? ''));

                if (str_starts_with($colA, 'GRADE :') || str_starts_with($colA, '--- GRADE')) {
                    continue;
                }

                if (empty($colB) && empty($colC)) {
                    continue;
                }

                // Recherche du militaire par matricule en priorité
                $militaire = null;
                if (!empty($colB)) {
                    $cleanMatricule = ltrim($colB, "'");
                    $militaire = Militaire::where('matricule', $cleanMatricule)->first();
                }

                // Fallback par nom complet si non trouvé
                if (!$militaire && !empty($colC)) {
                    $cleanNom = trim(ltrim($colC, "'"));
                    $parts = explode(' ', $cleanNom, 2);
                    if (count($parts) === 2) {
                        $militaire = Militaire::where('nom', $parts[0])
                            ->where('prenom', $parts[1])
                            ->first();
                    }
                    if (!$militaire) {
                        $militaire = Militaire::whereRaw("TRIM(CONCAT(nom, ' ', prenom)) = ?", [$cleanNom])->first();
                    }
                }

                if (!$militaire) {
                    $skippedCount++;
                    continue;
                }

                // Extraction des notes numériques
                $cleanNum = function ($val) {
                    if (is_numeric($val)) return (float) $val;
                    if (is_string($val)) {
                        $cleaned = str_replace([' ', ','], ['', '.'], trim(ltrim($val, "'")));
                        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
                    }
                    return 0.0;
                };

                $ptsFonction = max(0, $cleanNum($row[5] ?? 0));
                $noteRendement = max(0, $cleanNum($row[6] ?? 0));
                $bonusEmploi = max(0, $cleanNum($row[7] ?? 0));
                $ptsDiplomes = max(0, $cleanNum($row[8] ?? 0));
                $ptsDeco = max(0, $cleanNum($row[9] ?? 0));
                $ptsAncGrade = max(0, $cleanNum($row[10] ?? 0));
                $joursPunition = max(0, (int) round($cleanNum($row[11] ?? 0)));

                $malusPunition = round($joursPunition * 0.1, 2);

                // Calcul limite d'âge
                $ageRetraite = Grade::getRetraiteForGrade($militaire->grade_actuel);
                $anneesRestantes = null;
                $malusLimiteAge = 0.0;

                if ($ageRetraite && $militaire->date_naissance) {
                    $birthYear = Carbon::parse($militaire->date_naissance)->year;
                    $anneeRetraite = $birthYear + $ageRetraite;
                    $anneesRestantes = max(0, $anneeRetraite - $annee);
                    if ($anneesRestantes <= 1) {
                        $malusLimiteAge = 2.0;
                    } elseif ($anneesRestantes === 2) {
                        $malusLimiteAge = 1.0;
                    }
                }

                $total = $noteRendement + $ptsFonction + $bonusEmploi + $ptsDiplomes + $ptsDeco + $ptsAncGrade - $malusLimiteAge - $malusPunition;

                Avancement::updateOrCreate(
                    [
                        'militaire_id' => $militaire->id,
                        'annee' => $annee,
                    ],
                    [
                        'grade_actuel' => $militaire->grade_actuel,
                        'points_fonction_actuelle' => $ptsFonction,
                        'note_rendement' => $noteRendement,
                        'bonus_dernier_emploi' => $bonusEmploi,
                        'points_diplomes' => $ptsDiplomes,
                        'points_decorations' => $ptsDeco,
                        'points_anciennete_grade' => $ptsAncGrade,
                        'jours_punition' => $joursPunition,
                        'malus_punition' => $malusPunition,
                        'annees_restantes_retraite' => $anneesRestantes,
                        'malus_limite_age' => $malusLimiteAge,
                        'total_points' => max(0, $total),
                    ]
                );

                $updatedCount++;
            }

            // Recalcul des rangs et purge du cache
            $this->recalculerRangsEnBase($annee);
            Cache::forget("avancements:candidates_list_{$annee}");

            return response()->json([
                'success' => true,
                'message' => "Importation réussie : {$updatedCount} dossiers d'avancement mis à jour avec succès.",
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'importation : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construit la liste optimisée des candidats avec mise en cache pour chargement instantané
     */
    protected function buildCandidatesList(int $annee): array
    {
        return Cache::remember("avancements:candidates_list_{$annee}", 600, function () use ($annee) {
            // 1. Récupération des promotions éligibles depuis EligibiliteController
            $eligibiliteController = app(EligibiliteController::class);
            $computedData = $eligibiliteController->getAllComputedData();
            $promotions = $computedData['promotions'] ?? [];

            // Hiérarchie des grades
            $gradesHierarchy = Grade::pluck('ordre', 'nom_grade')->all();

            $promotionsMap = [];
            $promoMilitaireIds = [];
            foreach ($promotions as $promo) {
                $mId = $promo['militaire']['id'] ?? null;
                if ($mId) {
                    $promoMilitaireIds[] = $mId;
                    $promotionsMap[$mId] = $promo;
                }
            }

            // 2. Récupération des avancements déjà enregistrés
            $savedAvancements = Avancement::where('annee', $annee)->get()->keyBy('militaire_id');

            $allIds = array_unique(array_merge($promoMilitaireIds, $savedAvancements->keys()->all()));

            if (empty($allIds)) {
                return [];
            }

            $militaires = Militaire::whereIn('id', $allIds)
                ->with(['certificats'])
                ->get()
                ->keyBy('id');

            $candidates = [];

            foreach ($allIds as $mId) {
                $m = $militaires->get($mId);
                if (!$m) continue;

                $saved = $savedAvancements->get($mId);
                $promoInfo = $promotionsMap[$mId] ?? null;

                $gradeActuel = $saved->grade_actuel ?? $m->grade_actuel;
                $gradeCible = $saved->grade_cible ?? ($promoInfo['grade_cible'] ?? '');
                $fonctionNom = $m->fonction_actuelle ?: $m->position_actuelle ?: 'En fonction';

                // Calcul limite d'âge et retraite : si reste <= 1 an = -2 pts, si reste 2 ans = -1 pt, sinon 0
                $ageRetraite = Grade::getRetraiteForGrade($gradeActuel);
                $anneesRestantes = null;
                $malusLimiteAgeDefaut = 0.0;
                if ($ageRetraite && $m->date_naissance) {
                    $anneeRetraite = (int) Carbon::parse($m->date_naissance)->year + $ageRetraite;
                    $anneesRestantes = max(0, $anneeRetraite - $annee);
                    if ($anneesRestantes <= 1) {
                        $malusLimiteAgeDefaut = 2.0; // -2 pts
                    } elseif ($anneesRestantes == 2) {
                        $malusLimiteAgeDefaut = 1.0; // -1 pt
                    } else {
                        $malusLimiteAgeDefaut = 0.0;
                    }
                }

                $diplomesFormates = $this->formatList($m->diplomes_academiques);
                $decorationsFormatees = $this->formatList($m->decorations);

                // Notes
                $ptsFonction = $saved ? (float) $saved->points_fonction_actuelle : 0.0;
                $noteRendement = $saved ? (float) $saved->note_rendement : 0.0;
                $bonusEmploi = $saved ? (float) $saved->bonus_dernier_emploi : 0.0;
                $ptsDiplomes = $saved ? (float) $saved->points_diplomes : 0.0;
                $ptsDecos = $saved ? (float) $saved->points_decorations : 0.0;
                $ptsAncGrade = $saved ? (float) $saved->points_anciennete_grade : 0.0;
                $joursPunition = $saved ? (int) $saved->jours_punition : 0;
                $malusPunition = round($joursPunition * 0.1, 2);
                $malusLimiteAge = $saved ? (float) $saved->malus_limite_age : $malusLimiteAgeDefaut;

                // Total = Rendement + Fonction + Bonus + Diplômes + Décorations + Ancienneté Grade - Malus Limite Âge - Malus Punition
                $total = round($noteRendement + $ptsFonction + $bonusEmploi + $ptsDiplomes + $ptsDecos + $ptsAncGrade - $malusLimiteAge - $malusPunition, 2);

                $candidates[] = [
                    'militaire_id' => $m->id,
                    'avancement_id' => $saved?->id,
                    'matricule' => $m->matricule,
                    'mle_grm' => $m->mle_grm ?? '',
                    'nom' => $m->nom,
                    'prenom' => $m->prenom,
                    'nom_complet' => trim($m->nom . ' ' . $m->prenom),
                    'photo_url' => $m->photo_url,
                    'grade_actuel' => $gradeActuel,
                    'grade_ordre' => $gradesHierarchy[$gradeActuel] ?? 0,
                    'grade_cible' => $gradeCible,
                    'fonction_nom' => $fonctionNom,
                    'diplomes_texte' => $diplomesFormates,
                    'decorations_texte' => $decorationsFormatees,
                    'anciennete_grade_annees' => $m->anciennete_grade ?? 0,
                    'age' => $m->age ?? 0,
                    'annees_restantes_retraite' => $anneesRestantes,
                    // Champs de notation éditables
                    'points_fonction_actuelle' => $ptsFonction,
                    'note_rendement' => $noteRendement,
                    'bonus_dernier_emploi' => $bonusEmploi,
                    'points_diplomes' => $ptsDiplomes,
                    'points_decorations' => $ptsDecos,
                    'points_anciennete_grade' => $ptsAncGrade,
                    'jours_punition' => $joursPunition,
                    'malus_punition' => $malusPunition,
                    'malus_limite_age' => $malusLimiteAge,
                    'total_points' => max(0, $total),
                    'rang' => $saved?->rang ?? 0,
                    'est_enregistre' => (bool) $saved,
                ];
            }

            // Classement PAR GRADE avec ordre hiérarchique décroissant des grades
            return $this->classerParGrade($candidates, $gradesHierarchy);
        });
    }

    /**
     * Classe les candidats au sein de chaque grade, et ordonne les grades du plus élevé au plus bas
     */
    protected function classerParGrade(array $candidates, array $gradesHierarchy): array
    {
        $grouped = [];
        foreach ($candidates as $c) {
            $g = $c['grade_actuel'] ?? 'Non défini';
            $grouped[$g][] = $c;
        }

        // Trier les grades du plus élevé au moins élevé (ordre décroissant)
        uksort($grouped, function ($gradeA, $gradeB) use ($gradesHierarchy) {
            $orderA = $gradesHierarchy[$gradeA] ?? 0;
            $orderB = $gradesHierarchy[$gradeB] ?? 0;
            return $orderB <=> $orderA;
        });

        $finalList = [];

        foreach ($grouped as $grade => &$list) {
            // Au sein du grade, tri par total_points décroissant
            usort($list, function ($a, $b) {
                if ($b['total_points'] != $a['total_points']) {
                    return ($b['total_points'] > $a['total_points']) ? 1 : -1;
                }
                if ($b['anciennete_grade_annees'] != $a['anciennete_grade_annees']) {
                    return $b['anciennete_grade_annees'] <=> $a['anciennete_grade_annees'];
                }
                return strcmp($a['nom'], $b['nom']);
            });

            // Rangs 1, 2, 3...
            $rank = 1;
            foreach ($list as &$item) {
                $item['rang'] = $rank++;
                $finalList[] = $item;
            }
        }

        return $finalList;
    }

    /**
     * Met à jour les rangs définitifs en base de données
     */
    protected function recalculerRangsEnBase(int $annee): void
    {
        $grades = Avancement::where('annee', $annee)->pluck('grade_actuel')->unique();

        foreach ($grades as $grade) {
            $items = Avancement::where('annee', $annee)
                ->where('grade_actuel', $grade)
                ->orderByDesc('total_points')
                ->orderByDesc('points_anciennete_grade')
                ->get();

            $rank = 1;
            foreach ($items as $item) {
                $item->update(['rang' => $rank++]);
            }
        }
    }

    protected function formatList($val): string
    {
        if (!$val) return '';
        $trimmed = trim((string) $val);
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return implode(', ', array_filter(array_map('trim', $decoded)));
            }
        }
        return $trimmed;
    }
}
