<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SpaRegionsSheet implements FromArray, WithTitle, WithStyles
{
    public const GRADES = [
        'GAR', 'GCA', 'GDD', 'GDB',
        'CLM', 'COL', 'LCL', 'CES',
        'CNE', 'LTN', 'SLT', 'ASP',
        'EOA', 'MJR', 'ADC', 'ADJ',
        'MDC', 'MDL', 'ESOA', 'MRG'
    ];

    public function __construct(private array $regionsData)
    {
    }

    public function title(): string
    {
        return "Situation par Région";
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['SITUATION PAR REGION DE GENDARMERIE (19 RÉGIONS + 1 DISTRICT DE BAMAKO)'];
        $rows[] = ['Période du 01 au 31 ' . now()->locale('fr')->translatedFormat('F Y')];
        $rows[] = [''];

        $headers = array_merge(['UNITE / REGION'], self::GRADES, ['TOTAL']);
        $totauxGlobaux = array_fill_keys(self::GRADES, 0);
        $totalGeneral = 0;

        $rows[] = $headers;

        foreach ($this->regionsData as $code => $reg) {
            $nom = $reg['nom'] ?? $code;
            $ligne = [mb_strtoupper("{$nom} ({$code})")];
            $totalReg = 0;

            foreach (self::GRADES as $g) {
                $val = (int) ($reg[$g] ?? 0);
                $ligne[] = $val > 0 ? $val : '';
                $totauxGlobaux[$g] += $val;
                $totalReg += $val;
            }
            $ligne[] = $totalReg > 0 ? $totalReg : '';
            $totalGeneral += $totalReg;

            $rows[] = $ligne;
        }

        // Ligne TOTAL GENERAL
        $rowTot = ['TOTAL GENERAL'];
        foreach (self::GRADES as $g) {
            $rowTot[] = $totauxGlobaux[$g] > 0 ? $totauxGlobaux[$g] : '';
        }
        $rowTot[] = $totalGeneral > 0 ? $totalGeneral : '';
        $rows[] = $rowTot;

        // Ampliations et Signatures
        $rows[] = [''];
        $rows[] = ['AMPLIATIONS:', '', '', '', '', '', '', '', '', 'Le Directeur Général,'];
        $rows[] = ['MD/MDAC…CR'];
        $rows[] = ['Archive & Chrono.'];
        $rows[] = [''];
        $rows[] = ['', '', '', '', '', '', '', '', '', 'Général de Brigade Moussa Toumani KONE'];
        $rows[] = ['', '', '', '', '', '', '', '', '', 'Chevalier de l\'Ordre National'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $lastCol = Coordinate::stringFromColumnIndex(count(self::GRADES) + 2); // Col O (15 cols)

        // Police Times New Roman 8pt
        $sheet->getStyle("A1:{$lastCol}{$highestRow}")->getFont()->setName('Times New Roman')->setSize(8);

        // Titre
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

        // Dimensions des colonnes
        $sheet->getColumnDimension('A')->setWidth(32);
        for ($c = 2; $c <= count(self::GRADES) + 1; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setWidth(5.8);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(7.5);

        // Tableau (En-têtes ligne 4, données jusqu'au total)
        $regionsCount = count($this->regionsData);
        $totalRow = 4 + $regionsCount + 1;

        $sheet->getRowDimension(4)->setRowHeight(22);
        $tableRange = "A4:{$lastCol}{$totalRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        $sheet->getStyle($tableRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B4:{$lastCol}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header ligne 4
        $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true);

        // Total row
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);

        // Signatures
        $sigRow = $totalRow + 2;
        $sheet->getStyle("A{$sigRow}")->getFont()->setBold(true);
        $sheet->getStyle("J{$sigRow}")->getFont()->setBold(true);
        $sheet->getStyle("J" . ($sigRow + 4))->getFont()->setBold(true);
        $sheet->getStyle("J" . ($sigRow + 5))->getFont()->setItalic(true);

        return [];
    }
}
