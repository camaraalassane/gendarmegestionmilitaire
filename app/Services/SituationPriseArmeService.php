<?php

namespace App\Services;

use App\Models\Militaire;
use App\Models\OrganismeDetachement;
use App\Models\Region;
use App\Models\RegistreOperationnel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SituationPriseArmeService
{
    /**
     * Mappage direct indexé par ID entier de grade vers le libellé officiel SPA (Officiers Généraux au rang).
     */
    public const GRADE_ID_TO_SPA = [
        20 => 'GAR',
        19 => 'GCA',
        18 => 'GDD',
        17 => 'GDB',
        16 => 'CLM',
        15 => 'COL',
        14 => 'LCL',
        13 => 'CES',
        12 => 'CNE',
        11 => 'LTN',
        10 => 'SLT',
        24 => 'ASP',
        22 => 'EOA', 27 => 'EOA',
        9  => 'MJR',
        8  => 'ADC',
        7  => 'ADJ',
        6  => 'MDC',
        5  => 'MDL',
        23 => 'ESOA',
        1  => 'GA', 2 => 'GA', 3 => 'GA', 4 => 'GA', 26 => 'GA',
    ];

    /**
     * Ordre officiel des grades de la SPA (du plus haut au rang : Officiers Généraux jusqu'aux Militaires du rang).
     */
    public const SPA_GRADES_ORDRE = [
        'GAR', 'GCA', 'GDD', 'GDB',
        'CLM', 'COL', 'LCL', 'CES',
        'CNE', 'LTN', 'SLT', 'ASP',
        'EOA', 'MJR', 'ADC', 'ADJ',
        'MDC', 'MDL', 'ESOA', 'GA'
    ];

    /**
     * Mappage direct indexé par ID entier de grade vers la colonne Région (20 grades distincts).
     */
    public const GRADE_ID_TO_REGION_COL = [
        20 => 'GAR',
        19 => 'GCA',
        18 => 'GDD',
        17 => 'GDB',
        16 => 'CLM',
        15 => 'COL',
        14 => 'LCL',
        13 => 'CES',
        12 => 'CNE',
        11 => 'LTN',
        10 => 'SLT',
        24 => 'ASP',
        22 => 'EOA', 27 => 'EOA',
        9  => 'MJR',
        8  => 'ADC',
        7  => 'ADJ',
        6  => 'MDC',
        5  => 'MDL',
        23 => 'ESOA',
        1  => 'MRG', 2 => 'MRG', 3 => 'MRG', 4 => 'MRG', 26 => 'MRG',
    ];

    /**
     * Ordre officiel hiérarchique pour les 20 Régions (De haut en bas : Officiers Généraux jusqu'au rang - 20 grades).
     */
    public const REGION_GRADES_COLS = [
        'GAR', 'GCA', 'GDD', 'GDB',
        'CLM', 'COL', 'LCL', 'CES',
        'CNE', 'LTN', 'SLT', 'ASP',
        'EOA', 'MJR', 'ADC', 'ADJ',
        'MDC', 'MDL', 'ESOA', 'MRG'
    ];

    /**
     * Calcule dynamiquement la matrice SPA (17 grades x 48 organismes x Sexe H/D)
     * directement à partir des données réelles de la table `militaires`.
     * Requête 100% numérique et groupée via les index B-Tree (mise en cache).
     */
    public function calculerMatriceDynamique(): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:spa:matrice_dynamique", 1800, function () {
            $organismes = OrganismeDetachement::where('est_actif', 1)->orderBy('ordre')->get();
            $orgIdToCode = $organismes->pluck('code', 'id')->toArray();

            // Initialisation de la structure de la matrice (17 lignes de grades)
            $matrice = [];
            foreach (self::SPA_GRADES_ORDRE as $gradeLabel) {
                $matrice[$gradeLabel] = [];
                foreach ($organismes as $org) {
                    $matrice[$gradeLabel][$org->code] = ['h' => 0, 'd' => 0];
                }
            }

            // Agrégation SQL purement numérique
            $grmId = $organismes->firstWhere('code', 'GRM')?->id ?? 1;

            $counts = DB::table('militaires')
                ->select(
                    'grade_id',
                    DB::raw("COALESCE(organisme_detachement_id, {$grmId}) as org_id"),
                    'sexe_code',
                    DB::raw('count(*) as total')
                )
                ->whereIn('statut_code', [1, 2, 3, 4]) // Actif, Détaché, Mission, Stage
                ->whereNull('deleted_at')
                ->groupBy('grade_id', DB::raw("COALESCE(organisme_detachement_id, {$grmId})"), 'sexe_code')
                ->get();

            foreach ($counts as $row) {
                $spaCode = self::GRADE_ID_TO_SPA[(int) $row->grade_id] ?? 'GA';
                $orgCode = $orgIdToCode[(int) $row->org_id] ?? 'GRM';
                $genderKey = ((int) $row->sexe_code === 2) ? 'd' : 'h';

                if (isset($matrice[$spaCode][$orgCode][$genderKey])) {
                    $matrice[$spaCode][$orgCode][$genderKey] += (int) $row->total;
                }
            }

            // Calcul des totaux par ligne (H, D, TOTAL)
            foreach ($matrice as $gradeLabel => &$rowLigne) {
                $rowTotH = 0;
                $rowTotD = 0;
                foreach ($organismes as $org) {
                    $rowTotH += (int) ($rowLigne[$org->code]['h'] ?? 0);
                    $rowTotD += (int) ($rowLigne[$org->code]['d'] ?? 0);
                }
                $rowLigne['total_h'] = $rowTotH;
                $rowLigne['total_d'] = $rowTotD;
                $rowLigne['total'] = $rowTotH + $rowTotD;
            }
            unset($rowLigne);

            return $matrice;
        });
    }

    /**
     * Calcule dynamiquement la situation pour les 20 régions (19 Régions + 1 District de Bamako)
     * à partir des données réelles de la table `militaires` (mise en cache).
     */
    public function calculerRegionsDynamique(): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:spa:regions_dynamique", 1800, function () {
            $regions = Region::orderBy('ordre')->get();
            $regionIdToCode = $regions->pluck('code', 'id')->toArray();

            $regionsData = [];
            foreach ($regions as $r) {
                $regionsData[$r->code] = [
                    'id' => $r->id,
                    'nom' => $r->nom,
                    'code' => $r->code,
                    'total' => 0,
                ];
                foreach (self::REGION_GRADES_COLS as $col) {
                    $regionsData[$r->code][$col] = 0;
                }
            }

            $counts = DB::table('militaires')
                ->select(
                    DB::raw('COALESCE(region_id, 1) as reg_id'),
                    'grade_id',
                    'sexe_code',
                    DB::raw('count(*) as total')
                )
                ->whereIn('statut_code', [1, 2, 3, 4])
                ->whereNull('deleted_at')
                ->groupBy(DB::raw('COALESCE(region_id, 1)'), 'grade_id', 'sexe_code')
                ->get();

            foreach ($counts as $row) {
                $regCode = $regionIdToCode[(int) $row->reg_id] ?? 'R1';
                $col = self::GRADE_ID_TO_REGION_COL[(int) $row->grade_id] ?? 'MDL';
                $tot = (int) $row->total;
                $isDame = ((int) $row->sexe_code === 2);

                if (isset($regionsData[$regCode])) {
                    if (!isset($regionsData[$regCode]['grades'])) {
                        $regionsData[$regCode]['grades'] = [];
                        $regionsData[$regCode]['total_h'] = 0;
                        $regionsData[$regCode]['total_d'] = 0;
                        foreach (self::REGION_GRADES_COLS as $c) {
                            $regionsData[$regCode]['grades'][$c] = ['total' => 0, 'h' => 0, 'd' => 0];
                            $regionsData[$regCode][$c . '_h'] = 0;
                            $regionsData[$regCode][$c . '_d'] = 0;
                        }
                    }

                    $regionsData[$regCode][$col] += $tot;
                    $regionsData[$regCode]['total'] += $tot;
                    $regionsData[$regCode]['grades'][$col]['total'] += $tot;

                    if ($isDame) {
                        $regionsData[$regCode][$col . '_d'] += $tot;
                        $regionsData[$regCode]['total_d'] += $tot;
                        $regionsData[$regCode]['grades'][$col]['d'] += $tot;
                    } else {
                        $regionsData[$regCode][$col . '_h'] += $tot;
                        $regionsData[$regCode]['total_h'] += $tot;
                        $regionsData[$regCode]['grades'][$col]['h'] += $tot;
                    }
                }
            }

            return $regionsData;
        });
    }

    /**
     * Calcule les statistiques globales en temps réel à partir de la base de données (mise en cache).
     */
    public function calculerStatsGlobales(): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:spa:stats_globales", 1800, function () {
            $organismesCount = OrganismeDetachement::where('est_actif', 1)->count();
            $regionsCount = Region::count();

            $grmId = OrganismeDetachement::where('code', 'GRM')->value('id') ?? 1;

            $posDetacheId = \App\Models\Position::idDetachement();

            $mStats = DB::table('militaires')
                ->selectRaw("
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) THEN 1 END) as total_spa,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND (sexe_code = 1 OR sexe_code IS NULL) THEN 1 END) as total_hommes,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND sexe_code = 2 THEN 1 END) as total_dames,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND (
                        (organisme_detachement_id IS NOT NULL AND organisme_detachement_id != {$grmId})
                        OR position_actuelle ILIKE '%détachement%'
                        OR position_id = {$posDetacheId}
                    ) THEN 1 END) as total_detaches,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND grade_id IN (17, 18, 19, 20) THEN 1 END) as total_officiers_generaux,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND grade_id IN (13, 14, 15, 16) THEN 1 END) as total_officiers_superieurs,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND grade_id IN (10, 11, 12, 24, 22, 27) THEN 1 END) as total_officiers_subalternes,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND grade_id IN (5, 6, 7, 8, 9, 23) THEN 1 END) as total_sous_officiers,
                    COUNT(CASE WHEN statut_code IN (1, 2, 3, 4) AND grade_id IN (1, 2, 3, 4, 26) THEN 1 END) as total_militaires_rang
                ")
                ->whereNull('deleted_at')
                ->first();

            $regCounts = RegistreOperationnel::selectRaw("
                COUNT(CASE WHEN type_registre_code = " . RegistreOperationnel::TYPE_BLESSE . " THEN 1 END) as blesses,
                COUNT(CASE WHEN type_registre_code = " . RegistreOperationnel::TYPE_DISPARU . " THEN 1 END) as disparus,
                COUNT(CASE WHEN type_registre_code = " . RegistreOperationnel::TYPE_GRAND_MALADE . " THEN 1 END) as grands_malades,
                COUNT(CASE WHEN type_registre_code = " . RegistreOperationnel::TYPE_DECEDE . " THEN 1 END) as decedes,
                COUNT(CASE WHEN type_registre_code = " . RegistreOperationnel::TYPE_DESERTEUR . " THEN 1 END) as deserteurs
            ")->first();

            return [
                'total_spa' => (int) ($mStats->total_spa ?? 0),
                'total_hommes' => (int) ($mStats->total_hommes ?? 0),
                'total_dames' => (int) ($mStats->total_dames ?? 0),
                'total_detaches' => (int) ($mStats->total_detaches ?? 0),
                'total_officiers_generaux' => (int) ($mStats->total_officiers_generaux ?? 0),
                'total_officiers_superieurs' => (int) ($mStats->total_officiers_superieurs ?? 0),
                'total_officiers_subalternes' => (int) ($mStats->total_officiers_subalternes ?? 0),
                'total_sous_officiers' => (int) ($mStats->total_sous_officiers ?? 0),
                'total_militaires_rang' => (int) ($mStats->total_militaires_rang ?? 0),
                'total_blesses' => (int) ($regCounts->blesses ?? 0),
                'total_disparus' => (int) ($regCounts->disparus ?? 0),
                'total_grands_malades' => (int) ($regCounts->grands_malades ?? 0),
                'total_decedes' => (int) ($regCounts->decedes ?? 0),
                'total_deserteurs' => (int) ($regCounts->deserteurs ?? 0),
                'total_organismes' => $organismesCount,
                'total_regions' => $regionsCount,
            ];
        });
    }

    /**
     * Calcule dynamiquement la situation pour les 48 Organismes de Détachement
     * au format Cartes avec décomposition H / D pour chaque grade.
     */
    public function calculerOrganismesCardsDynamique(): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:spa:organismes_cards", 1800, function () {
            $organismes = OrganismeDetachement::where('est_actif', 1)->orderBy('ordre')->get();
            $orgIdToCode = $organismes->pluck('code', 'id')->toArray();
            $grmId = $organismes->firstWhere('code', 'GRM')?->id ?? 1;

            $orgsData = [];
            foreach ($organismes as $org) {
                $orgsData[$org->code] = [
                    'id' => $org->id,
                    'nom' => $org->nom,
                    'code' => $org->code,
                    'total' => 0,
                    'total_h' => 0,
                    'total_d' => 0,
                    'grades' => [],
                ];
                foreach (self::REGION_GRADES_COLS as $col) {
                    $orgsData[$org->code][$col] = 0;
                    $orgsData[$org->code][$col . '_h'] = 0;
                    $orgsData[$org->code][$col . '_d'] = 0;
                    $orgsData[$org->code]['grades'][$col] = [
                        'total' => 0,
                        'h' => 0,
                        'd' => 0,
                    ];
                }
            }

            $counts = DB::table('militaires')
                ->select(
                    DB::raw("COALESCE(organisme_detachement_id, {$grmId}) as org_id"),
                    'grade_id',
                    'sexe_code',
                    DB::raw('count(*) as total')
                )
                ->whereIn('statut_code', [1, 2, 3, 4])
                ->whereNull('deleted_at')
                ->groupBy(DB::raw("COALESCE(organisme_detachement_id, {$grmId})"), 'grade_id', 'sexe_code')
                ->get();

            foreach ($counts as $row) {
                $orgCode = $orgIdToCode[(int) $row->org_id] ?? 'GRM';
                $col = self::GRADE_ID_TO_REGION_COL[(int) $row->grade_id] ?? 'MDL';
                $tot = (int) $row->total;
                $isDame = ((int) $row->sexe_code === 2);

                if (isset($orgsData[$orgCode])) {
                    $orgsData[$orgCode][$col] += $tot;
                    $orgsData[$orgCode]['total'] += $tot;
                    $orgsData[$orgCode]['grades'][$col]['total'] += $tot;

                    if ($isDame) {
                        $orgsData[$orgCode][$col . '_d'] += $tot;
                        $orgsData[$orgCode]['total_d'] += $tot;
                        $orgsData[$orgCode]['grades'][$col]['d'] += $tot;
                    } else {
                        $orgsData[$orgCode][$col . '_h'] += $tot;
                        $orgsData[$orgCode]['total_h'] += $tot;
                        $orgsData[$orgCode]['grades'][$col]['h'] += $tot;
                    }
                }
            }

            return $orgsData;
        });
    }

    /**
     * Mappe un libellé de colonne de grade (ex: 'ADC', 'MRG') vers les IDs réels dans la table grades.
     */
    public static function getGradeIdsForCol(string $col): array
    {
        return match (strtoupper(trim($col))) {
            'GAR' => [20],
            'GCA' => [19],
            'GDD' => [18],
            'GDB' => [17],
            'CLM' => [16],
            'COL' => [15],
            'LCL' => [14],
            'CES' => [13],
            'CNE' => [12],
            'LTN' => [11],
            'SLT' => [10],
            'ASP' => [24],
            'EOA' => [22, 27],
            'MJR' => [9],
            'ADC' => [8],
            'ADJ' => [7],
            'MDC' => [6],
            'MDL' => [5],
            'ESOA' => [23],
            'MRG', 'GA' => [1, 2, 3, 4, 26],
            default => [],
        };
    }

    /**
     * Retourne le nom officiel et complet du grade pour affichage dans la modale.
     */
    public static function getGradeLibelleForCol(string $col): string
    {
        return match (strtoupper(trim($col))) {
            'GAR' => "Général d'Armée",
            'GCA' => "Général de Corps d'Armée",
            'GDD' => "Général de Division",
            'GDB' => "Général de Brigade",
            'CLM' => "Colonel-Major",
            'COL' => "Colonel",
            'LCL' => "Lieutenant-Colonel",
            'CES' => "Chef d'Escadron",
            'CNE' => "Capitaine",
            'LTN' => "Lieutenant",
            'SLT' => "Sous-Lieutenant",
            'ASP' => "Aspirant",
            'EOA' => "Élève-Officier d'Active",
            'MJR' => "Adjudant-Chef Major / Major",
            'ADC' => "Adjudant-Chef",
            'ADJ' => "Adjudant",
            'MDC' => "Maréchal des Logis-Chef",
            'MDL' => "Maréchal des Logis",
            'ESOA' => "Élève Sous-Officier d'Active",
            'MRG', 'GA' => "Gendarme / Militaire du Rang",
            default => $col,
        };
    }
}
