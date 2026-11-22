<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TheatreUniteAppui extends Model
{
    use HasFactory;

    protected $table = 'theatre_unites_appui';

    protected $fillable = [
        'theatre_id',
        'code',
        'nom',
        'effectif',
        'chef_detachement',
        'couleur',
        'observations',
    ];

    protected $casts = [
        'effectif' => 'integer',
    ];

    /**
     * Théâtre d'opérations auquel cette unité d'appui est rattachée.
     */
    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class, 'theatre_id');
    }
}
