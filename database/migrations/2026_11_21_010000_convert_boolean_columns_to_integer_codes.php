<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Conversion stricte boolean -> smallint (1 / 0) sur PostgreSQL
            $colsMilitaires = [
                'est_blesse_guerre' => 0,
                'est_non_mobilisable' => 0,
                'est_disparu' => 0,
                'a_permis_conduire' => 0,
                'antecedent_judiciaire' => 0,
                'antecedent_disciplinaire' => 0,
            ];

            foreach ($colsMilitaires as $col => $default) {
                if (Schema::hasColumn('militaires', $col)) {
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} DROP DEFAULT");
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} TYPE smallint USING (CASE WHEN {$col} IS TRUE THEN 1 ELSE 0 END)");
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} SET DEFAULT {$default}");
                }
            }

            if (Schema::hasColumn('registres_operationnels', 'est_actif_fichier')) {
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier DROP DEFAULT");
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier TYPE smallint USING (CASE WHEN est_actif_fichier IS TRUE THEN 1 ELSE 0 END)");
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier SET DEFAULT 1");
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $colsMilitaires = [
                'est_blesse_guerre' => 'false',
                'est_non_mobilisable' => 'false',
                'est_disparu' => 'false',
                'a_permis_conduire' => 'false',
                'antecedent_judiciaire' => 'false',
                'antecedent_disciplinaire' => 'false',
            ];

            foreach ($colsMilitaires as $col => $default) {
                if (Schema::hasColumn('militaires', $col)) {
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} DROP DEFAULT");
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} TYPE boolean USING (CASE WHEN {$col} = 1 THEN TRUE ELSE FALSE END)");
                    DB::statement("ALTER TABLE militaires ALTER COLUMN {$col} SET DEFAULT {$default}");
                }
            }

            if (Schema::hasColumn('registres_operationnels', 'est_actif_fichier')) {
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier DROP DEFAULT");
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier TYPE boolean USING (CASE WHEN est_actif_fichier = 1 THEN TRUE ELSE FALSE END)");
                DB::statement("ALTER TABLE registres_operationnels ALTER COLUMN est_actif_fichier SET DEFAULT true");
            }
        }
    }
};
