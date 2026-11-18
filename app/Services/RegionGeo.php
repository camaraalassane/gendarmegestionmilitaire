<?php

namespace App\Services;

use App\Models\Region;

class RegionGeo
{
    private static ?array $data = null;
    private static ?float $cosK = null;

    /**
     * Charge les polygones des 20 régions du Mali.
     */
    private static function loadData(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = resource_path('data/regions_geo.json');
        if (!file_exists($path)) {
            $path = storage_path('app/regions_geo.json');
        }

        if (file_exists($path)) {
            self::$data = json_decode(file_get_contents($path), true) ?: [];
        } else {
            self::$data = [];
        }

        self::$cosK = cos(deg2rad(17.5));

        return self::$data;
    }

    /**
     * Projette les coordonnées GPS (lat, lon) dans le repère cartographique SVG.
     */
    public static function projeter(float $lat, float $lon): array
    {
        if (self::$cosK === null) {
            self::$cosK = cos(deg2rad(17.5));
        }

        return [
            'x' => ($lon + 12.3) * self::$cosK * 40.0,
            'y' => (25.0 - $lat) * 40.0,
        ];
    }

    /**
     * Vérifie si les coordonnées GPS (lat, lon) se trouvent dans la région indiquée.
     * $tolerancePx : tolérance frontalière en pixels SVG (1 px ≈ 2.8 km sur l'échelle 40 px/degré).
     * Par défaut 1.0 px (~2.8 km) pour absorber les imprécisions des tracés administratifs simplifiés.
     */
    public static function contient(string $regionCode, float $lat, float $lon, float $tolerancePx = 1.0): bool
    {
        $geo = self::loadData();
        if (!isset($geo[$regionCode])) {
            return true; // Si région non répertoriée dans le tracé, autoriser par défaut
        }

        $regionInfo = $geo[$regionCode];
        $bb = $regionInfo['bb'] ?? null;
        $rings = $regionInfo['rings'] ?? [];

        if (empty($rings)) {
            return true;
        }

        $pt = self::projeter($lat, $lon);
        $x = $pt['x'];
        $y = $pt['y'];

        $tol = max(0.0, $tolerancePx);

        // 1. Pré-filtre rapide par boîte englobante (Bounding Box) avec marge
        if ($bb && count($bb) === 4) {
            if ($x < ($bb[0] - $tol) || $y < ($bb[1] - $tol) ||
                $x > ($bb[2] + $tol) || $y > ($bb[3] + $tol)) {
                return false;
            }
        }

        // 2. Test précis Point-dans-Polygone (Ray-Casting Even-Odd)
        if (self::pointInRings($x, $y, $rings)) {
            return true;
        }

        // 3. Marge frontalière (si tolérance demandée)
        if ($tol > 0.0) {
            return self::pointNearRings($x, $y, $rings, $tol);
        }

        return false;
    }

    /**
     * Identifie dans quelle région du Mali tombent des coordonnées GPS (lat, lon).
     * Renvoie le code de la région (ex: 'R1', 'R12') ou null si hors du territoire national.
     */
    public static function trouverRegion(float $lat, float $lon, float $tolerancePx = 1.0): ?string
    {
        $geo = self::loadData();
        $pt = self::projeter($lat, $lon);
        $x = $pt['x'];
        $y = $pt['y'];
        $tol = max(0.0, $tolerancePx);

        foreach ($geo as $code => $data) {
            $bb = $data['bb'] ?? null;
            if ($bb && count($bb) === 4) {
                if ($x < ($bb[0] - $tol) || $y < ($bb[1] - $tol) ||
                    $x > ($bb[2] + $tol) || $y > ($bb[3] + $tol)) {
                    continue;
                }
            }

            $rings = $data['rings'] ?? [];
            if (self::pointInRings($x, $y, $rings) || ($tol > 0.0 && self::pointNearRings($x, $y, $rings, $tol))) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Algorithme de Ray-Casting standard (Even-Odd rule).
     */
    private static function pointInRings(float $x, float $y, array $rings): bool
    {
        $inside = false;
        foreach ($rings as $poly) {
            $n = count($poly);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $xi = $poly[$i][0];
                $yi = $poly[$i][1];
                $xj = $poly[$j][0];
                $yj = $poly[$j][1];
                $intersect = (($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);
                if ($intersect) {
                    $inside = !$inside;
                }
            }
        }
        return $inside;
    }

    /**
     * Distance minimale point-segment pour la tolérance frontalière.
     */
    private static function pointNearRings(float $x, float $y, array $rings, float $tolerancePx): bool
    {
        $tolSq = $tolerancePx * $tolerancePx;

        foreach ($rings as $poly) {
            $n = count($poly);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $x1 = $poly[$i][0]; $y1 = $poly[$i][1];
                $x2 = $poly[$j][0]; $y2 = $poly[$j][1];
                $dx = $x2 - $x1;
                $dy = $y2 - $y1;
                $lenSq = $dx * $dx + $dy * $dy;
                if ($lenSq <= 0.00001) {
                    continue;
                }
                $t = max(0.0, min(1.0, (($x - $x1) * $dx + ($y - $y1) * $dy) / $lenSq));
                $projX = $x1 + $t * $dx;
                $projY = $y1 + $t * $dy;
                $distSq = ($x - $projX) * ($x - $projX) + ($y - $projY) * ($y - $projY);
                if ($distSq <= $tolSq) {
                    return true;
                }
            }
        }

        return false;
    }
}
