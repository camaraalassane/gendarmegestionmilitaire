<?php

namespace App\Modules\Operations\Services;

use App\Models\Theatre;
use App\Models\Region;
use App\Models\Unite;
use Illuminate\Support\Collection;

/**
 * Module Opérations : Gestion des théâtres d'opérations, déploiements tactiques et unités d'appui.
 */
class OperationsService
{
    /**
     * Récupère la liste officielle des 10 théâtres d'opérations avec leurs effectifs.
     */
    public function getTheatresActifs(): Collection
    {
        return Theatre::where('statut', 'actif')
            ->with(['unitesAppui'])
            ->orderBy('nom')
            ->get();
    }

    /**
     * Récupère le découpage territorial et opérationnel par région.
     */
    public function getRegionsOperationnelles(): Collection
    {
        return Region::with(['unites' => fn ($q) => $q->where('est_operationnelle', true)])
            ->orderByRaw('COALESCE(ordre, 999)')
            ->get();
    }
}
