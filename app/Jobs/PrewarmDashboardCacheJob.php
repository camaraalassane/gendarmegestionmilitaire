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

            // 1. Pré-calcul Dashboard et mise à jour immédiate du Stale-While-Revalidate
            $dashboardCtrl = app(\App\Http\Controllers\DashboardController::class);
            $stats = $dashboardCtrl->calculerStatistiquesResume($annee, $v);
            \Illuminate\Support\Facades\Cache::put("v{$v}:dashboard:statistiques_summary", $stats, 86400);
            \Illuminate\Support\Facades\Cache::put('dashboard:statistiques_summary:last_ready', $stats, 86400);

            $charts = $dashboardCtrl->calculerDonneesGraphiques($v);
            \Illuminate\Support\Facades\Cache::put("v{$v}:dashboard:charts_data", $charts, 86400);
            \Illuminate\Support\Facades\Cache::put('dashboard:charts_data:last_ready', $charts, 86400);

            // 2. Pré-calcul Éligibilités
            $eligibiliteCtrl = app(\App\Http\Controllers\EligibiliteController::class);
            $eligibiliteCtrl->assurerCalcul();

            // Avancements : paginés en SQL depuis eligibilites_calculees, rien à pré-charger (évite de charger tous les candidats en mémoire).

            Log::info("PrewarmDashboardCacheJob : Caches lourds (v{$v}) pré-calculés avec succès en tâche de fond.");
        } catch (\Throwable $e) {
            Log::error("PrewarmDashboardCacheJob : échec du pré-calcul : " . $e->getMessage(), ['exception' => $e]);
            \Illuminate\Support\Facades\Cache::forget('prewarm:dashboard:lock'); // permet une nouvelle tentative
        }
    }
}
