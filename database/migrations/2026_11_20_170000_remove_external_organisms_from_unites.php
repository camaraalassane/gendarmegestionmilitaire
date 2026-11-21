<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $externalIds = range(292, 326);

        // 1. Déconnecter les références dans affectations
        DB::table('affectations')
            ->whereIn('unite_id', $externalIds)
            ->update(['unite_id' => null]);

        // 2. Déconnecter les références dans militaires (sécurité supplémentaire)
        DB::table('militaires')
            ->whereIn('unite_id', $externalIds)
            ->update(['unite_id' => null]);

        // 3. Déconnecter les références dans registres_operationnels (sécurité supplémentaire)
        if (DB::getSchemaBuilder()->hasTable('registres_operationnels')) {
            DB::table('registres_operationnels')
                ->whereIn('unite_id', $externalIds)
                ->update(['unite_id' => null]);
        }

        // 4. Supprimer les organismes externes de la table des unités de gendarmerie
        DB::table('unites')
            ->whereIn('id', $externalIds)
            ->delete();

        // 5. Supprimer également toute unité portant le type 'Détachement / Ministère' résiduelle
        DB::table('unites')
            ->where('type', 'Détachement / Ministère')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
