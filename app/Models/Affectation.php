<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affectation extends Model
{
    use HasFactory;

    protected $fillable = [
        'militaire_id',
        'unite',
        'region',
        'date_mutation',
    ];

    protected $casts = [
        'date_mutation' => 'date',
    ];

    public function militaire(): BelongsTo
    {
        return $this->belongsTo(Militaire::class);
    }
}
