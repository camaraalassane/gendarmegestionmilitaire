<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Résultat pré-calculé des éligibilités : une ligne par éligibilité, rangée par version des données.
        Schema::create('eligibilites_calculees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');              // version du cache des militaires au moment du calcul
            $table->string('type', 20);                      // promotions | formations | retraites | contrats | mutations
            $table->unsignedBigInteger('militaire_id');
            $table->string('grade_actuel')->nullable();
            $table->string('formation', 40)->nullable();     // code de formation (type = formations)
            $table->bigInteger('tri')->default(0);           // clé de tri croissante (voir EligibiliteController)
            $table->text('payload');                         // ligne JSON identique à l'ancien tableau en mémoire

            $table->index(['version', 'type', 'tri', 'id'], 'elig_calc_type_tri_idx');
            $table->index(['version', 'type', 'grade_actuel'], 'elig_calc_grade_idx');
            $table->index(['version', 'type', 'formation'], 'elig_calc_formation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibilites_calculees');
    }
};
