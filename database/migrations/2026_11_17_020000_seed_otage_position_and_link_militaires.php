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
        // 1. Créer la position officielle 'Otage' dans la table positions si elle n'existe pas
        $otageId = DB::table('positions')->whereRaw('LOWER(nom) = ?', ['otage'])->value('id');

        if (!$otageId) {
            $otageId = DB::table('positions')->insertGetId([
                'nom' => 'Otage',
                'type' => 'operationnelle',
                'description' => "Militaire capturé / en captivité ou prise d'otage",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Lier les militaires explicitement en position Otage (ou cas formel comme DIAWARA Mamadou)
        if ($otageId) {
            // A. Militaires ayant déjà "Otage" dans leur position actuelle
            DB::table('militaires')
                ->whereRaw("LOWER(position_actuelle) LIKE '%otage%'")
                ->update([
                    'position_id' => $otageId,
                    'position_actuelle' => 'Otage',
                ]);

            // B. Cas officiel documenté (ex: DIAWARA Mamadou, matricule 6402)
            DB::table('militaires')
                ->where('matricule', '6402')
                ->update([
                    'position_id' => $otageId,
                    'position_actuelle' => 'Otage',
                ]);

            // C. Militaires vivants et non radiés dont l'observation mentionne formellement la captivité
            // sans mention de libération (avec ou sans accent) ni d'évasion
            DB::table('militaires')
                ->whereNull('position_id')
                ->where(function ($q) {
                    $q->whereNull('statut_code')
                      ->orWhereNotIn('statut_code', [6, 8, 9]); // Exclut déserteurs, radiés, décédés
                })
                ->whereNull('date_deces')
                ->whereRaw("LOWER(COALESCE(statut, '')) NOT LIKE '%décéd%'")
                ->whereRaw("LOWER(COALESCE(statut, '')) NOT LIKE '%deced%'")
                ->whereRaw("LOWER(COALESCE(statut, '')) NOT LIKE '%radi%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) LIKE '%otage%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%libéré%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%libere%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%libérat%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%liberat%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%relâch%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%relach%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%évad%'")
                ->whereRaw("LOWER(COALESCE(observations, '')) NOT LIKE '%evad%'")
                ->update([
                    'position_id' => $otageId,
                    'position_actuelle' => 'Otage',
                ]);
        }

        // 3. Réconcilier toute autre position textuelle existante vers son ID correspondant
        $positions = DB::table('positions')->select('id', 'nom')->get();
        foreach ($positions as $pos) {
            DB::table('militaires')
                ->whereNull('position_id')
                ->whereRaw('LOWER(TRIM(position_actuelle)) = ?', [mb_strtolower(trim($pos->nom))])
                ->update(['position_id' => $pos->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback destructif nécessaire
    }
};
