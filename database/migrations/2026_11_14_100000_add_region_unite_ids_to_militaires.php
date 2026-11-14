<?php

use App\Models\Militaire;
use App\Support\CarteRattachement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache chaque militaire à une vraie région et une vraie unité (clés étrangères),
 * pour que la carte calcule ses effectifs avec un seul GROUP BY indexé.
 * Les colonnes texte `unite` et `region` sont conservées (rien n'est supprimé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('region')->constrained('regions')->nullOnDelete();
            $table->foreignId('unite_id')->nullable()->after('unite')->constrained('unites')->nullOnDelete();
            $table->index(['region_id', 'unite_id', 'statut'], 'militaires_region_unite_statut_idx');
        });

        // SQLite ne crée pas d'index sur les clés étrangères : on ajoute celui des unités par région.
        if (!Schema::hasIndex('unites', ['region_id'])) {
            Schema::table('unites', fn (Blueprint $t) => $t->index('region_id', 'unites_region_id_idx'));
        }

        // Rattachement des militaires existants (par paquets, sans événements de modèle)
        $resolveur = new CarteRattachement();
        $total = 0;
        $rattaches = 0;

        DB::table('militaires')->select('id', 'unite', 'region')->orderBy('id')->chunkById(500, function ($rows) use ($resolveur, &$total, &$rattaches) {
            foreach ($rows as $m) {
                $total++;
                $ids = $resolveur->resoudre($m->unite, $m->region);
                if ($ids['unite_id'] || $ids['region_id']) {
                    $rattaches++;
                    DB::table('militaires')->where('id', $m->id)->update($ids);
                }
            }
        });

        echo "  Militaires rattachés : {$rattaches} / {$total}\n";
    }

    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropIndex('militaires_region_unite_statut_idx');
            $table->dropConstrainedForeignId('unite_id');
            $table->dropConstrainedForeignId('region_id');
        });

        if (Schema::hasIndex('unites', 'unites_region_id_idx')) {
            Schema::table('unites', fn (Blueprint $t) => $t->dropIndex('unites_region_id_idx'));
        }
    }
};
