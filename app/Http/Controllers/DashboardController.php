<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Support\Effectif;
use App\Models\Alerte;
use App\Models\Grade;
use App\Models\Region;
use App\Models\Position;
use App\Models\Unite;
use Illuminate\Support\Facades\DB;
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
        $isWarm = \Illuminate\Support\Facades\Cache::has("v{$v}:dashboard:statistiques_summary");

        return Inertia::render('Dashboard', [
            'statistiques' => $isWarm
                ? $this->statistiquesResume($anneeCourante, $v)
                : Inertia::defer(fn () => $this->statistiquesResume($anneeCourante, $v)),
            'chartsData' => Inertia::defer(fn () => $this->donneesGraphiques($v)),
        ]);
    }

    /** Indicateurs clés du dashboard (en cache). Utilisés par l'écran ET par l'export Excel. */
    public function statistiquesResume(?int $anneeCourante = null, ?int $v = null): array
    {
        $anneeCourante ??= (int) Carbon::now()->year;
        $v ??= \App\Models\Militaire::getCacheVersion();

        return Cache::remember("v{$v}:dashboard:statistiques_summary", 86400, function () use ($anneeCourante, $v) {
            // 1. Départs en retraite année N (incluant déserteurs et procédures échus)
            $totalRetraitesN = \Illuminate\Support\Facades\DB::table('militaires')
                ->join('grades', 'militaires.grade_id', '=', 'grades.id')
                ->whereNotNull('militaires.date_naissance')
                ->whereNotNull('grades.retraite_obligatoire')
                ->where(function ($q) {
                    $q->whereNull('militaires.position_actuelle')
                      ->orWhere('militaires.position_actuelle', 'not ilike', '%otage%');
                })
                ->where(function ($q) {
                    $q->whereNull('militaires.observations')
                      ->orWhere('militaires.observations', 'not ilike', '%otage%');
                })
                ->where('militaires.est_disparu', 0)
                ->whereIn('militaires.statut_code', \App\Support\Effectif::CODES_RETRAITABLES)
                ->whereNull('militaires.deleted_at')
                ->where(function ($q) use ($anneeCourante) {
                    $q->where(function ($sq) use ($anneeCourante) {
                        $sq->whereIn('militaires.statut_code', [1, 2, 3, 4])
                           ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$anneeCourante})");
                    })->orWhere(function ($sq) use ($anneeCourante) {
                        $sq->whereIn('militaires.statut_code', [5, 6])
                           ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire <= {$anneeCourante})");
                    });
                })
                ->count();

            // 2. Proposables avancement année N (source unique d'éligibilités calculées pour performance ultra-rapide)
            $totalProposablesN = Cache::remember("v{$v}:dashboard:proposables_n_count", 86400, function () {
                $vPrete = (int) DB::table('eligibilites_calculees')->where('type', 'meta')->max('version');
                if ($vPrete > 0) {
                    return (int) DB::table('eligibilites_calculees')->where('version', $vPrete)->where('type', 'promotions')->count();
                }
                $propBoth = $this->getProposablesBoth();
                return $propBoth['proposablesN']['total'] ?? 0;
            });

            // 3. Synthèse globale des effectifs en 1 seule requête SQL agrégée ultra-rapide
            $stats = Militaire::whereNull('deleted_at')->selectRaw("
                COUNT(CASE WHEN " . Effectif::sqlActivite('statut') . " THEN 1 END) as total_actifs,
                COUNT(CASE WHEN " . Effectif::sqlActivite('statut') . " AND (position_actuelle ILIKE '%detache%' OR position_actuelle ILIKE '%détach%') THEN 1 END) as detaches,
                COUNT(CASE WHEN " . Effectif::sqlActivite('statut') . " AND (position_actuelle ILIKE '%mission%') THEN 1 END) as missions,
                COUNT(CASE WHEN " . Effectif::sqlActivite('statut') . " AND (position_actuelle ILIKE '%stage%') THEN 1 END) as stages,
                COUNT(CASE WHEN " . Effectif::sqlActivite('statut') . " AND (
                    (position_actuelle IS NULL OR position_actuelle = '')
                    OR (
                        position_actuelle NOT ILIKE '%detache%' AND position_actuelle NOT ILIKE '%détach%'
                        AND position_actuelle NOT ILIKE '%mission%'
                        AND position_actuelle NOT ILIKE '%stage%'
                    )
                ) THEN 1 END) as actifs_stricts,
                COUNT(CASE WHEN statut_code = 9 OR (date_deces >= '{$anneeCourante}-01-01' AND date_deces <= '{$anneeCourante}-12-31') THEN 1 END) as decedes,
                COUNT(CASE WHEN statut_code = 6 OR LOWER(motif_radiation) LIKE '%desert%' THEN 1 END) as deserteurs,
                COUNT(CASE WHEN statut_code = 8 OR statut = 'radié' THEN 1 END) as radies,
                COUNT(CASE WHEN statut_code IS NULL AND statut IS NULL THEN 1 END) as non_classes
            ")->first();

            // 4. Registres opérationnels en 1 seule requête SQL
            $regStats = \App\Models\RegistreOperationnel::selectRaw("
                COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_BLESSE . " THEN 1 END) as blesses,
                COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_GRAND_MALADE . " THEN 1 END) as grands_malades,
                COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_DISPARU . " THEN 1 END) as disparus
            ")->first();

            $totalActifs = (int) ($stats->total_actifs ?? 0);

            return [
                'annee' => $anneeCourante,
                'total_militaires' => $totalActifs,
                'militaires_actifs' => $totalActifs,
                'actifs_stricts' => (int) ($stats->actifs_stricts ?? 0),
                'detaches' => (int) ($stats->detaches ?? 0),
                'missions' => (int) ($stats->missions ?? 0),
                'stages' => (int) ($stats->stages ?? 0),
                'radies' => (int) ($stats->radies ?? 0),
                'non_classes' => (int) ($stats->non_classes ?? 0),
                'proposables_n' => (int) $totalProposablesN,
                'retraites_n' => (int) $totalRetraitesN,
                'decedes_cette_annee' => (int) ($stats->decedes ?? 0),
                'deserteurs' => (int) ($stats->deserteurs ?? 0),
                'total_blesses' => (int) ($regStats->blesses ?? 0),
                'total_grands_malades' => (int) ($regStats->grands_malades ?? 0),
                'total_disparus' => (int) ($regStats->disparus ?? 0),
            ];
        });
    }

    /** Données des graphiques du dashboard (en cache). Utilisées par l'écran ET par l'export Excel. */
    public function donneesGraphiques(?int $v = null): array
    {
        $v ??= \App\Models\Militaire::getCacheVersion();

        return Cache::remember("v{$v}:dashboard:charts_data", 86400, function () {
        // 1. STATISTIQUES PAR STATUT (Situation Administrative Statutaire & Opérationnelle)
        // Utilise les filtres unifiés d'Effectif pour une cohérence rigoureuse à 100% avec les fiches et la liste
        $countDetaches = (int) Effectif::appliquerFiltreDetaches(Militaire::whereNull('deleted_at'))->count();
        $countActifsStricts = (int) Effectif::appliquerFiltreActifsStricts(Militaire::whereNull('deleted_at'))->count();
        $countMissions = (int) Effectif::appliquerFiltreMissions(Militaire::whereNull('deleted_at'))->count();
        $countStages = (int) Effectif::appliquerFiltreStages(Militaire::whereNull('deleted_at'))->count();

        $statutCounts = Militaire::whereNull('deleted_at')
            ->selectRaw('statut_code, COUNT(*) AS c')
            ->groupBy('statut_code')
            ->pluck('c', 'statut_code')
            ->toArray();

        $statutsMeta = [
            'actif_strict' => ['val' => $countActifsStricts, 'code' => 'actif_strict', 'label' => 'Actif (En service)', 'color' => '#059669', 'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
            'détaché'      => ['val' => $countDetaches,      'code' => 'détaché',      'label' => 'Détaché',            'color' => '#0284c7', 'bg' => 'bg-sky-50 text-sky-800 border-sky-200'],
            'en mission'   => ['val' => $countMissions,      'code' => 'mission',      'label' => 'En mission',         'color' => '#8b5cf6', 'bg' => 'bg-purple-50 text-purple-800 border-purple-200'],
            'en stage'     => ['val' => $countStages,        'code' => 'stage',        'label' => 'En stage / formation', 'color' => '#f59e0b', 'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'procédure en cours' => ['val' => (int) ($statutCounts[5] ?? 0), 'code' => '5', 'label' => 'Procédure en cours', 'color' => '#d97706', 'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
            'déserteur'    => ['val' => (int) ($statutCounts[6] ?? 0), 'code' => '6', 'label' => 'Déserteur',          'color' => '#ea580c', 'bg' => 'bg-orange-50 text-orange-800 border-orange-200'],
            'retraité'     => ['val' => (int) ($statutCounts[7] ?? 0), 'code' => '7', 'label' => 'Retraité',           'color' => '#9333ea', 'bg' => 'bg-purple-50 text-purple-800 border-purple-200'],
            'radié'        => ['val' => (int) ($statutCounts[8] ?? 0), 'code' => '8', 'label' => 'Radié',              'color' => '#64748b', 'bg' => 'bg-slate-50 text-slate-800 border-slate-200'],
            'décédé'       => ['val' => (int) ($statutCounts[9] ?? 0), 'code' => '9', 'label' => 'Décédé',             'color' => '#e11d48', 'bg' => 'bg-rose-50 text-rose-800 border-rose-200'],
        ];

        $statutsDistribution = [];
        foreach ($statutsMeta as $meta) {
            $statutsDistribution[] = [
                'code'  => $meta['code'],
                'label' => $meta['label'],
                'value' => $meta['val'],
                'color' => $meta['color'],
                'bg'    => $meta['bg'],
            ];
        }

        // 2. STATISTIQUES PAR POSITION (Comptage SQL pur par ID numérique entier position_id)
        $uniteId = Position::idUnite();
        $allPositions = Position::orderBy('id')->get(['id', 'nom', 'code']);

        // Comptage groupé par position_id entier (utilise l'index btree militaires_position_id_idx)
        // Prend en compte tous les militaires du fichier actif (actifs, déserteurs, procédures, etc.)
        $countsByPositionId = DB::table('militaires')
            ->whereNull('deleted_at')
            ->whereIn('statut_code', Effectif::CODES_RETRAITABLES)
            ->whereNotNull('position_id')
            ->groupBy('position_id')
            ->selectRaw('position_id, count(*) as total')
            ->pluck('total', 'position_id')
            ->toArray();

        // Militaires sans position_id explicite mais avec une unité => rattachés à "À l'Unité / Au Poste"
        $sansPosition = (int) DB::table('militaires')
            ->whereNull('deleted_at')
            ->whereIn('statut_code', Effectif::CODES_RETRAITABLES)
            ->whereNull('position_id')
            ->count();

        $positionsDistribution = [];
        foreach ($allPositions as $p) {
            $val = (int) ($countsByPositionId[$p->id] ?? 0);
            if ($p->id === $uniteId) {
                $val += $sansPosition;
            }
            $positionsDistribution[] = [
                'id' => (int) $p->id,
                'label' => $p->nom,
                'code' => $p->code,
                'value' => $val,
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

            $mapRetraite = function ($m) use ($anneeCourante) {
                $dRetraite = Carbon::parse($m->date_naissance)->addYears($m->retraite_obligatoire);
                $anneeRetraite = (int) $dRetraite->year;
                $remarque = null;
                if (in_array((int) $m->statut_code, [5, 6], true) && $anneeRetraite < $anneeCourante) {
                    $quoi = (int) $m->statut_code === 6 ? 'Déserteur' : 'Procédure en cours';
                    $depuis = $anneeCourante - $anneeRetraite;
                    $remarque = "{$quoi} : âge de retraite atteint en {$anneeRetraite} (il y a {$depuis} an" . ($depuis > 1 ? 's' : '') . ")";
                }
                return [
                    'id' => $m->id,
                    'matricule' => $m->matricule,
                    'nom' => $m->nom,
                    'prenom' => $m->prenom,
                    'nom_complet' => $m->nom . ' ' . $m->prenom,
                    'grade_actuel' => $m->grade_actuel,
                    'statut' => $m->statut,
                    'date_retraite' => $dRetraite->format('Y-m-d'),
                    'date_retraite_formatted' => $dRetraite->format('d/m/Y'),
                    'remarque' => $remarque,
                ];
            };

            $baseQuery = fn() => \Illuminate\Support\Facades\DB::table('militaires')
                ->join('grades', 'militaires.grade_id', '=', 'grades.id')
                ->whereNotNull('militaires.date_naissance')
                ->whereNotNull('grades.retraite_obligatoire')
                ->where(function ($q) {
                    $q->whereNull('militaires.position_actuelle')
                      ->orWhere('militaires.position_actuelle', 'not ilike', '%otage%');
                })
                ->where(function ($q) {
                    $q->whereNull('militaires.observations')
                      ->orWhere('militaires.observations', 'not ilike', '%otage%');
                })
                ->where('militaires.est_disparu', 0)
                ->whereIn('militaires.statut_code', \App\Support\Effectif::CODES_RETRAITABLES)
                ->whereNull('militaires.deleted_at')
                ->select([
                    'militaires.id', 'militaires.matricule', 'militaires.nom', 'militaires.prenom',
                    'militaires.grade_actuel', 'militaires.statut', 'militaires.statut_code', 'militaires.date_naissance',
                    'grades.retraite_obligatoire'
                ]);

            $retN = $baseQuery()
                ->where(function ($q) use ($anneeCourante) {
                    $q->where(function ($sq) use ($anneeCourante) {
                        $sq->whereIn('militaires.statut_code', [1, 2, 3, 4])
                           ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$anneeCourante})");
                    })->orWhere(function ($sq) use ($anneeCourante) {
                        $sq->whereIn('militaires.statut_code', [5, 6])
                           ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire <= {$anneeCourante})");
                    });
                })
                ->get()
                ->map($mapRetraite)
                ->all();

            $retN1 = $baseQuery()
                ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$anneeN1})")
                ->get()
                ->map($mapRetraite)
                ->all();

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
        return Cache::remember("v{$v}:dashboard:proposables_both", 86400, function () {
            $aujourdhui = Carbon::now();
            $anneeCourante = (int) $aujourdhui->year;
            $anneeN1 = $anneeCourante + 1;
            $datePropN_Str = "{$anneeCourante}-12-31";
            $datePropN1_Str = "{$anneeN1}-12-31";
            $nowY = (int) $aujourdhui->year;
            $nowMd = (int) $aujourdhui->format('md');
            $refMd = 1231;

            $fastDiffYearsProp = static function (?string $dateStr, int $refY, int $refMd): int {
                if (!$dateStr || strlen($dateStr) < 10) return 0;
                $y = (int) substr($dateStr, 0, 4);
                $md = (int) (substr($dateStr, 5, 2) . substr($dateStr, 8, 2));
                $diff = $refY - $y;
                if ($refMd < $md) $diff--;
                return max(0, $diff);
            };

            $fastAncienneteFormattedProp = static function (?string $dateStr, int $refY): string {
                if (!$dateStr || strlen($dateStr) < 10) return '0 an';
                $y = (int) substr($dateStr, 0, 4);
                $m = (int) substr($dateStr, 5, 2);
                $d = (int) substr($dateStr, 8, 2);
                $diffYears = $refY - $y;
                $diffMonths = 12 - $m;
                if (31 < $d) $diffMonths--;
                if ($diffMonths < 0) {
                    $diffYears--;
                    $diffMonths += 12;
                }
                if ($diffYears < 0) return "moins d'1 mois";
                if ($diffYears > 0 && $diffMonths > 0) {
                    return "{$diffYears} an" . ($diffYears > 1 ? 's' : '') . " et {$diffMonths} mois";
                } elseif ($diffYears > 0) {
                    return "{$diffYears} an" . ($diffYears > 1 ? 's' : '');
                } elseif ($diffMonths > 0) {
                    return "{$diffMonths} mois";
                } else {
                    return "moins d'1 mois";
                }
            };

            $rawCerts = \Illuminate\Support\Facades\DB::table('certificat_militaire')
                ->join('certificats', 'certificats.id', '=', 'certificat_militaire.certificat_id')
                ->select('certificat_militaire.militaire_id', 'certificats.niveau_certificat', 'certificat_militaire.date_obtention')
                ->get();

            $certsMap = [];
            foreach ($rawCerts as $c) {
                $certsMap[$c->militaire_id][$c->niveau_certificat] = $c->date_obtention ? substr((string)$c->date_obtention, 0, 10) : true;
            }

            $militaires = \Illuminate\Support\Facades\DB::table('militaires')
                ->whereRaw(\App\Support\Effectif::sqlActivite('statut'))
                ->whereNull('deleted_at')
                ->select('id', 'nom', 'prenom', 'matricule', 'grade_actuel', 'date_naissance', 'date_entree_service', 'date_derniere_promotion', 'antecedent_judiciaire', 'antecedent_disciplinaire')
                ->cursor();

            $propN = [];
            $propN1 = [];

            foreach ($militaires as $m) {
                $grade = $m->grade_actuel;
                $mId = $m->id;
                $hasCerts = $certsMap[$mId] ?? [];
                $conditionsBase = !$m->antecedent_judiciaire && !$m->antecedent_disciplinaire;

                $dNaissanceStr = $m->date_naissance;
                $dEntreeStr = $m->date_entree_service;
                $dPromoStr = $m->date_derniere_promotion;

                $age = $fastDiffYearsProp($dNaissanceStr, $nowY, $nowMd);

                $evalGrade = function(int $refYear) use ($grade, $hasCerts, $conditionsBase, $dPromoStr, $dEntreeStr, $age, $refMd, $fastDiffYearsProp) {
                    $ancGrade = $dPromoStr ? $fastDiffYearsProp($dPromoStr, $refYear, $refMd) : 0;
                    $ancTotale = $dEntreeStr ? $fastDiffYearsProp($dEntreeStr, $refYear, $refMd) : 0;

                    if ($grade === 'Sous-lieutenant' && $ancGrade >= 2) return 'Lieutenant';
                    if ($grade === 'Lieutenant' && $ancGrade >= 3) return 'Capitaine';
                    if ($grade === 'Capitaine' && $ancGrade >= 3) return 'Commandant';
                    if ($grade === 'Commandant' && $ancGrade >= 3) return 'Lieutenant-colonel';
                    if ($grade === 'Lieutenant-colonel' && $ancGrade >= 3) return 'Colonel';
                    if ($grade === 'Colonel' && $ancGrade >= 6) return 'Colonel-Major';

                    if ($grade === 'Soldat 1' && isset($hasCerts['CAT1']) && $conditionsBase && $ancTotale >= 5) {
                        return 'Caporal';
                    }
                    if ($grade === 'Caporal' && isset($hasCerts['CAT1']) && isset($hasCerts['CAT2']) && $conditionsBase) {
                        return 'Sergent';
                    }
                    if ($grade === 'Caporal' && isset($hasCerts['CAT1']) && !isset($hasCerts['CAT2']) && $age >= 47 && $ancGrade >= 3 && $conditionsBase) {
                        return 'Caporal-chef';
                    }
                    if ($grade === 'Sergent' && $conditionsBase && $ancGrade >= 2 && $ancTotale >= 5) {
                        return 'Sergent-Chef';
                    }
                    if ($grade === 'Sergent-Chef' && $conditionsBase && $ancGrade >= 3) {
                        return 'Adjudant';
                    }
                    if ($grade === 'Adjudant' && $conditionsBase && $ancGrade >= 2) {
                        return 'Adjudant-Chef';
                    }
                    if ($grade === 'Adjudant-Chef' && isset($hasCerts['CIA']) && isset($hasCerts['BA1']) && isset($hasCerts['BA2']) && $age >= 45 && $conditionsBase && $ancGrade >= 2) {
                        return 'Adjudant-Chef Major';
                    }
                    if (isset($hasCerts['BA2'])) {
                        if (($grade === 'Adjudant-Chef' && $age <= 45 && $ancTotale >= 15 && $ancGrade >= 2)
                            || (mb_strtolower((string)$grade) === 'adjudant-chef major' && $ancGrade >= 2)) {
                            return 'Sous-lieutenant';
                        }
                    }
                    return null;
                };

                $getDateAncienneteStr = function(string $cible) use ($grade, $dPromoStr, $dEntreeStr, $dNaissanceStr, $hasCerts) {
                    if ($grade === 'Lieutenant' && $cible === 'Capitaine') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Sous-lieutenant' && $cible === 'Lieutenant') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Capitaine' && $cible === 'Commandant') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Commandant' && $cible === 'Lieutenant-colonel') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Lieutenant-colonel' && $cible === 'Colonel') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Colonel' && $cible === 'Colonel-Major') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 6, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Soldat 1' && $cible === 'Caporal') {
                        return $dEntreeStr ? sprintf('%04d-%s', (int)substr($dEntreeStr, 0, 4) + 5, substr($dEntreeStr, 5, 5)) : null;
                    }
                    if ($grade === 'Caporal' && $cible === 'Sergent') {
                        $dObt = is_string($hasCerts['CAT1'] ?? false) ? $hasCerts['CAT1'] : null;
                        return $dObt ? sprintf('%04d-%s', (int)substr($dObt, 0, 4) + 3, substr($dObt, 5, 5)) : null;
                    }
                    if ($grade === 'Caporal' && $cible === 'Caporal-chef') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Sergent' && $cible === 'Sergent-Chef') {
                        if ($dPromoStr && $dEntreeStr) {
                            $d2 = sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5));
                            $d5 = sprintf('%04d-%s', (int)substr($dEntreeStr, 0, 4) + 5, substr($dEntreeStr, 5, 5));
                            return max($d2, $d5);
                        }
                        return null;
                    }
                    if ($grade === 'Sergent-Chef' && $cible === 'Adjudant') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 3, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Adjudant' && $cible === 'Adjudant-Chef') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : null;
                    }
                    if ($grade === 'Adjudant-Chef' && $cible === 'Adjudant-Chef Major') {
                        if ($dPromoStr && $dNaissanceStr) {
                            $d2 = sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5));
                            $d45 = sprintf('%04d-%s', (int)substr($dNaissanceStr, 0, 4) + 45, substr($dNaissanceStr, 5, 5));
                            return max($d2, $d45);
                        }
                        return null;
                    }
                    if ($cible === 'Sous-lieutenant') {
                        return $dPromoStr ? sprintf('%04d-%s', (int)substr($dPromoStr, 0, 4) + 2, substr($dPromoStr, 5, 5)) : null;
                    }
                    return null;
                };

                $gradeCibleN = $evalGrade($anneeCourante);
                if ($gradeCibleN) {
                    $dateAncStr = $getDateAncienneteStr($gradeCibleN);
                    if ($dateAncStr && $dateAncStr <= $datePropN_Str) {
                        $ancDateFormatted = sprintf('%02d/%02d/%04d', (int)substr($dateAncStr, 8, 2), (int)substr($dateAncStr, 5, 2), (int)substr($dateAncStr, 0, 4));
                        $propN[] = [
                            'id' => $m->id,
                            'matricule' => $m->matricule,
                            'nom' => $m->nom,
                            'prenom' => $m->prenom,
                            'nom_complet' => $m->nom . ' ' . $m->prenom,
                            'grade_actuel' => $m->grade_actuel,
                            'grade_cible' => $gradeCibleN,
                            'date_proposition' => $datePropN_Str,
                            'date_proposition_formatted' => "31/12/{$anneeCourante}",
                            'date_anciennete' => $dateAncStr,
                            'date_anciennete_formatted' => $ancDateFormatted,
                            'anciennete_grade_formatted' => $fastAncienneteFormattedProp($dPromoStr ?? $dEntreeStr, $anneeCourante),
                        ];
                        continue;
                    }
                }

                $gradeCibleN1 = $evalGrade($anneeN1);
                if ($gradeCibleN1) {
                    $dateAncStr = $getDateAncienneteStr($gradeCibleN1);
                    if ($dateAncStr && $dateAncStr <= $datePropN1_Str) {
                        $ancDateFormatted = sprintf('%02d/%02d/%04d', (int)substr($dateAncStr, 8, 2), (int)substr($dateAncStr, 5, 2), (int)substr($dateAncStr, 0, 4));
                        $propN1[] = [
                            'id' => $m->id,
                            'matricule' => $m->matricule,
                            'nom' => $m->nom,
                            'prenom' => $m->prenom,
                            'nom_complet' => $m->nom . ' ' . $m->prenom,
                            'grade_actuel' => $m->grade_actuel,
                            'grade_cible' => $gradeCibleN1,
                            'date_proposition' => $datePropN1_Str,
                            'date_proposition_formatted' => "31/12/{$anneeN1}",
                            'date_anciennete' => $dateAncStr,
                            'date_anciennete_formatted' => $ancDateFormatted,
                            'anciennete_grade_formatted' => $fastAncienneteFormattedProp($dPromoStr ?? $dEntreeStr, $anneeN1),
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
