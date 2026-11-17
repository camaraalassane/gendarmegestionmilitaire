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

        // 2. Lier tous les militaires identifiés comme otages à cet ID officiel
        if ($otageId) {
            DB::table('militaires')
                ->where(function ($q) {
                    $q->whereRaw("LOWER(position_actuelle) LIKE '%otage%'")
                      ->orWhereRaw("LOWER(observations) LIKE '%otage%' AND LOWER(observations) NOT LIKE '%libéré%'");
                })
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
