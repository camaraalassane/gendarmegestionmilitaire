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
        // 1. Table des Régions et Grands Commandements
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index(); // ex: R1, R2, DGGN, GSIGN, DETACHEMENT
            $table->string('nom')->index();           // ex: REGION N°1, Direction Générale...
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // 2. Table des Unités (Brigades, Escadrons, Écoles, Directions, etc.)
        Schema::create('unites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('nom')->index();
            $table->string('code')->nullable();
            $table->string('type')->nullable()->index(); // Brigade Territoriale, Escadron, PSIG, etc.
            $table->string('compagnie')->nullable();
            $table->string('groupement')->nullable();
            $table->boolean('est_operationnelle')->default(true)->index(); // true = effectif > 0, false = effectif 0 / en projet
            $table->timestamps();
        });

        // 3. Table des Positions administratives et opérationnelles
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique()->index(); // En activité, Détachement, Escadron Djiguiya...
            $table->string('code')->nullable();
            $table->string('type')->nullable(); // administrative, operationnelle, mission
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unites');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('positions');
    }
};
