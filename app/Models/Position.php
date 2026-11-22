<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Position extends Model
{
    use HasFactory;

    // Codes stables
    public const CODE_UNITE = 'UNITE';
    public const CODE_DETACHEMENT = 'DETACHEMENT';
    public const CODE_STAGE = 'STAGE';
    public const CODE_MISSION = 'MISSION';
    public const CODE_OTAGE = 'OTAGE';
    public const CODE_CONGE = 'CONGE';
    public const CODE_ATTENTE = 'ATTENTE';
    public const CODE_DISPONIBILITE = 'DISPONIBILITE';

    // Identifiants par défaut
    public const ID_UNITE = 21;
    public const ID_DETACHEMENT = 26;
    public const ID_STAGE = 28;
    public const ID_MISSION = 29;
    public const ID_OTAGE = 31;
    public const ID_CONGE = 32;
    public const ID_ATTENTE = 33;
    public const ID_DISPONIBILITE = 34;

    protected $fillable = [
        'id',
        'nom',
        'code',
        'type',
        'description',
    ];

    /**
     * Résout dynamiquement l'ID de détachement en base (par code ou libellé), avec fallback sur la constante.
     */
    public static function idDetachement(): int
    {
        return Cache::rememberForever('pos_id_detachement', function () {
            return static::where('code', self::CODE_DETACHEMENT)
                ->orWhere('nom', 'ilike', '%détachement%')
                ->value('id') ?? self::ID_DETACHEMENT;
        });
    }

    /**
     * Résout dynamiquement l'ID de cas d'otage en base.
     */
    public static function idOtage(): int
    {
        return Cache::rememberForever('pos_id_otage', function () {
            return static::where('code', self::CODE_OTAGE)
                ->orWhere('nom', 'ilike', '%otage%')
                ->value('id') ?? self::ID_OTAGE;
        });
    }

    /**
     * Résout dynamiquement l'ID "À l'Unité / Au Poste" en base.
     */
    public static function idUnite(): int
    {
        return Cache::rememberForever('pos_id_unite', function () {
            return static::where('code', self::CODE_UNITE)
                ->orWhere('nom', 'ilike', "%Unité%")
                ->value('id') ?? self::ID_UNITE;
        });
    }
}
