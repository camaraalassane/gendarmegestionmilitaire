<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\SpaMatriceSheet;
use App\Exports\Sheets\SpaRegionsSheet;
use App\Exports\Sheets\SpaRegistresSheet;
use App\Models\RegistreOperationnel;
use App\Models\OrganismeDetachement;
use App\Models\Region;
use App\Services\SituationPriseArmeService;

class SituationPriseArmesExport implements WithMultipleSheets
{
    public function __construct()
    {
    }

    public function sheets(): array
    {
        $spaService = app(SituationPriseArmeService::class);
        $organismes = OrganismeDetachement::where('est_actif', 1)->orderBy('ordre')->get()->toArray();
        $regions = Region::orderBy('ordre')->get();

        $sheets = [];

        // 100% Dynamique depuis la base de données
        $matrice = $spaService->calculerMatriceDynamique();
        $regionsData = $spaService->calculerRegionsDynamique();
        $titrePeriode = "SITUATION DE PRISE D'ARMES (EFFECTIF EN DIRECT AU " . now()->format('d/m/Y') . ")";
        $referenceNumero = 'N°______________/4 GRM-SDRH-DAGP';
        $signataireNom = 'Général de Brigade Moussa Toumani KONE';
        $signataireTitre = 'Le Directeur Général';
        $signataireDecoration = 'Chevalier de l\'Ordre National';

        // 1. Feuille Situation Prise d'Armes (Matrice 17x48)
        $matriceData = [
            'titre_periode' => $titrePeriode,
            'reference_numero' => $referenceNumero,
            'signataire_nom' => $signataireNom,
            'signataire_titre' => $signataireTitre,
            'signataire_decoration' => $signataireDecoration,
            'organismes' => $organismes,
            'matrice' => $matrice,
        ];
        $sheets[] = new SpaMatriceSheet($matriceData);

        // 2. Feuille Situation par Région (19 Régions + 1 District de Bamako)
        if (empty($regionsData)) {
            $regionsData = [];
            foreach ($regions as $r) {
                $regionsData[$r->code] = ['nom' => $r->nom];
            }
        }
        $sheets[] = new SpaRegionsSheet($regionsData);

        // 3. Feuille Blessés de Guerre & en opération (depuis registres_operationnels)
        $blesses = RegistreOperationnel::blesses()->with(['region', 'unite.region'])->orderBy('numero_ordre')->get()->map(function ($b, $i) {
            $reg = $b->region ?? $b->unite?->region;
            $regLibelle = $reg ? ($reg->code ? "[{$reg->code}] {$reg->nom}" : $reg->nom) : '';
            return [
                $b->numero_ordre ?? ($i + 1),
                $b->grade_texte ?? '',
                $b->prenom ?? '',
                $b->nom ?? '',
                $b->matricule ?? '',
                $regLibelle,
                $b->unite_texte ?? '',
                $b->circonstances ?? '',
                $b->date_evenement ? $b->date_evenement->format('Y-m-d') : ($b->annee_evenement ? "En {$b->annee_evenement}" : ''),
                $b->observations ?? 'Opération',
            ];
        })->toArray();
        $sheets[] = new SpaRegistresSheet(
            'Blessés de Guerre',
            'LISTE DU PERSONNEL DE LA GENDARMERIE BLESSES EN OPERATION ET BLESSES DE GUERRE',
            ['N° ord', 'Grade', 'Prénoms', 'Noms', 'Mles', 'Région', 'Unités', 'Circonstances', 'Dates', 'Ob.'],
            $blesses
        );

        // 4. Feuille Portés disparus & Otages (depuis registres_operationnels)
        $disparus = RegistreOperationnel::disparus()->with(['region', 'unite.region'])->orderBy('numero_ordre')->get()->map(function ($d, $i) {
            $reg = $d->region ?? $d->unite?->region;
            $regLibelle = $reg ? ($reg->code ? "[{$reg->code}] {$reg->nom}" : $reg->nom) : '';
            return [
                $d->numero_ordre ?? ($i + 1),
                $d->grade_texte ?? '',
                $d->prenom ?? '',
                $d->nom ?? '',
                $d->matricule ?? '',
                $regLibelle,
                $d->unite_texte ?? '',
                $d->date_evenement ? $d->date_evenement->format('Y-m-d') : ($d->annee_evenement ? "En {$d->annee_evenement}" : ''),
                $d->circonstances ?? '',
                $d->observations ?? 'Porté disparu / Otage',
            ];
        })->toArray();
        $sheets[] = new SpaRegistresSheet(
            'Portés Disparus',
            'ETAT DE SUIVI DES PORTES DISPARUS ET OTAGES DE LA GENDARMERIE NATIONALE',
            ['N° ord', 'Grade', 'Prénoms', 'Noms', 'Mle', 'Région', 'Dernières Unité', 'Dates disparition', 'Circonstances', 'Ob.'],
            $disparus
        );

        // 5. Feuille Déserteurs (depuis registres_operationnels)
        $deserteurs = RegistreOperationnel::deserteurs()->with(['region', 'unite.region'])->orderBy('numero_ordre')->get()->map(function ($des, $i) {
            $reg = $des->region ?? $des->unite?->region;
            $regLibelle = $reg ? ($reg->code ? "[{$reg->code}] {$reg->nom}" : $reg->nom) : '';
            return [
                $des->numero_ordre ?? ($i + 1),
                $des->matricule ?? '',
                $des->prenom ?? '',
                $des->nom ?? '',
                $des->grade_texte ?? '',
                $regLibelle,
                $des->unite_texte ?? '',
                $des->date_evenement ? $des->date_evenement->format('Y-m-d') : ($des->annee_evenement ?? ''),
                $des->observations ?? '',
            ];
        })->toArray();
        $sheets[] = new SpaRegistresSheet(
            'Déserteurs',
            'SITUATION DES DESERTEURS DE LA GENDARMERIE NATIONALE',
            ['N° ord', 'Mle', 'Prénoms', 'Noms', 'Grade', 'Région', 'Unité', 'Année / Date', 'Observations'],
            $deserteurs
        );

        // 6. Feuille Décédés (depuis registres_operationnels)
        $decedes = RegistreOperationnel::decedes()->with(['region', 'unite.region'])->orderBy('numero_ordre')->get()->map(function ($dec, $i) {
            $reg = $dec->region ?? $dec->unite?->region;
            $regLibelle = $reg ? ($reg->code ? "[{$reg->code}] {$reg->nom}" : $reg->nom) : '';
            return [
                $dec->numero_ordre ?? ($i + 1),
                $dec->prenom ?? '',
                $dec->nom ?? '',
                $dec->matricule ?? '',
                $dec->grade_texte ?? '',
                $regLibelle,
                $dec->unite_texte ?? '',
                $dec->date_evenement ? $dec->date_evenement->format('Y-m-d') : ($dec->annee_evenement ? "En {$dec->annee_evenement}" : ''),
                $dec->circonstances ?? '',
            ];
        })->toArray();
        $sheets[] = new SpaRegistresSheet(
            'Décédés en Opération',
            'LISTE DES MILITAIRES DECEDES EN OPERATIONS OU SERVICE COMMANDE OU D\'AUTRES CIRCONSTANCES',
            ['N° ord', 'Prénoms', 'Noms', 'Mle', 'Grade', 'Région', 'Unité', 'Date Décès', 'Circonstances'],
            $decedes
        );

        // 7. Feuille Non Mobilisables (Grands Malades) (depuis registres_operationnels)
        $malades = RegistreOperationnel::grandsMalades()->with(['region', 'unite.region'])->orderBy('numero_ordre')->get()->map(function ($m, $i) {
            $reg = $m->region ?? $m->unite?->region;
            $regLibelle = $reg ? ($reg->code ? "[{$reg->code}] {$reg->nom}" : $reg->nom) : '';
            return [
                $m->numero_ordre ?? ($i + 1),
                $m->grade_texte ?? '',
                $m->prenom ?? '',
                $m->nom ?? '',
                $m->matricule ?? '',
                $regLibelle,
                $m->unite_texte ?? '',
                $m->nature_pathologie ?? '',
                $m->observations ?? '',
            ];
        })->toArray();
        $sheets[] = new SpaRegistresSheet(
            'Non Mobilisables',
            'SITUATION DES ELEMENTS NON MOBILISABLES (GRANDS MALADES ET RECLASSEMENTS SEDENTAIRES)',
            ['N° ord', 'Grade', 'Prénoms', 'Noms', 'Mle', 'Région', 'Unité', 'Nature / Pathologie', 'Ob.'],
            $malades
        );

        return $sheets;
    }
}
