<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrganismeDetachement;
use App\Models\RegistreOperationnel;
use App\Models\SituationPriseArme;
use App\Models\Grade;
use App\Models\Region;
use App\Models\Unite;
use App\Models\Militaire;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PriseArmeReferenceSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================================
        // 1. ORGANISMES DE DETACHEMENT (48 STRUCTURES OFFICIELLES)
        // ========================================================
        $organismes = [
            ['code' => 'GRM', 'nom' => 'Gendarmerie Nationale', 'ordre' => 1, 'est_organique' => true],
            ['code' => 'EMPPR', 'nom' => 'Etat-major Particulier du Président de la République', 'ordre' => 2, 'est_organique' => false],
            ['code' => 'DOCS', 'nom' => 'Direction de l\'Office Central des Stupéfiants', 'ordre' => 3, 'est_organique' => false],
            ['code' => 'PM', 'nom' => 'Primature', 'ordre' => 4, 'est_organique' => false],
            ['code' => 'MEF', 'nom' => 'Ministère de l\'Economie et des Finances', 'ordre' => 5, 'est_organique' => false],
            ['code' => 'CCM', 'nom' => 'Cour Constitutionnelle du Mali', 'ordre' => 6, 'est_organique' => false],
            ['code' => 'EGM', 'nom' => 'Ecole de Guerre du Mali', 'ordre' => 7, 'est_organique' => false],
            ['code' => 'MDAF', 'nom' => 'Ministère des Domaines de l\'Etat et des Affaires Foncières', 'ordre' => 8, 'est_organique' => false],
            ['code' => 'MATD', 'nom' => 'Ministère de l\'Administration Territoriale et de la Décentralisation', 'ordre' => 9, 'est_organique' => false],
            ['code' => 'MDAC', 'nom' => 'Ministère de la Défense et des Anciens Combattants', 'ordre' => 10, 'est_organique' => false],
            ['code' => 'MSPC', 'nom' => 'Ministère de la Sécurité et de la Protection Civile', 'ordre' => 11, 'est_organique' => false],
            ['code' => 'DGPC', 'nom' => 'Direction Générale de la Protection Civile', 'ordre' => 12, 'est_organique' => false],
            ['code' => 'DGPTS', 'nom' => 'Direction Générale de la Police Technique et Scientifique', 'ordre' => 13, 'est_organique' => false],
            ['code' => 'MJGS', 'nom' => 'Ministère de la Justice et des Droits de l\'Homme, Garde des Sceaux', 'ordre' => 14, 'est_organique' => false],
            ['code' => 'MRECRI', 'nom' => 'Ministère de la Refondation de l\'Etat, des Relations avec les Institutions', 'ordre' => 15, 'est_organique' => false],
            ['code' => 'FIRA', 'nom' => 'Force d\'Intervention Rapide des Armées', 'ordre' => 16, 'est_organique' => false],
            ['code' => 'DCSSA', 'nom' => 'Direction Centrale du Service de Santé des Armées', 'ordre' => 17, 'est_organique' => false],
            ['code' => 'MAECI', 'nom' => 'Ministère des Affaires Etrangères et de la Coopération Internationale', 'ordre' => 18, 'est_organique' => false],
            ['code' => 'DRHA', 'nom' => 'Direction des Ressources Humaines des Armées', 'ordre' => 19, 'est_organique' => false],
            ['code' => 'MRPCN', 'nom' => 'Ministère de la Réconciliation, de la Paix et de la Cohésion Nationale', 'ordre' => 20, 'est_organique' => false],
            ['code' => 'DCA', 'nom' => 'Direction du Commissariat des Armées', 'ordre' => 21, 'est_organique' => false],
            ['code' => 'QGDB', 'nom' => 'Quartier Général Division / Brigade', 'ordre' => 22, 'est_organique' => false],
            ['code' => 'DGE', 'nom' => 'Délégation Générale aux Elections', 'ordre' => 23, 'est_organique' => false],
            ['code' => 'CNDDR', 'nom' => 'Commission Nationale de Désarmement, Démobilisation et Réinsertion', 'ordre' => 24, 'est_organique' => false],
            ['code' => 'DEM', 'nom' => 'Direction des Ecoles Militaires', 'ordre' => 25, 'est_organique' => false],
            ['code' => 'CNT', 'nom' => 'Conseil National de Transition', 'ordre' => 26, 'est_organique' => false],
            ['code' => 'CCGCC', 'nom' => 'Centre Conjoint de Coordination des Opérations', 'ordre' => 27, 'est_organique' => false],
            ['code' => 'EMGA', 'nom' => 'Etat-Major Général des Armées', 'ordre' => 28, 'est_organique' => false],
            ['code' => 'IGAS', 'nom' => 'Inspection Générale des Armées et Services', 'ordre' => 29, 'est_organique' => false],
            ['code' => 'MMEEX', 'nom' => 'Ministère des Maliens Etablis à l\'Extérieur', 'ordre' => 30, 'est_organique' => false],
            ['code' => 'MJS', 'nom' => 'Ministère de la Jeunesse et des Sports', 'ordre' => 31, 'est_organique' => false],
            ['code' => 'OCLEI', 'nom' => 'Office Central de Lutte contre l\'Enrichissement Illicite', 'ordre' => 32, 'est_organique' => false],
            ['code' => 'FAT', 'nom' => 'Force Anti-Terroriste (FAT-GRM)', 'ordre' => 33, 'est_organique' => false],
            ['code' => 'MPFEF', 'nom' => 'Ministère de la Promotion de la Femme, de l\'Enfant et de la Famille', 'ordre' => 34, 'est_organique' => false],
            ['code' => 'DSM', 'nom' => 'Direction de la Sécurité Militaire', 'ordre' => 35, 'est_organique' => false],
            ['code' => 'BIS', 'nom' => 'Brigade d\'Investigation Spécialisée (lutte antiterroriste)', 'ordre' => 36, 'est_organique' => false],
            ['code' => 'DIRPA', 'nom' => 'Direction de l\'Information et des Relations Publiques des Armées', 'ordre' => 37, 'est_organique' => false],
            ['code' => 'EMP', 'nom' => 'Ecole de Maintien de la Paix Alioune Blondin Beye', 'ordre' => 38, 'est_organique' => false],
            ['code' => 'MSDS', 'nom' => 'Ministère de la Santé et du Développement Social', 'ordre' => 39, 'est_organique' => false],
            ['code' => 'ANSE', 'nom' => 'Agence Nationale de la Sécurité d\'Etat', 'ordre' => 40, 'est_organique' => false],
            ['code' => 'DJM', 'nom' => 'Direction de la Justice Militaire', 'ordre' => 41, 'est_organique' => false],
            ['code' => 'MACIHT', 'nom' => 'Ministère de l\'Artisanat, de la Culture, de l\'Industrie Hôtelière et du Tourisme', 'ordre' => 42, 'est_organique' => false],
            ['code' => 'DSMil', 'nom' => 'Direction du Sport Militaire', 'ordre' => 43, 'est_organique' => false],
            ['code' => 'MESRS', 'nom' => 'Ministère de l\'Enseignement Supérieur et de la Recherche Scientifique', 'ordre' => 44, 'est_organique' => false],
            ['code' => 'SPLPAL PC', 'nom' => 'Secrétariat Permanent de Lutte contre la Prolifération des Armes Légères', 'ordre' => 45, 'est_organique' => false],
            ['code' => 'CNI', 'nom' => 'Commission Nationale d\'Indemnisation', 'ordre' => 46, 'est_organique' => false],
            ['code' => 'MMEE', 'nom' => 'Ministère des Mines, de l\'Energie et de l\'Eau', 'ordre' => 47, 'est_organique' => false],
            ['code' => 'V.G', 'nom' => 'Vérificateur Général (BVG)', 'ordre' => 48, 'est_organique' => false],
        ];

        foreach ($organismes as $org) {
            OrganismeDetachement::updateOrCreate(['code' => $org['code']], $org);
        }

        // ========================================================
        // 2. PARSING DU FICHIER CSV OFFICIEL POUR LES REGISTRES
        // ========================================================
        $path = storage_path('app/import_reference_grm.csv');
        if (!file_exists($path)) {
            $this->command->error("Fichier de référence non trouvé : {$path}");
            return;
        }

        $gradesMap = Grade::all()->keyBy(fn($g) => mb_strtolower(trim($g->code_grade ?? $g->nom_grade)));
        $gradesByName = Grade::all()->keyBy(fn($g) => mb_strtolower(trim($g->nom_grade)));
        $regionsByCode = Region::all()->keyBy('code');
        $regionsByName = Region::all()->keyBy(fn($r) => mb_strtolower(trim($r->nom)));

        $resolveGrade = function(?string $text) use ($gradesMap, $gradesByName) {
            if (empty($text)) return [null, null, null];
            $clean = mb_strtolower(trim($text));
            $clean = str_replace(['s/ltn', 'slt'], 'slt', $clean);
            $clean = str_replace(['acm', 'maj'], 'acm', $clean);
            $clean = str_replace('gdb', 'gbr', $clean);
            $clean = str_replace('gdd', 'gdv', $clean);
            $clean = str_replace('clm', 'clm', $clean);

            if (isset($gradesMap[$clean])) {
                $g = $gradesMap[$clean];
                return [$g->id, $g->ordre, $g->nom_grade];
            }
            if (isset($gradesByName[$clean])) {
                $g = $gradesByName[$clean];
                return [$g->id, $g->ordre, $g->nom_grade];
            }
            // Essai avec préfixes usuels
            foreach ($gradesMap as $code => $g) {
                if (str_starts_with($clean, $code) || str_starts_with($code, $clean)) {
                    return [$g->id, $g->ordre, $g->nom_grade];
                }
            }
            return [null, 99, trim($text)];
        };

        $parseDate = function(?string $val) {
            if (empty($val)) return [null, null];
            $t = trim($val);
            if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $t, $m)) {
                $dt = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                return [$dt, (int)$m[1]];
            }
            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $t, $m)) {
                $dt = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                return [$dt, (int)$m[3]];
            }
            if (preg_match('/\b(19\d{2}|20\d{2})\b/', $t, $m)) {
                return [null, (int)$m[1]];
            }
            return [null, null];
        };

        $file = fopen($path, 'r');
        $mode = 'HEADER';
        $countBlesses = 0;
        $countDisparus = 0;
        $countDecedes = 0;
        $countDeserteurs = 0;
        $countMalades = 0;
        $seenDeserteurs = [];

        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || !array_filter($row)) continue;
            $first = trim($row[0] ?? '');

            if (str_contains($first, "ETAT DE SUIVI DES BLESSES DE GEURRE")) {
                $mode = 'BLESSES';
                continue;
            } elseif (str_contains($first, "ETAT DE SUIVI DES PORTES DISPARUS")) {
                $mode = 'DISPARUS';
                continue;
            } elseif (str_contains($first, "SITUATION  DES  DESERTEURS") || str_contains($first, "SITUATION DES DESERTEURS")) {
                $mode = 'DESERTEURS';
                continue;
            } elseif (str_contains($first, "LISTE DES MILITAIRES DECEDES")) {
                $mode = 'DECEDES';
                continue;
            } elseif (str_contains($first, "SITUATION  DES  ELEMENTS NON MOBILISABLES") || str_contains($first, "SITUATION DES ELEMENTS NON MOBILISABLES")) {
                $mode = 'MALADES';
                continue;
            } elseif (str_contains($first, "SITUATION DE PRISE D'ARME") || str_contains($first, "SITUATION PAR REGION")) {
                $mode = 'AUTRE';
                continue;
            }

            if ($first === 'N° ord' || $first === 'N°' || $first === 'GRADES' || $first === 'Total' || $first === 'AMPLIATIONS:' || $first === 'Légende:') {
                continue;
            }

            // 1. BLESSES DE GUERRE (171)
            // N° ord, Grade, Prénoms, Noms, Mles, Unités, Circonstances, Dates, Ob.
            if ($mode === 'BLESSES' && is_numeric($first)) {
                $gradeTxt = trim($row[1] ?? '');
                $prenom = trim($row[2] ?? '');
                $nom = trim($row[3] ?? '');
                $mle = trim($row[4] ?? '');
                $unite = trim($row[5] ?? '');
                $circonstances = trim($row[6] ?? '');
                $dateStr = trim($row[7] ?? '');
                $ob = trim($row[8] ?? '');

                [$gid, $gOrdre, $gLabel] = $resolveGrade($gradeTxt);
                [$dateEvt, $anneeEvt] = $parseDate($dateStr);

                // Déduction du code circonstance
                $typeCir = 1; // 1: Opération
                if (stripos($ob, 'Mission') !== false || stripos($circonstances, 'mission') !== false) $typeCir = 2;
                if (stripos($circonstances, 'EEI') !== false || stripos($circonstances, 'engin explosif') !== false) $typeCir = 4;
                if (stripos($circonstances, 'embuscade') !== false) $typeCir = 5;
                if (stripos($circonstances, 'accident') !== false) $typeCir = 6;

                RegistreOperationnel::updateOrCreate(
                    [
                        'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                        'numero_ordre' => (int)$first,
                        'matricule' => $mle !== '' && $mle !== 'Mr' && $mle !== 'MR' ? $mle : null,
                    ],
                    [
                        'nom' => $nom ?: 'INCONNU',
                        'prenom' => $prenom,
                        'grade_id' => $gid,
                        'grade_ordre' => $gOrdre,
                        'grade_texte' => $gLabel ?: $gradeTxt,
                        'unite_texte' => $unite,
                        'date_evenement' => $dateEvt,
                        'annee_evenement' => $anneeEvt,
                        'type_circonstance_code' => $typeCir,
                        'circonstances' => $circonstances,
                        'observations' => $ob,
                    ]
                );
                $countBlesses++;
            }

            // 2. PORTES DISPARUS (9)
            // N° ord, Grade, Prénoms, Noms, Mle, Dernières Unité, Dates disparition, Circonstances, Ob.
            elseif ($mode === 'DISPARUS' && is_numeric($first)) {
                $gradeTxt = trim($row[1] ?? '');
                $prenom = trim($row[2] ?? '');
                $nom = trim($row[3] ?? '');
                $mle = trim($row[4] ?? '');
                $unite = trim($row[5] ?? '');
                $dateStr = trim($row[6] ?? '');
                $circonstances = trim($row[7] ?? '');
                $ob = trim($row[8] ?? '');

                [$gid, $gOrdre, $gLabel] = $resolveGrade($gradeTxt);
                [$dateEvt, $anneeEvt] = $parseDate($dateStr);

                RegistreOperationnel::updateOrCreate(
                    [
                        'type_registre_code' => RegistreOperationnel::TYPE_DISPARU,
                        'numero_ordre' => (int)$first,
                    ],
                    [
                        'matricule' => $mle !== '' && $mle !== 'Mr' ? $mle : null,
                        'nom' => $nom ?: 'INCONNU',
                        'prenom' => $prenom,
                        'grade_id' => $gid,
                        'grade_ordre' => $gOrdre,
                        'grade_texte' => $gLabel ?: $gradeTxt,
                        'unite_texte' => $unite,
                        'date_evenement' => $dateEvt,
                        'annee_evenement' => $anneeEvt,
                        'type_circonstance_code' => 1, // Enlevé / Kidnappé / Otage
                        'circonstances' => $circonstances,
                        'observations' => $ob ?: 'Porté disparu / Otage - À maintenir au fichier RH',
                        'est_actif_fichier' => true,
                    ]
                );
                $countDisparus++;
            }

            // 3. DESERTEURS (17)
            // N° ord, Mle, Prénoms, Noms, Grade, Année, Observations
            elseif ($mode === 'DESERTEURS' && is_numeric($first)) {
                $mle = trim($row[1] ?? '');
                $prenom = trim($row[2] ?? '');
                $nom = trim($row[3] ?? '');
                $gradeTxt = trim($row[4] ?? '');
                $dateStr = trim($row[5] ?? '');
                $ob = trim($row[6] ?? '');

                if (isset($seenDeserteurs[$mle])) continue;
                $seenDeserteurs[$mle] = true;

                [$gid, $gOrdre, $gLabel] = $resolveGrade($gradeTxt);
                [$dateEvt, $anneeEvt] = $parseDate($dateStr);

                RegistreOperationnel::updateOrCreate(
                    [
                        'type_registre_code' => RegistreOperationnel::TYPE_DESERTEUR,
                        'matricule' => $mle,
                    ],
                    [
                        'numero_ordre' => (int)$first,
                        'nom' => $nom ?: 'INCONNU',
                        'prenom' => $prenom,
                        'grade_id' => $gid,
                        'grade_ordre' => $gOrdre,
                        'grade_texte' => $gLabel ?: $gradeTxt,
                        'date_evenement' => $dateEvt,
                        'annee_evenement' => $anneeEvt,
                        'type_circonstance_code' => 1, // Défection
                        'circonstances' => 'Déclaration de désertion',
                        'observations' => $ob,
                        'est_actif_fichier' => false,
                    ]
                );
                $countDeserteurs++;
            }

            // 4. DECEDES (71)
            // N° ord, Prénoms, Noms, Mle, Grade, Unité, Date Décès, Circonstances
            elseif ($mode === 'DECEDES' && is_numeric($first)) {
                $prenom = trim($row[1] ?? '');
                $nom = trim($row[2] ?? '');
                $mle = trim($row[3] ?? '');
                $gradeTxt = trim($row[4] ?? '');
                $unite = trim($row[5] ?? '');
                $dateStr = trim($row[6] ?? '');
                $circonstances = trim($row[7] ?? '');

                [$gid, $gOrdre, $gLabel] = $resolveGrade($gradeTxt);
                [$dateEvt, $anneeEvt] = $parseDate($dateStr);

                $typeCir = 1; // 1: Opération
                if (stripos($circonstances, 'maladie') !== false) $typeCir = 4;
                if (stripos($circonstances, 'noyade') !== false) $typeCir = 7;
                if (stripos($circonstances, 'accident') !== false) $typeCir = 6;
                if (stripos($circonstances, 'naturelle') !== false) $typeCir = 8;
                if (stripos($circonstances, 'EEI') !== false || stripos($circonstances, 'drone') !== false) $typeCir = 3;

                RegistreOperationnel::updateOrCreate(
                    [
                        'type_registre_code' => RegistreOperationnel::TYPE_DECEDE,
                        'numero_ordre' => (int)$first,
                        'nom' => $nom ?: 'INCONNU',
                        'prenom' => $prenom,
                    ],
                    [
                        'matricule' => $mle !== '' && $mle !== 'Mr' && $mle !== 'M' ? $mle : null,
                        'grade_id' => $gid,
                        'grade_ordre' => $gOrdre,
                        'grade_texte' => $gLabel ?: $gradeTxt,
                        'unite_texte' => $unite,
                        'date_evenement' => $dateEvt,
                        'annee_evenement' => $anneeEvt,
                        'type_circonstance_code' => $typeCir,
                        'circonstances' => $circonstances,
                        'est_actif_fichier' => false,
                    ]
                );
                $countDecedes++;
            }

            // 5. ELEMENTS NON MOBILISABLES (GRANDS MALADES) (87)
            // N° ord, Grade, Prénoms, Noms, Mle, Unité, Nature, Ob.
            elseif ($mode === 'MALADES' && is_numeric($first)) {
                $gradeTxt = trim($row[1] ?? '');
                $prenom = trim($row[2] ?? '');
                $nom = trim($row[3] ?? '');
                $mle = trim($row[4] ?? '');
                $unite = trim($row[5] ?? '');
                $nature = trim($row[6] ?? '');
                $ob = trim($row[7] ?? '');

                [$gid, $gOrdre, $gLabel] = $resolveGrade($gradeTxt);

                $codeInapt = 1; // Sédentaire
                if (stripos($nature, 'amput') !== false) $codeInapt = 4;
                if (stripos($nature, 'inapte port') !== false) $codeInapt = 2;
                if (stripos($nature, 'exemption') !== false) $codeInapt = 3;
                if (stripos($nature, 'diab') !== false || stripos($nature, 'chronique') !== false) $codeInapt = 5;

                RegistreOperationnel::updateOrCreate(
                    [
                        'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                        'numero_ordre' => (int)$first,
                    ],
                    [
                        'matricule' => $mle !== '' && $mle !== 'Mr' && $mle !== 'M' ? $mle : null,
                        'nom' => $nom ?: 'INCONNU',
                        'prenom' => $prenom,
                        'grade_id' => $gid,
                        'grade_ordre' => $gOrdre,
                        'grade_texte' => $gLabel ?: $gradeTxt,
                        'unite_texte' => $unite,
                        'type_circonstance_code' => $codeInapt,
                        'nature_pathologie' => $nature,
                        'observations' => $ob,
                        'est_actif_fichier' => true,
                    ]
                );
                $countMalades++;
            }
        }
        fclose($file);

        // ========================================================
        // 3. SITUATION DE PRISE D'ARMES HISTORIQUE OFFICIELLE
        // ========================================================
        $spaOctobre2026 = [
            'periode_mois' => 10,
            'periode_annee' => 2026,
            'titre_periode' => 'Période du 01 au 31 Octobre 2026',
            'reference_numero' => 'N°______________/4 GRM-SDRH-DAGP',
            'total_hommes' => 10438,
            'total_dames' => 2161,
            'total_general' => 12599,
            'signataire_nom' => 'Général de Brigade Moussa Toumani KONE',
            'signataire_titre' => 'Le Directeur Général',
            'signataire_decoration' => 'Chevalier de l\'Ordre National',
            'donnees_matrice' => [
                'GDD' => ['total_h' => 1, 'total_d' => 1, 'total' => 2, 'EMPPR' => ['h' => 1]],
                'GDB' => ['total_h' => 7, 'total_d' => 6, 'total' => 13, 'GRM' => ['h' => 1], 'MEF' => ['h' => 1], 'DEM' => ['h' => 3], 'CCGCC' => ['h' => 1], 'DIRPA' => ['h' => 1]],
                'CLM' => ['total_h' => 15, 'total_d' => 15, 'total' => 30, 'GRM' => ['h' => 1], 'DOCS' => ['h' => 1], 'MATD' => ['h' => 2], 'MSPC' => ['h' => 2], 'DGE' => ['h' => 1], 'EMGA' => ['h' => 1], 'FAT' => ['h' => 1, 'd' => 1], 'DSM' => ['h' => 2], 'ANSE' => ['h' => 1], 'MESRS' => ['h' => 1], 'V.G' => ['h' => 1]],
                'COL' => ['total_h' => 70, 'total_d' => 27, 'total' => 97, 'GRM' => ['h' => 41, 'd' => 1], 'EMPPR' => ['h' => 1], 'PM' => ['h' => 4], 'EGM' => ['h' => 1], 'MDAF' => ['h' => 2], 'MATD' => ['h' => 4], 'MSPC' => ['h' => 3], 'MJGS' => ['h' => 1], 'MAECI' => ['h' => 1], 'MRPCN' => ['h' => 1], 'DEM' => ['h' => 2], 'MSDS' => ['h' => 7], 'DJM' => ['h' => 1]],
                'LCL' => ['total_h' => 63, 'total_d' => 21, 'total' => 84, 'GRM' => ['h' => 36, 'd' => 2], 'EMPPR' => ['h' => 2, 'd' => 2], 'PM' => ['h' => 2], 'MATD' => ['h' => 1], 'MDAC' => ['h' => 2], 'MSPC' => ['h' => 2], 'CNDDR' => ['h' => 1], 'FAT' => ['h' => 2], 'DIRPA' => ['h' => 1], 'EMP' => ['h' => 7], 'MSDS' => ['h' => 2], 'DJM' => ['h' => 1]],
                'CES' => ['total_h' => 60, 'total_d' => 17, 'total' => 77, 'GRM' => ['h' => 37, 'd' => 3], 'EMPPR' => ['h' => 1], 'DOCS' => ['h' => 2, 'd' => 1], 'PM' => ['h' => 1], 'MSPC' => ['h' => 1], 'MJGS' => ['h' => 1], 'DCSSA' => ['h' => 1, 'd' => 3], 'MAECI' => ['h' => 1], 'DSM' => ['h' => 1], 'EMP' => ['h' => 3], 'MSDS' => ['h' => 1], 'DJM' => ['h' => 1], 'SPLPAL PC' => ['h' => 2]],
                'CNE' => ['total_h' => 105, 'total_d' => 33, 'total' => 138, 'GRM' => ['h' => 63, 'd' => 5], 'DOCS' => ['h' => 6, 'd' => 1], 'PM' => ['h' => 1], 'MSPC' => ['h' => 1], 'DGPC' => ['h' => 6], 'DCSSA' => ['h' => 2, 'd' => 1], 'MAECI' => ['h' => 1], 'DEM' => ['h' => 7], 'CCGCC' => ['h' => 3], 'IGAS' => ['h' => 2, 'd' => 2], 'OCLEI' => ['h' => 4, 'd' => 2]],
                'LTN' => ['total_h' => 244, 'total_d' => 71, 'total' => 315, 'GRM' => ['h' => 166, 'd' => 10], 'EMPPR' => ['h' => 3], 'DOCS' => ['h' => 6, 'd' => 1], 'MATD' => ['h' => 1], 'MDAC' => ['h' => 1], 'MSPC' => ['h' => 2], 'DGPTS' => ['h' => 9], 'DCSSA' => ['h' => 3], 'QGDB' => ['h' => 1], 'DEM' => ['h' => 8], 'CCGCC' => ['h' => 4], 'EMGA' => ['h' => 2], 'IGAS' => ['h' => 12, 'd' => 12], 'MMEEX' => ['h' => 1], 'MJS' => ['h' => 6], 'OCLEI' => ['h' => 1], 'FAT' => ['h' => 1, 'd' => 1], 'DSM' => ['h' => 3], 'SPLPAL PC' => ['h' => 2]],
                'SLT' => ['total_h' => 304, 'total_d' => 67, 'total' => 371, 'GRM' => ['h' => 223, 'd' => 14], 'EMPPR' => ['h' => 4], 'DOCS' => ['h' => 4, 'd' => 1], 'PM' => ['h' => 2], 'EGM' => ['h' => 1], 'MDAF' => ['h' => 1], 'MATD' => ['h' => 1], 'MDAC' => ['h' => 2], 'MSPC' => ['h' => 1, 'd' => 1], 'DGPC' => ['h' => 2], 'MJGS' => ['h' => 5, 'd' => 1], 'MRECRI' => ['h' => 1], 'MAECI' => ['h' => 1], 'DEM' => ['h' => 13], 'CCGCC' => ['h' => 5], 'EMGA' => ['h' => 1], 'IGAS' => ['h' => 8, 'd' => 8], 'MJS' => ['h' => 4], 'OCLEI' => ['h' => 2], 'FAT' => ['h' => 1], 'DSM' => ['h' => 2], 'ANSE' => ['h' => 1], 'MACIHT' => ['h' => 1, 'd' => 1]],
                'EAO. & ASP.' => ['total_h' => 94, 'total_d' => 1, 'total' => 95, 'GRM' => ['h' => 89, 'd' => 4], 'CNT' => ['h' => 1]],
                'MJR' => ['total_h' => 153, 'total_d' => 19, 'total' => 172, 'GRM' => ['h' => 129], 'DOCS' => ['h' => 9], 'MEF' => ['h' => 1], 'CCM' => ['h' => 1], 'MDAF' => ['h' => 1], 'DGPC' => ['h' => 2], 'FAT' => ['h' => 1], 'DIRPA' => ['h' => 4, 'd' => 4], 'ANSE' => ['h' => 2], 'SPLPAL PC' => ['h' => 3]],
                'ADC' => ['total_h' => 2258, 'total_d' => 274, 'total' => 2532, 'GRM' => ['h' => 1808, 'd' => 101], 'EMPPR' => ['h' => 22], 'DOCS' => ['h' => 75, 'd' => 12], 'PM' => ['h' => 9], 'MEF' => ['h' => 1], 'MDAC' => ['h' => 14], 'MSPC' => ['h' => 16, 'd' => 1], 'DGPC' => ['h' => 4], 'MJGS' => ['h' => 22, 'd' => 5], 'FIRA' => ['h' => 1], 'DRHA' => ['h' => 8], 'DCA' => ['h' => 1], 'DGE' => ['h' => 1], 'DEM' => ['h' => 4], 'CCGCC' => ['h' => 1], 'EMGA' => ['h' => 3], 'IGAS' => ['h' => 1], 'MMEEX' => ['h' => 17], 'OCLEI' => ['h' => 3], 'FAT' => ['h' => 2], 'MPFEF' => ['h' => 25], 'DSM' => ['h' => 4], 'BIS' => ['h' => 18, 'd' => 4], 'DIRPA' => ['h' => 22], 'MSDS' => ['h' => 4], 'ANSE' => ['h' => 38], 'DJM' => ['h' => 3], 'MACIHT' => ['h' => 1], 'MESRS' => ['h' => 1], 'SPLPAL PC' => ['h' => 1, 'd' => 1], 'MMEE' => ['h' => 6], 'V.G' => ['h' => 16]],
                'ADJ' => ['total_h' => 795, 'total_d' => 120, 'total' => 915, 'GRM' => ['h' => 565, 'd' => 82], 'EMPPR' => ['h' => 4], 'DOCS' => ['h' => 24, 'd' => 6], 'PM' => ['h' => 7], 'MATD' => ['h' => 1], 'MDAC' => ['h' => 3], 'MSPC' => ['h' => 3, 'd' => 1], 'MRPCN' => ['h' => 1], 'DEM' => ['h' => 1], 'EMGA' => ['h' => 2], 'IGAS' => ['h' => 23], 'FAT' => ['h' => 59], 'BIS' => ['h' => 1], 'EMP' => ['h' => 4], 'ANSE' => ['h' => 4], 'MESRS' => ['h' => 3], 'SPLPAL PC' => ['h' => 1]],
                'MDC' => ['total_h' => 1958, 'total_d' => 151, 'total' => 2109, 'GRM' => ['h' => 1610, 'd' => 181], 'DOCS' => ['h' => 16, 'd' => 2], 'PM' => ['h' => 2], 'MATD' => ['h' => 3], 'MDAC' => ['h' => 2], 'MSPC' => ['h' => 1, 'd' => 2], 'DCSSA' => ['h' => 1], 'MRPCN' => ['h' => 1], 'DGE' => ['h' => 4], 'CNT' => ['h' => 2], 'EMGA' => ['h' => 4], 'IGAS' => ['h' => 25], 'FAT' => ['h' => 73], 'DSM' => ['h' => 1], 'ANSE' => ['h' => 28]],
                'MDL' => ['total_h' => 1746, 'total_d' => 488, 'total' => 2234, 'GRM' => ['h' => 1212, 'd' => 42], 'DOCS' => ['h' => 4, 'd' => 2], 'MDAC' => ['h' => 2], 'MSPC' => ['h' => 1], 'MRPCN' => ['h' => 1], 'DGE' => ['h' => 1], 'EMGA' => ['h' => 352], 'FAT' => ['h' => 105], 'DSM' => ['h' => 1], 'ANSE' => ['h' => 22]],
                'EG' => ['total_h' => 2, 'total_d' => 0, 'total' => 2, 'GRM' => ['h' => 2]],
                'GA' => ['total_h' => 2563, 'total_d' => 850, 'total' => 3413, 'GRM' => ['h' => 1713], 'EMGA' => ['h' => 850]],
            ],
            // Déploiement par région pour les 20 régions (District de Bamako + 19 Régions)
            'donnees_regions' => [
                'R1' => ['nom' => 'District de Bamako', 'MDL' => 480, 'MDC' => 135, 'ADJ' => 50, 'ADC' => 256, 'MJR' => 6, 'SLT' => 25, 'LTN' => 15, 'CNE' => 5, 'CES' => 5, 'LCL' => 1, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 979],
                'R2' => ['nom' => 'Région de Kayes', 'MDL' => 159, 'MDC' => 166, 'ADJ' => 61, 'ADC' => 276, 'MJR' => 2, 'SLT' => 6, 'LTN' => 12, 'CNE' => 6, 'CES' => 1, 'LCL' => 2, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 692],
                'R3' => ['nom' => 'Région de Koulikoro', 'MDL' => 170, 'MDC' => 161, 'ADJ' => 65, 'ADC' => 371, 'MJR' => 11, 'SLT' => 7, 'LTN' => 16, 'CNE' => 3, 'CES' => 3, 'LCL' => 2, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 810],
                'R4' => ['nom' => 'Région de Sikasso', 'MDL' => 113, 'MDC' => 165, 'ADJ' => 67, 'ADC' => 362, 'MJR' => 12, 'SLT' => 5, 'LTN' => 12, 'CNE' => 7, 'CES' => 0, 'LCL' => 4, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 748],
                'R5' => ['nom' => 'Région de Ségou', 'MDL' => 92, 'MDC' => 134, 'ADJ' => 36, 'ADC' => 215, 'MJR' => 8, 'SLT' => 7, 'LTN' => 13, 'CNE' => 4, 'CES' => 0, 'LCL' => 2, 'COL' => 2, 'CLM' => 0, 'GBD' => 0, 'total' => 513],
                'R6' => ['nom' => 'Région de Mopti', 'MDL' => 74, 'MDC' => 153, 'ADJ' => 12, 'ADC' => 78, 'MJR' => 8, 'SLT' => 18, 'LTN' => 11, 'CNE' => 4, 'CES' => 0, 'LCL' => 3, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 362],
                'R7' => ['nom' => 'Région de Tombouctou', 'MDL' => 70, 'MDC' => 58, 'ADJ' => 21, 'ADC' => 74, 'MJR' => 2, 'SLT' => 2, 'LTN' => 5, 'CNE' => 2, 'CES' => 1, 'LCL' => 1, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 236],
                'R8' => ['nom' => 'Région de Gao', 'MDL' => 46, 'MDC' => 18, 'ADJ' => 20, 'ADC' => 111, 'MJR' => 1, 'SLT' => 2, 'LTN' => 3, 'CNE' => 2, 'CES' => 1, 'LCL' => 1, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 205],
                'R9' => ['nom' => 'Région de Kidal', 'MDL' => 47, 'MDC' => 15, 'ADJ' => 2, 'ADC' => 13, 'MJR' => 1, 'SLT' => 1, 'LTN' => 1, 'CNE' => 1, 'CES' => 0, 'LCL' => 1, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 82],
                'R10' => ['nom' => 'Région de Nioro du Sahel', 'MDL' => 12, 'MDC' => 19, 'ADJ' => 8, 'ADC' => 27, 'MJR' => 2, 'SLT' => 0, 'LTN' => 3, 'CNE' => 2, 'CES' => 1, 'LCL' => 1, 'COL' => 1, 'CLM' => 0, 'GBD' => 0, 'total' => 76],
                'R11' => ['nom' => 'Région de Ménaka', 'MDL' => 0, 'MDC' => 7, 'ADJ' => 7, 'ADC' => 19, 'MJR' => 0, 'SLT' => 1, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 1, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 35],
                'R12' => ['nom' => 'Région de Kita', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R13' => ['nom' => 'Région de Dioïla', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R14' => ['nom' => 'Région de Nara', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R15' => ['nom' => 'Région de Bougouni', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R16' => ['nom' => 'Région de Koutiala', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R17' => ['nom' => 'Région de San', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R18' => ['nom' => 'Région de Douentza', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R19' => ['nom' => 'Région de Bandiagara', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
                'R20' => ['nom' => 'Région de Taoudénit', 'MDL' => 0, 'MDC' => 0, 'ADJ' => 0, 'ADC' => 0, 'MJR' => 0, 'SLT' => 0, 'LTN' => 0, 'CNE' => 0, 'CES' => 0, 'LCL' => 0, 'COL' => 0, 'CLM' => 0, 'GBD' => 0, 'total' => 0],
            ],
        ];

        SituationPriseArme::updateOrCreate(
            ['periode_mois' => 10, 'periode_annee' => 2026],
            $spaOctobre2026
        );

        $this->command->info("Seeder exécuté avec succès :");
        $this->command->info("- 48 Organismes de détachement initialisés");
        $this->command->info("- {$countBlesses} Blessés de guerre enregistrés");
        $this->command->info("- {$countDisparus} Portés disparus & Otages enregistrés");
        $this->command->info("- {$countDeserteurs} Déserteurs enregistrés");
        $this->command->info("- {$countDecedes} Décédés enregistrés");
        $this->command->info("- {$countMalades} Grands malades & Inaptes enregistrés");
        $this->command->info("- Situation officielle d'Octobre 2026 enregistrée");
    }
}
