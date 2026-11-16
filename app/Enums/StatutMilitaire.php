<?php

namespace App\Enums;

enum StatutMilitaire: int
{
    case Actif = 1;
    case Detache = 2;
    case Mission = 3;
    case Stage = 4;
    case Procedure = 5;
    case Deserteur = 6;
    case Retraite = 7;
    case Radie = 8;
    case Decede = 9;

    /**
     * Libellé normalisé en base (colonne texte 'statut').
     */
    public function label(): string
    {
        return match ($this) {
            self::Actif => 'actif',
            self::Detache => 'détaché',
            self::Mission => 'mission',
            self::Stage => 'stage',
            self::Procedure => 'procédure en cours',
            self::Deserteur => 'déserteur',
            self::Retraite => 'retraité',
            self::Radie => 'radié',
            self::Decede => 'décédé',
        };
    }

    /**
     * Nom affichable pour les vues et les exports.
     */
    public function nom(): string
    {
        return match ($this) {
            self::Actif => 'Actif (en service)',
            self::Detache => 'Détaché',
            self::Mission => 'En mission',
            self::Stage => 'En stage / formation',
            self::Procedure => 'Procédure en cours',
            self::Deserteur => 'Déserteur',
            self::Retraite => 'Retraité',
            self::Radie => 'Radié',
            self::Decede => 'Décédé',
        };
    }

    /**
     * Vérifie si le code fait partie de l'effectif en activité opérationnelle (codes 1 à 4).
     */
    public function estEnActivite(): bool
    {
        return $this->value >= 1 && $this->value <= 4;
    }

    /**
     * Codes numériques considérés comme « en activité ».
     *
     * @return int[]
     */
    public static function activiteCodes(): array
    {
        return [1, 2, 3, 4];
    }

    /**
     * Déduit l'énumération à partir de n'importe quel texte brut (insensible à la casse et aux accents).
     */
    public static function depuisTexte(?string $t): ?self
    {
        if ($t === null) {
            return null;
        }

        $raw = trim($t);
        if ($raw === '') {
            return null;
        }

        // Si c'est déjà un chiffre sous forme de chaîne (ex: "1")
        if (ctype_digit($raw)) {
            return self::tryFrom((int) $raw);
        }

        $norm = mb_strtolower($raw);
        $norm = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç', '-', '_'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c', ' ', ' '],
            $norm
        );

        if (str_starts_with($norm, 'actif')) {
            return self::Actif;
        }

        if (str_starts_with($norm, 'detach') || str_contains($norm, 'detach')) {
            return self::Detache;
        }

        if (str_contains($norm, 'mission')) {
            return self::Mission;
        }

        if (str_contains($norm, 'stage') || str_contains($norm, 'format')) {
            return self::Stage;
        }

        if (str_contains($norm, 'proc')) {
            return self::Procedure;
        }

        if (str_contains($norm, 'desert')) {
            return self::Deserteur;
        }

        if (str_starts_with($norm, 'retrait')) {
            return self::Retraite;
        }

        if (str_starts_with($norm, 'radi')) {
            return self::Radie;
        }

        if (str_starts_with($norm, 'deced') || str_contains($norm, 'deced') || str_contains($norm, 'mort')) {
            return self::Decede;
        }

        return null;
    }
}
