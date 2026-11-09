<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AvancementsExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $items;
    protected int $annee;
    protected ?string $gradeFiltre;
    protected array $gradeBannerRows = [];

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

        // 1. Titre officiel
        $titre = "TABLEAU D'AVANCEMENT - SESSION " . $this->annee . " (ANNÉE DE CLÔTURE)";
        if (!empty($this->gradeFiltre) && $this->gradeFiltre !== 'all') {
            $titre .= " - GRADE : " . mb_strtoupper($this->gradeFiltre);
        }
        $data[] = [$titre];

        // 2. Sous-titre & Instructions claires pour la commission
        $data[] = ["Généré le : " . date('d/m/Y à H:i') . " | Remplissez les colonnes F à L (Points & Punitions), puis réimportez ce fichier dans l'application."];
        $data[] = ["Règles : Punition = -0,1 pt/jour | Limite d'âge : -1 pt (2 ans restants avant retraite), -2 pts (1 an restant)"];

        // 4. Ligne d'en-tête (Ligne 4)
        $headers = [
            'Rang',
            'Matricule',
            'Nom & Prénom',
            'Grade actuel',
            'Grade cible',
            'Fonction (Pts)',
            'Rendement (Pts)',
            'Bonus Emploi (Pts)',
            'Diplômes (Pts)',
            'Décorations (Pts)',
            'Anc. Grade (Pts)',
            'Jours Punit.',
            'Malus Punit. (-0.1/j)',
            'Malus Âge',
            'TOTAL POINTS'
        ];
        $data[] = $headers;

        // Regrouper par grade
        $currentGrade = null;
        $currentRowIndex = 4; // Headers are on row 4

        // Calculer les effectifs par grade
        $gradeCounts = [];
        foreach ($this->items as $it) {
            $g = $it['grade_actuel'] ?? 'Non défini';
            $gradeCounts[$g] = ($gradeCounts[$g] ?? 0) + 1;
        }

        foreach ($this->items as $item) {
            $grade = $item['grade_actuel'] ?? 'Non défini';
            if ($this->gradeFiltre === 'all' || empty($this->gradeFiltre)) {
                if ($currentGrade !== $grade) {
                    $currentGrade = $grade;
                    $currentRowIndex++;
                    $countForGrade = $gradeCounts[$grade] ?? 0;
                    $bannerText = "GRADE : " . mb_strtoupper($grade) . " ({$countForGrade} Candidat" . ($countForGrade > 1 ? 's' : '') . ")";
                    $data[] = [$bannerText];
                    $this->gradeBannerRows[] = $currentRowIndex;
                }
            }

            $currentRowIndex++;
            $nomComplet = trim(($item['nom'] ?? '') . ' ' . ($item['prenom'] ?? ''));
            $joursPunit = (int) ($item['jours_punition'] ?? 0);
            $malusPunit = round($joursPunit * 0.1, 2);
            $malusAge = (float) ($item['malus_limite_age'] ?? 0);

            $row = [
                $item['rang'] ? (int) $item['rang'] : '-',
                $item['matricule'] ?? '',
                $nomComplet,
                $item['grade_actuel'] ?? '',
                $item['grade_cible'] ?? '',
                (float) ($item['points_fonction_actuelle'] ?? 0),
                (float) ($item['note_rendement'] ?? 0),
                (float) ($item['bonus_dernier_emploi'] ?? 0),
                (float) ($item['points_diplomes'] ?? 0),
                (float) ($item['points_decorations'] ?? 0),
                (float) ($item['points_anciennete_grade'] ?? 0),
                $joursPunit,
                $malusPunit > 0 ? -$malusPunit : 0.0,
                $malusAge > 0 ? -$malusAge : 0.0,
                (float) ($item['total_points'] ?? 0),
            ];

            $data[] = $row;
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
            'A' => 9,   // Rang
            'B' => 15,  // Matricule
            'C' => 28,  // Nom & Prénom
            'D' => 18,  // Grade actuel
            'E' => 18,  // Grade cible
            'F' => 17,  // Fonction Pts
            'G' => 17,  // Rendement Pts
            'H' => 18,  // Bonus emploi
            'I' => 16,  // Diplômes
            'J' => 17,  // Décorations
            'K' => 17,  // Anc. Grade
            'L' => 14,  // Jours Punit.
            'M' => 20,  // Malus Punit.
            'N' => 15,  // Malus Limite Âge
            'O' => 18,  // TOTAL POINTS
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Titre principal (Ligne 1)
        $sheet->mergeCells('A1:O1');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '075985'] // Bleu Militaire Officiel
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

        // 2. Sous-titre & Instructions (Lignes 2 et 3)
        $sheet->mergeCells('A2:O2');
        $sheet->mergeCells('A3:O3');
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(18);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0C4A6E']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F9FF']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 8.5, 'color' => ['rgb' => '475569']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0F9FF']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        // 3. En-têtes de colonnes (Ligne 4)
        $sheet->getRowDimension(4)->setRowHeight(28);

        // A-E : Infos militaires (Bleu Foncé)
        $sheet->getStyle('A4:E4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '075985']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        // F-L : Saisie des Points & Punitions (Bleu Clair distinctif pour la saisie)
        $sheet->getStyle('F4:L4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0284C7']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        // M-O : Calculs & Total (Bleu Marine Foncé)
        $sheet->getStyle('M4:O4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '082F49']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);

        $highestRow = $sheet->getHighestRow();

        // Style des séparateurs de grade et des cellules de données
        for ($r = 5; $r <= $highestRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(21);

            if (in_array($r, $this->gradeBannerRows, true)) {
                $sheet->mergeCells("A{$r}:O{$r}");
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10.5, 'color' => ['rgb' => '075985']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0F2FE'] // Bleu ciel doux
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'indent' => 1
                    ],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0284C7']],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0284C7']],
                    ]
                ]);
            } else {
                // Rang & Matricule
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("D{$r}:E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Colonnes de notes F à K (format 0.00)
                $sheet->getStyle("F{$r}:K{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("F{$r}:K{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Jours Punition L (format entier centré)
                $sheet->getStyle("L{$r}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Malus M & N
                $sheet->getStyle("M{$r}:N{$r}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("M{$r}:N{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Total Points O : Mis en valeur
                $sheet->getStyle("O{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '075985']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F0F9FF']
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
                ]);
                $sheet->getStyle("O{$r}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Bordures légères
                $sheet->getStyle("A{$r}:O{$r}")->getBorders()->getAllBorders()->applyFromArray([
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0']
                ]);
            }
        }

        return [];
    }
}
