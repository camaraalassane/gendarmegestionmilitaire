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
        Schema::table('avancements', function (Blueprint $table) {
            $table->decimal('points_fonction_actuelle', 6, 2)->default(0)->after('fonction_actuelle');
            $table->integer('annees_restantes_retraite')->nullable()->after('points_limite_age');
            $table->decimal('malus_limite_age', 6, 2)->default(0)->after('annees_restantes_retraite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('avancements', function (Blueprint $table) {
            $table->dropColumn(['points_fonction_actuelle', 'annees_restantes_retraite', 'malus_limite_age']);
        });
    }
};
