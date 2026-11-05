<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vérifier les alertes (promotions, formations, contrats, retraite) tous les jours à minuit
\Illuminate\Support\Facades\Schedule::command('alertes:check')->daily();
