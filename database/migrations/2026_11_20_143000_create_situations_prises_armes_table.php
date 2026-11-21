<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('situations_prises_armes', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('periode_mois')->index();
            $table->smallInteger('periode_annee')->index();
            $table->string('titre_periode', 150);
            $table->string('reference_numero', 150)->default('N°______________/4 GRM-SDRH-DAGP');
            $table->integer('total_hommes')->default(0);
            $table->integer('total_dames')->default(0);
            $table->integer('total_general')->default(0);
            $table->json('donnees_matrice')->nullable();
            $table->json('donnees_regions')->nullable();
            $table->string('signataire_nom', 150)->nullable();
            $table->string('signataire_titre', 200)->nullable();
            $table->string('signataire_decoration', 150)->nullable();
            $table->timestamps();

            $table->unique(['periode_mois', 'periode_annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situations_prises_armes');
    }
};
