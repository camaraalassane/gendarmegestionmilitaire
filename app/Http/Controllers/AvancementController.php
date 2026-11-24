<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Avancement;
use App\Models\Grade;
use App\Exports\AvancementsExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvancementController extends Controller
{
    protected ?array $candidatesPayloadCache = null;

    /**
     * Récupère les statistiques d'avancement et la liste des grades disponibles par catégorie
     * Exécuté en une seule requête SQL d'agrégation ultra-rapide (< 15 ms).
     */
    public function getStats(int $annee): array
    {
        $v = (int) DB::table('eligibilites_calculees')->where('type', 'meta')->max('version');
        if ($v === 0) {
            $eligibiliteController = app(EligibiliteController::class);
            $v = $eligibiliteController->assurerCalcul();
        }

        $officierGrades = Grade::all()->filter(fn($g) => Grade::isOfficier($g->nom_grade))->pluck('nom_grade')->all();
        $gradesHierarchy = Grade::pluck('ordre', 'nom_grade')->all();

        $counts = DB::table('militaires as m')
            ->leftJoin('eligibilites_calculees as ec', function ($join) use ($v) {
                $join->on('ec.militaire_id', '=', 'm.id')
                     ->where('ec.version', '=', $v)
                     ->where('ec.type', '=', 'promotions');
            })
            ->leftJoin('avancements as a', function ($join) use ($annee) {
                $join->on('a.militaire_id', '=', 'm.id')
                     ->where('a.annee', '=', $annee);
            })
            ->where(function ($w) {
                $w->whereNotNull('ec.militaire_id')
                  ->orWhereNotNull('a.id');
            })
            ->whereNull('m.deleted_at')
            ->whereNull('m.date_deces')
            ->whereRaw(\App\Support\Effectif::sqlActivite('m.statut'))
            ->select([
                DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel) as grade_actuel'),
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(a.id) as enregistres'),
            ])
            ->groupBy(DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel)'))
            ->get();

        $gradesOfficiers = [];
        $gradesSousOfficiers = [];
        $totalCandidats = 0;
        $totalOfficiers = 0;
        $totalSousOfficiers = 0;
        $totalEnregistres = 0;

        foreach ($counts as $row) {
            $g = $row->grade_actuel;
            if (!$g) continue;
            $cnt = (int) $row->total;
            $totalCandidats += $cnt;
            $totalEnregistres += (int) $row->enregistres;

            if (in_array($g, $officierGrades, true)) {
                $totalOfficiers += $cnt;
                $gradesOfficiers[] = $g;
            } else {
                $totalSousOfficiers += $cnt;
                $gradesSousOfficiers[] = $g;
            }
        }

        $sortGradesDesc = function (&$list) use ($gradesHierarchy) {
            usort($list, function ($a, $b) use ($gradesHierarchy) {
                return ($gradesHierarchy[$b] ?? 0) <=> ($gradesHierarchy[$a] ?? 0);
            });
        };
        $sortGradesDesc($gradesOfficiers);
        $sortGradesDesc($gradesSousOfficiers);

        return [
            'stats' => [
                'total_candidats' => $totalCandidats,
                'total_officiers' => $totalOfficiers,
                'total_sous_officiers' => $totalSousOfficiers,
                'total_grades_officiers' => count($gradesOfficiers),
                'total_grades_sous_officiers' => count($gradesSousOfficiers),
                'total_enregistres' => $totalEnregistres,
            ],
            'gradesOfficiers' => array_values($gradesOfficiers),
            'gradesSousOfficiers' => array_values($gradesSousOfficiers),
        ];
    }

    /**
     * Récupère la page de candidats avec calcul de rang SQL fenêtré (ROW_NUMBER() OVER PARTITION BY grade)
     * Consommation mémoire O(1) : seule la page demandée (ex: 25 lignes) est hydratée.
     */
    public function getPaginatedCandidates(
        int $annee,
        string $tab = 'sous_officiers',
        string $grade = 'all',
        string $search = '',
        int $page = 1,
        int $perPage = 25
    ): array {
        $v = (int) DB::table('eligibilites_calculees')->where('type', 'meta')->max('version');
        if ($v === 0) {
            $eligibiliteController = app(EligibiliteController::class);
            $v = $eligibiliteController->assurerCalcul();
        }

        $officierGrades = Grade::all()->filter(fn($g) => Grade::isOfficier($g->nom_grade))->pluck('nom_grade')->all();
        $isOfficierTab = ($tab === 'officiers');

        // Ordre officiel :
        // - Officiers : tri par total_points DESC, ancienneté grade DESC, nom, prénom, matricule
        // - Sous-Officiers : tri par date promotion ASC, date incorporation ASC, date naissance ASC, nom, prénom, matricule
        $windowOrder = $isOfficierTab
            ? "COALESCE(a.total_points, 0) DESC, COALESCE(a.points_anciennete_grade, 0) DESC, COALESCE(m.date_derniere_promotion, m.date_entree_service) ASC, m.nom ASC, m.prenom ASC, m.matricule ASC"
            : "COALESCE(m.date_derniere_promotion, m.date_entree_service) ASC, m.date_entree_service ASC, m.date_naissance ASC, m.nom ASC, m.prenom ASC, m.matricule ASC";

        $baseQuery = DB::table('militaires as m')
            ->leftJoin('eligibilites_calculees as ec', function ($join) use ($v) {
                $join->on('ec.militaire_id', '=', 'm.id')
                     ->where('ec.version', '=', $v)
                     ->where('ec.type', '=', 'promotions');
            })
            ->leftJoin('avancements as a', function ($join) use ($annee) {
                $join->on('a.militaire_id', '=', 'm.id')
                     ->where('a.annee', '=', $annee);
            })
            ->join('grades as g', 'g.nom_grade', '=', DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel)'))
            ->where(function ($w) {
                $w->whereNotNull('ec.militaire_id')
                  ->orWhereNotNull('a.id');
            })
            ->whereNull('m.deleted_at')
            ->whereNull('m.date_deces')
            ->whereRaw(\App\Support\Effectif::sqlActivite('m.statut'))
            ->select([
                'm.id as militaire_id',
                'm.matricule',
                'm.mle_grm',
                'm.nom',
                'm.prenom',
                'm.photo',
                'm.statut',
                DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel) as grade_actuel'),
                'g.ordre as grade_ordre',
                'm.date_derniere_promotion',
                'm.date_entree_service',
                'm.date_naissance',
                'm.fonction_actuelle',
                'm.position_actuelle',
                'm.diplomes_academiques',
                'm.decorations',
                'a.id as avancement_id',
                'a.grade_cible as saved_grade_cible',
                'a.points_fonction_actuelle',
                'a.note_rendement',
                'a.bonus_dernier_emploi',
                'a.points_diplomes',
                'a.points_decorations',
                'a.points_anciennete_grade',
                'a.jours_punition',
                'a.malus_punition',
                'a.points_limite_age',
                'a.total_points',
                'ec.payload as promo_payload',
                DB::raw("ROW_NUMBER() OVER (
                    PARTITION BY COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel) 
                    ORDER BY {$windowOrder}
                ) as rang")
            ]);

        if ($tab === 'officiers') {
            $baseQuery->whereIn(DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel)'), $officierGrades);
        } elseif ($tab === 'sous_officiers') {
            $baseQuery->whereNotIn(DB::raw('COALESCE(a.grade_actuel, ec.grade_actuel, m.grade_actuel)'), $officierGrades);
        }

        $query = DB::query()->fromSub($baseQuery, 'c');

        if (!empty($grade) && $grade !== 'all') {
            $query->where('c.grade_actuel', $grade);
        }

        if (!empty($search)) {
            $s = '%' . trim($search) . '%';
            $query->where(function ($w) use ($s) {
                $w->where('c.nom', 'ILIKE', $s)
                  ->orWhere('c.prenom', 'ILIKE', $s)
                  ->orWhere('c.matricule', 'ILIKE', $s)
                  ->orWhere('c.mle_grm', 'ILIKE', $s);
            });
        }

        $total = $query->count();
        $safePerPage = max(1, $perPage);
        $lastPage = max(1, (int) ceil($total / $safePerPage));
        $actualPage = min(max(1, $page), $lastPage);

        $rows = $query->orderBy('grade_ordre', 'desc')
                      ->orderBy('rang', 'asc')
                      ->offset(($actualPage - 1) * $safePerPage)
                      ->limit($safePerPage)
                      ->get();

        $enriched = $this->enrichRows($rows, $annee, $officierGrades);

        return [
            'data' => $enriched,
            'total' => $total,
            'page' => $actualPage,
            'per_page' => $safePerPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Enrichit une sélection limitée de lignes (la page courante) avec calcul des durées, certificats et totaux
     */
    protected function enrichRows($rows, int $annee, array $officierGrades): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $rowIds = $rows->pluck('militaire_id')->all();

        // Récupérer les certificats pour uniquement les militaires de la page
        $certRows = DB::table('certificat_militaire')
            ->join('certificats', 'certificats.id', '=', 'certificat_militaire.certificat_id')
            ->whereIn('certificat_militaire.militaire_id', $rowIds)
            ->select('certificat_militaire.militaire_id', 'certificats.niveau_certificat')
            ->get();

        $certsMap = [];
        foreach ($certRows as $cr) {
            if ($cr->niveau_certificat) {
                $certsMap[$cr->militaire_id][] = $cr->niveau_certificat;
            }
        }

        $refYear = (int) date('Y');
        $refDate = Carbon::create($refYear, 12, 31, 23, 59, 59);
        $result = [];

        foreach ($rows as $r) {
            $isOff = in_array($r->grade_actuel, $officierGrades, true);
            $categorieStatutaire = Grade::determinerCategorie($r->grade_actuel);
            $promoInfo = $r->promo_payload ? json_decode($r->promo_payload, true) : [];
            $gradeCible = $r->saved_grade_cible ?: ($promoInfo['grade_cible'] ?? '');
            $fonctionNom = $r->fonction_actuelle ?: $r->position_actuelle ?: 'En fonction';

            $dPromoStr = $r->date_derniere_promotion ? substr($r->date_derniere_promotion, 0, 10) : null;
            $dEntreeStr = $r->date_entree_service ? substr($r->date_entree_service, 0, 10) : null;
            $dNaissStr = $r->date_naissance ? substr($r->date_naissance, 0, 10) : null;

            $datePromoTexte = $dPromoStr ? sprintf('%02d/%02d/%04d', (int)substr($dPromoStr, 8, 2), (int)substr($dPromoStr, 5, 2), (int)substr($dPromoStr, 0, 4)) : '-';
            $dateEntreeTexte = $dEntreeStr ? sprintf('%02d/%02d/%04d', (int)substr($dEntreeStr, 8, 2), (int)substr($dEntreeStr, 5, 2), (int)substr($dEntreeStr, 0, 4)) : '-';
            $dateNaissTexte = $dNaissStr ? sprintf('%02d/%02d/%04d', (int)substr($dNaissStr, 8, 2), (int)substr($dNaissStr, 5, 2), (int)substr($dNaissStr, 0, 4)) : '-';

            $ancServiceAnnees = 0;
            $ancServiceTexte = '-';
            if ($dEntreeStr) {
                $diffEntree = Carbon::parse($dEntreeStr)->diff($refDate);
                $ancServiceAnnees = $diffEntree->y;
                $ancServiceTexte = $diffEntree->y . ' ans' . ($diffEntree->m > 0 ? ' ' . $diffEntree->m . ' mois' : '');
            }

            $ancGradeAnnees = 0;
            $ancGradeTexte = '-';
            if ($dPromoStr) {
                $diffPromo = Carbon::parse($dPromoStr)->diff($refDate);
                $ancGradeAnnees = $diffPromo->y;
                $ancGradeTexte = $diffPromo->y . ' ans' . ($diffPromo->m > 0 ? ' ' . $diffPromo->m . ' mois' : '');
            } else {
                $ancGradeAnnees = $ancServiceAnnees;
                $ancGradeTexte = $ancServiceTexte;
            }

            $age = 0;
            $anneesRestantes = null;
            $malusLimiteAgeDefaut = 0.0;
            $ageRetraite = Grade::getRetraiteForGrade($r->grade_actuel);
            if ($dNaissStr) {
                $age = Carbon::parse($dNaissStr)->age;
                if ($ageRetraite) {
                    $yNaiss = (int) substr($dNaissStr, 0, 4);
                    $anneesRestantes = max(0, ($yNaiss + $ageRetraite) - $annee);
                    if ($anneesRestantes <= 1) {
                        $malusLimiteAgeDefaut = 2.0;
                    } elseif ($anneesRestantes == 2) {
                        $malusLimiteAgeDefaut = 1.0;
                    }
                }
            }

            $photoUrl = null;
            if ($r->photo) {
                if (str_starts_with($r->photo, 'http://') || str_starts_with($r->photo, 'https://')) {
                    $photoUrl = $r->photo;
                } else {
                    $photoUrl = route('militaires.photo', $r->militaire_id, absolute: false) . '?v=' . substr(md5($r->photo), 0, 8);
                }
            }

            $ptsFonction = $r->points_fonction_actuelle !== null ? (float) $r->points_fonction_actuelle : 0.0;
            $noteRendement = $r->note_rendement !== null ? (float) $r->note_rendement : 0.0;
            $bonusEmploi = $r->bonus_dernier_emploi !== null ? (float) $r->bonus_dernier_emploi : 0.0;
            $ptsDiplomes = $r->points_diplomes !== null ? (float) $r->points_diplomes : 0.0;
            $ptsDecos = $r->points_decorations !== null ? (float) $r->points_decorations : 0.0;
            $ptsAncGrade = $r->points_anciennete_grade !== null ? (float) $r->points_anciennete_grade : 0.0;
            $joursPunition = $r->jours_punition !== null ? (int) $r->jours_punition : 0;
            $malusPunition = round($joursPunition * 0.1, 2);
            $malusLimiteAge = $r->points_limite_age !== null ? (float) $r->points_limite_age : $malusLimiteAgeDefaut;

            $totalPoints = round($noteRendement + $ptsFonction + $bonusEmploi + $ptsDiplomes + $ptsDecos + $ptsAncGrade - $malusLimiteAge - $malusPunition, 2);

            $certificatsFormates = isset($certsMap[$r->militaire_id]) ? implode(', ', $certsMap[$r->militaire_id]) : '';
            $diplomesFormates = $this->formatList($r->diplomes_academiques);
            $decorationsFormatees = $this->formatList($r->decorations);

            $result[] = [
                'militaire_id' => $r->militaire_id,
                'avancement_id' => $r->avancement_id,
                'matricule' => $r->matricule,
                'mle_grm' => $r->mle_grm ?? '',
                'nom' => $r->nom,
                'prenom' => $r->prenom,
                'nom_complet' => trim($r->nom . ' ' . $r->prenom),
                'photo_url' => $photoUrl,
                'grade_actuel' => $r->grade_actuel,
                'grade_ordre' => (int) $r->grade_ordre,
                'grade_cible' => $gradeCible,
                'is_officier' => $isOff,
                'categorie_personnel' => $isOff ? 'officier' : 'sous_officier',
                'categorie_statutaire' => $categorieStatutaire,
                'fonction_nom' => $fonctionNom,
                'date_derniere_promotion' => $datePromoTexte,
                'date_derniere_promotion_raw' => $dPromoStr ?? '',
                'date_entree_service' => $dateEntreeTexte,
                'date_entree_service_raw' => $dEntreeStr ?? '',
                'date_naissance' => $dateNaissTexte,
                'date_naissance_raw' => $dNaissStr ?? '',
                'anciennete_grade_texte' => $ancGradeTexte,
                'anciennete_grade_annees' => $ancGradeAnnees,
                'anciennete_service_texte' => $ancServiceTexte,
                'anciennete_service_annees' => $ancServiceAnnees,
                'certificats_texte' => $certificatsFormates,
                'diplomes_texte' => $diplomesFormates,
                'decorations_texte' => $decorationsFormatees,
                'message_eligibilite' => $promoInfo['message'] ?? ("Proposable pour " . $annee),
                'age' => $age,
                'annees_restantes_retraite' => $anneesRestantes,
                'points_fonction_actuelle' => $ptsFonction,
                'note_rendement' => $noteRendement,
                'bonus_dernier_emploi' => $bonusEmploi,
                'points_diplomes' => $ptsDiplomes,
                'points_decorations' => $ptsDecos,
                'points_anciennete_grade' => $ptsAncGrade,
                'jours_punition' => $joursPunition,
                'malus_punition' => $malusPunition,
                'malus_limite_age' => $malusLimiteAge,
                'total_points' => max(0, $totalPoints),
                'rang' => (int) $r->rang,
                'est_enregistre' => (bool) $r->avancement_id,
            ];
        }

        return $result;
    }

    /**
     * Calcule ou récupère en mémoire le payload complet des candidats et métriques pour la session.
     */
    public function getCandidatesPayload(int $annee): array
    {
        $stats = $this->getStats($annee);
        $candidates = $this->buildCandidatesList($annee);

        return [
            'candidates' => $candidates,
            'gradesOfficiers' => $stats['gradesOfficiers'],
            'gradesSousOfficiers' => $stats['gradesSousOfficiers'],
            'stats' => $stats['stats'],
        ];
    }

    /**
     * Affiche le tableau d'avancement avec pagination côté serveur ultra-performante
     */
    public function index(Request $request)
    {
        // Session : toujours l'année en clôture
        $annee = (int) $request->input('annee', date('Y'));
        $gradeFiltre = $request->input('grade', 'all');
        $tab = $request->input('tab');
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [15, 25, 50, 100, 9999], true)) {
            $perPage = 25;
        }

        $statsPayload = $this->getStats($annee);

        // Déterminer l'onglet actif par défaut
        if (!$tab) {
            $tab = ($statsPayload['stats']['total_officiers'] === 0 && $statsPayload['stats']['total_sous_officiers'] > 0)
                ? 'sous_officiers'
                : 'officiers';
        }

        $paginated = $this->getPaginatedCandidates($annee, $tab, $gradeFiltre, $search, $page, $perPage);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'candidates' => $paginated['data'],
                'totalItems' => $paginated['total'],
                'currentPage' => $paginated['page'],
                'perPage' => $paginated['per_page'],
                'lastPage' => $paginated['last_page'],
                'gradesOfficiers' => $statsPayload['gradesOfficiers'],
                'gradesSousOfficiers' => $statsPayload['gradesSousOfficiers'],
                'stats' => $statsPayload['stats'],
            ]);
        }

        return Inertia::render('Avancements/Index', [
            'selectedAnnee' => $annee,
            'selectedGrade' => $gradeFiltre,
            'initialTab' => $tab,
            'search' => $search,
            'currentPage' => $paginated['page'],
            'perPage' => $paginated['per_page'],
            'totalItems' => $paginated['total'],
            'candidates' => $paginated['data'],
            'gradesOfficiers' => $statsPayload['gradesOfficiers'],
            'gradesSousOfficiers' => $statsPayload['gradesSousOfficiers'],
            'stats' => $statsPayload['stats'],
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

        $candidates = $this->getPaginatedCandidates($annee, $tab, $gradeFiltre, '', 1, 999999)['data'];

        $suffix = '';
        if ($tab === 'officiers') {
            $suffix = '_Officiers';
        } elseif ($tab === 'sous_officiers') {
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
     * Construit la liste optimisée des candidats
     */
    public function buildCandidatesList(int $annee): array
    {
        return $this->getPaginatedCandidates($annee, 'all', 'all', '', 1, 999999)['data'];
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
