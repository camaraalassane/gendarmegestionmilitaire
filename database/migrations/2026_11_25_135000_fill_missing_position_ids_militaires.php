<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Assigner position_id = 21 (À l'Unité / Au Poste) pour tout militaire avec position_id NULL
        DB::table('militaires')
            ->whereNull('position_id')
            ->where(function ($q) {
                $q->where('position_actuelle', "À l'Unité / Au Poste")
                  ->orWhere('position_actuelle', 'ILIKE', '%unité%')
                  ->orWhere('position_actuelle', 'ILIKE', '%poste%')
                  ->orWhereNotNull('unite_id');
            })
            ->update(['position_id' => 21]);
    }

    public function down(): void
    {
    }
};
