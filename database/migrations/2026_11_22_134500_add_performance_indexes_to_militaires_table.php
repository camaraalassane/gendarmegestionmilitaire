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
        Schema::table('militaires', function (Blueprint $table) {
            // Index composite pour l'API et les vues opérationnelles (Théâtre, Région, Grade)
            if (!Schema::hasIndex('militaires', 'militaires_api_tri_idx')) {
                $table->index(['theatre_id', 'region_id', 'grade_id'], 'militaires_api_tri_idx');
            }

            // Index composite pour les tris par statut et ordre hiérarchique
            if (!Schema::hasIndex('militaires', 'militaires_statut_tri_idx')) {
                $table->index(['statut_code', 'grade_ordre', 'nom'], 'militaires_statut_tri_idx');
            }

            // Index sur la date de mise à jour pour le suivi et les syncs rapides
            if (!Schema::hasIndex('militaires', 'militaires_updated_idx')) {
                $table->index('updated_at', 'militaires_updated_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropIndex('militaires_api_tri_idx');
            $table->dropIndex('militaires_statut_tri_idx');
            $table->dropIndex('militaires_updated_idx');
        });
    }
};
