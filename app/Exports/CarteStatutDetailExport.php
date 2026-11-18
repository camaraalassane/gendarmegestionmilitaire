<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CarteStatutDetailExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $titre;
    protected string $perimetre;
    protected array $militaires;

    public function __construct(string $titre, string $perimetre, array $militaires)
    {
        $this->titre = $titre;
        $this->perimetre = $perimetre;
        $this->militaires = $militaires;
    }

    public function array(): array
    {
        $data = [];
        $data[] = ['DIRECTION GÉNÉRALE DE LA GENDARMERIE NATIONALE - ÉTAT-MAJOR'];
        $data[] = ['SITUATION DÉTAILLÉE DU PERSONNEL : ' . mb_strtoupper($this->titre)];
        $data[] = ['PÉRIMÈTRE GÉOGRAPHIQUE : ' . mb_strtoupper($this->perimetre) . '  |  TOTAL EFFECTIF : ' . count($this->militaires) . '  |  DATE D\'EXPORT : ' . date('d/m/Y H:i')];
        $data[] = ['']; // Ligne vide

        // En-têtes de colonnes
        $data[] = [
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
        ];

        $i = 1;
        foreach ($this->militaires as $m) {
            $row = [
                $i++,
                $m['matricule'] ?? '-',
                $m['mle_grm'] ?? '-',
                trim(($m['nom'] ?? '') . ' ' . ($m['prenom'] ?? '')),
                $m['grade'] ?? '-',
                $m['region_nom'] ?? 'Non rattaché',
                $m['unite_nom'] ?? 'Sans unité',
                $m['statut'] ?? '-',
                $m['position'] ?? '-',
                $m['date_naissance'] ?? '-',
                $m['date_retraite'] ?? '-',
            ];
            $data[] = array_map([$this, 'sanitizeCell'], $row);
        }

        $data[] = [''];
        $data[] = ['TOTAL : ' . count($this->militaires) . ' militaire(s)'];

        return $data;
    }

    public function headings(): array
    {
        return [];
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

        // 5. Bordures et alignements des lignes de données
        $nbLignes = count($this->militaires);
        if ($nbLignes > 0) {
            $endRow = 5 + $nbLignes;
            
            // Bordures fines sur tout le tableau
            $sheet->getStyle("A5:K{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);

            // Alternance zébrée légère
            for ($r = 6; $r <= $endRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:K{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],
                        ],
                    ]);
                }
            }

            // Alignements spécifiques
            $sheet->getStyle("A6:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B6:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E6:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("H6:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J6:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Total final en bas
            $totalRow = $endRow + 2;
            $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
            $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '0F172A']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }

        return [];
    }

    private function sanitizeCell($val): mixed
    {
        if (is_string($val) && $val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'" . $val;
        }
        return $val;
    }
}
