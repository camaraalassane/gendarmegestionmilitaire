<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Mappage officiel des 20 Régions Administratives et Territoriales du Mali
     * (19 Régions + 1 District de Bamako) selon la nomenclature officielle DGGN.
     */
    private const REGIONS_OFFICIELLES = [
        'R1'  => 'DISTRICT DE BAMAKO',
        'R2'  => 'KAYES',
        'R3'  => 'KOULIKORO',
        'R4'  => 'SIKASSO',
        'R5'  => 'SÉGOU',
        'R6'  => 'MOPTI',
        'R7'  => 'TOMBOUCTOU',
        'R8'  => 'GAO',
        'R9'  => 'KIDAL',
        'R10' => 'NIORO DU SAHEL',
        'R11' => 'MÉNAKA',
        'R12' => 'KITA',
        'R13' => 'DIOÏLA',
        'R14' => 'NARA',
        'R15' => 'BOUGOUNI',
        'R16' => 'KOUTIALA',
        'R17' => 'SAN',
        'R18' => 'DOUENTZA',
        'R19' => 'BANDIAGARA',
        'R20' => 'TAOUDÉNIT',
    ];

    public function up(): void
    {
        foreach (self::REGIONS_OFFICIELLES as $code => $nomOfficiel) {
            $reg = DB::table('regions')->where('code', $code)->first();
            if ($reg) {
                $ancienNom = $reg->nom;
                DB::table('regions')->where('id', $reg->id)->update([
                    'nom' => $nomOfficiel,
                    'updated_at' => now(),
                ]);

                // Synchroniser le champ textuel militaires.region pour cohérence complète
                DB::table('militaires')
                    ->where(function ($q) use ($reg, $ancienNom, $code) {
                        $q->where('region_id', $reg->id)
                          ->orWhere('region', $ancienNom)
                          ->orWhere('region', $code);
                    })
                    ->update(['region' => $nomOfficiel]);
            }
        }

        Cache::forget('ref:regions_spa');
        Cache::forget('ref:regions_all');
        \App\Models\Militaire::incrementCacheVersion();
    }

    public function down(): void
    {
        for ($i = 2; $i <= 20; $i++) {
            $code = "R{$i}";
            $nomOld = "REGION N°{$i}";
            DB::table('regions')->where('code', $code)->update(['nom' => $nomOld]);
        }
    }
};
