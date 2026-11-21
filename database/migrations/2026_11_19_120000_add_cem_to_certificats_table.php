<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\Certificat;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificats')) {
            Certificat::updateOrCreate(
                ['niveau_certificat' => 'CEM'],
                [
                    'nom_certificat' => "Cour d'État-Major",
                    'grade_associe' => 'Officier supérieur',
                    'conditions' => [
                        'grade_min' => 'Capitaine',
                        'age_max' => 45,
                        'pas_de_probleme_disciplinaire' => true,
                        'pas_de_probleme_judiciaire' => true,
                        'pas_deserteur' => true,
                    ],
                    'anciennete_requise' => null,
                    'certificat_precedent' => null,
                    'duree_certificat_precedent' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificats')) {
            Certificat::where('niveau_certificat', 'CEM')->delete();
        }
    }
};
