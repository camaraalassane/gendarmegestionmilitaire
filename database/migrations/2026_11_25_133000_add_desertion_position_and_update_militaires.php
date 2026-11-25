<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Position;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Créer la position "En désertion" si elle n'existe pas déjà
        $posDesertion = DB::table('positions')->where('code', 'DESERTION')->first();
        if (!$posDesertion) {
            $desertionId = DB::table('positions')->insertGetId([
                'code' => 'DESERTION',
                'nom' => 'En désertion',
                'type' => 'administrative',
                'description' => 'Militaire en état de désertion (abandon de poste / avis de recherche)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $desertionId = $posDesertion->id;
        }

        // 2. Créer la position "Non-activité / Retrait d'emploi" pour les procédures si elle n'existe pas
        $posNonActivite = DB::table('positions')->where('code', 'RETRAIT_EMPLOI')->first();
        if (!$posNonActivite) {
            $retraitId = DB::table('positions')->insertGetId([
                'code' => 'RETRAIT_EMPLOI',
                'nom' => 'Non-activité / Retrait d\'emploi',
                'type' => 'administrative',
                'description' => 'Militaire mis en non-activité par retrait d\'emploi (mesure disciplinaire / procédure judiciaire)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $retraitId = $posNonActivite->id;
        }

        // 3. Mettre à jour les 18 déserteurs (statut_code 6) pour leur attribuer la position "En désertion"
        DB::table('militaires')
            ->where('statut_code', 6)
            ->update([
                'position_id' => $desertionId,
                'position_actuelle' => 'En désertion',
            ]);

        // 4. Mettre à jour les militaires en procédure disciplinaire / judiciaire (statut_code 5) qui avaient "Disponibilite"
        DB::table('militaires')
            ->where('statut_code', 5)
            ->where(function ($q) {
                $q->where('position_actuelle', 'Disponibilite')
                  ->orWhere('position_id', 34);
            })
            ->update([
                'position_id' => $retraitId,
                'position_actuelle' => 'Non-activité / Retrait d\'emploi',
            ]);
    }

    public function down(): void
    {
        // Revenir en arrière si nécessaire
        $posDisp = DB::table('positions')->where('code', 'DISPONIBILITE')->first();
        if ($posDisp) {
            DB::table('militaires')
                ->where('statut_code', 6)
                ->where('position_actuelle', 'En désertion')
                ->update([
                    'position_id' => $posDisp->id,
                    'position_actuelle' => 'Disponibilite',
                ]);
        }
        DB::table('positions')->whereIn('code', ['DESERTION', 'RETRAIT_EMPLOI'])->delete();
    }
};
