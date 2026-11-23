<?php

namespace App\Http\Controllers;

use App\Exports\StatistiquesExport;
use App\Exports\CarteStatutDetailExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export Excel des statistiques de la carte et du tableau de bord.
 * Les chiffres viennent exactement des mêmes calculs (et du même cache) que les écrans : ils sont donc identiques.
 */
class StatistiquesExportController extends Controller
{
    /** GET /carte/export[?region=R4] */
    public function carte(Request $request)
    {
        $d = app(CarteController::class)->donnees();
        $annee = $d['stats']['annee'] ?? now()->year;
        $hr = $d['stats']['hors_region'] ?? [];
        $regions = collect($d['regions']);

        $code = $request->query('region');
        $filtre = $code ? $regions->firstWhere('code', $code) : null;
        $regionsExport = $filtre ? collect([$filtre]) : $regions;
        $portee = $filtre ? $filtre['nom'] : 'Mali entier';

        $somme = fn (string $k) => (int) $regionsExport->sum($k);
        $avecHors = fn (string $k) => $somme($k) + ($filtre ? 0 : (int) ($hr[$k] ?? 0));
        $nbUnites = (int) $regionsExport->sum(fn ($r) => count($r['unites']));
        $nbOp = (int) $regionsExport->sum(fn ($r) => collect($r['unites'])->where('operationnelle', true)->count());
        $effectifTotal = $avecHors('actifs') + $avecHors('detaches') + $avecHors('missions') + $avecHors('stages');
        $totalFemmes = $somme('femmes');
        $totalHommes = max(0, $effectifTotal - $totalFemmes);

        $regCounts = \App\Models\RegistreOperationnel::selectRaw("
            COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_BLESSE . " THEN 1 END) as blesses,
            COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_GRAND_MALADE . " THEN 1 END) as grands_malades,
            COUNT(CASE WHEN type_registre_code = " . \App\Models\RegistreOperationnel::TYPE_DISPARU . " THEN 1 END) as disparus
        ")->first();

        // --- Feuille 1 : synthèse ---
        $synthese = [
            ['Périmètre géographique', $portee],
            ['Effectif total en activité', $effectifTotal],
            ['Militaires en service actif (au poste)', $avecHors('actifs')],
            ['Militaires en détachement', $avecHors('detaches')],
            ['Militaires en mission opérationnelle', $avecHors('missions')],
            ['Militaires en stage / formation', $avecHors('stages')],
            ['Militaires présents au service', $effectifTotal - $somme('absents')],
            ['Militaires absents (congé, mission, stage)', $somme('absents')],
            ['Effectif masculin (Hommes)', $totalHommes],
            ['Effectif féminin (Femmes)', $totalFemmes],
            ['Nombre total d\'unités', $nbUnites],
            ['Unités opérationnelles actives', $nbOp],
            ['Personnels radiés des cadres', $avecHors('radies')],
            ["Départs à la retraite {$annee}", $avecHors('retraites')],
            ['Militaires déclarés déserteurs', $avecHors('deserteurs')],
            ["Militaires décédés en {$annee}", $avecHors('decedes')],
            ['Blessés de guerre / OPEX (Registre Opérationnel)', (int) ($regCounts->blesses ?? 0)],
            ['Grands malades / Non mobilisables (Santé)', (int) ($regCounts->grands_malades ?? 0)],
            ['Portés disparus et otages (Suivi)', (int) ($regCounts->disparus ?? 0)],
        ];
        if (!$filtre) {
            $synthese[] = ['Militaires en attente d\'affectation régionale', (int) ($d['stats']['non_rattaches'] ?? 0)];
        }

        // --- Feuille 2 : régions ---
        $lignesRegions = $regionsExport->map(function ($r) use ($effectifTotal) {
            $ops = collect($r['unites'])->where('operationnelle', true)->count();
            $femmes = (int) ($r['femmes'] ?? 0);
            $hommes = max(0, (int) $r['effectif'] - $femmes);

            return [
                $r['code'], $r['nom'], $r['chef_lieu'] ?? '',
                $r['effectif'],
                $effectifTotal > 0 ? round($r['effectif'] / $effectifTotal * 100, 1) : 0,
                $r['effectif'] - $r['absents'], $r['absents'],
                $hommes, $femmes,
                $r['actifs'], $r['detaches'], $r['missions'], $r['stages'],
                count($r['unites']), $ops,
                $r['radies'], $r['retraites'], $r['deserteurs'], $r['decedes'],
            ];
        })->values()->all();

        $totalRegions = ['', 'TOTAL', '', 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        foreach ($lignesRegions as $l) {
            foreach ([3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18] as $i) {
                $totalRegions[$i] += $l[$i];
            }
        }
        if (!$filtre && !empty($hr)) {
            $nrActifs = (int) $hr['actifs'] + (int) $hr['detaches'] + (int) $hr['missions'] + (int) $hr['stages'];
            $nrFemmes = (int) ($hr['femmes'] ?? 0);
            $nrHommes = max(0, $nrActifs - $nrFemmes);
            $nr = [
                '', 'Non rattachés', '', $nrActifs,
                $effectifTotal > 0 ? round($nrActifs / $effectifTotal * 100, 1) : 0,
                $nrActifs, 0,
                $nrHommes, $nrFemmes,
                (int) $hr['actifs'], (int) $hr['detaches'], (int) $hr['missions'], (int) $hr['stages'],
                0, 0,
                (int) $hr['radies'], (int) $hr['retraites'], (int) $hr['deserteurs'], (int) $hr['decedes']
            ];
            $lignesRegions[] = $nr;
            foreach ([3, 5, 7, 8, 9, 10, 11, 12, 15, 16, 17, 18] as $i) {
                $totalRegions[$i] += $nr[$i];
            }
            $totalRegions[4] = 100;
        }

        // --- Feuille 3 : unités ---
        $lignesUnites = [];
        foreach ($regionsExport as $r) {
            foreach (collect($r['unites'])->sortByDesc('effectif') as $u) {
                $lignesUnites[] = [
                    $r['nom'], $u['nom'], $u['type'] ?? '', $u['ville'] ?? '',
                    $u['operationnelle'] ? 'Oui' : 'Non', $u['effectif'],
                    $u['lat'] ?? '', $u['lon'] ?? '',
                ];
            }
        }

        $feuilles = [
            ['titre' => 'Synthèse', 'intitule' => "Carte opérationnelle : synthèse ({$portee})", 'entetes' => ['Indicateur', 'Valeur'], 'lignes' => $synthese, 'colonnes_nombres' => [2]],
            ['titre' => 'Régions', 'intitule' => 'Carte opérationnelle : effectifs par région',
                'entetes' => ['Code', 'Région', 'Chef-lieu', 'Effectif', '% du total', 'Présents', 'Absents', 'Hommes', 'Femmes', 'Actifs', 'Détachés', 'En mission', 'En stage', 'Unités', 'Unités opérationnelles', 'Radiés', "Retraites {$annee}", 'Déserteurs', "Décédés {$annee}"],
                'lignes' => $lignesRegions, 'total' => $totalRegions, 'colonnes_nombres' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19]],
            ['titre' => 'Unités', 'intitule' => 'Carte opérationnelle : militaires par unité',
                'entetes' => ['Région', 'Unité', 'Type', 'Ville', 'Opérationnelle', 'Effectif', 'Latitude', 'Longitude'],
                'lignes' => $lignesUnites, 'colonnes_nombres' => [6]],
        ];

        $nom = 'statistiques_carte_' . ($filtre ? \Illuminate\Support\Str::slug($filtre['nom']) . '_' : '') . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new StatistiquesExport($feuilles), $nom);
    }

    /** GET /dashboard/export-statistiques */
    public function dashboard()
    {
        $dash = app(DashboardController::class);
        $s = $dash->statistiquesResume();
        $c = $dash->donneesGraphiques();
        $annee = $s['annee'] ?? now()->year;

        $tableau = function (string $titre, string $intitule, string $premiereColonne, array $items) {
            $total = array_sum(array_map(fn ($i) => (int) $i['value'], $items));
            $lignes = array_map(fn ($i) => [
                $i['label'], (int) $i['value'], $total > 0 ? round($i['value'] / $total * 100, 1) : 0,
            ], $items);

            return [
                'titre' => $titre, 'intitule' => $intitule,
                'entetes' => [$premiereColonne, 'Effectif', '% du total'],
                'lignes' => $lignes, 'total' => ['TOTAL', $total, 100], 'colonnes_nombres' => [2, 3],
            ];
        };

        $synthese = [
            ['Effectif total en activité', (int) ($s['militaires_actifs'] ?? 0)],
            ['Militaires en service actif (au poste)', (int) ($s['actifs_stricts'] ?? 0)],
            ['Militaires en détachement', (int) ($s['detaches'] ?? 0)],
            ['Militaires en mission opérationnelle', (int) ($s['missions'] ?? 0)],
            ['Militaires en stage / formation', (int) ($s['stages'] ?? 0)],
            ["Départs à la retraite {$annee}", (int) ($s['retraites_n'] ?? 0)],
            ["Militaires éligibles à l'avancement {$annee}", (int) ($s['proposables_n'] ?? 0)],
            ["Militaires décédés en {$annee}", (int) ($s['decedes_cette_annee'] ?? 0)],
            ['Militaires déclarés déserteurs', (int) ($s['deserteurs'] ?? 0)],
            ['Personnels radiés des cadres', (int) ($s['radies'] ?? 0)],
            ['Blessés de guerre / OPEX (Registre Opérationnel)', (int) ($s['total_blesses'] ?? 0)],
            ['Grands malades / Non mobilisables (Santé)', (int) ($s['total_grands_malades'] ?? 0)],
            ['Portés disparus et otages (Suivi)', (int) ($s['total_disparus'] ?? 0)],
        ];

        $feuilles = [
            ['titre' => 'Synthèse', 'intitule' => "Tableau de bord : indicateurs clés {$annee}", 'entetes' => ['Indicateur', 'Valeur'], 'lignes' => $synthese, 'colonnes_nombres' => [2]],
            $tableau('Statuts', 'Situation administrative de tous les personnels', 'Statut', $c['statuts'] ?? []),
            $tableau('Positions', 'Effectif en activité par position', 'Position', $c['positions'] ?? []),
            $tableau('Régions', 'Effectif en activité par région', 'Région', $c['regions'] ?? []),
            $tableau('Catégories', 'Effectif en activité par catégorie statutaire', 'Catégorie', $c['categories'] ?? []),
            $tableau('Grades', 'Effectif en activité par grade', 'Grade', $c['grades'] ?? []),
        ];

        return Excel::download(new StatistiquesExport($feuilles), 'statistiques_dashboard_' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export Excel détaillé des militaires selon le statut cliqué et la région active
     * GET /carte/export-statut
     */
    public function exportStatut(Request $request)
    {
        $type = $request->query('type', 'actifs');
        $region = $request->query('region');

        $info = CarteController::buildQueryStatut($type, $region);

        $suffixeRegion = $info['region_code'] ? strtolower($info['region_code']) : 'mali_entier';
        $filename = "effectif_{$type}_{$suffixeRegion}_" . date('Ymd_His') . ".xlsx";

        return Excel::download(
            new CarteStatutDetailExport($info['query'], $info['titre'], $info['perimetre'], $info['retraitesMap']),
            $filename
        );
    }

    /**
     * Export Excel de la situation opérationnelle des théâtres d'opérations et unités d'appui
     * GET /carte/export-theatres[?theatre=DET-SAYE]
     */
    public function theatres(Request $request)
    {
        $d = app(CarteController::class)->donnees();
        $theatresCollection = collect($d['theatres'] ?? []);

        $theatreFiltre = $request->query('theatre');
        if ($theatreFiltre) {
            $theatresExport = $theatresCollection->where('code', $theatreFiltre);
            if ($theatresExport->isEmpty()) {
                $theatresExport = $theatresCollection;
                $theatreFiltre = null;
            }
        } else {
            $theatresExport = $theatresCollection;
        }

        // --- Feuille 1 : Synthèse des Théâtres ---
        $lignesTheatres = [];
        $totalGend = 0;
        $totalAppui = 0;
        $totalGlobal = 0;
        $totalActifs = 0;
        $totalBlesses = 0;
        $totalOfficiers = 0;
        $totalSousOff = 0;
        $totalMdr = 0;

        foreach ($theatresExport as $th) {
            $gend = (int) ($th['effectif_gendarmerie'] ?? $th['effectif'] ?? 0);
            $appui = (int) ($th['effectif_appui'] ?? 0);
            $global = (int) ($th['effectif_global'] ?? ($gend + $appui));
            $actifs = (int) ($th['actifs'] ?? 0);
            $blesses = (int) ($th['blesses'] ?? 0);
            $off = (int) ($th['officiers'] ?? 0);
            $soff = (int) ($th['sous_officiers'] ?? 0);
            $mdr = (int) ($th['mdr'] ?? 0);

            $appuisList = collect($th['unites_appui'] ?? [])
                ->map(fn($u) => ($u['code'] ?? '') . ': ' . ($u['effectif'] ?? 0))
                ->implode(' • ');

            $regionsList = implode(', ', $th['regions_noms'] ?? $th['regions_codes'] ?? []);

            $lignesTheatres[] = [
                $th['code'] ?? '',
                $th['nom'] ?? '',
                $th['pc_lieu'] ?? '',
                $regionsList,
                $gend,
                $appui,
                $global,
                $actifs,
                $blesses,
                $off,
                $soff,
                $mdr,
                $appuisList ?: 'Aucun appui',
            ];

            $totalGend += $gend;
            $totalAppui += $appui;
            $totalGlobal += $global;
            $totalActifs += $actifs;
            $totalBlesses += $blesses;
            $totalOfficiers += $off;
            $totalSousOff += $soff;
            $totalMdr += $mdr;
        }

        $totalLigne = [
            'TOTAL', '', '', '',
            $totalGend, $totalAppui, $totalGlobal,
            $totalActifs, $totalBlesses,
            $totalOfficiers, $totalSousOff, $totalMdr,
            '',
        ];

        // --- Feuille 2 : Détail des Unités d'Appui Interarmées ---
        $lignesAppui = [];
        $totalAppuiDetails = 0;

        foreach ($theatresExport as $th) {
            foreach (($th['unites_appui'] ?? []) as $ua) {
                $eff = (int) ($ua['effectif'] ?? 0);
                $lignesAppui[] = [
                    $th['code'] ?? '',
                    $th['nom'] ?? '',
                    $th['pc_lieu'] ?? '',
                    $ua['code'] ?? '',
                    $ua['nom'] ?? '',
                    $eff,
                    $ua['chef_detachement'] ?? '-',
                    $ua['observations'] ?? '-',
                ];
                $totalAppuiDetails += $eff;
            }
        }

        $totalAppuiLigne = [
            'TOTAL', '', '', '', '',
            $totalAppuiDetails,
            '', '',
        ];

        $titreSynthese = $theatreFiltre ? "Théâtre {$theatreFiltre}" : "Dispositif Théâtres";
        $intituleSynthese = $theatreFiltre
            ? "Situation Opérationnelle : {$theatreFiltre} - " . ($theatresExport->first()['nom'] ?? '')
            : "Dispositif Opérationnel des Théâtres d'Opérations & Appuis Interarmées (FAMa)";

        $feuilles = [
            [
                'titre' => $titreSynthese,
                'intitule' => $intituleSynthese,
                'entetes' => [
                    'Code', 'Théâtre d\'Opérations', 'PC / Emplacement', 'Emprise Régionale',
                    'Gendarmerie', 'Appuis FAMa', 'Total Forces',
                    'Actifs', 'Blessés', 'Officiers', 'Sous-Officiers', 'MDR',
                    'Détail des Appuis',
                ],
                'lignes' => $lignesTheatres,
                'total' => $totalLigne,
                'colonnes_nombres' => [5, 6, 7, 8, 9, 10, 11, 12],
            ],
            [
                'titre' => 'Unités d\'Appui FAMa',
                'intitule' => 'Détail des Unités d\'Appui Interarmées Déployées sur les Théâtres',
                'entetes' => [
                    'Code Théâtre', 'Nom Théâtre', 'PC', 'Arme / Spécialité',
                    'Désignation Complète', 'Effectif Déployé', 'Chef Détachement', 'Missions / Observations',
                ],
                'lignes' => $lignesAppui,
                'total' => $totalAppuiLigne,
                'colonnes_nombres' => [6],
            ],
        ];

        $prefix = $theatreFiltre ? 'theatre_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $theatreFiltre)) . '_' : 'theatres_operations_';
        $nom = $prefix . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new StatistiquesExport($feuilles), $nom);
    }
}
