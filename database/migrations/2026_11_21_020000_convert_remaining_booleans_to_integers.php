<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convertit tous les champs booléens restants de la base en smallint (codes 1 et 0)
     * pour comparaison numérique stricte sans ambiguïté dans PostgreSQL, PHP et Vue.js.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 1. unites.est_operationnelle
        DB::statement("ALTER TABLE unites ALTER COLUMN est_operationnelle DROP DEFAULT");
        DB::statement("ALTER TABLE unites ALTER COLUMN est_operationnelle TYPE smallint USING (CASE WHEN est_operationnelle = TRUE THEN 1 ELSE 0 END)");
        DB::statement("ALTER TABLE unites ALTER COLUMN est_operationnelle SET DEFAULT 1");

        // 2. organismes_detachement.est_actif & est_organique
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_actif DROP DEFAULT");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_actif TYPE smallint USING (CASE WHEN est_actif = TRUE THEN 1 ELSE 0 END)");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_actif SET DEFAULT 1");

        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_organique DROP DEFAULT");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_organique TYPE smallint USING (CASE WHEN est_organique = TRUE THEN 1 ELSE 0 END)");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_organique SET DEFAULT 0");

        // 3. alertes.est_vue
        DB::statement("ALTER TABLE alertes ALTER COLUMN est_vue DROP DEFAULT");
        DB::statement("ALTER TABLE alertes ALTER COLUMN est_vue TYPE smallint USING (CASE WHEN est_vue = TRUE THEN 1 ELSE 0 END)");
        DB::statement("ALTER TABLE alertes ALTER COLUMN est_vue SET DEFAULT 0");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE unites ALTER COLUMN est_operationnelle TYPE boolean USING (CASE WHEN est_operationnelle = 1 THEN TRUE ELSE FALSE END)");
        DB::statement("ALTER TABLE unites ALTER COLUMN est_operationnelle SET DEFAULT TRUE");

        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_actif TYPE boolean USING (CASE WHEN est_actif = 1 THEN TRUE ELSE FALSE END)");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_actif SET DEFAULT TRUE");

        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_organique TYPE boolean USING (CASE WHEN est_organique = 1 THEN TRUE ELSE FALSE END)");
        DB::statement("ALTER TABLE organismes_detachement ALTER COLUMN est_organique SET DEFAULT FALSE");

        DB::statement("ALTER TABLE alertes ALTER COLUMN est_vue TYPE boolean USING (CASE WHEN est_vue = 1 THEN TRUE ELSE FALSE END)");
        DB::statement("ALTER TABLE alertes ALTER COLUMN est_vue SET DEFAULT FALSE");
    }
};
