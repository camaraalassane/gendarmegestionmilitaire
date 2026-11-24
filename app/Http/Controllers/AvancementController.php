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
    protected ?array $candidatesPayloadCache = null;

    /**
     * Calcule ou récupère en mémoire le payload complet des candidats et métriques pour la session.
     */
    public function getCandidatesPayload(int $annee): array
    {
        if ($this->candidatesPayloadCache !== null) {
            return $this->candidatesPayloadCache;
        }

        $gradesHierarchy = Grade::pluck('ordre', 'nom_grade')->all();
        $candidates = $this->buildCandidatesList($annee);

        // Séparer les grades disponibles par catégorie
        $availableGradesOfficiers = [];
        $availableGradesSousOfficiers = [];

        foreach ($candidates as $c) {
            $g = $c['grade_actuel'] ?? null;
            if (!$g) continue;
            if (!empty($c['is_officier'])) {
                $availableGradesOfficiers[$g] = true;
            } else {
                $availableGradesSousOfficiers[$g] = true;
            }
        }

        $sortGradesDesc = function ($list) use ($gradesHierarchy) {
            $keys = array_keys($list);
            usort($keys, function ($a, $b) use ($gradesHierarchy) {
                $orderA = $gradesHierarchy[$a] ?? 0;
                $orderB = $gradesHierarchy[$b] ?? 0;
                return $orderB <=> $orderA;
            });
            return $keys;
        };

        $gradesOfficiers = $sortGradesDesc($availableGradesOfficiers);
        $gradesSousOfficiers = $sortGradesDesc($availableGradesSousOfficiers);

        $countOfficiers = count(array_filter($candidates, fn($c) => !empty($c['is_officier'])));
        $countSousOfficiers = count(array_filter($candidates, fn($c) => empty($c['is_officier'])));

        return $this->candidatesPayloadCache = [
            'candidates' => $candidates,
            'gradesOfficiers' => $gradesOfficiers,
            'gradesSousOfficiers' => $gradesSousOfficiers,
            'stats' => [
                'total_candidats' => count($candidates),
                'total_officiers' => $countOfficiers,
                'total_sous_officiers' => $countSousOfficiers,
                'total_grades_officiers' => count($gradesOfficiers),
                'total_grades_sous_officiers' => count($gradesSousOfficiers),
                'total_enregistres' => count(array_filter($candidates, fn($c) => !empty($c['est_enregistre']))),
            ]
        ];
    }

    /**
     * Affiche le tableau d'avancement avec onglets Officiers / Sous-Officiers
     * Chargement instantané de la coquille avec Inertia::defer pour les listes lourdes.
     */
    public function index(Request $request): Response
    {
        // Session : toujours l'année en clôture
        $annee = (int) $request->input('annee', date('Y'));
        $gradeFiltre = $request->input('grade', 'all');
        $tab = $request->input('tab', 'officiers');
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('Avancements/Index', [
            'selectedAnnee' => $annee,
            'selectedGrade' => $gradeFiltre,
            'initialTab' => $tab,
            'search' => $search,
            'candidates' => Inertia::defer(fn () => $this->getCandidatesPayload($annee)['candidates']),
            'gradesOfficiers' => Inertia::defer(fn () => $this->getCandidatesPayload($annee)['gradesOfficiers']),
            'gradesSousOfficiers' => Inertia::defer(fn () => $this->getCandidatesPayload($annee)['gradesSousOfficiers']),
            'stats' => Inertia::defer(fn () => $this->getCandidatesPayload($annee)['stats']),
        ]);
    }

    /**
     * Sauvegarde en lot des notes du tableau d'avancement (Officiers)
     */
    public function save(Request $request)
    {
        $request->validate([
            'annee' => 'required|integer',
            'items' => 'required|array',
            'items.*.militaire_id' => 'required|integer',
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
        $itemIds = collect($request->input('items', []))->pluck('militaire_id')->filter()->unique()->values();
        if ($itemIds->isNotEmpty()) {
            $totalFound = 0;
            foreach ($itemIds->chunk(5000) as $chunk) {
                $totalFound += \App\Models\Militaire::whereIn('id', $chunk)->count();
            }
            if ($totalFound !== $itemIds->count()) {
                return back()->withErrors(['items' => 'Un ou plusieurs militaires sont introuvables.']);
            }
        }
        $items = $request->input('items', []);
        $now = now();
        $records = [];

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

            $records[] = [
                'militaire_id' => (int) $item['militaire_id'],
                'annee' => $annee,
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
                'points_limite_age' => $malusLimiteAge,
                'total_points' => max(0, $total),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Écriture optimisée en une seule requête SQL upsert de masse
        if (!empty($records)) {
            foreach (array_chunk($records, 200) as $chunk) {
                Avancement::upsert(
                    $chunk,
                    ['militaire_id', 'annee'],
                    [
                        'grade_actuel', 'grade_cible', 'fonction_actuelle',
                        'points_fonction_actuelle', 'note_rendement', 'bonus_dernier_emploi',
                        'points_diplomes', 'points_decorations', 'points_anciennete_grade',
                        'jours_punition', 'malus_punition', 'points_limite_age',
                        'total_points', 'updated_at'
                    ]
                );
            }
        }

        // Recalcul des rangs par grade en base de données
        $this->recalculerRangsEnBase($annee);

        // Vider le cache de l'avancement pour prise en compte immédiate
        \App\Models\Militaire::incrementCacheVersion();
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
        $tab = $request->input('tab', 'all'); // 'officiers', 'sous_officiers', 'all'

        $candidates = $this->buildCandidatesList($annee);

        if (!empty($gradeFiltre) && $gradeFiltre !== 'all') {
            $candidates = array_values(array_filter($candidates, fn($c) => ($c['grade_actuel'] ?? '') === $gradeFiltre));
        }

        $suffix = '';
        if ($tab === 'officiers') {
            $candidates = array_values(array_filter($candidates, fn($c) => !empty($c['is_officier'])));
            $suffix = '_Officiers';
        } elseif ($tab === 'sous_officiers') {
            $candidates = array_values(array_filter($candidates, fn($c) => empty($c['is_officier'])));
            $suffix = '_Sous_Officiers';
        }

        $fileName = 'Tableau_Avancement_' . $annee . $suffix;
        if (!empty($gradeFiltre) && $gradeFiltre !== 'all') {
            $fileName .= '_' . str_replace(' ', '_', $gradeFiltre);
        }
        $fileName .= '_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new AvancementsExport($candidates, $annee, $gradeFiltre, $tab), $fileName);
    }

    /**
     * Importe un fichier Excel complété avec les notes du tableau d'avancement (Officiers)
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

            // Précharger tous les militaires actifs et vivants en mémoire pour éliminer les requêtes SQL N+1
            $allMilitaires = Militaire::enActivite()
                ->whereNull('date_deces')
                ->select('id', 'grade_id', 'matricule', 'nom', 'prenom', 'grade_actuel', 'date_naissance')
                ->get();

            $byMatricule = [];
            $byFullName = [];
            foreach ($allMilitaires as $m) {
                $mat = strtolower(trim((string)$m->matricule));
                if ($mat !== '') {
                    $byMatricule[$mat] = $m;
                }
                $nomPrenom = strtolower(preg_replace('/\s+/', ' ', trim($m->nom . ' ' . $m->prenom)));
                $prenomNom = strtolower(preg_replace('/\s+/', ' ', trim($m->prenom . ' ' . $m->nom)));
                $byFullName[$nomPrenom] = $m;
                $byFullName[$prenomNom] = $m;
            }

            $now = now();
            $recordsToUpsert = [];

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

                // Recherche en mémoire O(1) par matricule en priorité
                $militaire = null;
                if (!empty($colB)) {
                    $cleanMatricule = strtolower(trim(ltrim($colB, "'")));
                    $militaire = $byMatricule[$cleanMatricule] ?? null;
                }

                // Fallback en mémoire O(1) par nom complet si non trouvé
                if (!$militaire && !empty($colC)) {
                    $cleanNom = strtolower(preg_replace('/\s+/', ' ', trim(ltrim($colC, "'"))));
                    $militaire = $byFullName[$cleanNom] ?? null;
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

                $recordsToUpsert[] = [
                    'militaire_id' => $militaire->id,
                    'annee' => $annee,
                    'grade_actuel' => $militaire->grade_actuel,
                    'points_fonction_actuelle' => $ptsFonction,
                    'note_rendement' => $noteRendement,
                    'bonus_dernier_emploi' => $bonusEmploi,
                    'points_diplomes' => $ptsDiplomes,
                    'points_decorations' => $ptsDeco,
                    'points_anciennete_grade' => $ptsAncGrade,
                    'jours_punition' => $joursPunition,
                    'malus_punition' => $malusPunition,
                    'points_limite_age' => $malusLimiteAge,
                    'total_points' => max(0, round($total, 2)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $updatedCount++;
            }

            // Écriture par lot optimisée en une seule requête SQL upsert
            if (!empty($recordsToUpsert)) {
                foreach (array_chunk($recordsToUpsert, 200) as $chunk) {
                    Avancement::upsert(
                        $chunk,
                        ['militaire_id', 'annee'],
                        [
                            'grade_actuel', 'points_fonction_actuelle',
                            'note_rendement', 'bonus_dernier_emploi', 'points_diplomes',
                            'points_decorations', 'points_anciennete_grade', 'jours_punition',
                            'malus_punition', 'points_limite_age', 'total_points', 'updated_at'
                        ]
                    );
                }
            }

            // Recalcul des rangs et purge du cache
            $this->recalculerRangsEnBase($annee);
            \App\Models\Militaire::incrementCacheVersion();
        Cache::forget("avancements:candidates_list_{$annee}");

            return response()->json([
                'success' => true,
                'message' => "Importation réussie : {$updatedCount} dossiers d'avancement mis à jour avec succès.",
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Erreur import avancements : ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'importation. Veuillez vérifier la structure du fichier.'
            ], 500);
        }
    }

    /**
     * Construit la liste optimisée des candidats avec mise en cache
     */
    public function buildCandidatesList(int $annee): array
    {
        $v = \App\Models\Militaire::getCacheVersion();
        return Cache::remember("v{$v}:avancements:candidates_list_{$annee}", 86400, function () use ($annee) {
            // 1. Récupération des promotions éligibles depuis EligibiliteController
            $eligibiliteController = app(EligibiliteController::class);
            $promotions = $eligibiliteController->iterer('promotions');

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

            // Exclusion formelle des militaires déjà en retraite ou décédés (par lots de 5 000 max pour respecter la limite Postgres)
            $militaires = collect();
            foreach (array_chunk($allIds, 5000) as $chunkIds) {
                $chunkMilitaires = Militaire::whereIn('id', $chunkIds)
                    ->enActivite()
                    ->whereNull('date_deces')
                    ->with(['certificats'])
                    ->get();
                $militaires = $militaires->concat($chunkMilitaires);
            }
            $militaires = $militaires->keyBy('id');

            $candidates = [];

            foreach ($allIds as $mId) {
                $m = $militaires->get($mId);
                if (!$m) continue;

                // Double vérification stricte : personnel en activité (actif, détaché, etc.), non décédé et limite d'âge non dépassée
                $statutClean = strtolower(trim((string)$m->statut));
                if (!in_array($statutClean, ['actif', 'détaché', 'en mission', 'en stage']) || !empty($m->date_deces) || $m->estRetraiteDepassee()) {
                    continue;
                }

                $saved = $savedAvancements->get($mId);
                $promoInfo = $promotionsMap[$mId] ?? null;

                $gradeActuel = $saved->grade_actuel ?? $m->grade_actuel;
                $gradeCible = $saved->grade_cible ?? ($promoInfo['grade_cible'] ?? '');
                $fonctionNom = $m->fonction_actuelle ?: $m->position_actuelle ?: 'En fonction';

                $isOfficier = Grade::isOfficier($gradeActuel);
                $categorieStatutaire = Grade::determinerCategorie($gradeActuel);

                // Dates militaires et anciennetés
                $datePromoObj = $m->date_derniere_promotion ? Carbon::parse($m->date_derniere_promotion) : null;
                $dateEntreeObj = $m->date_entree_service ? Carbon::parse($m->date_entree_service) : null;
                $dateNaissObj = $m->date_naissance ? Carbon::parse($m->date_naissance) : null;

                $datePromoTexte = $datePromoObj ? $datePromoObj->format('d/m/Y') : '-';
                $datePromoRaw = $datePromoObj ? $datePromoObj->format('Y-m-d') : '';

                $dateEntreeTexte = $dateEntreeObj ? $dateEntreeObj->format('d/m/Y') : '-';
                $dateEntreeRaw = $dateEntreeObj ? $dateEntreeObj->format('Y-m-d') : '';

                $dateNaissTexte = $dateNaissObj ? $dateNaissObj->format('d/m/Y') : '-';
                $dateNaissRaw = $dateNaissObj ? $dateNaissObj->format('Y-m-d') : '';

                $ancGradeTexte = $m->anciennete_grade_detaillee ?: ($m->anciennete_grade ? $m->anciennete_grade . ' ans' : '-');
                $ancServiceTexte = $m->anciennete_detaillee ?: ($m->anciennete ? $m->anciennete . ' ans' : '-');

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
                $certificatsFormates = $this->formatCertificats($m);

                // Notes (Officiers)
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
                    'is_officier' => $isOfficier,
                    'categorie_personnel' => $isOfficier ? 'officier' : 'sous_officier',
                    'categorie_statutaire' => $categorieStatutaire,
                    'fonction_nom' => $fonctionNom,
                    'date_derniere_promotion' => $datePromoTexte,
                    'date_derniere_promotion_raw' => $datePromoRaw,
                    'date_entree_service' => $dateEntreeTexte,
                    'date_entree_service_raw' => $dateEntreeRaw,
                    'date_naissance' => $dateNaissTexte,
                    'date_naissance_raw' => $dateNaissRaw,
                    'anciennete_grade_texte' => $ancGradeTexte,
                    'anciennete_grade_annees' => $m->anciennete_grade ?? 0,
                    'anciennete_service_texte' => $ancServiceTexte,
                    'anciennete_service_annees' => $m->anciennete ?? 0,
                    'certificats_texte' => $certificatsFormates,
                    'diplomes_texte' => $diplomesFormates,
                    'decorations_texte' => $decorationsFormatees,
                    'message_eligibilite' => $promoInfo['message'] ?? ("Proposable pour " . $annee),
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
     * Classe les candidats au sein de chaque grade :
     * - Pour Officiers : classement par total_points décroissant
     * - Pour Sous-Officiers : classement par ancienneté (date nomination, date entrée, âge)
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
            $isOfficier = Grade::isOfficier($grade);

            if ($isOfficier) {
                // Au sein du grade d'officier : tri par total_points décroissant
                usort($list, function ($a, $b) {
                    if ($b['total_points'] != $a['total_points']) {
                        return ($b['total_points'] > $a['total_points']) ? 1 : -1;
                    }
                    if ($b['anciennete_grade_annees'] != $a['anciennete_grade_annees']) {
                        return $b['anciennete_grade_annees'] <=> $a['anciennete_grade_annees'];
                    }
                    return strcmp($a['nom'], $b['nom']);
                });
            } else {
                // Au sein du grade de sous-officier / MDR : tri strict par ANCIENNETÉ
                // 1. Date nomination au grade (date la plus ancienne = premier rang)
                // 2. Date entrée en service (date la plus ancienne = premier rang)
                // 3. Date de naissance (le plus âgé en premier)
                // 4. Nom alphabétique
                usort($list, function ($a, $b) {
                    $promoA = $a['date_derniere_promotion_raw'] ?: ($a['date_entree_service_raw'] ?: '9999-12-31');
                    $promoB = $b['date_derniere_promotion_raw'] ?: ($b['date_entree_service_raw'] ?: '9999-12-31');
                    $cmpPromo = strcmp($promoA, $promoB);
                    if ($cmpPromo !== 0) {
                        return $cmpPromo;
                    }

                    $entreeA = $a['date_entree_service_raw'] ?: '9999-12-31';
                    $entreeB = $b['date_entree_service_raw'] ?: '9999-12-31';
                    $cmpEntree = strcmp($entreeA, $entreeB);
                    if ($cmpEntree !== 0) {
                        return $cmpEntree;
                    }

                    $naissA = $a['date_naissance_raw'] ?: '9999-12-31';
                    $naissB = $b['date_naissance_raw'] ?: '9999-12-31';
                    $cmpNaiss = strcmp($naissA, $naissB);
                    if ($cmpNaiss !== 0) {
                        return $cmpNaiss;
                    }

                    return strcmp($a['nom'], $b['nom']);
                });
            }

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
        // Nettoyer les avancements des militaires déjà retraités ou décédés
        Avancement::where('annee', $annee)
            ->whereHas('militaire', function ($q) {
                $q->whereNotIn('statut_code', [1, 2, 3, 4])
                  ->orWhereNotNull('date_deces');
            })
            ->delete();

        $grades = Avancement::where('annee', $annee)->pluck('grade_actuel')->unique();

        foreach ($grades as $grade) {
            $isOfficier = Grade::isOfficier($grade);

            if ($isOfficier) {
                $items = Avancement::where('annee', $annee)
                    ->where('grade_actuel', $grade)
                    ->orderByDesc('total_points')
                    ->orderByDesc('points_anciennete_grade')
                    ->get();
            } else {
                $items = Avancement::where('avancements.annee', $annee)
                    ->where('avancements.grade_actuel', $grade)
                    ->leftJoin('militaires', 'avancements.militaire_id', '=', 'militaires.id')
                    ->orderByRaw("COALESCE(militaires.date_derniere_promotion, militaires.date_entree_service, '9999-12-31') ASC")
                    ->orderByRaw("COALESCE(militaires.date_entree_service, '9999-12-31') ASC")
                    ->orderByRaw("COALESCE(militaires.date_naissance, '9999-12-31') ASC")
                    ->select('avancements.*')
                    ->get();
            }

            $rank = 1;
            foreach ($items as $item) {
                if ($item->rang !== $rank) {
                    $item->update(['rang' => $rank]);
                }
                $rank++;
            }
        }
    }

    protected function formatCertificats($militaire): string
    {
        if (!$militaire || !$militaire->relationLoaded('certificats')) {
            return '';
        }
        $certs = $militaire->certificats->pluck('niveau_certificat')->filter()->values()->all();
        return implode(', ', $certs);
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
