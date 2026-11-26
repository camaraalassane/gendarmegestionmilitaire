<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Militaire;
use App\Models\Grade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SyncRetraites extends Command
{
    protected $signature = 'militaires:sync-retraites';
    protected $description = 'Synchronise les statuts retraite : bascule les départs passés (2025, 2024, ...) en statut retraité, et rétablit les partants de 2026 en statut actif jusqu\'au 31/12';

    public function handle(): int
    {
        $this->info("=== Synchronisation des statuts de retraite ===");
        
        $anneeCourante = (int) Carbon::now()->year; // 2026

        $basculesEnRetraite = 0;
        $retablisEnActif = 0;
        $dejaRetraites = 0;
        $actifs2026 = 0;

        foreach (Militaire::lazyById(1000) as $militaire) {
            // Un militaire en otage reste TOUJOURS en activité / maintenu au fichier jusqu'à sa découverte.
            // Il ne doit JAMAIS être basculé en retraité.
            if ($militaire->isOtage()) {
                if ($militaire->statut !== 'actif' || $militaire->statut_code !== 1) {
                    $militaire->statut = 'actif';
                    $militaire->statut_code = 1;
                    $militaire->save();
                }
                continue;
            }

            if (!$militaire->date_naissance || !$militaire->grade_actuel) {
                continue;
            }

            $ageRetraite = Grade::getRetraiteForGrade($militaire->grade_actuel);
            if (!$ageRetraite) {
                continue;
            }

            $anneeRetraite = (int) (Carbon::parse($militaire->date_naissance)->year + $ageRetraite);

            // 1. Départs passés (2025, 2024, 2023...) : doivent avoir le statut 'retraité'
            if ($anneeRetraite < $anneeCourante) {
                if (in_array((int) $militaire->statut_code, \App\Support\Effectif::CODES_ACTIVITE, true)) {
                    $militaire->statut = 'retraité';
                    $militaire->statut_code = 7;
                    $militaire->save();
                    $basculesEnRetraite++;
                    $this->line(" -> [PASSÉ -> RETRAITÉ] {$militaire->matricule} {$militaire->nom} {$militaire->prenom} ({$militaire->grade_actuel}, retraite échue en {$anneeRetraite})");
                } else {
                    $dejaRetraites++;
                }
            }
            // 2. Départs de cette année (2026) ou futurs : NE DOIVENT PAS avoir le statut 'retraité' avant le 31/12
            else {
                if (str_starts_with(mb_strtolower($militaire->statut ?? ''), 'retrait')) {
                    $militaire->statut = 'actif';
                    $militaire->save();
                    $retablisEnActif++;
                    $this->line(" -> [ANNÉE {$anneeRetraite} -> RÉTABLI ACTIF] {$militaire->matricule} {$militaire->nom} {$militaire->prenom} ({$militaire->grade_actuel}, retraite au 31/12/{$anneeRetraite})");
                }

                if ($anneeRetraite === $anneeCourante) {
                    $actifs2026++;
                }
            }
        }

        // Vider tous les caches pour actualiser instantanément le Dashboard et la liste Militaires
        // Pas de Cache::flush() : il remettrait à zéro le compteur de version et les verrous. On invalide par version.
        // Seulement si des fiches ont changé : sinon on garde les caches chauds (premier chargement rapide)
        if ($basculesEnRetraite + $retablisEnActif > 0) {
            Militaire::incrementCacheVersion();
        }

        $this->newLine();
        $this->info("Bilan de la synchronisation :");
        $this->info("- Militaires de 2026 réintégrés en statut 'actif' : {$retablisEnActif}");
        $this->info("- Anciens militaires (<= 2025) basculés en statut 'retraité' : {$basculesEnRetraite}");
        $this->info("- Total retraités archivés (<= 2025) : " . ($dejaRetraites + $basculesEnRetraite));
        $this->info("- Total prévisions Retraites N ({$anneeCourante}) : {$actifs2026}");

        return Command::SUCCESS;
    }
}
