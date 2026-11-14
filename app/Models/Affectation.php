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
        'region_id',
        'unite_id',
        'unite',
        'region',
        'date_mutation',
    ];

    protected $casts = [
        'date_mutation' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Affectation $aff) {
            \App\Support\CarteRattachement::synchroniser($aff);
        });
    }

    public function militaire(): BelongsTo
    {
        return $this->belongsTo(Militaire::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class, 'unite_id');
    }
}
