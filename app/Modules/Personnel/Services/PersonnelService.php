<?php

namespace App\Modules\Personnel\Services;

use App\Models\Militaire;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Module Personnel : Gestion administrative, effectifs et requêtes des militaires.
 */
class PersonnelService
{
    /**
     * Retourne la requête de base pour les militaires en service actif.
     */
    public function getActifsQuery(): Builder
    {
        return Militaire::query()->where(function ($q) {
            $q->where('statut_code', 1)
              ->orWhere(function ($sub) {
                  $sub->whereNull('statut_code')->whereRaw("LOWER(TRIM(statut)) = 'actif'");
              });
        })->whereNull('date_deces');
    }

    /**
     * Recherche et pagination optimisée des militaires.
     */
    public function paginateMilitaires(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Militaire::query()->with(['grade', 'unite', 'region']);

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('matricule', 'ilike', "%{$s}%")
                  ->orWhere('mle_grm', 'ilike', "%{$s}%")
                  ->orWhere('nom', 'ilike', "%{$s}%")
                  ->orWhere('prenom', 'ilike', "%{$s}%");
            });
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['position'])) {
            $query->where('position_actuelle', $filters['position']);
        }

        return $query->orderBy('nom')->paginate($perPage);
    }
}
