<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AvancementsExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $items;
    protected int $annee;
    protected ?string $gradeFiltre;

    public function __construct(array $items, int $annee, ?string $gradeFiltre = null)
    {
        $this->items = $items;
        $this->annee = $annee;
        $this->gradeFiltre = $gradeFiltre;
    }

    /**
     * Neutralise les injections de formules CSV/Excel (CWE-1236)
     */
    public static function sanitizeCell($val): mixed
    {
        if (is_string($val) && $val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'" . $val;
        }
        return $val;
    }

    public function array(): array
    {
        $data = [];

        // Titre principal
        $titre = "TABLEAU D'AVANCEMENT - SESSION " . $this->annee . " (ANNÉE DE CLÔTURE)";
        if (!empty($this->gradeFiltre) && $this->gradeFiltre !== 'all') {
            $titre .= " - GRADE : " . mb_strtoupper($this->gradeFiltre);
        }
        $data[] = [$titre];
        $data[] = ["Généré le : " . date('d/m/Y à H:i') . " | Règle Punitions : -0,1 pt/jour | Règle Limite d'âge : -1 pt (2 ans restants), -2 pts (1 an restant)"];
        $data[] = ['']; // Ligne vide

        // En-têtes de colonnes (sans Observations)
        $headers = [
            'Rang',
            'Matricule',
            'Nom & Prénom',
            'Grade actuel',
            'Grade cible',
            'Fonction Actuelle (Pts)',
            'Rendement (Pts)',
            'Bonus Emploi (Pts)',
            'Diplômes (Pts)',
            'Décorations (Pts)',
            'Anc. Grade (Pts)',
            'Jours Punit.',
            'Malus Punit. (-0.1/j)',
            'Malus Limite Âge',
            'TOTAL POINTS'
        ];
        $data[] = $headers;

        // Regrouper par grade si pas de filtre unique
        $currentGrade = null;
        foreach ($this->items as $item) {
            $grade = $item['grade_actuel'] ?? 'Non défini';
            if ($this->gradeFiltre === 'all' || empty($this->gradeFiltre)) {
                if ($currentGrade !== $grade) {
                    $currentGrade = $grade;
                    $data[] = ["--- GRADE : " . mb_strtoupper($grade) . " ---"];
                }
            }

            $nomComplet = trim(($item['nom'] ?? '') . ' ' . ($item['prenom'] ?? ''));
            $malusPunit = round(($item['jours_punition'] ?? 0) * 0.1, 2);
            $malusAge = (float) ($item['malus_limite_age'] ?? 0);

            $row = [
                $item['rang'] ? '#' . $item['rang'] : '-',
                $item['matricule'] ?? '',
                $nomComplet,
                $item['grade_actuel'] ?? '',
                $item['grade_cible'] ?? '',
                number_format((float)($item['points_fonction_actuelle'] ?? 0), 2, '.', ''),
                number_format((float)($item['note_rendement'] ?? 0), 2, '.', ''),
                number_format((float)($item['bonus_dernier_emploi'] ?? 0), 2, '.', ''),
                number_format((float)($item['points_diplomes'] ?? 0), 2, '.', ''),
                number_format((float)($item['points_decorations'] ?? 0), 2, '.', ''),
                number_format((float)($item['points_anciennete_grade'] ?? 0), 2, '.', ''),
                $item['jours_punition'] ?? 0,
                '-' . number_format($malusPunit, 2, '.', ''),
                $malusAge > 0 ? '-' . number_format($malusAge, 2, '.', '') : '0.00',
                number_format((float)($item['total_points'] ?? 0), 2, '.', ''),
            ];

            $data[] = array_map([self::class, 'sanitizeCell'], $row);
        }

        return $data;
    }

    public function title(): string
    {
        return 'Avancement_' . $this->annee;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // Rang
            'B' => 16, // Matricule
            'C' => 26, // Nom & Prénom
            'D' => 18, // Grade actuel
            'E' => 18, // Grade cible
            'F' => 22, // Fonction Pts
            'G' => 16, // Rendement Pts
            'H' => 18, // Bonus emploi
            'I' => 14, // Diplômes
            'J' => 14, // Décorations
            'K' => 16, // Anc. Grade
            'L' => 14, // Jours Punit.
            'M' => 18, // Malus Punit.
            'N' => 18, // Malus Limite Âge
            'O' => 18, // TOTAL POINTS
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Titre
        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '075985']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Date & Règles
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Ligne d'en-tête (Ligne 4)
        $sheet->getStyle('A4:O4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0284C7']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);

        // Bordures et alignements sur les lignes de données
        $highestRow = $sheet->getHighestRow();
        for ($r = 5; $r <= $highestRow; $r++) {
            $cellVal = $sheet->getCell("A{$r}")->getValue();
            if (str_starts_with((string)$cellVal, '--- GRADE')) {
                $sheet->mergeCells("A{$r}:O{$r}");
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '075985']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0F2FE']
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ]);
            } else {
                $sheet->getStyle("O{$r}")->getFont()->setBold(true);
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$r}:O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        return [];
    }
}
