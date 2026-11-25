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
    public const CODE_DESERTION = 'DESERTION';
    public const CODE_PROCEDURE = 'PROCEDURE';
    public const CODE_RETRAIT_EMPLOI = 'RETRAIT_EMPLOI'; // Pour compatibilité historique

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

    private static function likeOp(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Résout dynamiquement l'ID de détachement en base (par code ou libellé), avec fallback sur la constante.
     */
    public static function idDetachement(): int
    {
        return Cache::rememberForever('pos_id_detachement', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_DETACHEMENT)
                ->orWhere('nom', $like, '%détachement%')
                ->value('id') ?? self::ID_DETACHEMENT;
        });
    }

    /**
     * Résout dynamiquement l'ID de stage / formation en base.
     */
    public static function idStage(): int
    {
        return Cache::rememberForever('pos_id_stage', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_STAGE)
                ->orWhere('nom', $like, '%stage%')
                ->value('id') ?? self::ID_STAGE;
        });
    }

    /**
     * Résout dynamiquement l'ID de mission en base.
     */
    public static function idMission(): int
    {
        return Cache::rememberForever('pos_id_mission', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_MISSION)
                ->orWhere('nom', $like, '%mission%')
                ->value('id') ?? self::ID_MISSION;
        });
    }

    /**
     * Résout dynamiquement l'ID de cas d'otage en base.
     */
    public static function idOtage(): int
    {
        return Cache::rememberForever('pos_id_otage', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_OTAGE)
                ->orWhere('nom', $like, '%otage%')
                ->value('id') ?? self::ID_OTAGE;
        });
    }

    /**
     * Résout dynamiquement l'ID "À l'Unité / Au Poste" en base.
     */
    public static function idUnite(): int
    {
        return Cache::rememberForever('pos_id_unite', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_UNITE)
                ->orWhere('nom', $like, "%Unité%")
                ->value('id') ?? self::ID_UNITE;
        });
    }

    /**
     * Résout dynamiquement l'ID "En désertion" en base.
     */
    public static function idDesertion(): ?int
    {
        return Cache::rememberForever('pos_id_desertion', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_DESERTION)
                ->orWhere('nom', $like, '%désertion%')
                ->value('id');
        });
    }

    /**
     * Résout dynamiquement l'ID "Procédure en cours" en base.
     */
    public static function idProcedure(): ?int
    {
        return Cache::rememberForever('pos_id_procedure', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_PROCEDURE)
                ->orWhere('code', self::CODE_RETRAIT_EMPLOI)
                ->orWhere('nom', $like, '%procédure%')
                ->orWhere('nom', $like, '%retrait%emploi%')
                ->value('id');
        });
    }

    /**
     * Résout dynamiquement l'ID "Disponibilite" en base.
     */
    public static function idDisponibilite(): ?int
    {
        return Cache::rememberForever('pos_id_disponibilite', function () {
            $like = static::likeOp();
            return static::where('code', self::CODE_DISPONIBILITE)
                ->orWhere('nom', $like, '%disponib%')
                ->value('id') ?? self::ID_DISPONIBILITE;
        });
    }

    /**
     * Invalide les caches d'IDs de position lors de modifications.
     */
    public static function clearPositionIdCache(): void
    {
        Cache::forget('pos_id_detachement');
        Cache::forget('pos_id_stage');
        Cache::forget('pos_id_mission');
        Cache::forget('pos_id_otage');
        Cache::forget('pos_id_unite');
        Cache::forget('pos_id_desertion');
        Cache::forget('pos_id_procedure');
        Cache::forget('pos_id_disponibilite');
    }

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearPositionIdCache();
        });
        static::deleted(function () {
            static::clearPositionIdCache();
        });
    }
}
