<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Classeur Excel de statistiques (carte ou dashboard) : une feuille par tableau.
 * Chaque feuille est décrite par un tableau :
 *   ['titre' => 'Régions', 'intitule' => 'Titre affiché en haut', 'entetes' => [...], 'lignes' => [[...]], 'total' => [...]|null]
 */
class StatistiquesExport implements WithMultipleSheets
{
    /** @param array<int,array> $feuilles */
    public function __construct(private array $feuilles)
    {
    }

    public function sheets(): array
    {
        return array_map(fn ($f) => new FeuilleStatistiques($f), $this->feuilles);
    }
}
