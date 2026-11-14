<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use App\Models\Region;
use App\Models\Unite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CarteController extends Controller
{
    /**
     * Affiche la carte opérationnelle et le maillage territorial du Mali.
     */
    public function index(Request $request)
    {
        // 1. Récupérer toutes les régions avec leurs unités
        $regions = Region::with(['unites' => function ($q) {
            $q->orderBy('nom');
        }])
        ->orderByRaw('COALESCE(ordre, 999) ASC')
        ->orderBy('nom')
        ->get();

        // 2. Récupérer les militaires avec leurs statuts
        $militaires = Militaire::select([
            'id', 'matricule', 'mle_grm', 'nom', 'prenom', 'grade_actuel', 
            'sexe', 'statut', 'position_actuelle', 'unite', 'region'
        ])->get();

        // 3. Calculer les effectifs agrégés par région et par unité
        $regionStats = [];
        $uniteStats = [];

        foreach ($regions as $reg) {
            $regionStats[$reg->id] = [
                'total' => 0,
                'presents' => 0,
                'absents' => 0,
                'hommes' => 0,
                'femmes' => 0,
                'en_mission' => 0,
                'en_conge' => 0,
                'en_stage' => 0,
                'autres_absences' => 0,
            ];

            foreach ($reg->unites as $u) {
                $uniteStats[$u->id] = [
                    'total' => 0,
                    'presents' => 0,
                    'absents' => 0,
                ];
            }
        }

        // Affecter chaque militaire à sa région et son unité
        foreach ($militaires as $mil) {
            $matchedRegionId = null;
            $milRegClean = mb_strtolower(trim($mil->region ?? ''));
            $milPosClean = mb_strtolower(trim($mil->position_actuelle ?? ''));

            // Trouver la région correspondante
            foreach ($regions as $reg) {
                if (
                    $reg->id == $mil->region ||
                    mb_strtolower($reg->code) === $milRegClean ||
                    mb_strtolower($reg->nom) === $milRegClean ||
                    (($milRegClean === 'bko' || str_contains($milRegClean, 'region n°1') || str_contains($milRegClean, 'région n°1')) && $reg->code === 'R1') ||
                    (!empty($milRegClean) && str_contains(mb_strtolower($reg->description ?? ''), $milRegClean)) ||
                    (!empty($milPosClean) && (mb_strtolower($reg->nom) === $milPosClean || mb_strtolower($reg->code) === $milPosClean))
                ) {
                    $matchedRegionId = $reg->id;
                    break;
                }
            }

            // Statut de disponibilité
            $statut = mb_strtolower(trim($mil->statut ?? ''));
            $pos = mb_strtolower(trim($mil->position_actuelle ?? ''));
            $isAbsent = (
                str_contains($statut, 'cong') || str_contains($pos, 'cong') ||
                str_contains($statut, 'miss') || str_contains($pos, 'miss') ||
                str_contains($statut, 'stag') || str_contains($pos, 'stag') || str_contains($pos, 'form') ||
                str_contains($statut, 'malad') || str_contains($pos, 'malad') ||
                str_contains($statut, 'susp') || str_contains($pos, 'susp') ||
                str_contains($statut, 'retrait') || str_contains($statut, 'radi') || str_contains($statut, 'décès')
            );

            $isHomme = (mb_strtoupper(trim($mil->sexe ?? '')) === 'M' || mb_strtoupper(trim($mil->sexe ?? '')) === 'H' || empty($mil->sexe));

            if ($matchedRegionId && isset($regionStats[$matchedRegionId])) {
                $regionStats[$matchedRegionId]['total']++;
                if ($isAbsent) {
                    $regionStats[$matchedRegionId]['absents']++;
                    if (str_contains($statut, 'miss') || str_contains($pos, 'miss')) {
                        $regionStats[$matchedRegionId]['en_mission']++;
                    } elseif (str_contains($statut, 'cong') || str_contains($pos, 'cong')) {
                        $regionStats[$matchedRegionId]['en_conge']++;
                    } elseif (str_contains($statut, 'stag') || str_contains($pos, 'stag')) {
                        $regionStats[$matchedRegionId]['en_stage']++;
                    } else {
                        $regionStats[$matchedRegionId]['autres_absences']++;
                    }
                } else {
                    $regionStats[$matchedRegionId]['presents']++;
                }

                if ($isHomme) {
                    $regionStats[$matchedRegionId]['hommes']++;
                } else {
                    $regionStats[$matchedRegionId]['femmes']++;
                }
            }

            // Unité correspondante
            $milUnitClean = mb_strtolower(trim($mil->unite ?? ''));
            if (!empty($milUnitClean)) {
                foreach ($regions as $reg) {
                    foreach ($reg->unites as $u) {
                        if (
                            $u->id == $mil->unite ||
                            mb_strtolower($u->nom) === $milUnitClean ||
                            (!empty($u->code) && mb_strtolower($u->code) === $milUnitClean) ||
                            str_contains(mb_strtolower($u->nom), $milUnitClean)
                        ) {
                            if (isset($uniteStats[$u->id])) {
                                $uniteStats[$u->id]['total']++;
                                if ($isAbsent) {
                                    $uniteStats[$u->id]['absents']++;
                                } else {
                                    $uniteStats[$u->id]['presents']++;
                                }
                            }
                            break 2;
                        }
                    }
                }
            }
        }

        // Formatage pour le frontend
        $regionsFormatted = $regions->map(function ($reg) use ($regionStats, $uniteStats) {
            $stats = $regionStats[$reg->id] ?? [
                'total' => 0, 'presents' => 0, 'absents' => 0,
                'hommes' => 0, 'femmes' => 0, 'en_mission' => 0,
                'en_conge' => 0, 'en_stage' => 0, 'autres_absences' => 0
            ];

            $tauxPresence = $stats['total'] > 0 
                ? round(($stats['presents'] / $stats['total']) * 100, 1) 
                : 100;

            return [
                'id' => $reg->id,
                'code' => $reg->code,
                'nom' => $reg->nom,
                'description' => $reg->description,
                'latitude' => $reg->latitude !== null ? (float) $reg->latitude : null,
                'longitude' => $reg->longitude !== null ? (float) $reg->longitude : null,
                'chef_lieu' => $reg->chef_lieu,
                'ordre' => $reg->ordre,
                'is_central' => false,
                'stats' => array_merge($stats, ['taux_presence' => $tauxPresence]),
                'unites_count' => $reg->unites->count(),
                'unites_operationnelles_count' => $reg->unites->where('est_operationnelle', true)->count(),
                'unites' => $reg->unites->map(function ($u) use ($uniteStats, $reg) {
                    $uStat = $uniteStats[$u->id] ?? ['total' => 0, 'presents' => 0, 'absents' => 0];
                    return [
                        'id' => $u->id,
                        'region_id' => $reg->id,
                        'region_code' => $reg->code,
                        'nom' => $u->nom,
                        'code' => $u->code,
                        'type' => $u->type,
                        'compagnie' => $u->compagnie,
                        'groupement' => $u->groupement,
                        'latitude' => $u->latitude !== null ? (float) $u->latitude : null,
                        'longitude' => $u->longitude !== null ? (float) $u->longitude : null,
                        'ville' => $u->ville,
                        'est_operationnelle' => $u->est_operationnelle,
                        'stats' => $uStat,
                    ];
                }),
            ];
        });

        // Statistiques globales nationales
        $totalEffectif = $militaires->count();
        $totalPresents = $militaires->filter(fn($m) => !str_contains(mb_strtolower($m->statut ?? ''), 'retrait') && !str_contains(mb_strtolower($m->position_actuelle ?? ''), 'cong'))->count();

        $statsGlobales = [
            'total_militaires' => $totalEffectif,
            'total_regions' => $regions->count(),
            'total_unites' => Unite::count(),
            'unites_operationnelles' => Unite::where('est_operationnelle', true)->count(),
            'unites_non_operationnelles' => Unite::where('est_operationnelle', false)->count(),
        ];

        return Inertia::render('Carte/Index', [
            'regions' => $regionsFormatted,
            'statsGlobales' => $statsGlobales,
        ]);
    }
}
