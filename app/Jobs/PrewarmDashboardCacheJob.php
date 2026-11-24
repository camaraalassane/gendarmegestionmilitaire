<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PrewarmDashboardCacheJob implements ShouldQueue
{
    use Queueable;

    /**
     * Nombre de tentatives en cas d'erreur.
     */
    public int $tries = 2;

    /**
     * Délai d'attente avant abandon.
     */
    public int $timeout = 60;

    /**
     * Exécute le pré-calcul des métriques lourdes en arrière-plan.
     */
    public function handle(): void
    {
        try {
            $annee = (int) date('Y');
            $v = \App\Models\Militaire::getCacheVersion();

            // 1. Pré-calcul Dashboard
            $dashboardCtrl = app(\App\Http\Controllers\DashboardController::class);
            $dashboardCtrl->statistiquesResume($annee, $v);
            $dashboardCtrl->donneesGraphiques($v);

            // 2. Pré-calcul Éligibilités
            $eligibiliteCtrl = app(\App\Http\Controllers\EligibiliteController::class);
            $eligibiliteCtrl->getAllComputedData();

            // 3. Pré-calcul Avancements session courante
            $avancementCtrl = app(\App\Http\Controllers\AvancementController::class);
            $avancementCtrl->buildCandidatesList($annee);

            Log::info("PrewarmDashboardCacheJob : Caches lourds (v{$v}) pré-calculés avec succès en tâche de fond.");
        } catch (\Throwable $e) {
            Log::warning("PrewarmDashboardCacheJob : Erreur lors du pré-calcul en arrière-plan : " . $e->getMessage());
        }
    }
}
