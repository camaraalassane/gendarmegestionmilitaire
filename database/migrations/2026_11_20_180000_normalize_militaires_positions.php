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
        $posUnite = DB::table('positions')->where('nom', "À l'Unité / Au Poste")->value('id') ?? 21;
        $posDetache = DB::table('positions')->where('nom', 'En détachement')->value('id') ?? 26;
        $posStage = DB::table('positions')->where('nom', 'En stage / Formation')->value('id') ?? 28;
        $posOtage = DB::table('positions')->where('nom', "Cas d'otage")->value('id')
            ?? (DB::table('positions')->where('nom', 'Otage')->value('id') ?? 27);

        // 1. Berthe Fousseyni (cour penale) -> Détaché MJGS
        DB::table('militaires')
            ->where('id', 22)
            ->update([
                'position_actuelle' => 'En détachement',
                'position_id' => $posDetache,
                'organisme_detachement_id' => 14,
                'unite' => "Ministère de la Justice et des Droits de l'Homme, Garde des Sceaux",
                'unite_id' => null,
            ]);

        // 2. Diarra (immersion EMGA) -> Détaché EMGA
        DB::table('militaires')
            ->where('id', 48)
            ->update([
                'position_actuelle' => 'En détachement',
                'position_id' => $posDetache,
                'organisme_detachement_id' => 28,
                'unite' => "Etat-Major Général des Armées",
                'unite_id' => null,
            ]);

        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // 3. Stages / Ecoles (Sanou, Doumbia, Camara)
        DB::table('militaires')
            ->where(function($q) use ($likeOp) {
                $q->where('position_actuelle', $likeOp, '%cours-sup%')
                  ->orWhere('position_actuelle', $likeOp, '%ecole de guerre%');
            })
            ->update([
                'position_actuelle' => 'En stage / Formation',
                'position_id' => $posStage,
            ]);

        // 4. Otage (Diawara)
        DB::table('militaires')
            ->where('position_actuelle', $likeOp, '%otage%')
            ->update([
                'position_actuelle' => "Cas d'otage",
                'position_id' => $posOtage,
            ]);

        // 5. Entraineur Basket & Pas rejoint -> À l'Unité / Au Poste
        DB::table('militaires')
            ->whereIn('id', [23, 52])
            ->update([
                'position_actuelle' => "À l'Unité / Au Poste",
                'position_id' => $posUnite,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
