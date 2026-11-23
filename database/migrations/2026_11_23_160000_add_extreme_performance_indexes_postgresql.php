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
        // 1. Table militaires
        Schema::table('militaires', function (Blueprint $table) {
            // Index sur theatre_id (manquant jusqu'ici)
            $table->index('theatre_id', 'idx_militaires_theatre_id');
            // Index composite theatre + deleted_at pour filtrer ultra-vite les projections
            $table->index(['theatre_id', 'deleted_at'], 'idx_militaires_theatre_del');
            // Index composite statut_code + deleted_at + grade_id (utilisé pour les effectifs SPA et Dashboard)
            $table->index(['deleted_at', 'statut_code', 'grade_id'], 'idx_militaires_del_stat_grade');
            // Index composite grade_id + date_naissance pour les calculs de départs à la retraite
            $table->index(['grade_id', 'date_naissance'], 'idx_militaires_grade_naissance');
            // Index composite statut_code + est_blesse_guerre
            $table->index(['statut_code', 'est_blesse_guerre'], 'idx_militaires_stat_blesse');
            // Index composite statut_code + est_disparu
            $table->index(['statut_code', 'est_disparu'], 'idx_militaires_stat_disparu');
        });

        // 2. Table theatre_unite_appuis
        if (Schema::hasTable('theatre_unite_appuis')) {
            Schema::table('theatre_unite_appuis', function (Blueprint $table) {
                $table->index('theatre_id', 'idx_theatre_unite_appuis_theatre_id');
            });
        }

        // 3. Table grades
        Schema::table('grades', function (Blueprint $table) {
            $table->index('retraite_obligatoire', 'idx_grades_retraite_obligatoire');
        });

        // 4. Table theatres
        if (Schema::hasTable('theatres')) {
            Schema::table('theatres', function (Blueprint $table) {
                $table->index('statut', 'idx_theatres_statut');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropIndex('idx_militaires_theatre_id');
            $table->dropIndex('idx_militaires_theatre_del');
            $table->dropIndex('idx_militaires_del_stat_grade');
            $table->dropIndex('idx_militaires_grade_naissance');
            $table->dropIndex('idx_militaires_stat_blesse');
            $table->dropIndex('idx_militaires_stat_disparu');
        });

        if (Schema::hasTable('theatre_unite_appuis')) {
            Schema::table('theatre_unite_appuis', function (Blueprint $table) {
                $table->dropIndex('idx_theatre_unite_appuis_theatre_id');
            });
        }

        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex('idx_grades_retraite_obligatoire');
        });

        if (Schema::hasTable('theatres')) {
            Schema::table('theatres', function (Blueprint $table) {
                $table->dropIndex('idx_theatres_statut');
            });
        }
    }
};
