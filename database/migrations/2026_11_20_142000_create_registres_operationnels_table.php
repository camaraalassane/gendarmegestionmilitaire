<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registres_operationnels', function (Blueprint $table) {
            $table->id();
            // 1: Blessé en opération / guerre, 2: Porté disparu / Otage, 3: Grand Malade / Inapte, 4: Décédé, 5: Déserteur
            $table->smallInteger('type_registre_code')->index();
            $table->integer('numero_ordre')->nullable()->index();
            $table->string('matricule', 50)->nullable()->index();
            $table->string('mle_grm', 50)->nullable()->index();
            $table->string('nom', 150)->index();
            $table->string('prenom', 150)->nullable()->index();
            $table->unsignedBigInteger('grade_id')->nullable()->index();
            $table->integer('grade_ordre')->nullable()->index();
            $table->string('grade_texte', 100)->nullable();
            $table->smallInteger('sexe_code')->default(1)->index(); // 1 = H, 2 = D
            $table->unsignedBigInteger('region_id')->nullable()->index();
            $table->unsignedBigInteger('unite_id')->nullable()->index();
            $table->string('unite_texte', 255)->nullable();
            $table->date('date_evenement')->nullable()->index();
            $table->smallInteger('annee_evenement')->nullable()->index();
            $table->smallInteger('type_circonstance_code')->nullable()->index();
            $table->text('circonstances')->nullable();
            $table->text('nature_pathologie')->nullable();
            $table->text('decision_aptitude')->nullable();
            $table->text('observations')->nullable();
            $table->boolean('est_actif_fichier')->default(true)->index();
            $table->unsignedBigInteger('militaire_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('grade_id')->references('id')->on('grades')->nullOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('unite_id')->references('id')->on('unites')->nullOnDelete();
            $table->foreign('militaire_id')->references('id')->on('militaires')->nullOnDelete();

            $table->index(['type_registre_code', 'region_id']);
            $table->index(['type_registre_code', 'grade_id']);
            $table->index(['type_registre_code', 'annee_evenement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registres_operationnels');
    }
};
