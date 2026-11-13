<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Unite;
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
            Region::where('code', $code)->update([
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'chef_lieu' => $data['chef_lieu'],
            ]);
        }

        // 2. Coordonnées de repère des villes clés pour les unités (les plus précises en premier)
        $villesPoints = [
            'sangarébougou' => ['lat' => 12.7230, 'lng' => -7.9150, 'nom' => 'Sangarébougou'],
            'sanankoroba' => ['lat' => 12.4333, 'lng' => -7.9500, 'nom' => 'Sanankoroba'],
            "n'gabakoro" => ['lat' => 12.6950, 'lng' => -7.9050, 'nom' => "N'gabakoro-droit"],
            'ngabakoro' => ['lat' => 12.6950, 'lng' => -7.9050, 'nom' => "N'gabakoro-droit"],
            'diallakorodji' => ['lat' => 12.7280, 'lng' => -7.9850, 'nom' => 'Diallakorodji'],
            'dialakoroba' => ['lat' => 12.3500, 'lng' => -7.9500, 'nom' => 'Dialakoroba'],
            'koussané' => ['lat' => 14.6500, 'lng' => -11.0500, 'nom' => 'Koussané'],
            'koussane' => ['lat' => 14.6500, 'lng' => -11.0500, 'nom' => 'Koussané'],
            'sebekoro' => ['lat' => 13.2333, 'lng' => -9.2833, 'nom' => 'Sébékoro'],
            'sébékoro' => ['lat' => 13.2333, 'lng' => -9.2833, 'nom' => 'Sébékoro'],
            'sanoubougou' => ['lat' => 11.3200, 'lng' => -5.6700, 'nom' => 'Sanoubougou'],
            'manankoro' => ['lat' => 10.6833, 'lng' => -6.9500, 'nom' => 'Manankoro'],
            'sandaré' => ['lat' => 14.8145, 'lng' => -9.8970, 'nom' => 'Sandaré'],
            'sandare' => ['lat' => 14.8145, 'lng' => -9.8970, 'nom' => 'Sandaré'],
            'bamako' => ['lat' => 12.6392, 'lng' => -8.0029, 'nom' => 'Bamako'],
            'faladiè' => ['lat' => 12.5936, 'lng' => -7.9628, 'nom' => 'Faladiè'],
            'sénou' => ['lat' => 12.5358, 'lng' => -7.9287, 'nom' => 'Sénou'],
            'kalaban' => ['lat' => 12.5700, 'lng' => -8.0200, 'nom' => 'Kalaban-coro'],
            'baguinéda' => ['lat' => 12.6200, 'lng' => -7.7800, 'nom' => 'Baguinéda'],
            'kati' => ['lat' => 12.7442, 'lng' => -8.0726, 'nom' => 'Kati'],
            'koulikoro' => ['lat' => 12.8628, 'lng' => -7.5599, 'nom' => 'Koulikoro'],
            'dioïla' => ['lat' => 12.4833, 'lng' => -6.7833, 'nom' => 'Dioïla'],
            'nara' => ['lat' => 15.1667, 'lng' => -7.2833, 'nom' => 'Nara'],
            'banamba' => ['lat' => 13.5500, 'lng' => -7.4500, 'nom' => 'Banamba'],
            'kolokani' => ['lat' => 13.5667, 'lng' => -8.0333, 'nom' => 'Kolokani'],
            'kayes' => ['lat' => 14.4469, 'lng' => -11.4445, 'nom' => 'Kayes'],
            'kita' => ['lat' => 13.0344, 'lng' => -9.4895, 'nom' => 'Kita'],
            'kéniéba' => ['lat' => 12.8550, 'lng' => -11.2260, 'nom' => 'Kéniéba'],
            'bafoulabé' => ['lat' => 13.8064, 'lng' => -10.8322, 'nom' => 'Bafoulabé'],
            'diéma' => ['lat' => 14.5447, 'lng' => -9.1664, 'nom' => 'Diéma'],
            'nioro' => ['lat' => 15.2289, 'lng' => -9.5878, 'nom' => 'Nioro du Sahel'],
            'sikasso' => ['lat' => 11.3176, 'lng' => -5.6665, 'nom' => 'Sikasso'],
            'bougouni' => ['lat' => 11.4167, 'lng' => -7.4833, 'nom' => 'Bougouni'],
            'koutiala' => ['lat' => 12.3833, 'lng' => -5.4667, 'nom' => 'Koutiala'],
            'yanfolila' => ['lat' => 11.1667, 'lng' => -8.1500, 'nom' => 'Yanfolila'],
            'ségou' => ['lat' => 13.4317, 'lng' => -6.2157, 'nom' => 'Ségou'],
            'san' => ['lat' => 13.3034, 'lng' => -4.8956, 'nom' => 'San'],
            'niono' => ['lat' => 14.2500, 'lng' => -5.9833, 'nom' => 'Niono'],
            'mopti' => ['lat' => 14.4958, 'lng' => -4.1852, 'nom' => 'Mopti'],
            'sévaré' => ['lat' => 14.5333, 'lng' => -4.1000, 'nom' => 'Sévaré'],
            'bandiagara' => ['lat' => 14.3500, 'lng' => -3.6167, 'nom' => 'Bandiagara'],
            'douentza' => ['lat' => 15.0000, 'lng' => -2.9500, 'nom' => 'Douentza'],
            'djenné' => ['lat' => 13.9061, 'lng' => -4.5533, 'nom' => 'Djenné'],
            'bankass' => ['lat' => 13.6667, 'lng' => -3.6667, 'nom' => 'Bankass'],
            'koro' => ['lat' => 14.0667, 'lng' => -3.0833, 'nom' => 'Koro'],
            'tombouctou' => ['lat' => 16.7666, 'lng' => -3.0026, 'nom' => 'Tombouctou'],
            'goundam' => ['lat' => 16.4167, 'lng' => -3.6667, 'nom' => 'Goundam'],
            'diré' => ['lat' => 16.2667, 'lng' => -3.3833, 'nom' => 'Diré'],
            'niafunké' => ['lat' => 15.9333, 'lng' => -3.9833, 'nom' => 'Niafunké'],
            'gao' => ['lat' => 16.2717, 'lng' => -0.0447, 'nom' => 'Gao'],
            'ansongo' => ['lat' => 15.6667, 'lng' => 0.5000, 'nom' => 'Ansongo'],
            'bourem' => ['lat' => 16.9667, 'lng' => -0.3667, 'nom' => 'Bourem'],
            'kidal' => ['lat' => 18.4411, 'lng' => 1.4078, 'nom' => 'Kidal'],
            'tessalit' => ['lat' => 20.2572, 'lng' => 0.9889, 'nom' => 'Tessalit'],
            'ménaka' => ['lat' => 15.9182, 'lng' => 2.4022, 'nom' => 'Ménaka'],
        ];

        // Affectation automatique pour chaque unité selon son libellé
        $unites = Unite::with('region')->get();
        foreach ($unites as $unite) {
            $nomLower = mb_strtolower($unite->nom);
            $matched = null;

            foreach ($villesPoints as $keyword => $coords) {
                // Si mot court (ex: san, koro), vérifier mot entier avec délimiteurs
                if (mb_strlen($keyword) <= 4) {
                    if (preg_match('/(^|[\s\-\'\,\.\/])' . preg_quote($keyword, '/') . '($|[\s\-\'\,\.\/])/iu', $nomLower)) {
                        $matched = $coords;
                        break;
                    }
                } else {
                    if (str_contains($nomLower, $keyword)) {
                        $matched = $coords;
                        break;
                    }
                }
            }

            if ($matched) {
                // Petite variation aléatoire pour éviter que deux brigades de la même ville soient au même pixel
                $offsetLat = (crc32($unite->nom) % 100 - 50) / 10000;
                $offsetLng = (crc32($unite->nom . 'lng') % 100 - 50) / 10000;
                $unite->update([
                    'latitude' => $matched['lat'] + $offsetLat,
                    'longitude' => $matched['lng'] + $offsetLng,
                    'ville' => $matched['nom'],
                ]);
            } elseif ($unite->region && $unite->region->latitude) {
                // Par défaut : coordonnées de la région avec léger décalage
                $offsetLat = (crc32($unite->nom) % 150 - 75) / 10000;
                $offsetLng = (crc32($unite->nom . 'lng') % 150 - 75) / 10000;
                $unite->update([
                    'latitude' => $unite->region->latitude + $offsetLat,
                    'longitude' => $unite->region->longitude + $offsetLng,
                    'ville' => $unite->region->chef_lieu,
                ]);
            }
        }
    }
}
