<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('militaires', 'grade_ordre')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->smallInteger('grade_ordre')->default(0)->nullable()->after('grade_actuel');
                $table->text('recherche')->nullable()->after('observations');
                $table->index(['grade_ordre', 'nom', 'prenom'], 'militaires_tri_grade_nom_idx');
            });
        }

        // Remplir grade_ordre à partir de la table grades
        DB::statement("UPDATE militaires SET grade_ordre = COALESCE((
            SELECT ordre FROM grades WHERE grades.nom_grade = militaires.grade_actuel LIMIT 1
        ), 0)");

        // Remplir le champ recherche regroupé en minuscules
        DB::statement("UPDATE militaires SET recherche = LOWER(TRIM(
            COALESCE(matricule, '') || ' ' ||
            COALESCE(mle_grm, '') || ' ' ||
            COALESCE(numero_info, '') || ' ' ||
            COALESCE(numero_nina, '') || ' ' ||
            COALESCE(nom, '') || ' ' ||
            COALESCE(prenom, '') || ' ' ||
            COALESCE(grade_actuel, '') || ' ' ||
            COALESCE(position_actuelle, '') || ' ' ||
            COALESCE(unite, '') || ' ' ||
            COALESCE(region, '') || ' ' ||
            COALESCE(specialite, '') || ' ' ||
            COALESCE(fonction_actuelle, '') || ' ' ||
            COALESCE(telephone, '')
        ))");

        // Index pg_trgm pour PostgreSQL (recherche instantanée plein texte)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS militaires_recherche_trgm_idx ON militaires USING gin (recherche gin_trgm_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('militaires', 'grade_ordre')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS militaires_recherche_trgm_idx');
            }

            Schema::table('militaires', function (Blueprint $table) {
                $table->dropIndex('militaires_tri_grade_nom_idx');
                $table->dropColumn(['grade_ordre', 'recherche']);
            });
        }
    }
};
