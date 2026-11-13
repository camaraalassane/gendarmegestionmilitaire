<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unite extends Model
{
    use HasFactory;

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
}
