<?php

use App\Models\Position;
use App\Support\CarteRattachement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Position relationnelle sur militaires
        Schema::table('militaires', function (Blueprint $table) {
            if (!Schema::hasColumn('militaires', 'position_id')) {
                $table->foreignId('position_id')->nullable()->after('position_actuelle')->constrained('positions')->nullOnDelete();
                $table->index('position_id', 'militaires_position_id_idx');
            }
        });

        // 2. Relations de lieu sur la table affectations
        Schema::table('affectations', function (Blueprint $table) {
            if (!Schema::hasColumn('affectations', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('region')->constrained('regions')->nullOnDelete();
            }
            if (!Schema::hasColumn('affectations', 'unite_id')) {
                $table->foreignId('unite_id')->nullable()->after('unite')->constrained('unites')->nullOnDelete();
            }
            $table->index(['militaire_id', 'region_id', 'unite_id'], 'affectations_lieu_idx');
        });

        // 3. Remplissage automatique de position_id sur les militaires existants
        $positions = Position::all(['id', 'nom']);
        foreach ($positions as $p) {
            DB::table('militaires')
                ->whereNull('position_id')
                ->where('position_actuelle', $p->nom)
                ->update(['position_id' => $p->id]);
        }

        // 4. Remplissage automatique des affectations existantes
        if (class_exists(CarteRattachement::class)) {
            $resolveur = new CarteRattachement();
            $affs = DB::table('affectations')->get(['id', 'unite', 'region']);
            foreach ($affs as $aff) {
                $ids = $resolveur->resoudre($aff->unite, $aff->region);
                if ($ids['unite_id'] || $ids['region_id']) {
                    DB::table('affectations')->where('id', $aff->id)->update([
                        'unite_id' => $ids['unite_id'],
                        'region_id' => $ids['region_id'],
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affectations', function (Blueprint $table) {
            $table->dropIndex('affectations_lieu_idx');
            if (Schema::hasColumn('affectations', 'unite_id')) {
                $table->dropConstrainedForeignId('unite_id');
            }
            if (Schema::hasColumn('affectations', 'region_id')) {
                $table->dropConstrainedForeignId('region_id');
            }
        });

        Schema::table('militaires', function (Blueprint $table) {
            $table->dropIndex('militaires_position_id_idx');
            if (Schema::hasColumn('militaires', 'position_id')) {
                $table->dropConstrainedForeignId('position_id');
            }
        });
    }
};
