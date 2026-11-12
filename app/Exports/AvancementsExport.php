<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AvancementsExport implements WithMultipleSheets
{
    protected array $items;
    protected int $annee;
    protected ?string $gradeFiltre;
    protected string $tab;

    public function __construct(array $items, int $annee, ?string $gradeFiltre = null, string $tab = 'all')
    {
        $this->items = $items;
        $this->annee = $annee;
        $this->gradeFiltre = $gradeFiltre;
        $this->tab = $tab;
    }

    public static function sanitizeCell($val): mixed
    {
        if (is_string($val) && $val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'" . $val;
        }
        return $val;
    }

    public function sheets(): array
    {
        $officiers = array_values(array_filter($this->items, fn($c) => !empty($c['is_officier'])));
        $sousOfficiers = array_values(array_filter($this->items, fn($c) => empty($c['is_officier'])));

        $sheets = [];

        if ($this->tab === 'officiers') {
            $sheets[] = new AvancementOfficiersSheet($officiers, $this->annee, $this->gradeFiltre);
        } elseif ($this->tab === 'sous_officiers') {
            $sheets[] = new AvancementSousOfficiersSheet($sousOfficiers, $this->annee, $this->gradeFiltre);
        } else {
            // 'all' : les deux feuilles
            if (!empty($officiers)) {
                $sheets[] = new AvancementOfficiersSheet($officiers, $this->annee, $this->gradeFiltre);
            }
            if (!empty($sousOfficiers)) {
                $sheets[] = new AvancementSousOfficiersSheet($sousOfficiers, $this->annee, $this->gradeFiltre);
            }
            if (empty($sheets)) {
                $sheets[] = new AvancementOfficiersSheet([], $this->annee, $this->gradeFiltre);
            }
        }

        return $sheets;
    }
}

/**
 * Feuille d'avancement pour les OFFICIERS (Notation par points)
 */
class AvancementOfficiersSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
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

    public function title(): string
    {
        return 'Officiers - Notation';
    }

    public function array(): array
    {
        $data = [];

        $titre = "TABLEAU D'AVANCEMENT OFFICIERS - SESSION " . $this->annee;
        if (!empty($this->gradeFiltre) && $this->gradeFiltre !== 'all') {
            $titre .= " - GRADE : " . mb_strtoupper($this->gradeFiltre);
        }
        $data[] = [$titre];
        $data[] = ["Généré le : " . date('d/m/Y à H:i') . " | Grille de notation : Total = Rendement + Fonction + Bonus + Diplômes + Décorations + Ancienneté - Punitions - Limite d'âge"];
        $data[] = ["Règles : Punition = -0,1 pt/jour | Limite d'âge : -1 pt (2 ans restants avant retraite), -2 pts (1 an restant)"];

        $headers = [
            'Rang',
            'Matricule',
            'Nom & Prénom',
            'Grade actuel',
            'Grade cible',
            'Unité',
            'Région',
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

        $currentGrade = null;
        $currentRowIndex = 4;

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
                $item['unite'] ?? '',
                $item['region'] ?? '',
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

            $data[] = array_map([AvancementsExport::class, 'sanitizeCell'], $row);
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(5, count($sheet->toArray()));

        $sheet->mergeCells('A1:Q1');
        $sheet->mergeCells('A2:Q2');
        $sheet->mergeCells('A3:Q3');

        foreach ($this->gradeBannerRows as $bRow) {
            $sheet->mergeCells("A{$bRow}:Q{$bRow}");
            $sheet->getStyle("A{$bRow}:Q{$bRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0C4A6E'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($bRow)->setRowHeight(24);
        }

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 15],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '475569'], 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '64748B'], 'size' => 8.5],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];

        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(30);

        if ($lastRow >= 5) {
            $dataRange = "A5:Q{$lastRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B5:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D5:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H5:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("Q5:Q{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0369A1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 12,
            'C' => 30,
            'D' => 20,
            'E' => 20,
            'F' => 18,
            'G' => 18,
            'H' => 14,
            'I' => 14,
            'J' => 15,
            'K' => 13,
            'L' => 14,
            'M' => 14,
            'N' => 12,
            'O' => 14,
            'P' => 12,
            'Q' => 16,
        ];
    }
}

/**
 * Feuille d'avancement pour les SOUS-OFFICIERS & RANG (Classement automatique par Ancienneté)
 */
class AvancementSousOfficiersSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
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

    public function title(): string
    {
        return 'Sous-Officiers - Ancienneté';
    }

    public function array(): array
    {
        $data = [];

        $titre = "TABLEAU D'AVANCEMENT SOUS-OFFICIERS & RANG - SESSION " . $this->annee;
        if (!empty($this->gradeFiltre) && $this->gradeFiltre !== 'all') {
            $titre .= " - GRADE : " . mb_strtoupper($this->gradeFiltre);
        }
        $data[] = [$titre];
        $data[] = ["Généré le : " . date('d/m/Y à H:i') . " | Règle : Classement automatique par ancienneté statutaire dans le grade (comme dans Éligibilités)"];
        $data[] = ["Critères de départage : 1. Date nomination grade | 2. Date entrée en service | 3. Âge (le plus âgé en premier)"];

        $headers = [
            'Rang',
            'Matricule',
            'Nom & Prénom',
            'Grade actuel',
            'Grade cible',
            'Unité',
            'Région',
            'Date Nomination Grade',
            'Ancienneté dans le Grade',
            'Date Incorporation',
            'Ancienneté de Service',
            'Diplômes / Certificats',
            'Statut Éligibilité'
        ];
        $data[] = $headers;

        $currentGrade = null;
        $currentRowIndex = 4;

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

            $certs = $item['certificats_texte'] ?: ($item['diplomes_texte'] ?: '-');

            $row = [
                $item['rang'] ? (int) $item['rang'] : '-',
                $item['matricule'] ?? '',
                $nomComplet,
                $item['grade_actuel'] ?? '',
                $item['grade_cible'] ?? '',
                $item['unite'] ?? '',
                $item['region'] ?? '',
                $item['date_derniere_promotion'] ?: '-',
                $item['anciennete_grade_texte'] ?: '-',
                $item['date_entree_service'] ?: '-',
                $item['anciennete_service_texte'] ?: '-',
                $certs,
                $item['message_eligibilite'] ?: "Proposable {$this->annee}",
            ];

            $data[] = array_map([AvancementsExport::class, 'sanitizeCell'], $row);
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(5, count($sheet->toArray()));

        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');

        foreach ($this->gradeBannerRows as $bRow) {
            $sheet->mergeCells("A{$bRow}:M{$bRow}");
            $sheet->getStyle("A{$bRow}:M{$bRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getRowDimension($bRow)->setRowHeight(24);
        }

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 15],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '475569'], 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '64748B'], 'size' => 8.5],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];

        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(30);

        if ($lastRow >= 5) {
            $dataRange = "A5:M{$lastRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A5:A{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0284C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("B5:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D5:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 13,
            'C' => 30,
            'D' => 20,
            'E' => 20,
            'F' => 18,
            'G' => 18,
            'H' => 18,
            'I' => 20,
            'J' => 18,
            'K' => 20,
            'L' => 25,
            'M' => 28,
        ];
    }
}
