<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class PurgeMilitaires extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-militaires {--force : Exécuter sans confirmation interactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime tous les militaires et données opérationnelles associées en conservant les configurations et les utilisateurs.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Êtes-vous certain de vouloir purger TOUS les militaires et leurs données associées ? Cette action est irréversible.')) {
                $this->warn('Opération annulée.');
                return self::SUCCESS;
            }
        }

        $this->info("=== Purge complète des militaires et données opérationnelles ===");

        $driver = DB::connection()->getDriverName();

        $tables = [
            'certificat_militaire',
            'certificat_documents',
            'contrats',
            'affectations',
            'avancements',
            'alertes',
            'eligibilites',
            'registres_operationnels',
            'situations_prises_armes',
            'militaires',
            'activity_logs',
        ];

        if ($driver === 'pgsql') {
            DB::transaction(function () use ($tables) {
                foreach ($tables as $table) {
                    DB::statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE;");
                    $this->line(" - Table [{$table}] vidée et IDs réinitialisés à 1.");
                }
            });
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            foreach ($tables as $table) {
                DB::table($table)->delete();
                DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}';");
                $this->line(" - Table [{$table}] vidée.");
            }
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line(" - Table [{$table}] vidée.");
            }
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        }

        // Nettoyage des photos sur les disques
        $photoDisks = ['local', 'public'];
        $photosDeleted = 0;
        foreach ($photoDisks as $disk) {
            try {
                if (Storage::disk($disk)->exists('militaires/photos')) {
                    $files = Storage::disk($disk)->files('militaires/photos');
                    foreach ($files as $file) {
                        Storage::disk($disk)->delete($file);
                        $photosDeleted++;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore storage errors if directory does not exist
            }
        }
        $this->line(" - {$photosDeleted} photo(s) supprimée(s) du stockage.");

        // Nettoyage du cache applicatif
        Cache::flush();
        $this->line(" - Cache applicatif vidé (statistiques et compteurs réinitialisés).");

        $this->newLine();
        $this->info("Purge terminée avec succès : 0 militaire en base. Prêt pour l'import des données réelles !");
        return self::SUCCESS;
    }
}
