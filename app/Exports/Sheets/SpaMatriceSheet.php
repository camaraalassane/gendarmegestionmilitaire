<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SpaMatriceSheet implements FromArray, WithTitle, WithStyles, WithDrawings
{
    private int $totalCols = 100;
    private int $totalRowIdx = 29;
    private array $dColLetters = [];
    private string $lastColLetter = 'CV';
    private string $totHColLetter = 'CT';
    private string $totDColLetter = 'CU';
    private string $totGenColLetter = 'CV';

    public function __construct(private array $data)
    {
    }

    public function title(): string
    {
        return "Situation Prise d'Armes";
    }

    public function drawings()
    {
        $imagePath = public_path('images/logo_grm.jpeg');
        if (!file_exists($imagePath)) {
            $imagePath = storage_path('app/excel_assets/logo_grm_0.jpeg');
        }

        if (file_exists($imagePath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo Gendarmerie');
            $drawing->setDescription('Logo DGGN');
            $drawing->setPath($imagePath);
            $drawing->setCoordinates('AF2');
            $drawing->setWidth(100);
            $drawing->setHeight(91);
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(1);

            return $drawing;
        }

        return [];
    }

    public function array(): array
    {
        $organismes = $this->data['organismes'] ?? [];
        $orgCount = count($organismes);
        $this->totalCols = 1 + ($orgCount * 2) + 3;
        $this->lastColLetter = Coordinate::stringFromColumnIndex($this->totalCols);

        $totHIdx = 1 + ($orgCount * 2) + 1;
        $totDIdx = $totHIdx + 1;
        $totGenIdx = $totDIdx + 1;

        $this->totHColLetter = Coordinate::stringFromColumnIndex($totHIdx);
        $this->totDColLetter = Coordinate::stringFromColumnIndex($totDIdx);
        $this->totGenColLetter = Coordinate::stringFromColumnIndex($totGenIdx);

        // Right side header starting col (AU is col 47, AT is col 46)
        $atColIdx = Coordinate::columnIndexFromString('AT');
        $auColIdx = Coordinate::columnIndexFromString('AU');

        $rows = [];

        // Row 1
        $r1 = array_fill(0, $this->totalCols, '');
        $r1[0] = 'MINISTERE DE LA DEFENSE ET DES ANCIENS COMBATTANTS';
        $r1[$atColIdx - 1] = 'REPUBLIQUE DU MALI';
        $rows[] = $r1;

        // Row 2
        $r2 = array_fill(0, $this->totalCols, '');
        $r2[0] = '*******';
        $r2[$auColIdx - 1] = 'Un Peuple - Un But - Une Foi';
        $rows[] = $r2;

        // Row 3
        $r3 = array_fill(0, $this->totalCols, '');
        $r3[0] = 'GENDARMERIE NATIONALE';
        $rows[] = $r3;

        // Row 4
        $r4 = array_fill(0, $this->totalCols, '');
        $r4[0] = '*******';
        $r4[$auColIdx - 1] = '*******';
        $rows[] = $r4;

        // Row 5
        $r5 = array_fill(0, $this->totalCols, '');
        $r5[0] = 'DIRECTION GENERALE';
        $rows[] = $r5;

        // Row 6
        $r6 = array_fill(0, $this->totalCols, '');
        $r6[$auColIdx - 1] = $this->data['reference_numero'] ?? 'N°______________/4 GRM-SDRH-DAGP';
        $rows[] = $r6;

        // Row 7 (Blank)
        $rows[] = array_fill(0, $this->totalCols, '');

        // Row 8 (Titre principal)
        $r8 = array_fill(0, $this->totalCols, '');
        $r8[0] = "SITUATION DE PRISE D'ARMES DE LA GENDARMERIE NATIONALE";
        $rows[] = $r8;

        // Row 9 (Période)
        $r9 = array_fill(0, $this->totalCols, '');
        $r9[0] = $this->data['titre_periode'] ?? ('Période du 01 au 31 ' . now()->locale('fr')->translatedFormat('F Y'));
        $rows[] = $r9;

        // Row 10 (Organismes codes)
        $r10 = ['GRADES'];
        foreach ($organismes as $org) {
            $r10[] = $org['code'];
            $r10[] = ''; // Fusionné avec la colonne suivante
        }
        $r10[] = 'TOTAUX';
        $r10[] = '';
        $r10[] = '';
        $rows[] = $r10;

        // Row 11 (Subheaders H / D)
        $r11 = [''];
        $this->dColLetters = [];
        $colIdx = 2;
        foreach ($organismes as $org) {
            $r11[] = 'H';
            $r11[] = 'D';
            $this->dColLetters[] = Coordinate::stringFromColumnIndex($colIdx + 1);
            $colIdx += 2;
        }
        $r11[] = 'H';
        $r11[] = 'D';
        $this->dColLetters[] = $this->totDColLetter;
        $r11[] = 'TOTAL';
        $rows[] = $r11;

        // Rows 12 to 28 (Grades)
        $matrice = $this->data['matrice'] ?? [];
        $totauxCols = array_fill(0, $this->totalCols, 0);

        foreach ($matrice as $gradeNom => $ligneData) {
            $row = [$gradeNom];
            $ligneTotalH = 0;
            $ligneTotalD = 0;
            $cIdx = 1;

            foreach ($organismes as $org) {
                $code = $org['code'];
                $valH = (int) ($ligneData[$code]['h'] ?? 0);
                $valD = (int) ($ligneData[$code]['d'] ?? 0);

                $row[] = $valH > 0 ? $valH : '';
                $row[] = $valD > 0 ? $valD : '';

                $totauxCols[$cIdx] += $valH;
                $totauxCols[$cIdx + 1] += $valD;

                $ligneTotalH += $valH;
                $ligneTotalD += $valD;
                $cIdx += 2;
            }

            // Totaux ligne
            $row[] = $ligneTotalH > 0 ? $ligneTotalH : '';
            $row[] = $ligneTotalD > 0 ? $ligneTotalD : '';
            $ligneTotGen = $ligneTotalH + $ligneTotalD;
            $row[] = $ligneTotGen > 0 ? $ligneTotGen : '';

            $totauxCols[$cIdx] += $ligneTotalH;
            $totauxCols[$cIdx + 1] += $ligneTotalD;
            $totauxCols[$cIdx + 2] += $ligneTotGen;

            $rows[] = $row;
        }

        // Row 29 (TOTAL)
        $this->totalRowIdx = count($rows) + 1;
        $rowTotal = ['TOTAL'];
        for ($c = 1; $c < $this->totalCols; $c++) {
            $val = $totauxCols[$c];
            $rowTotal[] = $val > 0 ? $val : '';
        }
        $rows[] = $rowTotal;

        // Row 30 (Légende)
        $rows[] = array_fill(0, $this->totalCols, '');
        $rLegTitle = array_fill(0, $this->totalCols, '');
        $rLegTitle[0] = 'Légende:';
        $rows[] = $rLegTitle;

        // Légendes officielles identiques au classeur
        $legendes = [
            ['_ GRM: Gendarmerie Nationale,', '_ IGAS: Inspection Généralé des Armées et Services', '_MENC: Ministère de l\'Economie Numerique et de la Communication'],
            ['_ ANSE: Agence Nationale de la Sécurité d\'Etat', '_MAP: Minitère de l\'Agriculture et de la Pêche', '_MJS: Ministère de la jeunesse et des Sports'],
            ['_BIS: Brigade spécialisée de lutte contre le terrorisme', '_ CDR: Commission Dialogues et Reconciliations', '_ MEN: Ministère de l\'Education Nationale'],
            ['_ BVG: Vérificateur Général', '_MDSSPA: Ministère du Dévéloppement Sociale', '_ MJDHG: Ministère de la Justice et des Droits de l\'Homme'],
            ['_ DCA: Direction du commissariat des Armées', '_ AN: Assemblée Nationale', '_ MRPCN: Ministère de la Réconciliation, de la Paix'],
            ['_DEM: Direction des Ecoles Militaire', '_ ML: Ministère du Logement', '_SPLPAL PC: Sécretariat Permanent Armes Légères'],
            ['_DSMil: Direction du Sport Militaire', '_ DCSSA: Direction Centrale Santé des Armées', '_ PM: Primature'],
            ['_ DSM: Direction de la Sécurité Militaire', '_MSDS: Ministère de la santé et du Dév. Social', '_ PRM: Présidence de la République'],
            ['_ DOCS: Direction Office Centrale des Stupéfiants', '_ EMGA: Etat major Général des Armées', '_ MACIHT: Ministère Artisanat, Culture, Tourisme'],
            ['_ DGPTS: Direction Police Technique et Scientifique', '_ EMPPR: Etat major Particulier Présidence', '_ Cour Constitutionelle du Mali'],
            ['_ DGPC: Direction Générale Protection Civile', '_ MM EE Ministère des Mines de l\'Energie et Eau', '_ Ecole de Guerre du Mali'],
            ['_ DGSE: Direction Générale de la Sécurité d\'Etat', '_ MEF: Ministère de l\'Economie et des Finances', '_ Ministère des Domaines de l\'Etat'],
            ['_ DJM: Direction de la Justice Militaire', '_ MAECI: Ministère Affaires Etrangères', '_MRECRI: Ministère Refondation de l\'Etat'],
            ['_ DGE: Délégation Générale des Elections', '_ MPFEF: Promotion de la Femme, Enfant, Famille', '_MMEEX: Ministère des Maliens Etablis Extérieur'],
        ];

        $pIdx = Coordinate::columnIndexFromString('P') - 1;
        $arIdx = Coordinate::columnIndexFromString('AR') - 1;

        foreach ($legendes as $leg) {
            $rLeg = array_fill(0, $this->totalCols, '');
            $rLeg[0] = $leg[0];
            $rLeg[$pIdx] = $leg[1];
            $rLeg[$arIdx] = $leg[2];
            $rows[] = $rLeg;
        }

        // Ampliations et Signatures
        $rows[] = array_fill(0, $this->totalCols, '');
        $rSig1 = array_fill(0, $this->totalCols, '');
        $rSig1[0] = 'AMPLIATIONS:';
        $rSig1[$auColIdx - 1] = $this->data['signataire_titre'] ?? 'Le Directeur Général,';
        $rows[] = $rSig1;

        $rSig2 = array_fill(0, $this->totalCols, '');
        $rSig2[0] = 'MD/MDAC…CR';
        $rows[] = $rSig2;

        $rSig3 = array_fill(0, $this->totalCols, '');
        $rSig3[0] = 'Archive & Chrono.';
        $rows[] = $rSig3;

        $rows[] = array_fill(0, $this->totalCols, '');
        $rows[] = array_fill(0, $this->totalCols, '');

        $rSig4 = array_fill(0, $this->totalCols, '');
        $rSig4[$auColIdx - 1] = $this->data['signataire_nom'] ?? 'Général de Brigade Moussa Toumani KONE';
        $rows[] = $rSig4;

        $rSig5 = array_fill(0, $this->totalCols, '');
        $rSig5[$auColIdx - 1] = $this->data['signataire_decoration'] ?? 'Chevalier de l\'Ordre National';
        $rows[] = $rSig5;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $organismes = $this->data['organismes'] ?? [];
        $orgCount = count($organismes);
        $totalCols = 1 + ($orgCount * 2) + 3;
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        $totHIdx = 1 + ($orgCount * 2) + 1;
        $totDIdx = $totHIdx + 1;
        $totGenIdx = $totDIdx + 1;

        $totHColLetter = Coordinate::stringFromColumnIndex($totHIdx);
        $totDColLetter = Coordinate::stringFromColumnIndex($totDIdx);
        $totGenColLetter = Coordinate::stringFromColumnIndex($totGenIdx);

        // 1. Police générale par défaut: Times New Roman 7pt
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:{$lastColLetter}{$highestRow}")->getFont()->setName('Times New Roman')->setSize(7);

        // 2. Fusions En-têtes institutionnels
        $auColLetter = 'AU';
        $sheet->getStyle('A1:A5')->getFont()->setName('Times New Roman')->setSize(9)->setBold(true);
        $sheet->getStyle("{$auColLetter}1:{$auColLetter}6")->getFont()->setName('Times New Roman')->setSize(9)->setBold(true);
        $sheet->getStyle("{$auColLetter}1:{$auColLetter}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("AT1")->getFont()->setName('Times New Roman')->setSize(9)->setBold(true);
        $sheet->getStyle("AT1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // 3. Titre et Période
        $sheet->mergeCells("A8:{$lastColLetter}8");
        $sheet->mergeCells("A9:{$lastColLetter}9");
        $sheet->getStyle('A8')->getFont()->setName('Times New Roman')->setSize(10)->setBold(true);
        $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9')->getFont()->setName('Times New Roman')->setSize(9)->setBold(true)->setItalic(true);
        $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 4. Fusion Row 10 & 11
        $sheet->mergeCells('A10:A11');
        $colIdx = 2;
        foreach ($organismes as $org) {
            $c1 = Coordinate::stringFromColumnIndex($colIdx);
            $c2 = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->mergeCells("{$c1}10:{$c2}10");
            $colIdx += 2;
        }
        $sheet->mergeCells("{$totHColLetter}10:{$totGenColLetter}10");

        // 5. Largeurs de colonnes compactes (Photo 2)
        $sheet->getColumnDimension('A')->setWidth(11.5);
        for ($c = 2; $c < $totHIdx; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setWidth(2.8);
        }
        $sheet->getColumnDimension($totHColLetter)->setWidth(5.2);
        $sheet->getColumnDimension($totDColLetter)->setWidth(5.2);
        $sheet->getColumnDimension($totGenColLetter)->setWidth(5.8);

        // 6. Hauteurs de lignes d'en-tête
        $sheet->getRowDimension(10)->setRowHeight(34.5);
        $sheet->getRowDimension(11)->setRowHeight(15);

        // 7. Styles de la table A10:CV29
        $tableRange = "A10:{$lastColLetter}{$this->totalRowIdx}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        $sheet->getStyle($tableRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A10:A{$this->totalRowIdx}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // En-têtes du tableau (Lignes 10 et 11) : texte gras, retour à la ligne
        $sheet->getStyle("A10:{$lastColLetter}11")->getFont()->setBold(true);
        $sheet->getStyle("A10:{$lastColLetter}10")->getAlignment()->setWrapText(true);

        // Ligne TOTAL
        $sheet->getStyle("A{$this->totalRowIdx}:{$lastColLetter}{$this->totalRowIdx}")->getFont()->setBold(true);

        // 8. Couleur ROUGE (#FF0000) pour toutes les colonnes D (Dames)
        foreach ($this->dColLetters as $dCol) {
            // Sous-en-tête ligne 11
            $sheet->getStyle("{$dCol}11")->getFont()->getColor()->setRGB('FF0000');
            // Données lignes 12 à TOTAL
            $sheet->getStyle("{$dCol}12:{$dCol}{$this->totalRowIdx}")->getFont()->getColor()->setRGB('FF0000');
        }

        // 9. Style Légende
        $sheet->getStyle("A31")->getFont()->setBold(true)->setSize(8);
        for ($r = 32; $r <= 45; $r++) {
            $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFont()->setSize(7);
        }

        // 10. Style Ampliations & Signatures
        $sigRowStart = 47;
        $sheet->getStyle("A{$sigRowStart}")->getFont()->setBold(true)->setSize(8);
        $sheet->getStyle("{$auColLetter}{$sigRowStart}")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("{$auColLetter}" . ($sigRowStart + 5))->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("{$auColLetter}" . ($sigRowStart + 6))->getFont()->setItalic(true)->setSize(8);


        return [];
    }
}
