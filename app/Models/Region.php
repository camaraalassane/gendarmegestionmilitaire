<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Http\Controllers\CarteController;

class Region extends Model
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
        'code',
        'nom',
        'description',
        'latitude',
        'longitude',
        'chef_lieu',
        'ordre',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Unités rattachées à cette région.
     */
    public function unites(): HasMany
    {
        return $this->hasMany(Unite::class, 'region_id')->orderBy('nom');
    }

    /**
     * Militaires rattachés à cette région.
     */
    public function militaires(): HasMany
    {
        return $this->hasMany(Militaire::class, 'region_id');
    }
}
