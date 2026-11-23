<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Unite;
use App\Http\Controllers\CarteController;
use Illuminate\Database\Seeder;

class CoordinatesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Coordonnées des Régions
        $regionsCoords = [
            'R1' => ['lat' => 12.6392, 'lng' => -8.0029, 'chef_lieu' => 'Bamako'],
            'R2' => ['lat' => 14.4469, 'lng' => -11.4445, 'chef_lieu' => 'Kayes'],
            'R3' => ['lat' => 12.8628, 'lng' => -7.5599, 'chef_lieu' => 'Koulikoro'],
            'R4' => ['lat' => 11.3176, 'lng' => -5.6665, 'chef_lieu' => 'Sikasso'],
            'R5' => ['lat' => 13.4317, 'lng' => -6.2157, 'chef_lieu' => 'Ségou'],
            'R6' => ['lat' => 14.4958, 'lng' => -4.1852, 'chef_lieu' => 'Mopti'],
            'R7' => ['lat' => 16.7666, 'lng' => -3.0026, 'chef_lieu' => 'Tombouctou'],
            'R8' => ['lat' => 16.2717, 'lng' => -0.0447, 'chef_lieu' => 'Gao'],
            'R9' => ['lat' => 18.4411, 'lng' => 1.4078, 'chef_lieu' => 'Kidal'],
            'R10' => ['lat' => 15.2289, 'lng' => -9.5878, 'chef_lieu' => 'Nioro du Sahel'],
            'R11' => ['lat' => 15.9182, 'lng' => 2.4022, 'chef_lieu' => 'Ménaka'],
            'R12' => ['lat' => 13.0350, 'lng' => -9.4860, 'chef_lieu' => 'Kita'],
            'R13' => ['lat' => 12.4834, 'lng' => -6.7827, 'chef_lieu' => 'Dioïla'],
            'R14' => ['lat' => 15.1650, 'lng' => -7.2830, 'chef_lieu' => 'Nara'],
            'R15' => ['lat' => 11.4150, 'lng' => -7.4820, 'chef_lieu' => 'Bougouni'],
            'R16' => ['lat' => 12.3830, 'lng' => -5.4680, 'chef_lieu' => 'Koutiala'],
            'R17' => ['lat' => 13.3030, 'lng' => -4.8930, 'chef_lieu' => 'San'],
            'R18' => ['lat' => 14.9970, 'lng' => -2.9480, 'chef_lieu' => 'Douentza'],
            'R19' => ['lat' => 14.3500, 'lng' => -3.6180, 'chef_lieu' => 'Bandiagara'],
            'R20' => ['lat' => 22.6700, 'lng' => -3.9800, 'chef_lieu' => 'Taoudénit'],
            'DGGN' => ['lat' => 12.6515, 'lng' => -8.0142, 'chef_lieu' => 'Bamako (Camp I)'],
            'GSIGN' => ['lat' => 12.6321, 'lng' => -8.0185, 'chef_lieu' => 'Bamako'],
            'CDURM' => ['lat' => 12.6412, 'lng' => -7.9945, 'chef_lieu' => 'Bamako'],
            'CDUSF' => ['lat' => 12.6378, 'lng' => -8.0075, 'chef_lieu' => 'Bamako'],
            'CDECF' => ['lat' => 12.6052, 'lng' => -7.9782, 'chef_lieu' => 'Faladiè (Bamako)'],
            'INSPECTION' => ['lat' => 12.6515, 'lng' => -8.0142, 'chef_lieu' => 'Bamako'],
            'SIJ' => ['lat' => 12.6480, 'lng' => -8.0089, 'chef_lieu' => 'Camp I (Bamako)'],
            'DETACHEMENT' => ['lat' => 12.6392, 'lng' => -8.0029, 'chef_lieu' => 'Bamako / Déploiement national'],
        ];

        foreach ($regionsCoords as $code => $data) {
            $nom = (str_starts_with($code, 'R') && is_numeric(substr($code, 1))) ? 'REGION N°' . substr($code, 1) : $code;
            $ordre = (str_starts_with($code, 'R') && is_numeric(substr($code, 1))) ? (int) substr($code, 1) : 99;
            Region::updateOrCreate(
                ['code' => $code],
                [
                    'nom' => $nom,
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'chef_lieu' => $data['chef_lieu'],
                    'ordre' => $ordre,
                ]
            );
        }

        $regionsByCode = Region::all()->keyBy('code');

        // 2. Réassignations des unités égarées vers leur véritable région administrative des 20 régions du Mali
        $reassignUnits = [
            // R14 (Nara)
            103 => 'R14', // Guiré
            105 => 'R14', // PSIG Ballé
            129 => 'R14', // Escadron 3/7 Ballé
            131 => 'R14', // BT Ballé
            132 => 'R14', // BT Fallou
            133 => 'R14', // BT Dogofry
            102 => 'R14', // BT Mourdiah

            // R13 (Dioïla)
            93 => 'R13',  // BR Dioïla
            123 => 'R13', // PSIG Dioïla
            95 => 'R13',  // BT Fana
            96 => 'R13',  // BT Massigui
            124 => 'R13', // BT Ména
            125 => 'R13', // BT Beleko
            126 => 'R13', // BT Nangola
            127 => 'R13', // BT Banco

            // R17 (San)
            195 => 'R17', // BT Tominian
            196 => 'R17', // BT Mandiakuy
            197 => 'R17', // BT Kimparana

            // R12 (Kita)
            66 => 'R12', // BT Sagabari
            68 => 'R12', // BT Seféto

            // R16 (Koutiala)
            159 => 'R16', // BT Koury
            160 => 'R16', // BT Yorosso

            // R15 (Bougouni)
            150 => 'R15', // BT Kolondièba
            162 => 'R15', // Escadron Selingué
            170 => 'R15', // BT Kalana
            171 => 'R15', // BT Sélingué

            // R18 (Douentza)
            214 => 'R18', // BT Boni
            215 => 'R18', // BT Hombori

            // R19 (Bandiagara)
            225 => 'R19', // Escadron Diallassagou

            // R20 (Taoudénit)
            243 => 'R20', // EM Taoudénit
        ];

        foreach ($reassignUnits as $unitId => $toRegCode) {
            $u = Unite::find($unitId);
            if ($u && isset($regionsByCode[$toRegCode])) {
                $targetRegId = $regionsByCode[$toRegCode]->id;
                $u->updateQuietly([
                    'region_id' => $targetRegId,
                ]);
                \App\Models\Militaire::where('unite_id', $unitId)->update(['region_id' => $targetRegId]);
            }
        }

        \Illuminate\Support\Facades\DB::statement("
            UPDATE militaires m
            SET region_id = u.region_id
            FROM unites u
            WHERE m.unite_id = u.id
              AND u.region_id IS NOT NULL
              AND (m.region_id IS NULL OR m.region_id != u.region_id)
        ");

        // 3. Coordonnées précises des localités, cercles et communes du Mali
        $localitesPoints = [
            // Bamako (R1) - calibré strictement dans le polygone du District
            'bamako-coura' => ['lat' => 12.6450, 'lng' => -8.0050, 'nom' => 'Bamako-Coura'],
            'dogodouman' => ['lat' => 12.6550, 'lng' => -8.0450, 'nom' => 'Dogodouman'],
            'ouenzindougou' => ['lat' => 12.6050, 'lng' => -8.0400, 'nom' => 'Ouenzindougou'],
            'mountougoula' => ['lat' => 12.5850, 'lng' => -7.9550, 'nom' => 'Mountougoula'],
            'faladiè' => ['lat' => 12.5910, 'lng' => -7.9660, 'nom' => 'Faladiè'],
            'sénou' => ['lat' => 12.5650, 'lng' => -7.9600, 'nom' => 'Sénou'],
            "n'golobougou" => ['lat' => 12.5700, 'lng' => -7.9900, 'nom' => "N'Golobougou"],
            'samaya' => ['lat' => 12.6000, 'lng' => -8.0300, 'nom' => 'Samaya'],
            'bamako' => ['lat' => 12.6392, 'lng' => -8.0029, 'nom' => 'Bamako'],

            // Kayes (R2)
            'kayes ndi' => ['lat' => 14.4451, 'lng' => -11.4490, 'nom' => 'Kayes Ndi'],
            'félou' => ['lat' => 14.3500, 'lng' => -11.3500, 'nom' => 'Félou'],
            'manantali' => ['lat' => 13.2000, 'lng' => -10.4333, 'nom' => 'Manantali'],
            'kénièba' => ['lat' => 12.8564, 'lng' => -11.2764, 'nom' => 'Kénièba'],
            'kéniéba' => ['lat' => 12.8564, 'lng' => -11.2764, 'nom' => 'Kénièba'],
            'yélimané' => ['lat' => 15.0500, 'lng' => -10.5667, 'nom' => 'Yélimané'],
            'sadiola' => ['lat' => 13.8833, 'lng' => -11.7000, 'nom' => 'Sadiola'],
            'diamou' => ['lat' => 14.0667, 'lng' => -11.3167, 'nom' => 'Diamou'],
            'toukoto' => ['lat' => 13.4333, 'lng' => -10.0500, 'nom' => 'Toukoto'],
            'bafoulabé' => ['lat' => 13.8072, 'lng' => -10.8319, 'nom' => 'Bafoulabé'],
            'diboli' => ['lat' => 14.4550, 'lng' => -12.1800, 'nom' => 'Diboli'],
            'kirané' => ['lat' => 14.9333, 'lng' => -10.8500, 'nom' => 'Kirané'],
            'aourou' => ['lat' => 14.7333, 'lng' => -11.6667, 'nom' => 'Aourou'],
            'koussané' => ['lat' => 14.6502, 'lng' => -11.0455, 'nom' => 'Koussané'],
            'diadioumbéra' => ['lat' => 14.5333, 'lng' => -10.7167, 'nom' => 'Diadioumbéra'],
            'kouniakary' => ['lat' => 14.5667, 'lng' => -11.1667, 'nom' => 'Kouniakary'],
            'kouroukoto' => ['lat' => 12.6800, 'lng' => -11.3800, 'nom' => 'Kouroukoto'],
            'dialafara' => ['lat' => 12.6333, 'lng' => -11.2333, 'nom' => 'Dialafara'],
            'faraba' => ['lat' => 12.8000, 'lng' => -11.1500, 'nom' => 'Faraba'],
            'mahinamine' => ['lat' => 13.7667, 'lng' => -10.8500, 'nom' => 'Mahinamine'],
            'oussoubidiagna' => ['lat' => 13.6833, 'lng' => -10.4333, 'nom' => 'Oussoubidiagna'],
            'kayes' => ['lat' => 14.4447, 'lng' => -11.4435, 'nom' => 'Kayes'],

            // Koulikoro (R3)
            "n'gabakoro" => ['lat' => 12.6911, 'lng' => -7.9029, 'nom' => "N'gabakoro"],
            'sangarébougou' => ['lat' => 12.7209, 'lng' => -7.9164, 'nom' => 'Sangarébougou'],
            'diallakorodji' => ['lat' => 12.7315, 'lng' => -7.9876, 'nom' => 'Diallakorodji'],
            'baguinéda' => ['lat' => 12.6225, 'lng' => -7.7759, 'nom' => 'Baguinéda'],
            'kalaban-coro' => ['lat' => 12.5680, 'lng' => -8.0231, 'nom' => 'Kalaban-coro'],
            'dialakoroba' => ['lat' => 12.3453, 'lng' => -7.9534, 'nom' => 'Dialakoroba'],
            'sanankoroba' => ['lat' => 12.4349, 'lng' => -7.9544, 'nom' => 'Sanankoroba'],
            'kati-faladié' => ['lat' => 12.7462, 'lng' => -8.0731, 'nom' => 'Kati-Faladié'],
            'kati' => ['lat' => 12.7414, 'lng' => -8.0760, 'nom' => 'Kati'],
            'kangaba' => ['lat' => 11.9333, 'lng' => -8.4167, 'nom' => 'Kangaba'],
            'ouéléssebougou' => ['lat' => 12.2500, 'lng' => -7.9500, 'nom' => 'Ouéléssebougou'],
            'kourouba' => ['lat' => 11.9500, 'lng' => -8.3167, 'nom' => 'Kourouba'],
            'siby' => ['lat' => 12.3833, 'lng' => -8.3333, 'nom' => 'Siby'],
            'naréna' => ['lat' => 12.2167, 'lng' => -8.5333, 'nom' => 'Naréna'],
            'tougouni' => ['lat' => 13.6333, 'lng' => -7.9500, 'nom' => 'Tougouni'],
            'néguela' => ['lat' => 12.8500, 'lng' => -8.4000, 'nom' => 'Néguela'],
            'dio' => ['lat' => 12.8833, 'lng' => -8.2833, 'nom' => 'Dio'],
            'didiéni' => ['lat' => 13.5833, 'lng' => -8.2500, 'nom' => 'Didiéni'],
            'boron' => ['lat' => 13.8833, 'lng' => -7.5500, 'nom' => 'Boron'],
            'niaouléni' => ['lat' => 12.0167, 'lng' => -8.6500, 'nom' => 'Niaouléni'],
            'nossombougou' => ['lat' => 13.1167, 'lng' => -8.0833, 'nom' => 'Nossombougou'],
            'kolokani' => ['lat' => 13.5662, 'lng' => -8.0307, 'nom' => 'Kolokani'],
            'banamba' => ['lat' => 13.5504, 'lng' => -7.4454, 'nom' => 'Banamba'],
            'niamina' => ['lat' => 13.3167, 'lng' => -6.9833, 'nom' => 'Niamina'],
            'koula' => ['lat' => 13.3667, 'lng' => -7.5000, 'nom' => 'Koula'],
            'koulikoro' => ['lat' => 12.8620, 'lng' => -7.5559, 'nom' => 'Koulikoro'],

            // Sikasso (R4)
            'kadiolo' => ['lat' => 10.5500, 'lng' => -5.7500, 'nom' => 'Kadiolo'],
            'nièna' => ['lat' => 11.7333, 'lng' => -6.1833, 'nom' => 'Nièna'],
            'koumantou' => ['lat' => 11.4333, 'lng' => -6.5500, 'nom' => 'Koumantou'],
            'kignan' => ['lat' => 11.8333, 'lng' => -5.8667, 'nom' => 'Kignan'],
            'medine' => ['lat' => 11.3196, 'lng' => -5.6697, 'nom' => 'Médine (Sikasso)'],
            'sanoubougou' => ['lat' => 11.3171, 'lng' => -5.6676, 'nom' => 'Sanoubougou'],
            'sikasso' => ['lat' => 11.3161, 'lng' => -5.6680, 'nom' => 'Sikasso'],

            // Ségou (R5)
            'baraouéli' => ['lat' => 13.0800, 'lng' => -6.8300, 'nom' => 'Baraouéli'],
            'bla' => ['lat' => 12.9500, 'lng' => -5.7500, 'nom' => 'Bla'],
            'tamani' => ['lat' => 13.2300, 'lng' => -6.7000, 'nom' => 'Tamani'],
            'cinzana' => ['lat' => 13.2600, 'lng' => -5.9500, 'nom' => 'Cinzana'],
            'pélengana' => ['lat' => 13.4000, 'lng' => -6.1700, 'nom' => 'Pélengana'],
            'macina' => ['lat' => 13.9600, 'lng' => -5.3500, 'nom' => 'Macina'],
            'saye' => ['lat' => 13.6700, 'lng' => -5.7300, 'nom' => 'Saye'],
            'niono' => ['lat' => 14.2497, 'lng' => -5.9797, 'nom' => 'Niono'],
            'markala' => ['lat' => 13.6800, 'lng' => -6.0700, 'nom' => 'Markala'],
            'ségou' => ['lat' => 13.4325, 'lng' => -6.2148, 'nom' => 'Ségou'],

            // Mopti (R6)
            'sévaré' => ['lat' => 14.5347, 'lng' => -4.0978, 'nom' => 'Sévaré'],
            'konna' => ['lat' => 14.9500, 'lng' => -3.8900, 'nom' => 'Konna'],
            'djenné' => ['lat' => 13.9046, 'lng' => -4.5487, 'nom' => 'Djenné'],
            'ténenkou' => ['lat' => 14.4500, 'lng' => -4.9200, 'nom' => 'Ténenkou'],
            'youwarou' => ['lat' => 15.3300, 'lng' => -4.4300, 'nom' => 'Youwarou'],
            'mopti' => ['lat' => 14.4936, 'lng' => -4.1852, 'nom' => 'Mopti'],

            // Tombouctou (R7)
            'gossi' => ['lat' => 15.8000, 'lng' => -1.3000, 'nom' => 'Gossi'],
            'gourma - rharous' => ['lat' => 16.8800, 'lng' => -1.9200, 'nom' => 'Gourma - Rharous'],
            'rharous' => ['lat' => 16.8800, 'lng' => -1.9200, 'nom' => 'Rharous'],
            'goundam' => ['lat' => 16.4188, 'lng' => -3.6643, 'nom' => 'Goundam'],
            'diré' => ['lat' => 16.2645, 'lng' => -3.3881, 'nom' => 'Diré'],
            'niafunké' => ['lat' => 15.9356, 'lng' => -3.9863, 'nom' => 'Niafunké'],
            'léré' => ['lat' => 15.7100, 'lng' => -4.9100, 'nom' => 'Léré'],
            'tonka' => ['lat' => 16.3200, 'lng' => -3.7800, 'nom' => 'Tonka'],
            'tombouctou' => ['lat' => 16.7700, 'lng' => -2.9993, 'nom' => 'Tombouctou'],

            // Gao (R8)
            'bourem' => ['lat' => 16.9692, 'lng' => -0.3701, 'nom' => 'Bourem'],
            'ansongo' => ['lat' => 15.6670, 'lng' => 0.4962, 'nom' => 'Ansongo'],
            'labbézanga' => ['lat' => 15.0100, 'lng' => 0.7300, 'nom' => 'Labbézanga'],
            'gao' => ['lat' => 16.2700, 'lng' => -0.0400, 'nom' => 'Gao'],

            // Kidal (R9)
            'tessalit' => ['lat' => 20.2554, 'lng' => 0.9921, 'nom' => 'Tessalit'],
            'kidal' => ['lat' => 18.4410, 'lng' => 1.4083, 'nom' => 'Kidal'],

            // Nioro (R10)
            'diéma' => ['lat' => 14.5479, 'lng' => -9.1660, 'nom' => 'Diéma'],
            'sandaré' => ['lat' => 14.8096, 'lng' => -9.8949, 'nom' => 'Sandaré'],
            'nioro' => ['lat' => 15.2300, 'lng' => -9.5900, 'nom' => 'Nioro du Sahel'],

            // Ménaka (R11)
            'ménaka' => ['lat' => 15.9180, 'lng' => 2.4020, 'nom' => 'Ménaka'],

            // Kita (R12)
            'sebekoro' => ['lat' => 13.2373, 'lng' => -9.2853, 'nom' => 'Sébékoro'],
            'sagabari' => ['lat' => 13.3333, 'lng' => -9.7500, 'nom' => 'Sagabari'],
            'seféto' => ['lat' => 13.7500, 'lng' => -10.0500, 'nom' => 'Seféto'],
            'kita' => ['lat' => 13.0350, 'lng' => -9.4860, 'nom' => 'Kita'],

            // Dioïla (R13)
            'dioïla' => ['lat' => 12.4834, 'lng' => -6.7827, 'nom' => 'Dioïla'],
            'dioila' => ['lat' => 12.4834, 'lng' => -6.7827, 'nom' => 'Dioïla'],
            'fana' => ['lat' => 12.7667, 'lng' => -6.9833, 'nom' => 'Fana'],
            'massigui' => ['lat' => 12.1833, 'lng' => -6.9500, 'nom' => 'Massigui'],
            'ména' => ['lat' => 12.5500, 'lng' => -7.1000, 'nom' => 'Ména'],
            'beleko' => ['lat' => 12.4333, 'lng' => -6.5500, 'nom' => 'Beleko'],
            'nangola' => ['lat' => 12.7500, 'lng' => -6.7167, 'nom' => 'Nangola'],
            'banco' => ['lat' => 12.5167, 'lng' => -6.9000, 'nom' => 'Banco'],

            // Nara (R14)
            'ballé' => ['lat' => 15.3333, 'lng' => -7.7833, 'nom' => 'Ballé'],
            'fallou' => ['lat' => 14.4333, 'lng' => -7.8667, 'nom' => 'Fallou'],
            'dogofry' => ['lat' => 14.5167, 'lng' => -7.3167, 'nom' => 'Dogofry'],
            'guiré' => ['lat' => 14.8667, 'lng' => -7.1333, 'nom' => 'Guiré'],
            'mourdiah' => ['lat' => 14.4667, 'lng' => -7.4667, 'nom' => 'Mourdiah'],
            'nara' => ['lat' => 15.1650, 'lng' => -7.2830, 'nom' => 'Nara'],

            // Bougouni (R15)
            'manankoro' => ['lat' => 10.6816, 'lng' => -6.9511, 'nom' => 'Manankoro'],
            'yanfolila' => ['lat' => 11.1636, 'lng' => -8.1486, 'nom' => 'Yanfolila'],
            'kalana' => ['lat' => 10.7833, 'lng' => -8.1833, 'nom' => 'Kalana'],
            'sélingué' => ['lat' => 11.6900, 'lng' => -8.2300, 'nom' => 'Sélingué'],
            'selingué' => ['lat' => 11.6900, 'lng' => -8.2300, 'nom' => 'Sélingué'],
            'kolondièba' => ['lat' => 11.0833, 'lng' => -6.8833, 'nom' => 'Kolondièba'],
            'bougouni' => ['lat' => 11.4150, 'lng' => -7.4820, 'nom' => 'Bougouni'],

            // Koutiala (R16)
            'koury' => ['lat' => 12.1833, 'lng' => -4.7167, 'nom' => 'Koury'],
            'yorosso' => ['lat' => 12.3500, 'lng' => -4.7833, 'nom' => 'Yorosso'],
            'koutiala' => ['lat' => 12.3830, 'lng' => -5.4680, 'nom' => 'Koutiala'],

            // San (R17)
            'tominian' => ['lat' => 13.2800, 'lng' => -4.6000, 'nom' => 'Tominian'],
            'mandiakuy' => ['lat' => 13.0600, 'lng' => -4.6800, 'nom' => 'Mandiakuy'],
            'kimparana' => ['lat' => 12.8300, 'lng' => -4.9200, 'nom' => 'Kimparana'],
            'san' => ['lat' => 13.3030, 'lng' => -4.8930, 'nom' => 'San'],

            // Douentza (R18)
            'boni' => ['lat' => 15.1100, 'lng' => -2.1800, 'nom' => 'Boni'],
            'hombori' => ['lat' => 15.2800, 'lng' => -1.7000, 'nom' => 'Hombori'],
            'douentza' => ['lat' => 14.9970, 'lng' => -2.9480, 'nom' => 'Douentza'],

            // Bandiagara (R19)
            'bankass' => ['lat' => 13.6647, 'lng' => -3.6714, 'nom' => 'Bankass'],
            'koro' => ['lat' => 14.0650, 'lng' => -3.0830, 'nom' => 'Koro'],
            'diallassagou' => ['lat' => 13.9500, 'lng' => -3.5300, 'nom' => 'Diallassagou'],
            'bandiagara' => ['lat' => 14.3500, 'lng' => -3.6180, 'nom' => 'Bandiagara'],

            // Taoudénit (R20)
            'taoudénit' => ['lat' => 22.6700, 'lng' => -3.9800, 'nom' => 'Taoudénit'],
        ];

        // Trier par longueur de clé décroissante
        uksort($localitesPoints, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        // Affectation automatique pour chaque unité selon son libellé
        $unites = Unite::with('region')->get();
        foreach ($unites as $unite) {
            $nomLower = mb_strtolower($unite->nom, 'UTF-8');
            $matched = null;

            foreach ($localitesPoints as $loc => $coords) {
                $pattern = '/\b' . preg_quote($loc, '/') . '\b/u';
                if (preg_match($pattern, $nomLower)) {
                    $matched = $coords;
                    break;
                }
            }

            // Si c'est un État-Major de région sans ville spécifique, chef-lieu de sa région
            if (!$matched && $unite->region) {
                $rNomLower = mb_strtolower($unite->region->nom, 'UTF-8');
                foreach ($localitesPoints as $loc => $coords) {
                    $pattern = '/\b' . preg_quote($loc, '/') . '\b/u';
                    if (preg_match($pattern, $rNomLower)) {
                        $matched = $coords;
                        break;
                    }
                }
            }

            // Bamako : conserver les coordonnées actuelles si pas de correspondance spécifique
            if (!$matched && $unite->region_id == 1) {
                continue;
            }

            if ($matched) {
                $unite->updateQuietly([
                    'latitude' => $matched['lat'],
                    'longitude' => $matched['lng'],
                    'ville' => $matched['nom'],
                ]);
            } elseif ($unite->region && $unite->region->latitude) {
                $unite->updateQuietly([
                    'latitude' => $unite->region->latitude,
                    'longitude' => $unite->region->longitude,
                    'ville' => $unite->region->chef_lieu,
                ]);
            }
        }

        CarteController::flush();
    }
}
