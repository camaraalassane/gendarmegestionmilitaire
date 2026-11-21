<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theatre extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'nom',
        'description',
        'pc_lieu',
        'latitude',
        'longitude',
        'regions_codes',
        'couleur',
        'statut',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'regions_codes' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    /**
     * Militaires actuellement engagés ou affectés à ce théâtre d'opérations
     */
    public function militaires(): HasMany
    {
        return $this->hasMany(Militaire::class, 'theatre_id');
    }
}
