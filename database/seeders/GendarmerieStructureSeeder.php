<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Region;
use App\Models\Unite;
use Illuminate\Database\Seeder;

class GendarmerieStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Définition des Régions et Grands Commandements
        $regionsData = [
            ['code' => 'R1', 'nom' => 'REGION N°1', 'description' => 'District de Bamako et périphérie', 'ordre' => 1],
            ['code' => 'R2', 'nom' => 'REGION N°2', 'description' => 'Région de Kayes / Kita / Kéniéba', 'ordre' => 2],
            ['code' => 'R3', 'nom' => 'REGION N°3', 'description' => 'Région de Koulikoro / Kati / Nara / Dioïla', 'ordre' => 3],
            ['code' => 'R4', 'nom' => 'REGION N°4', 'description' => 'Région de Sikasso / Bougouni / Koutiala', 'ordre' => 4],
            ['code' => 'R5', 'nom' => 'REGION N°5', 'description' => 'Région de Ségou / San', 'ordre' => 5],
            ['code' => 'R6', 'nom' => 'REGION N°6', 'description' => 'Région de Mopti / Sévaré / Bandiagara / Douentza', 'ordre' => 6],
            ['code' => 'R7', 'nom' => 'REGION N°7', 'description' => 'Région de Tombouctou / Taoudénit', 'ordre' => 7],
            ['code' => 'R8', 'nom' => 'REGION N°8', 'description' => 'Région de Gao / Ansongo / Bourem', 'ordre' => 8],
            ['code' => 'R9', 'nom' => 'REGION N°9', 'description' => 'Région de Kidal / Tessalit', 'ordre' => 9],
            ['code' => 'R10', 'nom' => 'REGION N°10', 'description' => 'Région de Nioro du Sahel / Diéma', 'ordre' => 10],
            ['code' => 'R11', 'nom' => 'REGION N°11', 'description' => 'Région de Ménaka', 'ordre' => 11],
            ['code' => 'DGGN', 'nom' => 'DGGN', 'description' => 'Direction Générale de la Gendarmerie Nationale', 'ordre' => 12],
            ['code' => 'GSIGN', 'nom' => 'GSIGN', 'description' => 'Groupement Spécial d\'Intervention de la Gendarmerie Nationale', 'ordre' => 13],
            ['code' => 'CDURM', 'nom' => 'CDURM', 'description' => 'Commandement des Unités des Réserves Ministérielles', 'ordre' => 14],
            ['code' => 'CDUSF', 'nom' => 'CDUSF', 'description' => 'Commandement des Unités de surveillance des Frontières (GARSI)', 'ordre' => 15],
            ['code' => 'CDECF', 'nom' => 'CDECF', 'description' => 'Commandement des Ecoles et des Centres de Formation', 'ordre' => 16],
            ['code' => 'INSPECTION', 'nom' => 'INSPECTION', 'description' => 'Inspection de la Gendarmerie Nationale', 'ordre' => 17],
            ['code' => 'SIJ', 'nom' => 'SIJ', 'description' => 'Service d\'Investigations Judiciaires', 'ordre' => 18],
            ['code' => 'DETACHEMENT', 'nom' => 'DETACHEMENT', 'description' => 'Personnels détachés auprès d\'institutions et autres forces', 'ordre' => 19],
        ];

        $regionsMap = [];
        foreach ($regionsData as $data) {
            $reg = Region::firstOrCreate(['code' => $data['code']], $data);
            $regionsMap[$data['code']] = $reg->id;
            $regionsMap[$data['nom']] = $reg->id;
        }

        // Helper pour déterminer le type d'unité
        $detectType = function (string $nom) {
            $lower = mb_strtolower($nom);
            if (str_contains($lower, 'brigade de recherches')) return 'Brigade de Recherches';
            if (str_contains($lower, 'brigade territoriale')) return 'Brigade Territoriale';
            if (str_contains($lower, 'transports aériens')) return 'Brigade des Transports Aériens';
            if (str_contains($lower, 'fluviale')) return 'Brigade Fluviale';
            if (str_contains($lower, 'sécurité routière')) return 'Brigade de Sécurité Routière';
            if (str_contains($lower, 'escadron')) return 'Escadron';
            if (str_contains($lower, 'peloton') || str_contains($lower, 'psig')) return 'Peloton PSIG';
            if (str_contains($lower, 'etat-major') || str_contains($lower, 'état-major')) return 'État-Major';
            if (str_contains($lower, 'groupement')) return 'Groupement';
            if (str_contains($lower, 'compagnie')) return 'Compagnie';
            if (str_contains($lower, 'sous-direction')) return 'Sous-Direction';
            if (str_contains($lower, 'direction') || str_contains($lower, 'inspection') || str_contains($lower, 'cabinet')) return 'Direction / Service';
            if (str_contains($lower, 'école') || str_contains($lower, 'ecole') || str_contains($lower, 'centre')) return 'École / Formation';
            if (str_contains($lower, 'ministère') || str_contains($lower, 'agence') || str_contains($lower, 'primature') || str_contains($lower, 'présidence')) return 'Détachement / Ministère';
            return 'Autre';
        };

        // 2. Définition des Unités par Région (Opérationnelles et Non-Opérationnelles)
        $unites = [
            // --- REGION N°1 ---
            ['region' => 'R1', 'nom' => 'Etat-Major de la Région de Gendarmerie n°1', 'op' => true],
            ['region' => 'R1', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Bamako', 'op' => true],
            ['region' => 'R1', 'nom' => 'GIGM/RD', 'op' => true],
            ['region' => 'R1', 'nom' => 'GIGM/RG', 'op' => true],
            ['region' => 'R1', 'nom' => 'Etat-Major Compagnie Territoriale Gendarmerie de Bamako /Rive Gauche', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade de Recherches de Gendarmerie de Bamako Rive Gauche', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Bamako-Coura', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Fluviale de Gendarmerie de Bamako', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de N\'gabakoro-droit', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Sangarébougou', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Diallakorodji-Safo', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Dogodouman', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Ouenzindougou', 'op' => true],
            ['region' => 'R1', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Bamako/Rive Droite', 'op' => true],
            ['region' => 'R1', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Bamako (PSIG Bamako)', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade de Recherches de Gendarmerie de Bamako Rive Droite', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Baguinéda', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Mountougoula', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Faladiè', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Sénou', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Kalaban-coro', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de N\'Golobougou', 'op' => true],
            ['region' => 'R1', 'nom' => 'Etat-Major Groupement des Transports Aériens de Bamako', 'op' => true],
            ['region' => 'R1', 'nom' => 'Groupement des Transports Aériens de Bamako', 'op' => true],
            ['region' => 'R1', 'nom' => 'Etat-Major Compagnie des Transports Aériens de Bamako', 'op' => true],
            ['region' => 'R1', 'nom' => 'Brigade des Transports Aériens de Bamako', 'op' => true],
            // Non-Opérationnelles R1
            ['region' => 'R1', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Bamako Rive gauche (PSIG RG)', 'op' => false],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Samaya', 'op' => false],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Dialakoroba', 'op' => false],
            ['region' => 'R1', 'nom' => 'Brigade Territoriale de Gendarmerie de Sanankoroba', 'op' => false],
            ['region' => 'R1', 'nom' => 'La Section Cynophile (GTA Bamako)', 'op' => false],

            // --- REGION N°2 ---
            ['region' => 'R2', 'nom' => 'Etat-Major de la Région  de Gendarmerie n°2', 'op' => true],
            ['region' => 'R2', 'nom' => 'Etat-Major du Groupement Territorial de Gendarmerie Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Etat-Major du Groupement Mobile de Gendarmerie Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Escadron 2/1 de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Escadron 2/7 de Félou', 'op' => true],
            ['region' => 'R2', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Kita', 'op' => true],
            ['region' => 'R2', 'nom' => 'Escadron 2/3 de Manantali', 'op' => true],
            ['region' => 'R2', 'nom' => 'Escadron 2/4 de Kénièba', 'op' => true],
            ['region' => 'R2', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade de Recherches de Gendarmerie de Kita', 'op' => true],
            ['region' => 'R2', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade de Recherches de Gendarmerie de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Yélimané', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Sadiola', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Diamou', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade des Transports Aériens de Kayes', 'op' => true],
            ['region' => 'R2', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Kita', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Kita', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Toukoto', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Kénièba', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Bafoulabé', 'op' => true],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Manantali', 'op' => true],
            // Non-Opérationnelles R2
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Kayes Ndi', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade Territoriale de Gendarmerie de Diboli', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Kirané', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Aourou', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Koussané', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Diadioumbéra', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Kouniakary', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Kayes', 'op' => false],
            ['region' => 'R2', 'nom' => 'Escadron 2/6 de Kayes Ndi', 'op' => false],
            ['region' => 'R2', 'nom' => 'Escadron 2/5 de Yélimané', 'op' => false],
            ['region' => 'R2', 'nom' => 'Escadron 2/2 de Kita', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Sagabari', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Sebekoro', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Seféto', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Kouroukoto', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Dialafara', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie de Faraba', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade fluviale de Gendarmerie de Mahinamine', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade territoriale de Gendarmerie d’Oussoubidiagna', 'op' => false],
            ['region' => 'R2', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Kita', 'op' => false],

            // --- REGION N°3 ---
            ['region' => 'R3', 'nom' => 'Etat-Major de la Région de Gendarmerie n°3', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major du Groupement Territorial de Gendarmerie de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Groupement Mobile de Gendarmerie de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Escadron 3/6 de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Kati', 'op' => true],
            ['region' => 'R3', 'nom' => 'Escadron 3/5 de Kangaba', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Kati', 'op' => true],
            ['region' => 'R3', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Kati PSIG (Kati)', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade de Recherches de Gendarmerie de Kati', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Kati', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Ouéléssebougou', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Kourouba', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Siby', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Naréna', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Kangaba', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Dioïla', 'op' => true],
            ['region' => 'R3', 'nom' => 'Escadron 3/1 de Dioïla', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Dioïla', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade de Recherches de Gendarmerie de Dioila', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Dioïla', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Fana', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Massigui', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Nara', 'op' => true],
            ['region' => 'R3', 'nom' => 'Escadron 3/3 de Nara', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major Compagnie Territoriale de Gendamerie de Nara', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade de Recherches de Gendarmerie de Nara', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Nara', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Mourdiah', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Guiré', 'op' => true],
            ['region' => 'R3', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Ballé (PSIG Ballé)', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade de Recherches de Gendarmerie de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Koulikoro', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Niamina', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Koula', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Banamba', 'op' => true],
            ['region' => 'R3', 'nom' => 'Brigade Territoriale de Gendarmerie de Kolokani', 'op' => true],
            // Non-Opérationnelles R3
            ['region' => 'R3', 'nom' => 'Escadron 3/2 de Banamba', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/9 de Boron', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/4 de Kati', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/10 de Niaouléni', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/11 de Ouéléssebougou', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Territoriale de Gendarmerie de Tougouni', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Koulikoro', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Kati-Faladié', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Néguela', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Dio', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Kati', 'op' => false],
            ['region' => 'R3', 'nom' => 'Peloton de Surveillance et d’Intervention de Gendarmerie de Dioila', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Ména', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Beleko', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Nangola', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Banco', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Dioïla', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/7 de Ballé', 'op' => false],
            ['region' => 'R3', 'nom' => 'Peloton de Surveillance et d’Intervention de Gendarmerie de Nara', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Ballé', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Fallou', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Dogofry', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Nara', 'op' => false],
            ['region' => 'R3', 'nom' => 'Escadron 3/8 de Didiéni', 'op' => false],
            ['region' => 'R3', 'nom' => 'Peloton de Surveillance et d’Intervention de Gendarmerie de Kolokani', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Didiéni', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade territoriale de Gendarmerie de Nossombougou', 'op' => false],
            ['region' => 'R3', 'nom' => 'Brigade de Sécurité routière de Gendarmerie de Kolokani', 'op' => false],

            // --- REGION N°4 ---
            ['region' => 'R4', 'nom' => 'Etat-Major de la Région de Gendarmerie n°4', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Sikasso (PSIG Sikasso)', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade de Recherches de Gendarmerie de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade des Transports Aériens de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Medine', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Sanoubougou', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Nièna', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Kignan', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Kolondièba', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Kadiolo', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major Groupement Mobile de Gendarmerie de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Escadron 4/1 de Sikasso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Escadron 4/3 de Koutiala', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Koutiala', 'op' => true],
            ['region' => 'R4', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Koutiala (PSIG Koutiala)', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade de Recherches de Gendarmerie de Koutiala', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Koutiala', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Koury', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Yorosso', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Bougouni', 'op' => true],
            ['region' => 'R4', 'nom' => 'Escadron 4/2 de Selingué', 'op' => true],
            ['region' => 'R4', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Bougouni', 'op' => true],
            ['region' => 'R4', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Bougouni (PSIG Bougouni)', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade de Recherches de Gendarmerie de Bougouni', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Bougouni', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Koumantou', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Manankoro', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Yanfolila', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Kalana', 'op' => true],
            ['region' => 'R4', 'nom' => 'Brigade Territoriale de Gendarmerie de Sélingué', 'op' => true],

            // --- REGION N°5 ---
            ['region' => 'R5', 'nom' => 'Etat-Major de la Région de Gendarmerie n°5', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Ségou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Ségou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade de Recherches de Gendarmerie de Ségou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Ségou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Baraouéli', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Bla', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Tamani', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Cinzana-Gare', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Pélengana', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Macina', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Saye', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Niono', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Niono', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Markala', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major Groupement Mobile de Gendarmerie de Ségou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Escadron 5/1 de Segou', 'op' => true],
            ['region' => 'R5', 'nom' => 'Escadron 5/2 de Markala', 'op' => true],
            ['region' => 'R5', 'nom' => 'Escadron 5/4 de Niono', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de San', 'op' => true],
            ['region' => 'R5', 'nom' => 'Escadron 5/3 de San', 'op' => true],
            ['region' => 'R5', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de San', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de San', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Tominian', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Mandiakuy', 'op' => true],
            ['region' => 'R5', 'nom' => 'Brigade Territoriale de Gendarmerie de Kimparana', 'op' => true],

            // --- REGION N°6 ---
            ['region' => 'R6', 'nom' => 'Etat-Major de la Région de Gendarmerie n°6', 'op' => true],
            ['region' => 'R6', 'nom' => 'Escadron 6/1 de Sévaré', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Mopti', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Compagnie Territoriale de Gendamerie de Mopti', 'op' => true],
            ['region' => 'R6', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Youwarou (PSIG Youwarou)', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Fluviale de Gendarmerie de Mopti', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade de Recherches de Gendarmerie  de Sévaré', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Mopti', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade des Transports Aériens de Sévaré', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Sévaré', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Konna', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Djenné', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Ténenkou', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Youwarou', 'op' => true],
            ['region' => 'R6', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Douentza (PSIG Douentza)', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Douentza', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Boni', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Hombori', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Bandiagara', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Bandiagara', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Bandiagara', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Bankass', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Koro', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade de Recherches  de Gendarmerie de Koro', 'op' => true],
            ['region' => 'R6', 'nom' => 'Brigade Territoriale de Gendarmerie de Koro', 'op' => true],
            ['region' => 'R6', 'nom' => 'Escadron 6/6 de Koro', 'op' => true],
            ['region' => 'R6', 'nom' => 'Escadron 6/2 de Bandiagara', 'op' => true],
            ['region' => 'R6', 'nom' => 'Escadron 6/5 de Diallassagou', 'op' => true],
            ['region' => 'R6', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Koro', 'op' => true],

            // --- REGION N°7 ---
            ['region' => 'R7', 'nom' => 'Etat-Major de la Région de Gendarmerie n°7', 'op' => true],
            ['region' => 'R7', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Peloton de Surveillance et d\'Intervention de la Gendarmerie de Gossi (PSIG Gossi)', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade de Recherches de Gendarmerie de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade des Transports Aériens de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Gourma - Rharous', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Gossi', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Goundam', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Diré', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Niafunké', 'op' => true],
            ['region' => 'R7', 'nom' => 'Brigade Territoriale de Gendarmerie de Léré', 'op' => true],
            ['region' => 'R7', 'nom' => 'Etat-Major Groupement Mobile de Gendarmerie de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Escadron 7/1 de Goundam', 'op' => true],
            ['region' => 'R7', 'nom' => 'Escadron 7/3 de Tombouctou', 'op' => true],
            ['region' => 'R7', 'nom' => 'Escadron 7/5 de Tonka', 'op' => true],
            ['region' => 'R7', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Taoudénit', 'op' => true],

            // --- REGION N°8 ---
            ['region' => 'R8', 'nom' => 'Etat-Major de la Région de Gendarmerie n°8', 'op' => true],
            ['region' => 'R8', 'nom' => 'Etat-Major de la Compagnie Territoriale de Gendarmerie de Gao', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade de Recherches de Gendarmerie de Gao', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade Territoriale de Gendarmerie de Gao', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade des Transports Aériens de Gao', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade Territoriale de Gendarmerie de Bourem', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade Territoriale de Gendarmerie de Ansongo', 'op' => true],
            ['region' => 'R8', 'nom' => 'Brigade Territoriale de Gendarmerie de Labbézanga', 'op' => true],
            ['region' => 'R8', 'nom' => 'Escadron 8/1 de Gao', 'op' => true],
            ['region' => 'R8', 'nom' => 'Service du Personnel/Eléments du Nord non rédeployés/Légion Gao', 'op' => true],

            // --- REGION N°9 ---
            ['region' => 'R9', 'nom' => 'Etat-Major de la Région de Gendarmerie n°9', 'op' => true],
            ['region' => 'R9', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Kidal', 'op' => true],
            ['region' => 'R9', 'nom' => 'Escadron 9/1 de Kidal', 'op' => true],
            ['region' => 'R9', 'nom' => 'Etat-Major Compagnie Territoriale de Gendarmerie de Kidal', 'op' => true],
            ['region' => 'R9', 'nom' => 'Brigade Territoriale de Gendarmerie de Kidal', 'op' => true],
            ['region' => 'R9', 'nom' => 'Brigade Territoriale de Gendarmerie de Tessalit', 'op' => true],

            // --- REGION N°10 ---
            ['region' => 'R10', 'nom' => 'Etat-Major de la Région de Gendarmerie n°10', 'op' => true],
            ['region' => 'R10', 'nom' => 'Etat-Major Groupement Territorial de Gendarmerie de Nioro du Sahel', 'op' => true],
            ['region' => 'R10', 'nom' => 'Etat-Major Groupement Mobile de Gendarmerie de Nioro du Sahel', 'op' => true],
            ['region' => 'R10', 'nom' => 'Escadron 10/1 de Nioro du Sahel', 'op' => true],
            ['region' => 'R10', 'nom' => 'Etat-Major de Compagnie Territoriale de Nioro du Sahel', 'op' => true],
            ['region' => 'R10', 'nom' => 'Brigade Territoriale de Gendarmerie de Nioro du Sahel', 'op' => true],
            ['region' => 'R10', 'nom' => 'Brigade Territoriale de Gendarmerie de Diéma', 'op' => true],
            ['region' => 'R10', 'nom' => 'Brigade Territoriale de Gendarmerie de Sandaré', 'op' => true],

            // --- REGION N°11 ---
            ['region' => 'R11', 'nom' => 'Etat-Major de la Région de Gendarmerie n°11', 'op' => true],
            ['region' => 'R11', 'nom' => 'Etat-Major Groupement Mixte de Gendarmerie de Ménaka', 'op' => true],
            ['region' => 'R11', 'nom' => 'Brigade Territoriale de Gendarmerie de Ménaka', 'op' => true],
            ['region' => 'R11', 'nom' => 'Escadron 11/1 de Ménaka', 'op' => false],

            // --- DGGN ---
            ['region' => 'DGGN', 'nom' => 'Direction Generale Commandement', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Conseiller', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Cabinet du Directeur Générale de la Gendarmerie Nationale', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction des Ressources Humaines', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction des Ressources Humaines-Non Déployé', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction du Renseignement', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction des Opérations', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction de la Logistique', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction de la Police Judiciaire', 'op' => true],
            ['region' => 'DGGN', 'nom' => 'Sous-Direction des Finances', 'op' => true],

            // --- CDECF ---
            ['region' => 'CDECF', 'nom' => 'Commandement des Ecoles et des Centres de Formation', 'op' => true],

            // --- CDURM ---
            ['region' => 'CDURM', 'nom' => 'Etat-Major Commandement des Unités des Réserves Ministerielles', 'op' => true],
            ['region' => 'CDURM', 'nom' => 'Groupement Cyno-Technique', 'op' => true],
            ['region' => 'CDURM', 'nom' => 'Groupement d\'Honneurs et de sécurité', 'op' => true],
            ['region' => 'CDURM', 'nom' => 'Groupement Motorisé', 'op' => true],

            // --- CDUSF ---
            ['region' => 'CDUSF', 'nom' => 'Etat-Major Commandement des Unités de surveillance des Frontières', 'op' => true],
            ['region' => 'CDUSF', 'nom' => 'GARSI', 'op' => true],

            // --- GSIGN ---
            ['region' => 'GSIGN', 'nom' => 'Groupement Spécial d\'Intervention de la Gendarmerie Nationale', 'op' => true],

            // --- INSPECTION & SIJ ---
            ['region' => 'INSPECTION', 'nom' => 'Inspection de la Gendarmerie Nationale', 'op' => true],
            ['region' => 'SIJ', 'nom' => 'Service d\'Investigations Judiciaires', 'op' => true],

            // --- DETACHEMENT ---
            ['region' => 'DETACHEMENT', 'nom' => 'Etat-Major Général des Armées', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Agence Nationale de la Sécurité d\'Etat', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction de l\'Office Central des Stupéfiants', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Force Antiterroriste', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Force Spéciale Antiterroriste', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Etat-Major Particulier de la Présidence de la République', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Primature', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de la Défense et des Anciens Combattants', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de la Sécurité et de la Protection Civile', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de la Justice et des Droits de l\'homme, Garde des Sceaux', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de l\'Administration Territoriale et de la Décentralisation', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère des Affaires Etrangères et de la Cooperation Internationale', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction de la Sécurité Militaire', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction de la Justice Militaire', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction du Sport Militaire', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction des Ecoles Militaires', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction des Ressources Humaines des Armées', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction des Services Sociales des Armées', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction Général de la Protection Civile', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction Générale de la Police Technique et Scientifique', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Direction de la Commissariat des Armées', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Office Central de Lutte contre l\'Enrichissement Illicite (OCLEI)', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Sécretariat Permanent de la Lutte contre la Prolifération des Armes Légères et de Petit Calibre', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Brigade d\'Investigation Spécialisée de Lutte Contre le Terrorisme et la Criminalité Transnatinale Organisée', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ecole de Guerre du Mali', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ecole de Maintien de la Paix "Aliou Blondin M\'Beye"', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Bureau du Verificateur Général', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Cour Constitutionelle du Mali', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Assemblée Nationale du Mali', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Centre de Coordination et de Gestion des Crises et Catastrophes', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de l\'Economie et des Finances', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère des Mines et l\'Energie et de l\'Eau', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'Ministère de la Jeunesse et des Sports', 'op' => true],
            ['region' => 'DETACHEMENT', 'nom' => 'FIRA', 'op' => true],
        ];

        foreach ($unites as $u) {
            $regId = $regionsMap[$u['region']] ?? null;
            if ($regId) {
                Unite::firstOrCreate(
                    [
                        'region_id' => $regId,
                        'nom' => trim($u['nom']),
                    ],
                    [
                        'type' => $detectType($u['nom']),
                        'est_operationnelle' => $u['op'],
                    ]
                );
            }
        }

        // 3. Définition des Positions administratives et théâtres opérationnels
        $positions = [
            ['nom' => 'En activité', 'type' => 'administrative', 'description' => 'En service actif au sein de son unité'],
            ['nom' => 'Détachement', 'type' => 'administrative', 'description' => 'Détaché auprès d\'un ministère, EMGA ou institution'],
            ['nom' => 'Stage / Formation', 'type' => 'administrative', 'description' => 'En cours de formation ou stage'],
            ['nom' => 'Procédure en Cours', 'type' => 'administrative', 'description' => 'Dossier administratif ou disciplinaire en cours'],
            ['nom' => 'En mission', 'type' => 'operationnelle', 'description' => 'Mission temporaire'],
            ['nom' => 'GIGM', 'type' => 'operationnelle', 'description' => 'Groupe d\'Intervention de la Gendarmerie Nationale'],
            ['nom' => 'GTA Bamako', 'type' => 'operationnelle', 'description' => 'Groupement des Transports Aériens'],
            ['nom' => 'Escadron 2/1 de Kayes', 'type' => 'operationnelle', 'description' => 'Positionnement Escadron 2/1'],
            ['nom' => 'Escadron 7/3 de Tombouctou', 'type' => 'operationnelle', 'description' => 'Positionnement Escadron 7/3'],
            ['nom' => 'Escadron 7/5 de Tonka', 'type' => 'operationnelle', 'description' => 'Positionnement Escadron 7/5'],
            ['nom' => 'Escadron 8/1 de Gao', 'type' => 'operationnelle', 'description' => 'Positionnement Escadron 8/1'],
            ['nom' => 'Escadron Djiguiya', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Djiguiya'],
            ['nom' => 'Escadron Yereko', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Yereko'],
            ['nom' => 'Escadron Kalo', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Kalo'],
            ['nom' => 'Escadron Samory', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Samory'],
            ['nom' => 'Escadron Horon', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Horon'],
            ['nom' => 'Escadron Kilé', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Kilé'],
            ['nom' => 'Escadron Assahatt', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Assahatt'],
            ['nom' => 'Escadron Fangatigui', 'type' => 'operationnelle', 'description' => 'Théâtre opérationnel Fangatigui'],
            ['nom' => 'PSIG Bamako', 'type' => 'operationnelle', 'description' => 'Peloton de surveillance et d\'intervention'],
        ];

        foreach ($positions as $p) {
            Position::firstOrCreate(['nom' => $p['nom']], $p);
        }
    }
}
