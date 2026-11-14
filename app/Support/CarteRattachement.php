<?php

namespace App\Support;

use App\Models\Region;
use App\Models\Unite;
use Illuminate\Support\Str;

/**
 * Retrouve l'unité (unite_id) et la région (region_id) d'un militaire à partir
 * des textes libres `unite` et `region`.
 *
 * Les tables sont lues UNE seule fois (O(1) par militaire ensuite), contrairement
 * à l'ancien CarteController qui parcourait toutes les régions et unités pour chaque militaire.
 */
class CarteRattachement
{
    /** @var array<string,int> nom/code normalisé => id */
    private array $unites = [];
    private array $regions = [];
    /** @var array<int,int> unite_id => region_id */
    private array $regionDeUnite = [];
    /** @var array<string,string> nom normalisé d'unité => nom pour recherche approximative */
    private array $listeUnites = [];

    public function __construct()
    {
        foreach (Region::all(['id', 'code', 'nom']) as $r) {
            $this->regions[self::norm($r->code)] = $r->id;
            $this->regions[self::norm($r->nom)] = $r->id;
            // « Région de Kayes » => « kayes » ; « District de Bamako » => « bamako »
            $court = preg_replace('/^(region|district)\s+(de\s+|du\s+|d\')?/', '', self::norm($r->nom));
            $this->regions[$court] = $r->id;
            if ($r->code === 'R1') {
                foreach (['bko', 'bamako', 'region n°1', 'region n1'] as $alias) {
                    $this->regions[$alias] = $r->id;
                }
            }
        }

        foreach (Unite::all(['id', 'region_id', 'nom', 'code']) as $u) {
            $n = self::norm($u->nom);
            $this->unites[$n] = $u->id;
            if ($u->code) {
                $this->unites[self::norm($u->code)] = $u->id;
            }
            $this->regionDeUnite[$u->id] = $u->region_id;
            $this->listeUnites[$n] = $u->id;
        }
    }

    /** @return array{unite_id:?int, region_id:?int} */
    public function resoudre(?string $unite, ?string $region): array
    {
        $uniteId = null;
        $u = self::norm($unite);
        if ($u !== '') {
            $uniteId = $this->unites[$u] ?? null;

            // Repli approximatif (longueur mini 4 pour éviter les faux positifs)
            if ($uniteId === null && mb_strlen($u) >= 4) {
                foreach ($this->listeUnites as $nom => $id) {
                    if (str_contains($nom, $u) || str_contains($u, $nom)) {
                        $uniteId = $id;
                        break;
                    }
                }
            }
        }

        // La région de l'unité fait foi, sinon on lit le champ région
        $regionId = $uniteId ? ($this->regionDeUnite[$uniteId] ?? null) : null;
        if ($regionId === null) {
            $r = self::norm($region);
            $regionId = $r !== '' ? ($this->regions[$r] ?? null) : null;
        }

        return ['unite_id' => $uniteId, 'region_id' => $regionId];
    }

    private static function norm(?string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii((string) $v))));
    }
}
