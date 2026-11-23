<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Militaire;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Réinitialiser theatre_id sur tous les militaires
        DB::table('militaires')->update(['theatre_id' => null]);

        // 2. Supprimer les anciennes unités d'appui et les anciens théâtres fictifs
        DB::table('theatre_unites_appui')->delete();
        DB::table('theatres')->delete();

        // 3. Insérer les 10 Théâtres / Engagements Opérationnels Officiels issus de la RH
        $now = now();
        $theatresData = [
            [
                'id' => 1,
                'code' => 'TH-DANDERESSO',
                'nom' => 'Détachement Opérationnel de Danderesso',
                'description' => 'Détachement opérationnel de Danderesso (Région de Sikasso) — Unités : GARSI',
                'pc_lieu' => 'Danderesso',
                'latitude' => 11.450000,
                'longitude' => -5.450000,
                'regions_codes' => json_encode(['R4']),
                'couleur' => '#0284c7',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'TH-KALANA',
                'nom' => 'Détachement Opérationnel de Kalana',
                'description' => 'Détachement opérationnel de Kalana (Région de Sikasso / Bougouni) — Unités : GARSI - GSIGN',
                'pc_lieu' => 'Kalana',
                'latitude' => 10.783000,
                'longitude' => -8.183000,
                'regions_codes' => json_encode(['R4', 'R15']),
                'couleur' => '#0ea5e9',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'code' => 'TH-KIMPARANA',
                'nom' => 'Détachement Opérationnel de Kimparana',
                'description' => 'Détachement opérationnel de Kimparana (Région de Ségou / San) — Unités : GSIGN',
                'pc_lieu' => 'Kimparana',
                'latitude' => 12.833000,
                'longitude' => -5.017000,
                'regions_codes' => json_encode(['R5', 'R17']),
                'couleur' => '#10b981',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'code' => 'TH-MBEWANI',
                'nom' => 'Détachement Opérationnel de M\'Béwani',
                'description' => 'Détachement opérationnel de M\'Béwani (Région de Ségou) — Unités : GSIGN',
                'pc_lieu' => 'M\'Béwani',
                'latitude' => 13.983000,
                'longitude' => -5.917000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#059669',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'code' => 'TH-DOUGABOUGOU',
                'nom' => 'Détachement Opérationnel de Dougabougou',
                'description' => 'Détachement opérationnel de Dougabougou (Région de Ségou) — Unités : GARSI',
                'pc_lieu' => 'Dougabougou',
                'latitude' => 13.733000,
                'longitude' => -5.983000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#14b8a6',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'code' => 'TH-SOKOLO',
                'nom' => 'Détachement Opérationnel de Sokolo',
                'description' => 'Détachement opérationnel de Sokolo (Région de Ségou) — Unités : UNITÉS-GRM',
                'pc_lieu' => 'Sokolo',
                'latitude' => 14.733000,
                'longitude' => -6.117000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#ea580c',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'code' => 'TH-TOGORE-COUMBE',
                'nom' => 'Détachement Opérationnel de Togoré-Coumbé',
                'description' => 'Détachement opérationnel de Togoré-Coumbé (Région de Ségou) — Unités : UNITÉS-GRM',
                'pc_lieu' => 'Togoré-Coumbé',
                'latitude' => 14.300000,
                'longitude' => -5.350000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#d97706',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'code' => 'TH-DIALLOUBE',
                'nom' => 'Détachement Opérationnel de Dialloubé',
                'description' => 'Détachement opérationnel de Dialloubé (Région de Mopti) — Unités : UNITÉS-GRM',
                'pc_lieu' => 'Dialloubé',
                'latitude' => 14.767000,
                'longitude' => -3.983000,
                'regions_codes' => json_encode(['R6']),
                'couleur' => '#dc2626',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'code' => 'TH-TIMISSA',
                'nom' => 'Détachement Opérationnel de Timissa',
                'description' => 'Détachement opérationnel de Timissa (Région de Ségou / San) — Unités : UNITÉS-GRM',
                'pc_lieu' => 'Timissa',
                'latitude' => 13.433000,
                'longitude' => -4.850000,
                'regions_codes' => json_encode(['R5', 'R17']),
                'couleur' => '#8b5cf6',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'code' => 'TH-SAYE',
                'nom' => 'Détachement Opérationnel de Saye',
                'description' => 'Détachement opérationnel de Saye (Région de Ségou) — Unités : UNITÉS-GRM',
                'pc_lieu' => 'Saye',
                'latitude' => 13.083000,
                'longitude' => -5.350000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#6366f1',
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('theatres')->insert($theatresData);

        // 4. Insérer les unités prévues / d'appui
        $unitesData = [
            ['theatre_id' => 1, 'code' => 'GARSI', 'nom' => 'Groupe d\'Action Rapide de Surveillance et d\'Intervention', 'effectif' => 0, 'couleur' => '#0284c7', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 2, 'code' => 'GARSI-GSIGN', 'nom' => 'GARSI / GSIGN', 'effectif' => 0, 'couleur' => '#0ea5e9', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 3, 'code' => 'GSIGN', 'nom' => 'Groupe Spécial d\'Intervention de la Gendarmerie Nationale', 'effectif' => 0, 'couleur' => '#10b981', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 4, 'code' => 'GSIGN', 'nom' => 'Groupe Spécial d\'Intervention de la Gendarmerie Nationale', 'effectif' => 0, 'couleur' => '#059669', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 5, 'code' => 'GARSI', 'nom' => 'Groupe d\'Action Rapide de Surveillance et d\'Intervention', 'effectif' => 0, 'couleur' => '#14b8a6', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 6, 'code' => 'UNITES-GRM', 'nom' => 'Unités de Réserve et d\'Intervention GRM', 'effectif' => 0, 'couleur' => '#ea580c', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 7, 'code' => 'UNITES-GRM', 'nom' => 'Unités de Réserve et d\'Intervention GRM', 'effectif' => 0, 'couleur' => '#d97706', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 8, 'code' => 'UNITES-GRM', 'nom' => 'Unités de Réserve et d\'Intervention GRM', 'effectif' => 0, 'couleur' => '#dc2626', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 9, 'code' => 'UNITES-GRM', 'nom' => 'Unités de Réserve et d\'Intervention GRM', 'effectif' => 0, 'couleur' => '#8b5cf6', 'created_at' => $now, 'updated_at' => $now],
            ['theatre_id' => 10, 'code' => 'UNITES-GRM', 'nom' => 'Unités de Réserve et d\'Intervention GRM', 'effectif' => 0, 'couleur' => '#6366f1', 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('theatre_unites_appui')->insert($unitesData);

        // 5. Affectation rigoureuse des militaires aux 10 théâtres
        $officialKeywords = [
            6  => ['sokolo'],                                 // SOKOLO
            7  => ['togr', 'togor', 'coumb'],                 // TOGORE-COUMBE
            8  => ['dialloub', 'dialoub', 'dialoumb'],        // DIALLOUBE
            9  => ['timissa'],                                // TIMISSA
            10 => ['saye'],                                   // SAYE
            1  => ['danderesso'],                             // DANDERESSO
            2  => ['kalana'],                                 // KALANA
            3  => ['kimparana'],                              // KIMPARANA
            4  => ['bewani', 'mbewani', 'm\'bewani'],         // M'BEWANI
            5  => ['dougabougou'],                            // DOUGABOUGOU
        ];

        $militaires = Militaire::select('id', 'matricule', 'nom', 'prenom', 'unite', 'position_actuelle', 'engagement_ops')->get();

        $updates = [];
        foreach ($militaires as $m) {
            $assignedThId = null;

            // a) Vérifier la position actuelle (si détachement explicite)
            $pos = mb_strtolower(trim($m->position_actuelle ?? ''));
            if ($pos && !str_contains($pos, 'poste') && !str_contains($pos, 'unité')) {
                foreach ($officialKeywords as $thId => $kws) {
                    foreach ($kws as $kw) {
                        if (str_contains($pos, $kw)) {
                            $assignedThId = $thId;
                            break 2;
                        }
                    }
                }
            }

            // b) Vérifier l'unité
            if (!$assignedThId) {
                $unit = mb_strtolower(trim($m->unite ?? ''));
                foreach ($officialKeywords as $thId => $kws) {
                    foreach ($kws as $kw) {
                        if (str_contains($unit, $kw)) {
                            $assignedThId = $thId;
                            break 2;
                        }
                    }
                }
            }

            // c) Vérifier l'historique d'engagement opérationnel (retenir le dernier engagement mentionné)
            if (!$assignedThId) {
                $eng = mb_strtolower(trim($m->engagement_ops ?? ''));
                if ($eng && $eng !== '0 ops') {
                    $lastPos = -1;
                    $bestMatchThId = null;
                    foreach ($officialKeywords as $thId => $kws) {
                        foreach ($kws as $kw) {
                            $p = strrpos($eng, $kw);
                            if ($p !== false && $p > $lastPos) {
                                $lastPos = $p;
                                $bestMatchThId = $thId;
                            }
                        }
                    }
                    if ($bestMatchThId) {
                        $assignedThId = $bestMatchThId;
                    }
                }
            }

            if ($assignedThId) {
                $updates[$assignedThId][] = $m->id;
            }
        }

        foreach ($updates as $thId => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('militaires')->whereIn('id', $chunk)->update(['theatre_id' => $thId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('militaires')->update(['theatre_id' => null]);
        DB::table('theatre_unites_appui')->delete();
        DB::table('theatres')->delete();
    }
};
