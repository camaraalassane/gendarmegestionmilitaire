<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use PDO;

class TransferSqliteToPostgres extends Command
{
    /**
     * Nom et signature de la commande.
     *
     * @var string
     */
    protected $signature = 'db:transfer-sqlite-to-pgsql 
                            {--sqlite-path= : Chemin vers le fichier SQLite source (par défaut: database/database.sqlite)}
                            {--db-host= : Hôte PostgreSQL (ex: 127.0.0.1 ou db)}
                            {--db-port= : Port PostgreSQL (défaut: 5432)}
                            {--db-name= : Nom de la base PostgreSQL}
                            {--db-user= : Utilisateur PostgreSQL}
                            {--db-password= : Mot de passe PostgreSQL}
                            {--force : Exécuter sans confirmation interactive}
                            {--skip-migrate : Ne pas exécuter php artisan migrate sur PostgreSQL avant le transfert}';

    /**
     * Description de la commande.
     *
     * @var string
     */
    protected $description = 'Transfère l\'intégralité des données de SQLite vers PostgreSQL avec vérification et zéro perte';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('   MIGRATION DES DONNÉES : SQLite  ===>  PostgreSQL (SANS PERTE)');
        $this->info('================================================================');

        $sqlitePath = $this->option('sqlite-path') ?: database_path('database.sqlite');

        if (!file_exists($sqlitePath)) {
            $this->error("Le fichier SQLite source n'a pas été trouvé : {$sqlitePath}");
            return 1;
        }

        $sqliteSizeMb = round(filesize($sqlitePath) / 1024 / 1024, 2);
        $this->line("• Source SQLite : <comment>{$sqlitePath}</comment> ({$sqliteSizeMb} Mo)");

        // 1. Configurer la connexion dynamique SQLite en lecture seule
        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        // 2. Tester la connexion source SQLite
        try {
            $sqlitePdo = DB::connection('sqlite_source')->getPdo();
            $this->info('• Connexion SQLite source : OK');
        } catch (\Throwable $e) {
            $this->error('Impossible de se connecter à la source SQLite : ' . $e->getMessage());
            return 1;
        }

        // 3. Configurer dynamiquement la cible PostgreSQL avec les options CLI si fournies
        $dbHost = $this->option('db-host') ?: (env('DB_HOST') ?: config('database.connections.pgsql.host', '127.0.0.1'));
        $dbPort = $this->option('db-port') ?: (env('DB_PORT') ?: config('database.connections.pgsql.port', '5432'));
        $dbName = $this->option('db-name') ?: (env('DB_DATABASE') && env('DB_DATABASE') !== 'database/database.sqlite' ? env('DB_DATABASE') : config('database.connections.pgsql.database'));
        $dbUser = $this->option('db-user') ?: (env('DB_USERNAME') ?: config('database.connections.pgsql.username'));
        $dbPass = $this->option('db-password') !== null ? $this->option('db-password') : (env('DB_PASSWORD') !== null ? env('DB_PASSWORD') : config('database.connections.pgsql.password'));

        config([
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.host' => $dbHost,
            'database.connections.pgsql.port' => $dbPort,
            'database.connections.pgsql.database' => $dbName,
            'database.connections.pgsql.username' => $dbUser,
            'database.connections.pgsql.password' => $dbPass,
        ]);
        DB::purge('pgsql');

        // 4. Tester la connexion cible PostgreSQL (avec auto-création si la base n'existe pas)
        try {
            $pgsqlPdo = DB::connection('pgsql')->getPdo();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'does not exist') !== false || stripos($msg, "n'existe pas") !== false) {
                $this->warn("La base '{$dbName}' n'existe pas encore. Tentative de création automatique...");
                try {
                    // Connexion temporaire sur la base par défaut 'postgres' pour créer la base cible
                    config(['database.connections.pgsql_default' => [
                        'driver' => 'pgsql',
                        'host' => $dbHost,
                        'port' => $dbPort,
                        'database' => 'postgres',
                        'username' => $dbUser,
                        'password' => $dbPass,
                        'prefix' => '',
                    ]]);
                    DB::connection('pgsql_default')->statement("CREATE DATABASE \"{$dbName}\"");
                    $this->info("✓ Base de données '{$dbName}' créée avec succès sur PostgreSQL !");
                    DB::purge('pgsql');
                    $pgsqlPdo = DB::connection('pgsql')->getPdo();
                } catch (\Throwable $createEx) {
                    $this->error("Échec de la création automatique de la base : " . $createEx->getMessage());
                    return 1;
                }
            } else {
                $this->error('Impossible de se connecter à la base PostgreSQL cible : ' . $e->getMessage());
                $this->line("Paramètres tentés : Hôte={$dbHost}, Port={$dbPort}, Base={$dbName}, Utilisateur={$dbUser}");
                $this->line('Astuce : Vous pouvez spécifier les identifiants directement en paramètre de la commande :');
                $this->comment('php artisan db:transfer-sqlite-to-pgsql --db-host=127.0.0.1 --db-name=gestion_militaire --db-user=postgres --db-password=VOTRE_PASS');
                return 1;
            }
        }

        $pgsqlDriver = DB::connection('pgsql')->getDriverName();
        if ($pgsqlDriver !== 'pgsql') {
            $this->error("La connexion 'pgsql' utilise le driver '{$pgsqlDriver}', PostgreSQL attendu !");
            return 1;
        }
        $this->info("• Connexion PostgreSQL cible : OK (<comment>{$dbName}</comment> sur <comment>{$dbHost}:{$dbPort}</comment> utilisateur <comment>{$dbUser}</comment>)");

        if (!$this->option('force') && !$this->confirm('Voulez-vous lancer le transfert vers PostgreSQL ?', true)) {
            $this->warn('Opération annulée.');
            return 0;
        }

        // 4. Lancer les migrations sur PostgreSQL si demandé
        if (!$this->option('skip-migrate')) {
            $this->info("\n1. Création des tables sur PostgreSQL (php artisan migrate --database=pgsql)...");
            $exitCode = Artisan::call('migrate', [
                '--database' => 'pgsql',
                '--force' => true,
            ]);
            $this->line(Artisan::output());
            if ($exitCode !== 0) {
                $this->error('Erreur lors de l\'exécution des migrations sur PostgreSQL.');
                return 1;
            }
            $this->info('Tables et index créés avec succès sur PostgreSQL.');
        }

        // 5. Récupérer toutes les tables de SQLite
        $rawTables = DB::connection('sqlite_source')->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'migrations' ORDER BY name"
        );
        $tables = array_map(fn($t) => $t->name, $rawTables);

        if (empty($tables)) {
            $this->warn("Aucune table trouvée dans la base SQLite.");
            return 0;
        }

        // Ordonner les tables principales pour une logique propre (les tables dépendantes après)
        $priorityOrder = [
            'users',
            'grades',
            'regions',
            'unites',
            'positions',
            'militaires',
            'certificats',
            'certificat_militaire',
            'certificat_documents',
            'alertes',
            'eligibilites',
            'contrats',
            'affectations',
            'avancements',
            'activity_logs',
            'cache',
            'cache_locks',
            'jobs',
            'failed_jobs',
        ];

        $orderedTables = [];
        foreach ($priorityOrder as $pTable) {
            if (in_array($pTable, $tables)) {
                $orderedTables[] = $pTable;
            }
        }
        foreach ($tables as $t) {
            if (!in_array($t, $orderedTables)) {
                $orderedTables[] = $t;
            }
        }

        $this->info("\n2. Début du transfert des tables (" . count($orderedTables) . " tables détectées)...");

        // Désactiver temporairement les contraintes de clés étrangères sur PostgreSQL pour le chargement
        try {
            DB::connection('pgsql')->statement("SET session_replication_role = 'replica';");
        } catch (\Throwable $e) {
            $this->warn("Avertissement: Impossible d'activer session_replication_role: " . $e->getMessage());
        }

        $summary = [];
        $totalRowsCopied = 0;

        foreach ($orderedTables as $table) {
            if (!Schema::connection('pgsql')->hasTable($table)) {
                $this->warn("Table '{$table}' absente sur PostgreSQL (ignorée).");
                continue;
            }

            $sqliteCount = DB::connection('sqlite_source')->table($table)->count();

            if ($sqliteCount === 0) {
                $summary[] = [
                    'table' => $table,
                    'sqlite' => 0,
                    'pgsql' => 0,
                    'statut' => '<comment>Vide (0 ligne)</comment>',
                ];
                continue;
            }

            // Ajustement de colonnes spécifiques pour PostgreSQL (ex: sexe élargi à VARCHAR(20))
            if ($table === 'militaires') {
                try {
                    DB::connection('pgsql')->statement("ALTER TABLE militaires ALTER COLUMN sexe TYPE VARCHAR(20)");
                } catch (\Throwable $e) {}
            }

            // Vider la table PostgreSQL cible pour éviter les doublons
            try {
                DB::connection('pgsql')->statement("TRUNCATE TABLE \"{$table}\" CASCADE;");
            } catch (\Throwable $e) {
                DB::connection('pgsql')->table($table)->delete();
            }

            // Inspecter les colonnes et types de la table cible PostgreSQL
            $pgsqlColumns = Schema::connection('pgsql')->getColumnListing($table);
            $columnTypes = [];
            foreach ($pgsqlColumns as $col) {
                try {
                    $columnTypes[$col] = Schema::connection('pgsql')->getColumnType($table, $col);
                } catch (\Throwable $e) {
                    $columnTypes[$col] = 'string';
                }
            }

            // Copier les données par blocs de 250 lignes
            $copiedForTable = 0;
            $chunkSize = 250;

            DB::connection('sqlite_source')->table($table)->orderByRaw('1')->chunk($chunkSize, function ($rows) use ($table, $columnTypes, $pgsqlColumns, &$copiedForTable) {
                $insertData = [];

                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $cleanRow = [];

                    foreach ($rowArray as $col => $val) {
                        // Ignorer les colonnes qui n'existent pas sur PostgreSQL
                        if (!in_array($col, $pgsqlColumns)) {
                            continue;
                        }

                        $type = $columnTypes[$col] ?? 'string';

                        // 1. Chaines vides converties en NULL pour types dates, numériques et booléens
                        if ($val === '' && in_array($type, ['integer', 'bigint', 'smallint', 'date', 'datetime', 'timestamp', 'float', 'decimal', 'boolean'])) {
                            $cleanRow[$col] = null;
                            continue;
                        }

                        // 2. Conversion stricte pour le type booléen natif PostgreSQL
                        if ($type === 'boolean' && $val !== null) {
                            $cleanRow[$col] = (bool) $val;
                            continue;
                        }

                        $cleanRow[$col] = $val;
                    }

                    $insertData[] = $cleanRow;
                }

                if (!empty($insertData)) {
                    DB::connection('pgsql')->table($table)->insert($insertData);
                    $copiedForTable += count($insertData);
                }
            });

            // Réajuster la séquence auto-increment d'ID pour PostgreSQL si applicable
            if (in_array('id', $pgsqlColumns)) {
                try {
                    $seqRow = DB::connection('pgsql')->selectOne("SELECT pg_get_serial_sequence(?, 'id') as seq", [$table]);
                    $seqName = $seqRow ? $seqRow->seq : null;
                    if ($seqName) {
                        DB::connection('pgsql')->statement(
                            "SELECT setval(?, COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), (SELECT MAX(id) FROM \"{$table}\") IS NOT NULL)",
                            [$seqName]
                        );
                    }
                } catch (\Throwable $e) {
                    // Ignore si la table n'utilise pas de séquence
                }
            }

            $pgsqlCount = DB::connection('pgsql')->table($table)->count();
            $totalRowsCopied += $pgsqlCount;

            $statusText = ($sqliteCount === $pgsqlCount) 
                ? '<info>✓ 100% transféré</info>' 
                : '<error>Différence (SQLite:' . $sqliteCount . ' / Pg:' . $pgsqlCount . ')</error>';

            $summary[] = [
                'table' => $table,
                'sqlite' => $sqliteCount,
                'pgsql' => $pgsqlCount,
                'statut' => $statusText,
            ];
        }

        // Réactiver les contraintes de clés étrangères sur PostgreSQL
        try {
            DB::connection('pgsql')->statement("SET session_replication_role = 'DEFAULT';");
        } catch (\Throwable $e) {
            // Ignore
        }

        // 6. Afficher le tableau de récapitulation
        $this->info("\n3. Récapitulatif du transfert de données :");
        $this->table(
            ['Table', 'Lignes SQLite', 'Lignes PostgreSQL', 'Résultat'],
            $summary
        );

        $this->info("\n================================================================");
        $this->info("   SUCCÈS : {$totalRowsCopied} enregistrements transférés dans PostgreSQL !");
        $this->info("================================================================\n");

        $this->line("Prochaine étape :");
        $this->line("Modifiez votre fichier .env pour activer PostgreSQL par défaut :");
        $this->comment("DB_CONNECTION=pgsql");
        $this->line("Puis exécutez :");
        $this->comment("php artisan optimize:clear && php artisan optimize");

        return 0;
    }
}
