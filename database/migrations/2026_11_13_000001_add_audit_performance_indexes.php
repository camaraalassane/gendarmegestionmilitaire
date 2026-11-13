<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alertes: index sur created_at (triǸ  chaque affichage du dashboard et notification)
        if (Schema::hasTable('alertes')) {
            Schema::table('alertes', function (Blueprint $table) {
                $table->index('created_at', 'idx_alertes_created_at');
            });
        }

        // 2. Activity logs: index sur created_at (triǸ chronologiquement)
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->index('created_at', 'idx_activity_logs_created_at');
            });
        }

        // 3. Militaires: index sur deleted_at (filtrǸ dans chaque requǦte Eloquent avec SoftDeletes)
        if (Schema::hasTable('militaires') && Schema::hasColumn('militaires', 'deleted_at')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->index('deleted_at', 'idx_militaires_deleted_at');
            });
        }

        // 4. Grades: index sur nom_grade (jointure avec militaires.grade_actuel)
        if (Schema::hasTable('grades') && Schema::hasColumn('grades', 'nom_grade')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->index('nom_grade', 'idx_grades_nom_grade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('alertes')) {
            Schema::table('alertes', function (Blueprint $table) {
                $table->dropIndex('idx_alertes_created_at');
            });
        }

        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropIndex('idx_activity_logs_created_at');
            });
        }

        if (Schema::hasTable('militaires') && Schema::hasColumn('militaires', 'deleted_at')) {
            Schema::table('militaires', function (Blueprint $table) {
                $table->dropIndex('idx_militaires_deleted_at');
            });
        }

        if (Schema::hasTable('grades') && Schema::hasColumn('grades', 'nom_grade')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropIndex('idx_grades_nom_grade');
            });
        }
    }
};
