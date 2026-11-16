<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Enums\StatutMilitaire;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('militaires', 'statut_code')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->unsignedSmallInteger('statut_code')->nullable()->after('statut');
                $table->index(['statut_code', 'region_id', 'unite_id'], 'militaires_statut_code_lieu_idx');
                $table->index('statut_code', 'militaires_statut_code_idx');
            });
        }

        // Remplissage initial ultra-rapide des statuts existants
        DB::statement("UPDATE militaires SET statut_code = CASE
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'actif%' THEN 1
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'd_tach%' OR LOWER(TRIM(COALESCE(statut,''))) LIKE 'detach%' THEN 2
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE '%mission%' THEN 3
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE '%stage%' OR LOWER(TRIM(COALESCE(statut,''))) LIKE '%format%' THEN 4
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE '%proc%' THEN 5
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'd_serteur%' OR LOWER(TRIM(COALESCE(statut,''))) LIKE 'desert%' THEN 6
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'retrait%' THEN 7
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'radi%' THEN 8
            WHEN LOWER(TRIM(COALESCE(statut,''))) LIKE 'd_c_d%' OR LOWER(TRIM(COALESCE(statut,''))) LIKE 'mort%' THEN 9
            ELSE NULL
        END WHERE statut IS NOT NULL");

        // Rapprochement fin pour tout éventuel résidu non classé
        $unmatched = DB::table('militaires')
            ->whereNull('statut_code')
            ->whereNotNull('statut')
            ->select('id', 'statut')
            ->get();

        foreach ($unmatched as $row) {
            $code = StatutMilitaire::depuisTexte($row->statut)?->value;
            if ($code !== null) {
                DB::table('militaires')->where('id', $row->id)->update(['statut_code' => $code]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('militaires', 'statut_code')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->dropIndex('militaires_statut_code_lieu_idx');
                $table->dropIndex('militaires_statut_code_idx');
                $table->dropColumn('statut_code');
            });
        }
    }
};
