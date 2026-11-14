<?php

namespace App\Support;

/**
 * Définition UNIQUE de « l'effectif en activité », partagée par la carte opérationnelle
 * et le tableau de bord pour garantir des totaux rigoureusement identiques.
 *
 * En activité (dans l'effectif) :
 *  - Actif (en service régulier)
 *  - Détaché (mis à disposition)
 *  - En mission
 *  - En stage / formation
 *
 * Hors effectif (comptabilisés à part, JAMAIS ajoutés à l'effectif) :
 *  - Radiés (rayés des cadres)
 *  - Déserteurs
 *  - Retraites de l'année
 *  - Décédés de l'année
 */
class Effectif
{
    /** Motifs SQL des statuts considérés « en activité » dans l'effectif (les radiés sont EXCLUS et comptabilisés à part). */
    public const MOTIFS = ['actif', 'd_tach_%', 'mission%', 'stage%'];

    /** Condition SQL (PostgreSQL et SQLite) pour cibler les militaires en activité. */
    public static function sqlActivite(string $colonne = 'statut'): string
    {
        $st = "LOWER(TRIM(COALESCE({$colonne},'')))";
        $tests = array_map(
            fn ($m) => str_contains($m, '%') || str_contains($m, '_') ? "{$st} LIKE '{$m}'" : "{$st} = '{$m}'",
            self::MOTIFS
        );

        return '(' . implode(' OR ', $tests) . ')';
    }

    /** Condition SQL d'un statut précis (ex: 'actif', 'd_tach_%', 'mission%', 'stage%', 'radi%'). */
    public static function sqlStatut(string $motif, string $colonne = 'statut'): string
    {
        $st = "LOWER(TRIM(COALESCE({$colonne},'')))";

        return (str_contains($motif, '%') || str_contains($motif, '_')) ? "{$st} LIKE '{$motif}'" : "{$st} = '{$motif}'";
    }
}
