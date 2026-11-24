<?php

namespace App\Modules\SanteMilitaire\Services;

use App\Models\RegistreOperationnel;
use Illuminate\Support\Collection;

/**
 * Module Santé Militaire & Registres : Blessés de guerre, grands malades, inaptitudes et décès.
 */
class SanteMilitaireService
{
    /**
     * Effectifs des registres opérationnels actifs (blessés, malades, inaptes).
     */
    public function getEffectifsParType(): array
    {
        return [
            'blesses_guerre' => RegistreOperationnel::where('type_registre_code', 1)->count(),
            'disparus_otages' => RegistreOperationnel::where('type_registre_code', 2)->count(),
            'malades_inaptes' => RegistreOperationnel::where('type_registre_code', 3)->count(),
        ];
    }
}
