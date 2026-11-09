<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Avancement;
use App\Models\Grade;
use App\Exports\AvancementsExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvancementController extends Controller
{
    /**
     * Affiche le tableau d'avancement avec classement automatique par grade
     */
    public function index(Request $request): Response
    {
        $annee = (int) $request->input('annee', date('Y'));
        $gradeFiltre = $request->input('grade', 'all');
        $search = trim((string) $request->input('search', ''));

        $candidates = $this->buildCandidatesList($annee);

        // Liste de tous les grades disponibles parmi les candidats
        $availableGrades = collect($candidates)->pluck('grade_actuel')->filter()->unique()->values()->all();
        sort($availableGrades);

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
            'anneesDisponibles' => [
                (int) date('Y') - 1,
                (int) date('Y'),
                (int) date('Y') + 1,
                (int) date('Y') + 2,
            ],
            'stats' => [
                'total_candidats' => count($candidates),
                'total_grades' => count($availableGrades),
                'total_enregistres' => count(array_filter($candidates, fn($c) => !empty($c['est_enregistre']))),
            ]
        ]);
    }

    /**
     * Sauvegarde en lot ou unitaire des notes du tableau d'avancement
     */
    public function save(Request $request)
    {
        $request->validate([
            'annee' => 'required|integer',
            'items' => 'required|array',
            'items.*.militaire_id' => 'required|exists:militaires,id',
            'items.*.note_rendement' => 'nullable|numeric|min:0|max:100',
            'items.*.bonus_dernier_emploi' => 'nullable|numeric|min:0|max:100',
            'items.*.points_diplomes' => 'nullable|numeric|min:0|max:100',
            'items.*.points_decorations' => 'nullable|numeric|min:0|max:100',
            'items.*.points_anciennete_grade' => 'nullable|numeric|min:0|max:100',
            'items.*.jours_punition' => 'nullable|integer|min:0',
            'items.*.points_limite_age' => 'nullable|numeric|min:0|max:100',
            'items.*.observations' => 'nullable|string|max:500',
        ]);

        $annee = (int) $request->input('annee', date('Y'));
        $items = $request->input('items', []);

        foreach ($items as $item) {
            $joursPunition = max(0, (int) ($item['jours_punition'] ?? 0));
            $malusPunition = round($joursPunition * 0.1, 2);

            $noteRendement = (float) ($item['note_rendement'] ?? 0);
            $bonusEmploi = (float) ($item['bonus_dernier_emploi'] ?? 0);
            $ptsDiplomes = (float) ($item['points_diplomes'] ?? 0);
            $ptsDecos = (float) ($item['points_decorations'] ?? 0);
            $ptsAncGrade = (float) ($item['points_anciennete_grade'] ?? 0);
            $ptsLimiteAge = (float) ($item['points_limite_age'] ?? 0);

            $total = round($noteRendement + $bonusEmploi + $ptsDiplomes + $ptsDecos + $ptsAncGrade + $ptsLimiteAge - $malusPunition, 2);

            Avancement::updateOrCreate(
                [
                    'militaire_id' => $item['militaire_id'],
                    'annee' => $annee,
                ],
                [
                    'grade_actuel' => $item['grade_actuel'] ?? null,
                    'grade_cible' => $item['grade_cible'] ?? null,
                    'fonction_actuelle' => $item['fonction_actuelle'] ?? null,
                    'note_rendement' => $noteRendement,
                    'bonus_dernier_emploi' => $bonusEmploi,
                    'points_diplomes' => $ptsDiplomes,
                    'points_decorations' => $ptsDecos,
                    'points_anciennete_grade' => $ptsAncGrade,
                    'jours_punition' => $joursPunition,
                    'malus_punition' => $malusPunition,
                    'points_limite_age' => $ptsLimiteAge,
                    'total_points' => $total,
                    'observations' => $item['observations'] ?? null,
                ]
            );
        }

        // Recalcul des rangs par grade en base de données
        $this->recalculerRangsEnBase($annee);

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
     * Construit la liste des candidats inspirée en premier lieu des Promotions éligibles
     */
    protected function buildCandidatesList(int $annee): array
    {
        // 1. Récupération des promotions éligibles depuis EligibiliteController
        $eligibiliteController = app(EligibiliteController::class);
        $computedData = $eligibiliteController->getAllComputedData();
        $promotions = $computedData['promotions'] ?? [];

        // Extraire les infos des éligibles
        $promotionsMap = [];
        $promoMilitaireIds = [];
        foreach ($promotions as $promo) {
            $mId = $promo['militaire']['id'] ?? null;
            if ($mId) {
                $promoMilitaireIds[] = $mId;
                $promotionsMap[$mId] = $promo;
            }
        }

        // 2. Récupération des avancements déjà enregistrés pour cette année
        $savedAvancements = Avancement::where('annee', $annee)->get()->keyBy('militaire_id');

        // Réunir tous les militaires concernés (éligibles à la promotion + ceux déjà enregistrés dans l'avancement)
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
            if (!$m) {
                continue;
            }

            $saved = $savedAvancements->get($mId);
            $promoInfo = $promotionsMap[$mId] ?? null;

            $gradeActuel = $saved->grade_actuel ?? $m->grade_actuel;
            $gradeCible = $saved->grade_cible ?? ($promoInfo['grade_cible'] ?? '');
            $fonction = $saved->fonction_actuelle ?? ($m->fonction_actuelle ?: $m->position_actuelle ?: 'En fonction');

            // Formatage des diplômes et décorations
            $diplomesFormates = $this->formatList($m->diplomes_academiques);
            $decorationsFormatees = $this->formatList($m->decorations);

            // Points
            $noteRendement = $saved ? (float) $saved->note_rendement : 0.0;
            $bonusEmploi = $saved ? (float) $saved->bonus_dernier_emploi : 0.0;
            $ptsDiplomes = $saved ? (float) $saved->points_diplomes : 0.0;
            $ptsDecos = $saved ? (float) $saved->points_decorations : 0.0;
            $ptsAncGrade = $saved ? (float) $saved->points_anciennete_grade : 0.0;
            $joursPunition = $saved ? (int) $saved->jours_punition : 0;
            $malusPunition = round($joursPunition * 0.1, 2);
            $ptsLimiteAge = $saved ? (float) $saved->points_limite_age : 0.0;

            $total = round($noteRendement + $bonusEmploi + $ptsDiplomes + $ptsDecos + $ptsAncGrade + $ptsLimiteAge - $malusPunition, 2);

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
                'grade_cible' => $gradeCible,
                'fonction_actuelle' => $fonction,
                'diplomes_texte' => $diplomesFormates,
                'decorations_texte' => $decorationsFormatees,
                'anciennete_grade_annees' => $m->anciennete_grade ?? 0,
                'anciennete_service_annees' => $m->anciennete ?? 0,
                'age' => $m->age ?? 0,
                // Notes modifiables
                'note_rendement' => $noteRendement,
                'bonus_dernier_emploi' => $bonusEmploi,
                'points_diplomes' => $ptsDiplomes,
                'points_decorations' => $ptsDecos,
                'points_anciennete_grade' => $ptsAncGrade,
                'jours_punition' => $joursPunition,
                'malus_punition' => $malusPunition,
                'points_limite_age' => $ptsLimiteAge,
                'total_points' => $total,
                'rang' => $saved?->rang ?? 0,
                'observations' => $saved?->observations ?? '',
                'est_enregistre' => (bool) $saved,
                'has_antecedents' => (bool) ($m->antecedent_judiciaire || $m->antecedent_disciplinaire),
            ];
        }

        // Classement PAR GRADE : au sein de chaque grade, tri décroissant par total_points
        return $this->classerParGrade($candidates);
    }

    /**
     * Classe les candidats au sein de chaque grade par ordre décroissant de total_points
     */
    protected function classerParGrade(array $candidates): array
    {
        // Grouper par grade
        $grouped = [];
        foreach ($candidates as $c) {
            $g = $c['grade_actuel'] ?? 'Non défini';
            $grouped[$g][] = $c;
        }

        $finalList = [];

        foreach ($grouped as $grade => &$list) {
            // Tri décroissant : points total en premier, puis ancienneté grade en cas d'égalité, puis nom
            usort($list, function ($a, $b) {
                if ($b['total_points'] != $a['total_points']) {
                    return ($b['total_points'] > $a['total_points']) ? 1 : -1;
                }
                if ($b['anciennete_grade_annees'] != $a['anciennete_grade_annees']) {
                    return $b['anciennete_grade_annees'] <=> $a['anciennete_grade_annees'];
                }
                return strcmp($a['nom'], $b['nom']);
            });

            // Attribution du rang (1er, 2e, 3e...)
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

    /**
     * Helper de formatage de tableau JSON ou chaîne de texte
     */
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
