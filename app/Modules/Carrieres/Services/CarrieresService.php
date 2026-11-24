<?php

namespace App\Modules\Carrieres\Services;

use App\Models\Grade;
use App\Models\Avancement;
use Illuminate\Support\Collection;

/**
 * Module Carrières : Avancements de grade, éligibilités, ancienneté et décorations.
 */
class CarrieresService
{
    /**
     * Retourne la hiérarchie officielle ordonnée des grades de la Gendarmerie.
     */
    public function getGradesHierarchy(): Collection
    {
        return Grade::orderBy('ordre')->get();
    }

    /**
     * Récupère les avancements enregistrés pour une session donnée.
     */
    public function getAvancementsSession(int $annee): Collection
    {
        return Avancement::where('annee', $annee)
            ->with('militaire')
            ->orderBy('rang')
            ->get();
    }
}
