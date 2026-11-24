<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Classification stricte des unités :
     * 1 = Propre à la Gendarmerie (Brigades territoriales, escadrons, groupements, compagnies...)
     * 2 = Unités / Structures de Détachement (Présidence, EMGA, Ministères, DOCS, ANSE, FAT-GRM...)
     *
     * Toutes les unités ont une région géographique (ex: Bamako, etc.) et des coordonnées GPS.
     * Les militaires en détachement sont rattachés à leur unité de détachement et à leur région d'implantation.
     */
    public function up(): void
    {
        // 1. Ajouter la colonne type_categorie sur unites
        if (!Schema::hasColumn('unites', 'type_categorie')) {
            Schema::table('unites', function (Blueprint $table) {
                $table->smallInteger('type_categorie')->default(1)->index();
            });
        }

        // 2. Créer ou s'assurer de l'existence des unités de détachement manquantes dans la région 1 (District de Bamako)
        $prmUnitId = DB::table('unites')->where('code', 'PRM')->value('id');
        if (!$prmUnitId) {
            $prmUnitId = DB::table('unites')->insertGetId([
                'region_id' => 1,
                'nom' => 'Présidence de la République (Palais de Koulouba)',
                'code' => 'PRM',
                'type' => 'Détachement / Ministère',
                'type_categorie' => 2,
                'ville' => 'Bamako (Koulouba)',
                'latitude' => 12.6847,
                'longitude' => -7.9972,
                'est_operationnelle' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $splpalUnitId = DB::table('unites')->where('code', 'SPLPAL')->value('id');
        if (!$splpalUnitId) {
            $splpalUnitId = DB::table('unites')->insertGetId([
                'region_id' => 1,
                'nom' => 'Secrétariat Permanent de Lutte contre la Prolifération des Armes Légères (SPLPAL)',
                'code' => 'SPLPAL',
                'type' => 'Détachement / Ministère',
                'type_categorie' => 2,
                'ville' => 'Bamako',
                'latitude' => 12.6392,
                'longitude' => -8.0029,
                'est_operationnelle' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $mesrsUnitId = DB::table('unites')->where('code', 'MESRS')->value('id');
        if (!$mesrsUnitId) {
            $mesrsUnitId = DB::table('unites')->insertGetId([
                'region_id' => 1,
                'nom' => "Ministère de l'Enseignement Supérieur et de la Recherche Scientifique",
                'code' => 'MESRS',
                'type' => 'Détachement / Ministère',
                'type_categorie' => 2,
                'ville' => 'Bamako',
                'latitude' => 12.6392,
                'longitude' => -8.0029,
                'est_operationnelle' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Définir type_categorie = 2 pour toutes les unités de détachement
        $detacheUniteIds = [
            $prmUnitId, $splpalUnitId, $mesrsUnitId,
            292, // EMGA
            293, // ANSE
            294, // DOCS
            295, // FAT
            297, // EMPPR
            298, // Primature
            299, // MDAC
            300, // MSPC
            301, // MJGS
            302, // MATD
            303, // MAECI
            304, // DSM
            305, // DJM
            306, // DSMil
            307, // DEM
            308, // DRHA
            310, // DGPC
            311, // DGPTS
            313, // OCLEI
            315, // BIS
            316, // EGM
            317, // EMP
            318, // BVG
            319, // CCM
            320, // CNT / Assemblée
            321, // CCGCC
            322, // MEF
            323, // MMEE
            324, // MJS
            325, // FIRA
        ];

        DB::table('unites')->whereIn('id', $detacheUniteIds)->update(['type_categorie' => 2]);
        DB::table('unites')->where('type', 'Détachement / Ministère')->update(['type_categorie' => 2]);
        DB::table('unites')->whereNotIn('id', $detacheUniteIds)->where('type', '!=', 'Détachement / Ministère')->update(['type_categorie' => 1]);

        // 4. Associer chaque militaire détaché à son unité de détachement réelle et à sa région
        $mappingsOrganismeToUnite = [
            1  => $prmUnitId,    // PRM
            3  => 297,           // EMPPR (Présidence)
            29 => 292,           // EMGA (448)
            34 => 295,           // FAT (241)
            4  => 294,           // DOCS (133)
            44 => 306,           // DSMil (55)
            41 => 293,           // ANSE (45)
            15 => 301,           // MJGS (30)
            12 => 300,           // MSPC (26)
            10 => 302,           // MATD (23)
            46 => $splpalUnitId, // SPLPAL (22)
            5  => 298,           // PM / Primature (17)
            36 => 304,           // DSM (15)
            19 => 303,           // MAECI (9)
            33 => 313,           // OCLEI (9)
            11 => 299,           // MDAC (7)
            20 => 308,           // DRHA (5)
            26 => 307,           // DEM (5)
            14 => 311,           // DGPTS (4)
            32 => 324,           // MJS (3)
            42 => 305,           // DJM (2)
            6  => 322,           // MEF (2)
            45 => $mesrsUnitId,  // MESRS (1)
            48 => 323,           // MMEE (1)
            31 => 323,           // MMEEX (1)
        ];

        // Charger les noms des unités
        $unitesNames = DB::table('unites')->pluck('nom', 'id');
        $regionBamakoNom = DB::table('regions')->where('id', 1)->value('nom') ?? 'DISTRICT DE BAMAKO';

        $detaches = DB::table('militaires')
            ->where(function ($q) {
                $q->where('position_actuelle', 'En détachement')
                  ->orWhere('position_id', 26)
                  ->orWhere('statut', 'détaché')
                  ->orWhere('statut_code', 2);
            })
            ->get(['id', 'organisme_detachement_id']);

        $affectationsToInsert = [];

        foreach ($detaches as $m) {
            $targetUniteId = $mappingsOrganismeToUnite[$m->organisme_detachement_id] ?? $prmUnitId;
            $uniteNom = $unitesNames[$targetUniteId] ?? 'Structure de Détachement';

            DB::table('militaires')->where('id', $m->id)->update([
                'region_id' => 1,
                'region' => $regionBamakoNom,
                'unite_id' => $targetUniteId,
                'unite' => $uniteNom,
            ]);

            $affectationsToInsert[] = [
                'militaire_id' => $m->id,
                'region_id' => 1,
                'region' => $regionBamakoNom,
                'unite_id' => $targetUniteId,
                'unite' => $uniteNom,
                'date_mutation' => '2024-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Réinsérer les affectations propres de détachement par lots
        DB::table('affectations')->whereIn('militaire_id', $detaches->pluck('id'))->delete();
        foreach (array_chunk($affectationsToInsert, 250) as $chunk) {
            DB::table('affectations')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('unites', 'type_categorie')) {
            Schema::table('unites', function (Blueprint $table) {
                $table->dropColumn('type_categorie');
            });
        }
    }
};
