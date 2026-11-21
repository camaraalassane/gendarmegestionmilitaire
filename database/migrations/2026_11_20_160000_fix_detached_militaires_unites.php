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
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $detached = DB::table('militaires')
            ->whereNotNull('organisme_detachement_id')
            ->where(function ($q) use ($likeOp) {
                $q->where('position_actuelle', $likeOp, '%détach%')
                  ->orWhere('position_actuelle', $likeOp, '%detach%')
                  ->orWhere('position_id', 26);
            })
            ->get(['id', 'organisme_detachement_id']);

        foreach ($detached as $m) {
            $org = DB::table('organismes_detachement')->where('id', $m->organisme_detachement_id)->first();
            if ($org) {
                DB::table('militaires')
                    ->where('id', $m->id)
                    ->update([
                        'unite' => $org->nom,
                        'unite_id' => null,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback nécessaire
    }
};
