<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\OrganismeDetachement;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $orgs = DB::table('organismes_detachement')->pluck('id', 'code');
        $grmId = $orgs['GRM'] ?? 1;

        // 1. Synchronisation des 20 personnels détachés identifiés vers leur organisme exact
        $mappingDetaches = [
            16 => 'ANSE',
            19 => 'BIS',
            42 => 'MJGS',
            53 => 'MJGS',
            40 => 'BIS',
            49 => 'DOCS',
            12 => 'MDAC',
            55 => 'DOCS',
            34 => 'BIS',
            35 => 'MATD',
            9  => 'DGPC',
            108 => 'EMGA',
            14 => 'ANSE',
            17 => 'MSPC',
            21 => 'EMPPR',
            50 => 'DJM',
            15 => 'MDAC',
            37 => 'ANSE',
            28 => 'DSM',
            57 => 'CNDDR',
        ];

        foreach ($mappingDetaches as $militaireId => $codeOrg) {
            $orgId = $orgs[$codeOrg] ?? null;
            if ($orgId) {
                DB::table('militaires')
                    ->where('id', $militaireId)
                    ->update([
                        'organisme_detachement_id' => $orgId,
                        'statut_code' => 2,
                    ]);
            }
        }

        // 2. Assigner l'organisme organique principal (GRM) à tous les militaires actifs non détachés
        DB::table('militaires')
            ->whereNull('organisme_detachement_id')
            ->where('statut_code', '!=', 2)
            ->update(['organisme_detachement_id' => $grmId]);

        // 3. Synchroniser sexe_code (1 = Homme, 2 = Dame)
        DB::table('militaires')
            ->whereNull('sexe_code')
            ->update([
                'sexe_code' => DB::raw("CASE WHEN LOWER(COALESCE(sexe, '')) LIKE 'f%' THEN 2 ELSE 1 END")
            ]);

        // 4. Rattacher les militaires sans région explicite au District de Bamako (Région 1)
        DB::table('militaires')
            ->whereNull('region_id')
            ->update(['region_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback destructeur nécessaire
    }
};
