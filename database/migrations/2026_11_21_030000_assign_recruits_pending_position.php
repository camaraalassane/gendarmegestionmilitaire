<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Assigne la position "En attente d'affectation" (id: 33) à tous les militaires actifs
     * n'ayant pas encore de position définie.
     */
    public function up(): void
    {
        DB::table('militaires')
            ->whereNull('position_actuelle')
            ->orWhere('position_actuelle', '')
            ->update([
                'position_id' => 33,
                'position_actuelle' => "En attente d'affectation",
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback destructeur
    }
};
