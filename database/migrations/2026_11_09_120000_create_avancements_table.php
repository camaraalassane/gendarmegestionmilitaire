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
        Schema::create('avancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('militaire_id')->constrained('militaires')->onDelete('cascade');
            $table->unsignedSmallInteger('annee')->default(2026)->index();
            $table->string('grade_actuel')->nullable()->index();
            $table->string('grade_cible')->nullable();
            $table->string('fonction_actuelle')->nullable();
            
            // Critères de notation du tableau d'avancement
            $table->decimal('note_rendement', 6, 2)->default(0);
            $table->decimal('bonus_dernier_emploi', 6, 2)->default(0);
            $table->decimal('points_diplomes', 6, 2)->default(0);
            $table->decimal('points_decorations', 6, 2)->default(0);
            $table->decimal('points_anciennete_grade', 6, 2)->default(0);
            
            // Punitions : nombre de jours x -0.1 point
            $table->unsignedInteger('jours_punition')->default(0);
            $table->decimal('malus_punition', 6, 2)->default(0);
            
            $table->decimal('points_limite_age', 6, 2)->default(0);
            
            // Total & Classement
            $table->decimal('total_points', 6, 2)->default(0)->index();
            $table->unsignedInteger('rang')->nullable()->index();
            $table->text('observations')->nullable();
            
            $table->timestamps();

            // Un militaire ne peut avoir qu'une seule fiche d'avancement par année
            $table->unique(['militaire_id', 'annee']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avancements');
    }
};
