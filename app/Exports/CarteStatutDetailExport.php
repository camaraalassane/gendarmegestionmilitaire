<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CarteStatutDetailExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;
    protected string $titre;
    protected string $perimetre;
    protected array $retraitesMap;
    protected int $rowNumber = 0;

    public function __construct($query, string $titre, string $perimetre, array $retraitesMap = [])
    {
        $this->query = $query;
        $this->titre = $titre;
        $this->perimetre = $perimetre;
        $this->retraitesMap = $retraitesMap;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            ['DIRECTION GÉNÉRALE DE LA GENDARMERIE NATIONALE - ÉTAT-MAJOR'],
            ['SITUATION DÉTAILLÉE DU PERSONNEL : ' . mb_strtoupper($this->titre)],
            ['PÉRIMÈTRE GÉOGRAPHIQUE : ' . mb_strtoupper($this->perimetre) . '  |  DATE D\'EXPORT : ' . date('d/m/Y H:i')],
            [''], // Ligne vide
            [
                'N°',
                'Matricule FAMA',
                'MLE GRM',
                'Nom & Prénom',
                'Grade',
                'Région',
                'Unité d\'affectation',
                'Statut',
                'Position actuelle',
                'Date de naissance',
                'Date départ retraite',
            ],
        ];
    }

    public function map($m): array
    {
        $this->rowNumber++;

        $dateRetraite = $this->retraitesMap[$m->id]['date_retraite_formatted']
            ?? ($m->calculerDateRetraite()?->format('d/m/Y') ?? '-');

        $row = [
            $this->rowNumber,
            $m->matricule ?? '-',
            $m->mle_grm ?? '-',
            trim(($m->nom ?? '') . ' ' . ($m->prenom ?? '')),
            $m->grade?->nom_grade ?? $m->grade_actuel ?? '-',
            $m->region?->nom ?? 'Non rattaché',
            $m->unite?->nom ?? $m->unite ?? 'Sans unité',
            $m->statut ?? '-',
            $m->position_actuelle ?? '-',
            $m->date_naissance ? $m->date_naissance->format('d/m/Y') : '-',
            $dateRetraite,
        ];

        return array_map([self::class, 'sanitizeCell'], $row);
    }

    public function styles(Worksheet $sheet)
    {
        // 1. En-tête institutionnel
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'], // Slate 900
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // 2. Titre du rapport
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2:K2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0284C7'], // Sky 600
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(28);

        // 3. Périmètre & Date
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A3:K3')->applyFromArray([
            'font' => ['italic' => true, 'bold' => true, 'size' => 10, 'color' => ['rgb' => '334155']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'], // Slate 100
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // 4. En-têtes du tableau (Ligne 5)
        $sheet->getStyle('A5:K5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(25);

        // 5. Bordures et alignements sur les lignes de données
        $highestRow = $sheet->getHighestRow();
        if ($highestRow >= 6) {
            $sheet->getStyle("A5:K{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);

            for ($r = 6; $r <= $highestRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:K{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],
                        ],
                    ]);
                }
            }

            $sheet->getStyle("A6:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B6:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E6:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("H6:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J6:K{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Total final en bas
            $totalRow = $highestRow + 2;
            $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
            $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '0F172A']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->setCellValue("A{$totalRow}", 'TOTAL : ' . ($highestRow - 5) . ' militaire(s)');
        }

        return [];
    }

    public static function sanitizeCell($val): mixed
    {
        if (!is_string($val) || $val === '' || $val === '-') {
            return $val;
        }

        $first = $val[0];
        if (in_array($first, ['=', '+', '@', "\t", "\r"])) {
            return "'" . $val;
        }

        // Pour '-', ne protéger que s'il est suivi d'une lettre ou symbole formule
        // mais laisser intact '-' seul ou un nombre négatif pur (ex: -10)
        if ($first === '-' && strlen($val) > 1 && !is_numeric($val)) {
            return "'" . $val;
        }

        return $val;
    }
}
