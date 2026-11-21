<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Militaire;
use App\Models\RegistreOperationnel;
use App\Models\Grade;
use App\Models\Unite;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Définition des 25 dossiers réels issus de status_sanitaire & observations
        $dossiers = [
            // BLESSÉS DE GUERRE ET EN OPÉRATIONS (Type 1)
            [
                'id' => 61,
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'circonstances' => 'Blessé de guerre en service commandé',
                'nature_pathologie' => 'Blessure de guerre par arme / combat',
                'decision_aptitude' => 'Blessé de guerre',
                'observations' => 'Blessé de guerre officiel',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'blesse',
            ],
            [
                'id' => 102,
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'circonstances' => 'Explosion EEI à Ménaka. Perforation tympanique oreille droite, surdité de perception, hypoacousie bilatérale séquellaire post-traumatique (Invalidité 30%)',
                'nature_pathologie' => 'Traumatisme blast / EEI, hypoacousie bilatérale 30% invalidité',
                'decision_aptitude' => 'Evasan le 11/08/2017 suite explosion EEI à Ménaka',
                'observations' => 'Evasan 11/08/2017 - Explosion EEI Ménaka - Invalidité 30%',
                'date_evenement' => '2017-08-11',
                'annee_evenement' => 2017,
                'flag' => 'blesse',
            ],
            [
                'id' => 88,
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'circonstances' => 'Amputation traumatique des 03 doigts main droite suite incident en mission. Pathologie chronique consécutive',
                'nature_pathologie' => 'Amputation traumatique de 3 doigts de la main droite',
                'decision_aptitude' => 'Exemption de mission de terrain et activité physique pdt 6 mois renouvelable (CDT Marius SANOU le 10/05/2021)',
                'observations' => 'Amputation traumatique - Exemption terrain (CDT Marius SANOU)',
                'date_evenement' => '2021-05-10',
                'annee_evenement' => 2021,
                'flag' => 'blesse',
            ],
            [
                'id' => 76,
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'circonstances' => 'Victime d\'AVP au retour de mission commandée le 23/03/2016 : fracture du bras gauche et trois côtes',
                'nature_pathologie' => 'Polytraumatisme AVP en mission commandée (fracture bras gauche et 3 côtes)',
                'decision_aptitude' => 'Evasan le 26/12/2019 suite accident retour mission commandée',
                'observations' => 'Evasan 26/12/2019 - Fracture bras et 3 côtes en retour mission',
                'date_evenement' => '2019-12-26',
                'annee_evenement' => 2019,
                'flag' => 'blesse',
            ],
            [
                'id' => 94,
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'circonstances' => 'Séquelles de blessures survenues en service opérationnel',
                'nature_pathologie' => 'Séquelles de blessures de service',
                'decision_aptitude' => 'Sans exemption (Certificat médical de février 2019)',
                'observations' => 'Séquelles blessures - Certificat médical 02/2019',
                'date_evenement' => '2019-02-01',
                'annee_evenement' => 2019,
                'flag' => 'blesse',
            ],

            // DISPARUS / OTAGES (Type 2)
            [
                'id' => 8,
                'type_registre_code' => RegistreOperationnel::TYPE_DISPARU,
                'circonstances' => 'Militaire pris en otage en zone d\'opérations. Devant partir à la retraite depuis 2020, maintenu impérativement au fichier.',
                'nature_pathologie' => null,
                'decision_aptitude' => 'Maintenu au fichier actif de la Gendarmerie',
                'observations' => 'Otage devant partir à la Retraite depuis 2020 - À maintenir impérativement dans le Fichier',
                'date_evenement' => '2020-01-01',
                'annee_evenement' => 2020,
                'flag' => 'disparu',
            ],

            // GRANDS MALADES / NON MOBILISABLES / INAPTES SÉDENTAIRES (Type 3)
            [
                'id' => 16,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Inaptitude physique temporaire constatée médicalement',
                'nature_pathologie' => 'Inaptitude temporaire',
                'decision_aptitude' => 'Inapte temporaire au service actif de terrain',
                'observations' => 'Inaptitude temporaire',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'malade',
            ],
            [
                'id' => 52,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Hypertension artérielle sévère depuis février 2024 avec risque cardio-vasculaire',
                'nature_pathologie' => 'Hypertension artérielle sévère sous traitement au long cours',
                'decision_aptitude' => 'Suivi régulier cardiologique spécialisé, exemption de déploiement en zone d\'opérations (12/12/2024)',
                'observations' => 'HTA sévère - Exemption zone opérationnelle (12/12/2024)',
                'date_evenement' => '2024-12-12',
                'annee_evenement' => 2024,
                'flag' => 'malade',
            ],
            [
                'id' => 62,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Affection aiguë ayant nécessité un arrêt de travail prolongé',
                'nature_pathologie' => 'Arrêt de travail pour convalescence médicale',
                'decision_aptitude' => 'Arrêt de travail 01 mois P/c 23/10/2020',
                'observations' => 'Arrêt travail médical 23/10/2020',
                'date_evenement' => '2020-10-23',
                'annee_evenement' => 2020,
                'flag' => 'malade',
            ],
            [
                'id' => 81,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Rhumatisme chronique invalidant les mouvements et charges',
                'nature_pathologie' => 'Rhumatisme articulaire chronique',
                'decision_aptitude' => 'Exemption d\'activités physiques pendant 03 mois (03/07/2025)',
                'observations' => 'Rhumatisme chronique - Exemption physiques 3 mois (03/07/2025)',
                'date_evenement' => '2025-07-03',
                'annee_evenement' => 2025,
                'flag' => 'malade',
            ],
            [
                'id' => 83,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Malaise / pathologie lors du déploiement opérationnel PSIRC4 Dialoumbé',
                'nature_pathologie' => 'Maladie avec contre-indication aux efforts intenses',
                'decision_aptitude' => 'Absent à la mission PSIRC4 Dialoumbé, exempté d\'effort intense (07/07/2019)',
                'observations' => 'PSIRC4 Dialoumbé : absent maladie, pas d\'effort intense',
                'date_evenement' => '2019-07-07',
                'annee_evenement' => 2019,
                'flag' => 'malade',
            ],
            [
                'id' => 69,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Troubles psychiques et physiques nécessitant suivi médical régulier et soutien',
                'nature_pathologie' => 'Trouble psychique et physique avec retentissement opérationnel',
                'decision_aptitude' => 'Prise en charge médicale, avis favorable pour un rapprochement à Bamako (COL S. KEITA le 10/12/2021)',
                'observations' => 'Avis favorable rapprochement Bamako pour soins (COL S. KEITA 10/12/2021)',
                'date_evenement' => '2021-12-10',
                'annee_evenement' => 2021,
                'flag' => 'malade',
            ],
            [
                'id' => 74,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Infection virale hépatique chronique',
                'nature_pathologie' => 'Portage chronique du virus de l\'hépatite B (VHB)',
                'decision_aptitude' => 'Suivi régulier tous les 06 mois en service spécialisé (23/03/2022)',
                'observations' => 'Hépatite B chronique - Suivi semestriel (23/03/2022)',
                'date_evenement' => '2022-03-23',
                'annee_evenement' => 2022,
                'flag' => 'malade',
            ],
            [
                'id' => 75,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Affection métabolique chronique',
                'nature_pathologie' => 'Diabète de Type 2',
                'decision_aptitude' => 'Suivi endocrinologique et traitement continu',
                'observations' => 'Diabète Type 2 - Traitement au long cours',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'malade',
            ],
            [
                'id' => 82,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Maladie constatée lors de la mission PSIRC1 Saye',
                'nature_pathologie' => 'Maladie en mission PSIRC1',
                'decision_aptitude' => 'Absent pour maladie',
                'observations' => 'PSIRC1 Saye : absent-malade',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'malade',
            ],
            [
                'id' => 84,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Maladie constatée lors de la mission PSIRC1 Sokolo',
                'nature_pathologie' => 'Maladie aiguë en mission',
                'decision_aptitude' => 'Malade au détachement PSIRC1',
                'observations' => 'PSIRC1 Sokolo : malade',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'malade',
            ],
            [
                'id' => 90,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Altération physique consécutive après désignation pour la Mission Dialloubé 9',
                'nature_pathologie' => 'Inaptitude aux contraintes physiques lourdes et station prolongée',
                'decision_aptitude' => 'Exemption de port de charge lourde, station debout/assise prolongée, sport intense et déploiement ops pdt 6 mois (Accord du 01/07/2022)',
                'observations' => 'Exemption déploiement ops et port de charges (01/07/2022)',
                'date_evenement' => '2022-07-01',
                'annee_evenement' => 2022,
                'flag' => 'malade',
            ],
            [
                'id' => 99,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Pathologie oculaire chirurgicale avec perte de vision stéréoscopique',
                'nature_pathologie' => 'Pseudophakie œil droit',
                'decision_aptitude' => 'Inapte pour les missions de terrain (02/10/2019)',
                'observations' => 'Inapte missions de terrain (02/10/2019)',
                'date_evenement' => '2019-10-02',
                'annee_evenement' => 2019,
                'flag' => 'malade',
            ],
            [
                'id' => 101,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Accident de la voie publique ayant entraîné une baisse sévère d\'acuité visuelle et un glaucome secondaire à l\'œil droit',
                'nature_pathologie' => 'Glaucome secondaire post-AVP et baisse d\'acuité visuelle œil droit',
                'decision_aptitude' => 'Suivi ophtalmologique continu (14/06/2019)',
                'observations' => 'Glaucome secondaire post-AVP œil droit (14/06/2019)',
                'date_evenement' => '2019-06-14',
                'annee_evenement' => 2019,
                'flag' => 'malade',
            ],
            [
                'id' => 71,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Pathologie chronique avec contre-indication physique majeure',
                'nature_pathologie' => 'Pathologie chronique invalidante',
                'decision_aptitude' => 'Exemption d\'activités physiques, sport, marche, station debout et port de charge lourde pdt 6 mois (LCL Marius P. SANOU le 12/07/2024)',
                'observations' => 'Exemption totale charges et marche (LCL Marius SANOU 12/07/2024)',
                'date_evenement' => '2024-07-12',
                'annee_evenement' => 2024,
                'flag' => 'malade',
            ],
            [
                'id' => 13,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Affection vertébrale lombaire sévère avec conflit radiculaire canalaire',
                'nature_pathologie' => 'Hernie discale L5-S1 avec signe de conflit dans le canal rachidien',
                'decision_aptitude' => 'Expertise médicale - Dispense des efforts lombaires et charges',
                'observations' => 'Hernie discale L5-S1 - Conflit canalaire',
                'date_evenement' => null,
                'annee_evenement' => null,
                'flag' => 'malade',
            ],
            [
                'id' => 63,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Diabète sucré de Type II sous traitement régulier',
                'nature_pathologie' => 'Diabète de Type II',
                'decision_aptitude' => 'Certificat du 12/12/2019 - Surveillance métabolique continue',
                'observations' => 'Diabète Type 2 - Certificat du 12/12/2019',
                'date_evenement' => '2019-12-12',
                'annee_evenement' => 2019,
                'flag' => 'malade',
            ],
            [
                'id' => 103,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Pathologie chronique nécessitant un poste à l\'abri des contraintes de terrain',
                'nature_pathologie' => 'Pathologie chronique en milieu spécialisé',
                'decision_aptitude' => 'Suivi régulier dans un milieu spécialisé et emploi sédentaire pdt 6 mois (LCL Marius P. B. le 11/04/2024)',
                'observations' => 'Emploi sédentaire obligatoire (LCL Marius P. B. 11/04/2024)',
                'date_evenement' => '2024-04-11',
                'annee_evenement' => 2024,
                'flag' => 'malade',
            ],
            [
                'id' => 72,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Affection médicale avec intolérance aux efforts intenses et charges lourdes',
                'nature_pathologie' => 'Inaptitude physique opérationnelle',
                'decision_aptitude' => 'Exemption port charge lourde, station prolongée, sport intense, inaptitude déploiement ops pdt 6 mois (CDT Marius le 08/11/2023)',
                'observations' => 'Inapte missions de terrain (CDT Marius 08/11/2023)',
                'date_evenement' => '2023-11-08',
                'annee_evenement' => 2023,
                'flag' => 'malade',
            ],
            [
                'id' => 106,
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'circonstances' => 'Rhumatisme chronique articulaire avec crises douloureuses récurrentes',
                'nature_pathologie' => 'Rhumatisme articulaire chronique',
                'decision_aptitude' => 'Exemption station debout prolongée et charge lourde pdt 6 mois, apte sous réserve de contrôle trimestriel (LCL Marius le 30/10/2023)',
                'observations' => 'Rhumatisme chronique - Contrôle trimestriel (LCL Marius 30/10/2023)',
                'date_evenement' => '2023-10-30',
                'annee_evenement' => 2023,
                'flag' => 'malade',
            ],
        ];

        // 2. Nettoyer les doublons antérieurs éventuels dans registres_operationnels
        RegistreOperationnel::query()->delete();

        // 3. Insérer les 25 enregistrements et synchroniser les flags sur militaires
        $numOrdre = [
            RegistreOperationnel::TYPE_BLESSE => 1,
            RegistreOperationnel::TYPE_DISPARU => 1,
            RegistreOperationnel::TYPE_GRAND_MALADE => 1,
            RegistreOperationnel::TYPE_DECEDE => 1,
            RegistreOperationnel::TYPE_DESERTEUR => 1,
        ];

        foreach ($dossiers as $d) {
            $m = Militaire::find($d['id']);
            if (!$m) continue;

            $type = $d['type_registre_code'];
            $ord = $numOrdre[$type]++;

            // Mise à jour des flags sur le militaire
            if ($d['flag'] === 'blesse') {
                $m->est_blesse_guerre = true;
            } elseif ($d['flag'] === 'disparu') {
                $m->est_disparu = true;
            } elseif ($d['flag'] === 'malade') {
                $m->est_non_mobilisable = true;
            }
            $m->saveQuietly();

            // Création du registre opérationnel
            RegistreOperationnel::create([
                'type_registre_code' => $type,
                'numero_ordre' => $ord,
                'matricule' => $m->matricule,
                'mle_grm' => $m->mle_grm ?? $m->matricule,
                'nom' => $m->nom,
                'prenom' => $m->prenom,
                'grade_id' => $m->grade_id,
                'grade_ordre' => $m->grade_ordre ?? 0,
                'grade_texte' => $m->grade_actuel,
                'sexe_code' => $m->sexe_code ?? 1,
                'region_id' => $m->region_id,
                'unite_id' => $m->unite_id,
                'unite_texte' => $m->unite ?? ($m->unite_id ? Unite::find($m->unite_id)?->nom : null),
                'date_evenement' => $d['date_evenement'],
                'annee_evenement' => $d['annee_evenement'],
                'type_circonstance_code' => 1,
                'circonstances' => $d['circonstances'],
                'nature_pathologie' => $d['nature_pathologie'],
                'decision_aptitude' => $d['decision_aptitude'],
                'observations' => $d['observations'],
                'est_actif_fichier' => true,
                'militaire_id' => $m->id,
            ]);
        }
    }

    public function down(): void
    {
        RegistreOperationnel::query()->delete();
        Militaire::whereIn('id', [61, 102, 88, 76, 94])->update(['est_blesse_guerre' => false]);
        Militaire::where('id', 8)->update(['est_disparu' => false]);
        Militaire::whereIn('id', [16, 52, 62, 81, 83, 69, 74, 75, 82, 84, 90, 99, 101, 71, 13, 63, 103, 72, 106])->update(['est_non_mobilisable' => false]);
    }
};
