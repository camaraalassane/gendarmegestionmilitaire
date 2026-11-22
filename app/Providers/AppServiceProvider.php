<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use App\Models\ActivityLog;

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

        // Journalisation d'audit des connexions / déconnexions / échecs
        Event::listen(Login::class, function ($e) {
            ActivityLog::create([
                'user_id' => $e->user->id,
                'action' => 'Connexion',
                'description' => "Connexion de {$e->user->email}",
                'ip_address' => request()->ip(),
            ]);
            Log::channel('audit')->info('auth.login', ['email' => $e->user->email, 'ip' => request()->ip()]);
        });

        Event::listen(Logout::class, function ($e) {
            if ($e->user) {
                ActivityLog::create([
                    'user_id' => $e->user->id,
                    'action' => 'Déconnexion',
                    'description' => "Déconnexion de {$e->user->email}",
                    'ip_address' => request()->ip(),
                ]);
            }
        });

        Event::listen(Failed::class, function ($e) {
            Log::channel('audit')->warning('auth.failed', [
                'email' => $e->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]);
        });

        foreach ([\App\Models\Region::class, \App\Models\Unite::class, \App\Models\Militaire::class, \App\Models\Affectation::class] as $modele) {
            $vider = function () {
                if (\App\Models\Militaire::$isImporting) {
                    return;
                }
                \App\Http\Controllers\CarteController::flush();   // carte à jour
                \App\Support\CarteRattachement::oublier();          // régions/unités relues au prochain besoin
            };
            $modele::saved($vider);
            $modele::deleted($vider);
        }
    }
}
