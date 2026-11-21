<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\Unite;
use App\Models\OrganismeDetachement;
use App\Models\Region;

return new class extends Migration
{
    public function up(): void
    {
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $bamakoId = Region::where('nom', $likeOp, '%Bamako%')->value('id') ?? Region::value('id');

        // 1. AJOUTER LES VRAIS ESCADRONS & LE CAMP 1 DANS LA TABLE UNITES (Gendarmerie) S'ILS N'EXISTENT PAS
        if ($bamakoId) {
            $unitesToAdd = [
                [
                    'nom' => 'Camp 1 (DGGN Bamako)',
                    'code' => 'DGGN-C1',
                    'type' => 'Direction / Service',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Djiguiya',
                    'code' => 'ESC-DJIGUIYA',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Yereko',
                    'code' => 'ESC-YEREKO',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Kalo',
                    'code' => 'ESC-KALO',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Samory',
                    'code' => 'ESC-SAMORY',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Horon',
                    'code' => 'ESC-HORON',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Kilé',
                    'code' => 'ESC-KILE',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Assahatt',
                    'code' => 'ESC-ASSAHATT',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
                [
                    'nom' => 'Escadron Fangatigui',
                    'code' => 'ESC-FANGATIGUI',
                    'type' => 'Escadron',
                    'region_id' => $bamakoId,
                    'ville' => 'Bamako',
                    'est_operationnelle' => true,
                ],
            ];

            foreach ($unitesToAdd as $u) {
                $exists = Unite::where('nom', $u['nom'])->exists();
                if (!$exists) {
                    Unite::create($u);
                }
            }
        }

        // 2. AJOUTER PRM (Présidence de la République / Palais de Koulouba) DANS ORGANISMES_DETACHEMENT
        $prmExists = OrganismeDetachement::where('code', 'PRM')
            ->orWhere('nom', $likeOp, '%Présidence de la République%')
            ->exists();
        if (!$prmExists) {
            $maxOrdre = OrganismeDetachement::max('ordre') ?? 48;
            OrganismeDetachement::create([
                'code' => 'PRM',
                'nom' => 'Présidence de la République (Palais de Koulouba)',
                'nom_complet' => 'Présidence de la République du Mali',
                'categorie' => 'Institution',
                'ordre' => $maxOrdre + 1,
                'est_actif' => true,
            ]);
        }

        // 3. NETTOYER LA TABLE POSITIONS
        // Les entrées qui sont des unités, des détachements ou des doublons
        $redundantNames = [
            'GIGM',
            'GTA Bamako',
            'Escadron 2/1 de Kayes',
            'Escadron 7/3 de Tombouctou',
            'Escadron 7/5 de Tonka',
            'Escadron 8/1 de Gao',
            'Escadron Djiguiya',
            'Escadron Yereko',
            'Escadron Kalo',
            'Escadron Samory',
            'Escadron Horon',
            'Escadron Kilé',
            'Escadron Assahatt',
            'Escadron Fangatigui',
            'PSIG Bamako',
            'Camp 1 (DGGN Bamako)',
            'Palais de Koulouba (Présidence)',
            'Cité Administrative (Primature / Ministères)',
            'Poste Frontière',
            'Otage', // Doublon de 'Cas d\'otage'
        ];

        // S'assurer qu'aucun militaire n'est affecté à ces noms redondants (normalement 0)
        $posUnite = Position::where('nom', "À l'Unité / Au Poste")->first();
        if ($posUnite) {
            DB::table('militaires')
                ->whereIn('position_actuelle', $redundantNames)
                ->update([
                    'position_actuelle' => $posUnite->nom,
                    'position_id' => $posUnite->id,
                ]);
        }

        // Supprimer les redondances de la table positions
        Position::whereIn('nom', $redundantNames)->delete();

        // 4. S'ASSURER QUE LA LISTE OFFICIELLE DES 7 POSITIONS GÉNÉRIQUES EST PRÉSENTE
        $standardPositions = [
            ['nom' => "À l'Unité / Au Poste", 'type' => 'operationnelle', 'description' => 'Affectation opérationnelle normale au sein de l\'unité / brigade territoriale'],
            ['nom' => 'En détachement', 'type' => 'administrative', 'description' => 'Mis à disposition ou détaché auprès d\'un organisme extérieur (SPA)'],
            ['nom' => 'En mission', 'type' => 'operationnelle', 'description' => 'Déploiement temporaire ou opérationnel (OPEX, renforts)'],
            ['nom' => 'En stage / Formation', 'type' => 'administrative', 'description' => 'En cours de perfectionnement ou formation en école militaire'],
            ['nom' => 'Cas d\'otage', 'type' => 'operationnelle', 'description' => 'Militaire retenu en otage en zone hostile (maintenu au fichier actif)'],
            ['nom' => 'En congé / Convalescence', 'type' => 'administrative', 'description' => 'Congé régulier, permission ou convalescence médicale'],
            ['nom' => 'En attente d\'affectation', 'type' => 'operationnelle', 'description' => 'Personnel nouvellement recruté ou en instance de mutation'],
        ];

        foreach ($standardPositions as $sp) {
            $p = Position::where('nom', $sp['nom'])->first();
            if (!$p) {
                Position::create($sp);
            } else {
                $p->update([
                    'type' => $sp['type'],
                    'description' => $sp['description'] ?? $p->description,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback destructeur nécessaire
    }
};
