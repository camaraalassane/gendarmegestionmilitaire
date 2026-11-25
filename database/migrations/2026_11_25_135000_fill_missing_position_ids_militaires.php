<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\Militaire;

return new class extends Migration
{
    public function up(): void
    {
        // Résolution dynamique de l'ID "À l'Unité / Au Poste" par son code 'UNITE'
        $uniteId = Position::idUnite();
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // Assigner la position de l'unité pour tout militaire avec position_id NULL
        DB::table('militaires')
            ->whereNull('position_id')
            ->where(function ($q) use ($like) {
                $q->where('position_actuelle', "À l'Unité / Au Poste")
                  ->orWhere('position_actuelle', $like, '%unité%')
                  ->orWhere('position_actuelle', $like, '%poste%')
                  ->orWhereNotNull('unite_id');
            })
            ->update(['position_id' => $uniteId]);

        Militaire::incrementCacheVersion();
    }

    public function down(): void
    {
    }
};
