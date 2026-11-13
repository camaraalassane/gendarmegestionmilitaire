<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

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
}
