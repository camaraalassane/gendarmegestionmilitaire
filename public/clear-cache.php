<?php
/**
 * Script autonome de vidage du cache OPcache (PHP-FPM) et des caches Laravel.
 * Accessible directement via navigateur ou curl, SANS mot de passe sudo.
 */
header('Content-Type: text/html; charset=utf-8');

$logs = [];

// 1. Reset OPcache au niveau PHP-FPM
if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    $logs[] = [
        'type' => $ok ? 'success' : 'error',
        'title' => 'OPcache (PHP-FPM en mémoire RAM)',
        'message' => $ok ? 'Réinitialisation OPcache réussie : tous les fichiers PHP sont rechargés depuis le disque.' : 'Échec de la réinitialisation OPcache.',
    ];
} else {
    $logs[] = [
        'type' => 'info',
        'title' => 'OPcache',
        'message' => 'OPcache n\'est pas activé sur ce serveur.',
    ];
}

// 2. Initialiser le noyau Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 3. Vider le cache de l'application
try {
    \Illuminate\Support\Facades\Cache::flush();
    $logs[] = [
        'type' => 'success',
        'title' => 'Cache applicatif (Laravel Cache)',
        'message' => 'Cache applicatif vidé avec succès.',
    ];
} catch (\Throwable $e) {
    $logs[] = [
        'type' => 'error',
        'title' => 'Cache applicatif',
        'message' => $e->getMessage(),
    ];
}

// 4. Exécuter optimize:clear
try {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    $artisanClear = trim(\Illuminate\Support\Facades\Artisan::output());
    $logs[] = [
        'type' => 'success',
        'title' => 'Artisan optimize:clear',
        'message' => $artisanClear ?: 'Caches config, routes, vues nettoyés.',
    ];
} catch (\Throwable $e) {
    $logs[] = [
        'type' => 'error',
        'title' => 'Artisan optimize:clear',
        'message' => $e->getMessage(),
    ];
}

// 5. Synchronisation des statuts de retraite
try {
    \Illuminate\Support\Facades\Artisan::call('militaires:sync-retraites');
    $syncOutput = trim(\Illuminate\Support\Facades\Artisan::output());
    $logs[] = [
        'type' => 'success',
        'title' => 'Synchronisation des retraites',
        'message' => $syncOutput ?: 'Statuts synchronisés.',
    ];
} catch (\Throwable $e) {
    $logs[] = [
        'type' => 'info',
        'title' => 'Synchronisation des retraites',
        'message' => $e->getMessage(),
    ];
}

// 6. Statistiques réelles depuis la base de données
$totalMilitaires = \App\Models\Militaire::count();
$militairesActifs = \App\Models\Militaire::where('statut', 'LIKE', 'actif%')->count();
$militairesRetraites = \App\Models\Militaire::where('statut', 'LIKE', 'retrait%')->count();
$militairesDecedes = \App\Models\Militaire::where(function($q) { $q->where('statut', 'LIKE', 'd%c%d%')->orWhere('statut', 'LIKE', 'mort%'); })->count();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage Caches & OPcache - Gendarmerie</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 2rem; }
        .card { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); padding: 2rem; }
        h1 { font-size: 1.5rem; color: #0369a1; margin-top: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin: 1.5rem 0; }
        .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-align: center; }
        .stat-val { font-size: 1.75rem; font-weight: 800; color: #0284c7; }
        .stat-lbl { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; margin-top: 0.25rem; }
        .log-item { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 0.5rem; font-size: 0.875rem; }
        .log-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .log-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .log-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .btn { display: inline-block; background: #0284c7; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 1rem; }
        .btn:hover { background: #0369a1; }
    </style>
</head>
<body>
    <div class="card">
        <h1>✅ Caches & OPcache Réinitialisés</h1>
        <p>Les fichiers PHP en mémoire (OPcache) et les caches de Laravel ont été nettoyés avec succès.</p>

        <div class="grid">
            <div class="stat-box">
                <div class="stat-val"><?= $totalMilitaires ?></div>
                <div class="stat-lbl">Total</div>
            </div>
            <div class="stat-box">
                <div class="stat-val" style="color: #059669;"><?= $militairesActifs ?></div>
                <div class="stat-lbl">Actifs</div>
            </div>
            <div class="stat-box">
                <div class="stat-val" style="color: #475569;"><?= $militairesRetraites ?></div>
                <div class="stat-lbl">Retraités</div>
            </div>
            <div class="stat-box">
                <div class="stat-val" style="color: #dc2626;"><?= $militairesDecedes ?></div>
                <div class="stat-lbl">Décédés</div>
            </div>
        </div>

        <h3>Détails des opérations :</h3>
        <?php foreach ($logs as $log): ?>
            <div class="log-item log-<?= $log['type'] ?>">
                <strong><?= htmlspecialchars($log['title']) ?> :</strong> <?= htmlspecialchars($log['message']) ?>
            </div>
        <?php endforeach; ?>

        <a href="/dashboard" class="btn">← Retour au Tableau de bord</a>
    </div>
</body>
</html>
