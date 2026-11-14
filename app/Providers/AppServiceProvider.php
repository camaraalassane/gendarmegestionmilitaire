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

        foreach ([\App\Models\Region::class, \App\Models\Unite::class] as $modele) {
            $modele::saved(fn () => \App\Http\Controllers\CarteController::flush());
            $modele::deleted(fn () => \App\Http\Controllers\CarteController::flush());
        }
    }
}
