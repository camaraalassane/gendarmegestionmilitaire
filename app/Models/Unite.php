<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Http\Controllers\CarteController;

class Unite extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            CarteController::flush();
        });

        static::deleted(function () {
            CarteController::flush();
        });
    }

    protected $fillable = [
        'region_id',
        'nom',
        'code',
        'type',
        'compagnie',
        'groupement',
        'latitude',
        'longitude',
        'ville',
        'est_operationnelle',
    ];

    protected $casts = [
        'est_operationnelle' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

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
