<?php

namespace App\Console\Commands;

use App\Http\Controllers\CarteController;
use App\Models\Grade;
use App\Models\Militaire;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remplit une base DE TEST avec des militaires fictifs pour mesurer les temps de réponse à grande échelle.
 * Tous les matricules commencent par "LOAD-" : --purge les supprime sans toucher aux vraies données.
 *
 *   php artisan charge:militaires 300000
 *   php artisan charge:militaires --purge
 */
class ChargeMilitairesCommand extends Command
{
    protected $signature = 'charge:militaires {n=300000 : nombre de militaires fictifs} {--purge : supprime les militaires LOAD-*}';
    protected $description = 'Génère des militaires fictifs (LOAD-*) pour un test de charge';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusé en production.');
            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $this->info("Suppression des militaires fictifs (LOAD-*)...");
            $n = DB::table('militaires')->where('matricule', 'like', 'LOAD-%')->delete();
            Militaire::incrementCacheVersion();
            CarteController::flush();
            $this->info("{$n} militaires fictifs supprimés avec succès et caches purgés.");
            return self::SUCCESS;
        }

        $total = (int) $this->argument('n');
        $grades = Grade::select('id', 'nom_grade', 'ordre')->get();
        if ($grades->isEmpty()) {
            $this->error('Aucun grade en base : lancez d\'abord les seeders.');
            return self::FAILURE;
        }

        $regionIds   = DB::table('regions')->pluck('id')->all() ?: [null];
        $uniteIds    = DB::table('unites')->pluck('id')->all() ?: [null];
        $positionIds = DB::table('positions')->pluck('id')->all() ?: [null];

        $noms = ['TRAORE', 'COULIBALY', 'DIARRA', 'KEITA', 'SIDIBE', 'CAMARA', 'DEMBELE', 'TOURE', 'KONATE', 'SANOGO', 'DIALLO', 'MAIGA', 'CISSE', 'KANE'];
        $prenoms = ['Mamadou', 'Alassane', 'Oumar', 'Seydou', 'Fatoumata', 'Aminata', 'Ibrahim', 'Moussa', 'Boubacar', 'Adama', 'Kadiatou', 'Mariam', 'Bakary'];
        $statuts = [1 => 'actif', 2 => 'détaché', 3 => 'mission', 4 => 'stage', 7 => 'retraité'];
        $poids   = [1 => 90, 2 => 3, 3 => 3, 4 => 2, 7 => 2]; // % approximatifs

        $this->info("Début de l'insertion de {$total} militaires fictifs...");
        $debut = microtime(true);
        $batch = [];
        $now = now();

        for ($i = 1; $i <= $total; $i++) {
            $g = $grades->random();
            $nom = $noms[array_rand($noms)];
            $prenom = $prenoms[array_rand($prenoms)];
            $isFemme = in_array($prenom, ['Fatoumata', 'Aminata', 'Kadiatou', 'Mariam']);

            $r = random_int(1, 100);
            $code = 1;
            $cum = 0;
            foreach ($poids as $c => $p) {
                $cum += $p;
                if ($r <= $cum) {
                    $code = $c;
                    break;
                }
            }

            $matricule = 'LOAD-' . str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            $naissance = now()->subYears(random_int(20, 58))->subDays(random_int(0, 364))->format('Y-m-d');

            $batch[] = [
                'matricule' => $matricule,
                'nom' => $nom,
                'prenom' => $prenom,
                'sexe' => $isFemme ? 'Féminin' : 'Masculin',
                'sexe_code' => $isFemme ? 2 : 1,
                'date_naissance' => $naissance,
                'date_entree_service' => now()->subYears(random_int(1, 30))->format('Y-m-d'),
                'grade_actuel' => $g->nom_grade,
                'grade_id' => $g->id,
                'grade_ordre' => $g->ordre,
                'statut' => $statuts[$code],
                'statut_code' => $code,
                'region_id' => $regionIds[array_rand($regionIds)],
                'unite_id' => $uniteIds[array_rand($uniteIds)],
                'position_id' => $positionIds[array_rand($positionIds)],
                'recherche' => Str::lower("{$matricule} {$nom} {$prenom} {$g->nom_grade}"),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 2000) {
                DB::table('militaires')->insert($batch);
                $batch = [];
                if ($i % 20000 === 0) {
                    $this->line("  Progression : {$i} / {$total}");
                }
            }
        }

        if (!empty($batch)) {
            DB::table('militaires')->insert($batch);
        }

        try {
            DB::statement('ANALYZE militaires');
        } catch (\Throwable $e) {}

        Militaire::incrementCacheVersion();
        CarteController::flush();

        $duree = round(microtime(true) - $debut, 1);
        $this->info("✓ {$total} militaires créés en {$duree} s.");
        $this->comment("Pour nettoyer la base après vos mesures : php artisan charge:militaires --purge");

        return self::SUCCESS;
    }
}
