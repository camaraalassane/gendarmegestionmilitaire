<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FeuilleStatistiques implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private const LIGNE_ENTETES = 4; // 1 titre, 2 date, 3 vide, 4 en-têtes

    public function __construct(private array $f)
    {
    }

    public function title(): string
    {
        // Excel : 31 caractères maximum, sans [ ] : * ? / \
        return mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', '', $this->f['titre']), 0, 31);
    }

    public function array(): array
    {
        $lignes = [
            [mb_strtoupper($this->f['intitule'] ?? $this->f['titre'])],
            ['Date d\'export : ' . now()->format('d/m/Y H:i')],
            [''],
            $this->f['entetes'],
        ];

        foreach ($this->f['lignes'] as $l) {
            $lignes[] = array_map([$this, 'nettoyer'], $l);
        }

        if (!empty($this->f['total'])) {
            $lignes[] = array_map([$this, 'nettoyer'], $this->f['total']);
        }

        return $lignes;
    }

    public function styles(Worksheet $sheet): array
    {
        $nbCol = count($this->f['entetes']);
        $derniere = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nbCol);
        $nbLignes = self::LIGNE_ENTETES + count($this->f['lignes']) + (!empty($this->f['total']) ? 1 : 0);

        // En-tête non figé (défilement libre)
        // $sheet->freezePane('A' . (self::LIGNE_ENTETES + 1));

        // Ligne d'en-têtes
        $entete = "A" . self::LIGNE_ENTETES . ":{$derniere}" . self::LIGNE_ENTETES;
        $sheet->getStyle($entete)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($entete)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4F91');
        $sheet->getStyle($entete)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        // Colonnes de chiffres alignées à droite, avec séparateur de milliers
        if ($nbLignes > self::LIGNE_ENTETES) {
            $corps = "A" . (self::LIGNE_ENTETES + 1) . ":{$derniere}{$nbLignes}";
            $sheet->getStyle($corps)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('DDE3EA');
            foreach (($this->f['colonnes_nombres'] ?? []) as $col) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->getStyle("{$c}" . (self::LIGNE_ENTETES + 1) . ":{$c}{$nbLignes}")
                    ->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("{$c}" . (self::LIGNE_ENTETES + 1) . ":{$c}{$nbLignes}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // Ligne de total en gras
        if (!empty($this->f['total'])) {
            $sheet->getStyle("A{$nbLignes}:{$derniere}{$nbLignes}")->getFont()->setBold(true);
            $sheet->getStyle("A{$nbLignes}:{$derniere}{$nbLignes}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E4EBF3');
        }

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '5B6978']]],
        ];
    }

    /** Évite l'exécution de formules si une valeur texte commence par = + - @ (comme les autres exports du projet). */
    private function nettoyer($val): mixed
    {
        if (is_string($val) && $val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
            return "'" . $val;
        }

        return $val;
    }
}
