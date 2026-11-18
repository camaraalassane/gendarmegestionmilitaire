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

        // --- Feuille 1 : synthèse ---
        $synthese = [
            ['Périmètre', $portee],
            ['Effectif en activité', $effectifTotal],
            ['   dont actifs', $avecHors('actifs')],
            ['   dont détachés', $avecHors('detaches')],
            ['   dont en mission', $avecHors('missions')],
            ['   dont en stage / formation', $avecHors('stages')],
            ['Absents (congé, mission, stage, formation)', $somme('absents')],
            ['Présents', $effectifTotal - $somme('absents')],
            ['Femmes', $somme('femmes')],
            ['Unités', $nbUnites],
            ['Unités opérationnelles', $nbOp],
            ['Hors effectif : radiés', $avecHors('radies')],
            ["Hors effectif : retraites {$annee}", $avecHors('retraites')],
            ['Hors effectif : déserteurs', $avecHors('deserteurs')],
            ["Hors effectif : décédés {$annee}", $avecHors('decedes')],
        ];
        if (!$filtre) {
            $synthese[] = ['Militaires non rattachés à une région/unité', (int) ($d['stats']['non_rattaches'] ?? 0)];
        }

        // --- Feuille 2 : régions ---
        $lignesRegions = $regionsExport->map(function ($r) use ($effectifTotal) {
            $ops = collect($r['unites'])->where('operationnelle', true)->count();

            return [
                $r['code'], $r['nom'], $r['chef_lieu'] ?? '',
                $r['effectif'],
                $effectifTotal > 0 ? round($r['effectif'] / $effectifTotal * 100, 1) : 0,
                $r['effectif'] - $r['absents'], $r['absents'], $r['femmes'],
                $r['actifs'], $r['detaches'], $r['missions'], $r['stages'],
                count($r['unites']), $ops,
                $r['radies'], $r['retraites'], $r['deserteurs'], $r['decedes'],
            ];
        })->values()->all();

        $totalRegions = ['', 'TOTAL', '', 0, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        foreach ($lignesRegions as $l) {
            foreach ([3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17] as $i) {
                $totalRegions[$i] += $l[$i];
            }
        }
        if (!$filtre && !empty($hr)) {
            $nr = ['', 'Non rattachés', '', (int) $hr['actifs'] + (int) $hr['detaches'] + (int) $hr['missions'] + (int) $hr['stages'],
                0, 0, 0, 0, (int) $hr['actifs'], (int) $hr['detaches'], (int) $hr['missions'], (int) $hr['stages'], 0, 0,
                (int) $hr['radies'], (int) $hr['retraites'], (int) $hr['deserteurs'], (int) $hr['decedes']];
            $nr[5] = $nr[3];
            $nr[4] = $effectifTotal > 0 ? round($nr[3] / $effectifTotal * 100, 1) : 0;
            $lignesRegions[] = $nr;
            foreach ([3, 5, 8, 9, 10, 11, 14, 15, 16, 17] as $i) {
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
                'entetes' => ['Code', 'Région', 'Chef-lieu', 'Effectif', '% du total', 'Présents', 'Absents', 'Femmes', 'Actifs', 'Détachés', 'En mission', 'En stage', 'Unités', 'Unités opérationnelles', 'Radiés', "Retraites {$annee}", 'Déserteurs', "Décédés {$annee}"],
                'lignes' => $lignesRegions, 'total' => $totalRegions, 'colonnes_nombres' => [4, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18]],
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
            ['Effectif en activité', (int) ($s['militaires_actifs'] ?? 0)],
            ['   dont actifs', (int) ($s['actifs_stricts'] ?? 0)],
            ['   dont détachés', (int) ($s['detaches'] ?? 0)],
            ['   dont en mission', (int) ($s['missions'] ?? 0)],
            ['   dont en stage / formation', (int) ($s['stages'] ?? 0)],
            ["Retraites {$annee}", (int) ($s['retraites_n'] ?? 0)],
            ["Proposables à l'avancement {$annee}", (int) ($s['proposables_n'] ?? 0)],
            ["Décédés {$annee}", (int) ($s['decedes_cette_annee'] ?? 0)],
            ['Déserteurs', (int) ($s['deserteurs'] ?? 0)],
            ['Radiés', (int) ($s['radies'] ?? 0)],
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

        $result = CarteController::getMilitairesParStatut($type, $region);

        $suffixeRegion = $result['region_code'] ? strtolower($result['region_code']) : 'mali_entier';
        $filename = "effectif_{$type}_{$suffixeRegion}_" . date('Ymd_His') . ".xlsx";

        return Excel::download(
            new CarteStatutDetailExport($result['titre'], $result['perimetre'], $result['militaires']),
            $filename
        );
    }
}
