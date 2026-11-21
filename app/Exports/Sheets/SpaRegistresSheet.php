<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SpaRegistresSheet implements FromArray, WithTitle, WithStyles
{
    public function __construct(
        private string $sheetTitle,
        private string $mainHeading,
        private array $headers,
        private array $rows
    ) {
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function array(): array
    {
        $out = [];
        $out[] = [mb_strtoupper($this->mainHeading)];
        $out[] = ['Date d\'export : ' . now()->format('d/m/Y H:i') . ' | Total : ' . count($this->rows) . ' éléments enregistrés'];
        $out[] = [''];
        $out[] = $this->headers;

        foreach ($this->rows as $r) {
            $out[] = $r;
        }

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {

        $nbCols = count($this->headers);
        $lastCol = Coordinate::stringFromColumnIndex($nbCols);
        $nbRows = count($this->rows) + 4;

        // Police Times New Roman 9pt
        $sheet->getStyle("A1:{$lastCol}{$nbRows}")->getFont()->setName('Times New Roman')->setSize(9);

        // Titre
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);

        // Dimensions des colonnes raisonnables et compactes
        $defaultWidths = [
            'A' => 7,
            'B' => 10,
            'C' => 18,
            'D' => 16,
            'E' => 12,
            'F' => 20,
            'G' => 35,
            'H' => 14,
            'I' => 20,
            'J' => 20,
        ];
        for ($c = 1; $c <= $nbCols; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $w = $defaultWidths[$colLetter] ?? 15;
            $sheet->getColumnDimension($colLetter)->setWidth($w);
        }

        // En-têtes (Ligne 4)
        $sheet->getRowDimension(4)->setRowHeight(22);
        $range = "A4:{$lastCol}4";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Bordures
        if ($nbRows >= 4) {
            $dataRange = "A4:{$lastCol}{$nbRows}";
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
            $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }
}
