<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('theatres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('pc_lieu')->nullable(); // Poste de Commandement / Localité
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->json('regions_codes')->nullable(); // Ex: ["R5", "R6", "R17", "R18", "R19"]
            $table->string('couleur', 20)->default('#f59e0b');
            $table->string('statut', 30)->default('actif'); // actif, reserve, cloture
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->timestamps();
        });

        Schema::table('militaires', function (Blueprint $table) {
            $table->foreignId('theatre_id')->nullable()->after('organisme_detachement_id')->constrained('theatres')->nullOnDelete();
        });

        // Données initiales des Théâtres majeurs des Forces Armées & Gendarmerie du Mali
        $now = now();
        $theatres = [
            [
                'code' => 'TH-CTR',
                'nom' => 'Opération Maliko (Théâtre Centre)',
                'description' => 'Coordination interarmées des opérations du secteur Centre (Mopti, Ségou, San, Douentza, Bandiagara).',
                'pc_lieu' => 'Sévaré / Mopti',
                'latitude' => 14.526000,
                'longitude' => -4.103000,
                'regions_codes' => json_encode(['R5', 'R6', 'R17', 'R18', 'R19']),
                'couleur' => '#ea580c', // Orange opérationnel
                'statut' => 'actif',
                'date_debut' => '2020-01-30',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TH-EST',
                'nom' => 'Opération Maliko (Théâtre Est)',
                'description' => 'Couverture et sécurisation opérationnelle des régions orientales (Gao, Ménaka, Kidal).',
                'pc_lieu' => 'Gao',
                'latitude' => 16.271000,
                'longitude' => -0.044000,
                'regions_codes' => json_encode(['R8', 'R9', 'R11']),
                'couleur' => '#dc2626', // Rouge vif
                'statut' => 'actif',
                'date_debut' => '2020-01-30',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TH-SUD',
                'nom' => 'Opération Kélétigui (Théâtre Sud)',
                'description' => 'Sécurisation des frontières et des zones méridionales (Sikasso, Bougouni, Koulikoro, Dioïla, Koutiala, Bamako).',
                'pc_lieu' => 'Sikasso',
                'latitude' => 11.317000,
                'longitude' => -5.666000,
                'regions_codes' => json_encode(['R1', 'R3', 'R4', 'R13', 'R15', 'R16']),
                'couleur' => '#0284c7', // Bleu tactique
                'statut' => 'actif',
                'date_debut' => '2022-01-15',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'TH-NRD',
                'nom' => 'Théâtre Nord / Opération Dambé',
                'description' => 'Secteur septentrional et désertique (Tombouctou, Taoudénit, Nara).',
                'pc_lieu' => 'Tombouctou',
                'latitude' => 16.773000,
                'longitude' => -3.007000,
                'regions_codes' => json_encode(['R7', 'R14', 'R20']),
                'couleur' => '#f59e0b', // Ambre
                'statut' => 'actif',
                'date_debut' => '2021-03-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'DET-SOKOLO',
                'nom' => 'Détachement Opérationnel Sokolo',
                'description' => 'Détachement spécial et point d’appui opérationnel du cercle de Niono.',
                'pc_lieu' => 'Sokolo',
                'latitude' => 14.733000,
                'longitude' => -6.117000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#10b981', // Émeraude
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'DET-SAYE',
                'nom' => 'Détachement Opérationnel Saye',
                'description' => 'Poste de surveillance et d’intervention rapide du cercle de Macina.',
                'pc_lieu' => 'Saye',
                'latitude' => 13.083000,
                'longitude' => -5.350000,
                'regions_codes' => json_encode(['R5']),
                'couleur' => '#8b5cf6', // Violet
                'statut' => 'actif',
                'date_debut' => '2023-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('theatres')->insert($theatres);

        // Synchroniser les militaires ayant déjà une mention dans 'engagement_ops'
        $allTheatres = DB::table('theatres')->get();
        foreach ($allTheatres as $th) {
            $keyword = match ($th->code) {
                'TH-CTR' => 'Centre',
                'TH-EST' => 'Est',
                'TH-SUD' => 'Sud',
                'TH-NRD' => 'Dambé',
                'DET-SOKOLO' => 'Sokolo',
                'DET-SAYE' => 'Saye',
                default => null,
            };

            if ($keyword) {
                DB::table('militaires')
                    ->whereNull('theatre_id')
                    ->where('engagement_ops', 'LIKE', "%{$keyword}%")
                    ->update(['theatre_id' => $th->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropForeign(['theatre_id']);
            $table->dropColumn('theatre_id');
        });

        Schema::dropIfExists('theatres');
    }
};
