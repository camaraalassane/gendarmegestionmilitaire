<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Index sur alertes pour accélérer le withCount sur la liste des militaires
        DB::statement('CREATE INDEX IF NOT EXISTS idx_alertes_militaire_vue ON alertes (militaire_id, est_vue)');

        // 2. Index sur eligibilites_calculees pour accélérer la jointure des Avancements
        DB::statement('CREATE INDEX IF NOT EXISTS idx_elig_calc_ver_type_mil ON eligibilites_calculees (version, type, militaire_id)');

        // 3. Index sur avancements pour accélérer le tri et la jointure avec eligibilites_calculees
        DB::statement('CREATE INDEX IF NOT EXISTS idx_avancements_annee_militaire ON avancements (annee, militaire_id)');

        // 4. Index sur militaires (unite_id, statut_code) pour accélérer les agrégats de la Carte et des Effectifs
        DB::statement('CREATE INDEX IF NOT EXISTS idx_militaires_unite_statut ON militaires (unite_id, statut_code)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_alertes_militaire_vue');
        DB::statement('DROP INDEX IF EXISTS idx_elig_calc_ver_type_mil');
        DB::statement('DROP INDEX IF EXISTS idx_avancements_annee_militaire');
        DB::statement('DROP INDEX IF EXISTS idx_militaires_unite_statut');
    }
};
