<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->symbols()->uncompromised()
                : $rule;
        });

        foreach ([\App\Models\Region::class, \App\Models\Unite::class, \App\Models\Militaire::class, \App\Models\Affectation::class] as $modele) {
            $vider = function () {
                \App\Http\Controllers\CarteController::flush();   // carte à jour
                \App\Support\CarteRattachement::oublier();          // régions/unités relues au prochain besoin
            };
            $modele::saved($vider);
            $modele::deleted($vider);
        }
    }
}
