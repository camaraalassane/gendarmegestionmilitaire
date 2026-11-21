<?php

namespace App\Services;

use App\Models\Militaire;
use App\Models\OrganismeDetachement;
use App\Models\Region;
use App\Models\RegistreOperationnel;
use Illuminate\Support\Facades\DB;

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
     * Requête 100% numérique et groupée via les index B-Tree.
     */
    public function calculerMatriceDynamique(): array
    {
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
    }

    /**
     * Calcule dynamiquement la situation pour les 20 régions (19 Régions + 1 District de Bamako)
     * à partir des données réelles de la table `militaires`.
     */
    public function calculerRegionsDynamique(): array
    {
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
                DB::raw('count(*) as total')
            )
            ->whereIn('statut_code', [1, 2, 3, 4])
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('COALESCE(region_id, 1)'), 'grade_id')
            ->get();

        foreach ($counts as $row) {
            $regCode = $regionIdToCode[(int) $row->reg_id] ?? 'R1';
            $col = self::GRADE_ID_TO_REGION_COL[(int) $row->grade_id] ?? 'MDL';
            $tot = (int) $row->total;

            if (isset($regionsData[$regCode])) {
                $regionsData[$regCode][$col] += $tot;
                $regionsData[$regCode]['total'] += $tot;
            }
        }

        return $regionsData;
    }

    /**
     * Calcule les statistiques globales en temps réel à partir de la base de données.
     */
    public function calculerStatsGlobales(): array
    {
        $organismesCount = OrganismeDetachement::where('est_actif', 1)->count();
        $regionsCount = Region::count();

        $totalSpa = DB::table('militaires')
            ->whereIn('statut_code', [1, 2, 3, 4])
            ->whereNull('deleted_at')
            ->count();

        $totalHommes = DB::table('militaires')
            ->whereIn('statut_code', [1, 2, 3, 4])
            ->where(function ($q) {
                $q->where('sexe_code', 1)->orWhereNull('sexe_code');
            })
            ->whereNull('deleted_at')
            ->count();

        $totalDames = DB::table('militaires')
            ->whereIn('statut_code', [1, 2, 3, 4])
            ->where('sexe_code', 2)
            ->whereNull('deleted_at')
            ->count();

        $grmId = OrganismeDetachement::where('code', 'GRM')->value('id') ?? 1;
        $totalDetaches = DB::table('militaires')
            ->whereIn('statut_code', [1, 2, 3, 4])
            ->where(function ($q) use ($grmId) {
                $q->where(function ($sq) use ($grmId) {
                    $sq->whereNotNull('organisme_detachement_id')
                       ->where('organisme_detachement_id', '!=', $grmId);
                })->orWhere('position_actuelle', 'ilike', '%détachement%')
                  ->orWhere('position_id', 26);
            })
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_spa' => $totalSpa,
            'total_hommes' => $totalHommes,
            'total_dames' => $totalDames,
            'total_detaches' => $totalDetaches,
            'total_blesses' => RegistreOperationnel::where('type_registre_code', RegistreOperationnel::TYPE_BLESSE)->count(),
            'total_disparus' => RegistreOperationnel::where('type_registre_code', RegistreOperationnel::TYPE_DISPARU)->count(),
            'total_grands_malades' => RegistreOperationnel::where('type_registre_code', RegistreOperationnel::TYPE_GRAND_MALADE)->count(),
            'total_decedes' => RegistreOperationnel::where('type_registre_code', RegistreOperationnel::TYPE_DECEDE)->count(),
            'total_deserteurs' => RegistreOperationnel::where('type_registre_code', RegistreOperationnel::TYPE_DESERTEUR)->count(),
            'total_organismes' => $organismesCount,
            'total_regions' => $regionsCount,
        ];
    }
}
