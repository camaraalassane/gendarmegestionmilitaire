<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Règle d'or de gestion RH militaire :
     * Un militaire en position de détachement ne relève d'aucune région territoriale
     * ni d'aucune brigade/unité territoriale active de la Gendarmerie (soit dans une région, soit détaché).
     * Il dépend exclusivement de son Organisme de Détachement d'accueil (SPA / Présidence / Ministère...).
     */
    public function up(): void
    {
        // 1. Réinitialiser region, region_id, unite, unite_id et theatre_id pour tous les militaires en détachement
        DB::table('militaires')
            ->where(function ($q) {
                $q->where('position_actuelle', 'En détachement')
                  ->orWhere('position_id', 26)
                  ->orWhere('statut', 'détaché')
                  ->orWhere('statut_code', 2);
            })
            ->update([
                'region_id'  => null,
                'region'     => null,
                'unite_id'   => null,
                'unite'      => null,
                'theatre_id' => null,
            ]);

        // 2. Supprimer les affectations territoriales factices rattachées à ces militaires détachés
        $detacheIds = DB::table('militaires')
            ->where(function ($q) {
                $q->where('position_actuelle', 'En détachement')
                  ->orWhere('position_id', 26)
                  ->orWhere('statut', 'détaché')
                  ->orWhere('statut_code', 2);
            })
            ->pluck('id');

        DB::table('affectations')->whereIn('militaire_id', $detacheIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Aucun retour en arrière nécessaire
    }
};
