<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Militaire;
use App\Models\Position;
use App\Models\RegistreOperationnel;

class SyncOtagesDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gendarmerie:sync-otages';

    /**
     * The console command description.
     */
    protected $description = 'Synchronise ponctuellement les observations et statuts des personnels otages et disparus';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Début de la synchronisation des données d'otages et disparus...");

        $otagePosId = Position::idOtage();

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

        $count = 0;
        foreach ($otagesData as $mat => $data) {
            $m = Militaire::where('matricule', $mat)->first();
            if (!$m) continue;

            $m->position_id = $otagePosId;
            $m->position_actuelle = "Cas d'otage";
            $m->est_disparu = 1;
            if (empty($m->observations) || str_contains($m->observations, 'Otage') || str_contains($m->observations, 'disparu')) {
                $m->observations = $data['obs'];
            }
            if (!empty($data['fct'])) {
                $m->fonction_actuelle = $data['fct'];
            }
            $m->save();

            $reg = RegistreOperationnel::where('militaire_id', $m->id)
                ->where('type_registre_code', RegistreOperationnel::TYPE_DISPARU)
                ->first();

            if (!$reg) {
                $reg = new RegistreOperationnel();
                $reg->militaire_id = $m->id;
                $reg->type_registre_code = RegistreOperationnel::TYPE_DISPARU;
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

            $count++;
        }

        // Ex-otages libérés
        $exOtagesMats = ['9100', '8935', '11211', '13434'];
        $unitePosId = Position::idUnite();
        foreach ($exOtagesMats as $mat) {
            $m = Militaire::where('matricule', $mat)->first();
            if (!$m) continue;

            $m->est_disparu = 0;
            if ($m->position_id === $otagePosId || $m->position_actuelle === "Cas d'otage") {
                $m->position_id = $unitePosId;
                $m->position_actuelle = "À l'Unité / Au Poste";
            }
            $m->save();

            RegistreOperationnel::where('militaire_id', $m->id)
                ->where('type_registre_code', RegistreOperationnel::TYPE_DISPARU)
                ->update(['est_actif_fichier' => 0]);
        }

        $this->info("Synchronisation terminée avec succès ({$count} otages/disparus mis à jour).");
        return self::SUCCESS;
    }
}
