<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\Militaire;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Créer ou récupérer la position "En désertion" par son code 'DESERTION'
        $posDesertion = DB::table('positions')->where('code', Position::CODE_DESERTION)->first();
        if (!$posDesertion) {
            $desertionId = DB::table('positions')->insertGetId([
                'code' => Position::CODE_DESERTION,
                'nom' => 'En désertion',
                'type' => 'administrative',
                'description' => 'Militaire en état de désertion (abandon de poste / avis de recherche)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $desertionId = $posDesertion->id;
        }

        // 2. Créer ou harmoniser la position "Procédure en cours" (code 'PROCEDURE')
        // Si l'ancienne position RETRAIT_EMPLOI existe, on la met à jour vers 'PROCEDURE' / 'Procédure en cours'
        $posProcedure = DB::table('positions')
            ->where('code', Position::CODE_PROCEDURE)
            ->orWhere('code', 'RETRAIT_EMPLOI')
            ->first();

        if (!$posProcedure) {
            $procedureId = DB::table('positions')->insertGetId([
                'code' => Position::CODE_PROCEDURE,
                'nom' => 'Procédure en cours',
                'type' => 'administrative',
                'description' => 'Militaire en situation de procédure disciplinaire ou judiciaire',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $procedureId = $posProcedure->id;
            DB::table('positions')->where('id', $procedureId)->update([
                'code' => Position::CODE_PROCEDURE,
                'nom' => 'Procédure en cours',
                'description' => 'Militaire en situation de procédure disciplinaire ou judiciaire',
                'updated_at' => now(),
            ]);
        }

        // 3. Mettre à jour les déserteurs (statut_code 6) pour leur attribuer la position "En désertion"
        DB::table('militaires')
            ->where('statut_code', 6)
            ->update([
                'position_id' => $desertionId,
                'position_actuelle' => 'En désertion',
            ]);

        // 4. Mettre à jour TOUS les militaires en procédure (statut_code 5) avec la position "Procédure en cours"
        DB::table('militaires')
            ->where('statut_code', 5)
            ->update([
                'position_id' => $procedureId,
                'position_actuelle' => 'Procédure en cours',
            ]);

        // 5. Invalidation des caches après mise à jour des données en base
        Position::clearPositionIdCache();
        Militaire::incrementCacheVersion();
    }

    public function down(): void
    {
        // Trouver la position par défaut (À l'Unité) pour réassigner proprement avant toute suppression
        $uniteId = Position::idUnite();

        DB::table('militaires')
            ->where('statut_code', 6)
            ->where('position_actuelle', 'En désertion')
            ->update([
                'position_id' => $uniteId,
                'position_actuelle' => "À l'Unité / Au Poste",
            ]);

        DB::table('militaires')
            ->where('statut_code', 5)
            ->where('position_actuelle', 'Procédure en cours')
            ->update([
                'position_id' => $uniteId,
                'position_actuelle' => "À l'Unité / Au Poste",
            ]);

        // Supprimer les positions créées sans laisser d'orphelins
        DB::table('positions')->whereIn('code', [Position::CODE_DESERTION, Position::CODE_PROCEDURE, 'RETRAIT_EMPLOI'])->delete();

        Position::clearPositionIdCache();
        Militaire::incrementCacheVersion();
    }
};
