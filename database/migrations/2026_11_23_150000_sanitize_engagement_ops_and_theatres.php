<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Militaire;
use App\Http\Controllers\CarteController;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Mettre à jour engagement_ops pour chaque militaire rattaché à un théâtre officiel
        $theatres = DB::table('theatres')->get(['id', 'nom']);
        foreach ($theatres as $th) {
            DB::table('militaires')
                ->where('theatre_id', $th->id)
                ->update(['engagement_ops' => '1 OPS, ' . $th->nom]);
        }

        // 2. Mettre à jour tous les militaires sans théâtre officiel à '0 OPS' (nettoyage complet des valeurs parasites)
        DB::table('militaires')
            ->whereNull('theatre_id')
            ->update(['engagement_ops' => '0 OPS']);

        // 3. Vider les caches
        CarteController::flush();
        Militaire::incrementCacheVersion();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
