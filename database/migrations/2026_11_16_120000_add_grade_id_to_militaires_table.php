<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('militaires', 'grade_id')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->foreignId('grade_id')
                    ->nullable()
                    ->after('grade_actuel')
                    ->constrained('grades')
                    ->nullOnDelete()
                    ->index();
            });
        }

        // Remplissage automatique et immédiat des grade_id pour tous les militaires existants
        DB::statement("
            UPDATE militaires m
            SET grade_id = g.id
            FROM grades g
            WHERE (LOWER(TRIM(m.grade_actuel)) = LOWER(TRIM(g.nom_grade))
               OR LOWER(TRIM(m.grade_actuel)) = LOWER(TRIM(g.code_grade)))
              AND m.grade_id IS NULL
        ");

        // Index composé pour accélération maximale : statut_code + grade_id
        DB::statement("
            CREATE INDEX IF NOT EXISTS militaires_statut_grade_id_idx
            ON militaires (statut_code, grade_id)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS militaires_statut_grade_id_idx");

        if (Schema::hasColumn('militaires', 'grade_id')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->dropForeign(['grade_id']);
                $table->dropColumn('grade_id');
            });
        }
    }
};
