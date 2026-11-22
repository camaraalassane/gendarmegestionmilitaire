<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Militaire;
use App\Models\RegistreOperationnel;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $otagesData = [
            '8170' => [
                'obs' => 'Otage enlevé entre Boni et Nokara le 31 mai 2019',
                'fct' => 'Elément'
            ],
            '8329' => [
                'obs' => 'Mission Sokolo 4 otage',
                'fct' => 'Elément'
            ],
            '8607' => [
                'obs' => 'Porté Disparu à Boulkessi',
                'fct' => 'Chauffeur'
            ],
            '9102' => [
                'obs' => 'Otage A.C.B.T Diré',
                'fct' => 'Elément'
            ],
            '9745' => [
                'obs' => 'Porté disparu à Kidal le 21 Mai 2015',
                'fct' => 'Chauffeur'
            ],
            '11293' => [
                'obs' => 'Otage',
                'fct' => 'Chauffeur'
            ],
            '12097' => [
                'obs' => 'Porté disparu',
                'fct' => 'Elément'
            ],
            '12443' => [
                'obs' => "Porté disparu à entre Boni et Douentza suite embuscade de l'unité Garsi",
                'fct' => 'Elément'
            ],
            '12766' => [
                'obs' => "Porté disparu à entre Boni et Douentza suite embuscade de l'unité Garsi 19/08/2021",
                'fct' => 'Elément'
            ],
            '12923' => [
                'obs' => "Otage suite aux événements du 25 avril 2026",
                'fct' => 'Elément'
            ],
        ];

        foreach ($otagesData as $mat => $data) {
            $m = Militaire::where('matricule', $mat)->first();
            if (!$m) continue;

            $m->position_id = 31; // Cas d'otage
            $m->position_actuelle = "Cas d'otage";
            $m->est_disparu = 1;
            $m->observations = $data['obs'];
            if (!empty($data['fct'])) {
                $m->fonction_actuelle = $data['fct'];
            }
            $m->save();

            $reg = RegistreOperationnel::where('militaire_id', $m->id)
                ->where('type_registre_code', 2)
                ->first();

            if (!$reg) {
                $reg = new RegistreOperationnel();
                $reg->militaire_id = $m->id;
                $reg->type_registre_code = 2;
            }

            $reg->matricule = $m->matricule;
            $reg->mle_grm = $m->mle_grm;
            $reg->nom = $m->nom;
            $reg->prenom = $m->prenom;
            $reg->grade_id = $m->grade_id;
            $reg->grade_ordre = $m->grade_ordre ?? 0;
            $reg->grade_texte = $m->grade_actuel;
            $reg->sexe_code = $m->sexe_code ?? 1;
            $reg->region_id = $m->region_id;
            $reg->unite_id = $m->unite_id;
            $reg->unite_texte = $m->unite;
            $reg->circonstances = $data['obs'];
            $reg->observations = $data['obs'];
            $reg->est_actif_fichier = 1;
            $reg->save();
        }

        // Ex-otages libérés
        $exOtagesMats = ['9100', '8935', '11211', '13434'];
        foreach ($exOtagesMats as $mat) {
            $m = Militaire::where('matricule', $mat)->first();
            if (!$m) continue;

            $m->position_id = 21;
            $m->position_actuelle = "À l'Unité / Au Poste";
            $m->est_disparu = 0;
            $m->observations = "Ex-otage libéré";
            $m->save();

            RegistreOperationnel::where('militaire_id', $m->id)
                ->where('type_registre_code', 2)
                ->delete();
        }

        // Mandats de dépôt
        $m12198 = Militaire::where('matricule', '12198')->first();
        if ($m12198) {
            $m12198->fonction_actuelle = 'Elément';
            $m12198->antecedent_judiciaire = 1;
            $m12198->details_antecedent_judiciaire = 'Mandat de dépôt - Escadron Samory';
            $m12198->position_id = 26;
            $m12198->position_actuelle = 'En détachement';
            $m12198->save();
        }

        $m11099 = Militaire::where('matricule', '11099')->first();
        if ($m11099) {
            $m11099->fonction_actuelle = 'Elément';
            $m11099->observations = 'Fin du Sous-mandat de dépôt';
            $m11099->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
