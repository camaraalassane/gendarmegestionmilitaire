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
        Schema::create('theatre_unites_appui', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->string('code', 30); // ex: AA, AT, DTTIA, GENIE, DCSSA
            $table->string('nom', 150); // ex: Armée de l'Air, Armée de Terre, Transmissions
            $table->integer('effectif')->default(0);
            $table->string('chef_detachement', 150)->nullable();
            $table->string('couleur', 30)->nullable()->default('#0284c7');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('theatre_id');
            $table->index(['theatre_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theatre_unites_appui');
    }
};
