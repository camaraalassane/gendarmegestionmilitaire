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
        Schema::create('theatre_unites_appui', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->string('code', 30); // ex: AA, AT, DTTIA, GENIE, DCSSA
            $table->string('nom', 150); // ex: Armée de l'Air, Armée de Terre, Transmissions
            $table->integer('effectif')->default(0);
            $table->string('chef_detachement', 150)->nullable();
            $table->string('couleur', 30)->nullable()->default('#0284c7');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('theatre_id');
            $table->index(['theatre_id', 'code']);
        });

        // Seed des unités d'appui réalistes pour les théâtres existants
        $now = now();
        $theatresMap = DB::table('theatres')->pluck('id', 'code');

        $initialAppuis = [
            // Théâtre Centre (Sévaré)
            'TH-CTR' => [
                ['code' => 'AA', 'nom' => "Armée de l'Air", 'effectif' => 20, 'couleur' => '#0284c7', 'observations' => 'Hélicoptères & reconnaissance aérienne'],
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 35, 'couleur' => '#16a34a', 'observations' => 'GTIA & infanterie mécanisée'],
                ['code' => 'DTTIA', 'nom' => 'Transmissions', 'effectif' => 10, 'couleur' => '#9333ea', 'observations' => 'Liaisons tactiques & relais'],
                ['code' => 'GENIE', 'nom' => 'Génie Militaire', 'effectif' => 15, 'couleur' => '#d97706', 'observations' => 'Déminage, franchissement & appui mobilité'],
                ['code' => 'DCSSA', 'nom' => 'Santé Militaire', 'effectif' => 8, 'couleur' => '#e11d48', 'observations' => 'Antenne chirurgicale avancée'],
            ],
            // Théâtre Est (Gao)
            'TH-EST' => [
                ['code' => 'AA', 'nom' => "Armée de l'Air", 'effectif' => 15, 'couleur' => '#0284c7', 'observations' => 'Vecteurs aériens & drones'],
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 30, 'couleur' => '#16a34a', 'observations' => 'Compagnie interarmes de Gao'],
                ['code' => 'DTTIA', 'nom' => 'Transmissions', 'effectif' => 8, 'couleur' => '#9333ea', 'observations' => 'Transmissions satellitaires'],
                ['code' => 'GENIE', 'nom' => 'Génie Militaire', 'effectif' => 12, 'couleur' => '#d97706', 'observations' => 'Fortification & sécurisation PC'],
            ],
            // Théâtre Sud (Sikasso)
            'TH-SUD' => [
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 25, 'couleur' => '#16a34a', 'observations' => 'Patrouilles frontalières & surveillance'],
                ['code' => 'DTTIA', 'nom' => 'Transmissions', 'effectif' => 6, 'couleur' => '#9333ea', 'observations' => 'Transmissions de commandement'],
                ['code' => 'GENIE', 'nom' => 'Génie Militaire', 'effectif' => 8, 'couleur' => '#d97706', 'observations' => 'Appui travaux & génie combat'],
            ],
            // Théâtre Nord (Tombouctou)
            'TH-NRD' => [
                ['code' => 'AA', 'nom' => "Armée de l'Air", 'effectif' => 10, 'couleur' => '#0284c7', 'observations' => 'Surveillance aérienne'],
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 25, 'couleur' => '#16a34a', 'observations' => 'Détachement motorisé'],
                ['code' => 'DTTIA', 'nom' => 'Transmissions', 'effectif' => 6, 'couleur' => '#9333ea', 'observations' => 'Transmissions HF/VHF'],
                ['code' => 'DCSSA', 'nom' => 'Santé Militaire', 'effectif' => 5, 'couleur' => '#e11d48', 'observations' => 'Poste médical de secours'],
            ],
            // Détachement Sokolo
            'DET-SOKOLO' => [
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 15, 'couleur' => '#16a34a', 'observations' => 'Section d’infanterie'],
                ['code' => 'GENIE', 'nom' => 'Génie Militaire', 'effectif' => 5, 'couleur' => '#d97706', 'observations' => 'Reconnaissance d’itinéraires & déminage'],
            ],
            // Détachement Saye
            'DET-SAYE' => [
                ['code' => 'AT', 'nom' => "Armée de Terre", 'effectif' => 10, 'couleur' => '#16a34a', 'observations' => 'Groupe de combat'],
                ['code' => 'DTTIA', 'nom' => 'Transmissions', 'effectif' => 4, 'couleur' => '#9333ea', 'observations' => 'Station radio'],
            ],
        ];

        $rowsToInsert = [];
        foreach ($initialAppuis as $thCode => $unites) {
            $theatreId = $theatresMap->get($thCode);
            if (!$theatreId) continue;

            foreach ($unites as $u) {
                $rowsToInsert[] = [
                    'theatre_id' => $theatreId,
                    'code' => $u['code'],
                    'nom' => $u['nom'],
                    'effectif' => $u['effectif'],
                    'chef_detachement' => null,
                    'couleur' => $u['couleur'],
                    'observations' => $u['observations'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rowsToInsert)) {
            DB::table('theatre_unites_appui')->insert($rowsToInsert);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theatre_unites_appui');
    }
};
