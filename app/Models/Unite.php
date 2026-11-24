<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unite extends Model
{
    use HasFactory;

    public const CATEGORIE_GENDARMERIE = 1;
    public const CATEGORIE_DETACHEMENT = 2;

    protected $fillable = [
        'region_id',
        'nom',
        'code',
        'type',
        'type_categorie',
        'compagnie',
        'groupement',
        'latitude',
        'longitude',
        'ville',
        'est_operationnelle',
    ];

    protected $casts = [
        'type_categorie' => 'integer',
        'est_operationnelle' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function isDetachement(): bool
    {
        return (int) $this->type_categorie === self::CATEGORIE_DETACHEMENT;
    }

    public function isGendarmerie(): bool
    {
        return (int) $this->type_categorie !== self::CATEGORIE_DETACHEMENT;
    }

    /**
     * Région de rattachement.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Militaires rattachés à cette unité.
     */
    public function militaires(): HasMany
    {
        return $this->hasMany(Militaire::class, 'unite_id');
    }
}
