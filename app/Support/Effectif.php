<?php

namespace App\Support;

use App\Enums\StatutMilitaire;

/**
 * Définition UNIQUE de « l'effectif en activité », partagée par la carte opérationnelle
 * et le tableau de bord pour garantir des totaux rigoureusement identiques.
 *
 * Utilise désormais la colonne numérique indexée 'statut_code' :
 *  - 1 : Actif (en service régulier)
 *  - 2 : Détaché (mis à disposition)
 *  - 3 : En mission
 *  - 4 : En stage / formation
 *
 * Règle d'effectif : statut_code BETWEEN 1 AND 4
 *
 * Hors effectif (comptabilisés à part, JAMAIS ajoutés à l'effectif) :
 *  - 5 : Procédure en cours
 *  - 6 : Déserteurs
 *  - 7 : Retraités
 *  - 8 : Radiés (rayés des cadres)
 *  - 9 : Décédés
 */
class Effectif
{
    /** Codes numériques des statuts en activité. */
    public const CODES_ACTIVITE = [1, 2, 3, 4];

    /** Motifs SQL historiques conservés pour rétrocompatibilité. */
    public const MOTIFS = ['actif', 'actif%', 'd_tach_%', 'detach%', '%mission%', '%stage%', '%format%'];

    /**
     * Résout le nom de colonne pour toujours cibler la colonne numérique indexée statut_code.
     */
    protected static function resoudreColonne(string $colonne): string
    {
        if ($colonne === 'statut_code' || str_ends_with($colonne, '.statut_code')) {
            return $colonne;
        }

        if ($colonne === 'statut' || empty($colonne)) {
            return 'statut_code';
        }

        if (str_ends_with($colonne, '.statut')) {
            return substr($colonne, 0, -strlen('statut')) . 'statut_code';
        }

        return $colonne;
    }

    /**
     * Condition SQL ultra-rapide (utilisant l'index statut_code) pour cibler les militaires en activité.
     * Exemple : "(statut_code BETWEEN 1 AND 4)" ou "(militaires.statut_code BETWEEN 1 AND 4)"
     */
    public static function sqlActivite(string $colonne = 'statut_code'): string
    {
        $col = self::resoudreColonne($colonne);
        return "({$col} BETWEEN 1 AND 4)";
    }

    /**
     * Condition SQL d'un statut précis via son code numérique indexé.
     * Accepte un entier direct (1..9), une instance de StatutMilitaire, ou un motif texte historique ('actif', 'd_tach_%', 'radi%').
     */
    public static function sqlStatut(string|int|StatutMilitaire $motif, string $colonne = 'statut_code'): string
    {
        $col = self::resoudreColonne($colonne);

        if ($motif instanceof StatutMilitaire) {
            return "({$col} = {$motif->value})";
        }

        if (is_int($motif) || ctype_digit((string) $motif)) {
            $code = (int) $motif;
            return "({$col} = {$code})";
        }

        $codeEnum = StatutMilitaire::depuisTexte((string) $motif);
        if ($codeEnum !== null) {
            return "({$col} = {$codeEnum->value})";
        }

        // Repli texte si aucun code n'a pu être déduit
        $st = "LOWER(TRIM(COALESCE(statut,'')))";
        return (str_contains($motif, '%') || str_contains($motif, '_')) ? "{$st} LIKE '{$motif}'" : "{$st} = '{$motif}'";
    }
}
