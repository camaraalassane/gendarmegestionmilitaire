<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vérifier les alertes (promotions, formations, contrats, retraite) tous les jours à minuit
\Illuminate\Support\Facades\Schedule::command('alertes:check')->daily();

// Bascule automatique en 'retraité' des départs passés (et rétablissement des partants de l'année) : chaque nuit
\Illuminate\Support\Facades\Schedule::command('militaires:sync-retraites')->dailyAt('00:30')->withoutOverlapping();

// Filet de sécurité si aucun worker n'est lancé : traite la file (reconstruction des éligibilités, préchauffage) chaque minute.
// Nécessite le cron `* * * * * php artisan schedule:run`. Avec un worker permanent (supervisor) cette ligne est inoffensive.
\Illuminate\Support\Facades\Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
