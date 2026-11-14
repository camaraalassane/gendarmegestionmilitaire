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
            // Synchronisation relation lieu -> texte
            if ($aff->unite_id && (!$aff->unite || $aff->isDirty('unite_id'))) {
                $u = Unite::find($aff->unite_id);
                if ($u) {
                    $aff->unite = $u->nom;
                    if (!$aff->region_id) {
                        $aff->region_id = $u->region_id;
                    }
                }
            }

            if ($aff->region_id && (!$aff->region || $aff->isDirty('region_id'))) {
                $r = Region::find($aff->region_id);
                if ($r) {
                    $aff->region = $r->nom;
                }
            }

            // Repli texte -> relation lieu si les IDs manquent
            if ((!$aff->unite_id || !$aff->region_id) && ($aff->unite || $aff->region)) {
                $ids = (new \App\Support\CarteRattachement())->resoudre($aff->unite, $aff->region);
                if (!$aff->unite_id && $ids['unite_id']) {
                    $aff->unite_id = $ids['unite_id'];
                }
                if (!$aff->region_id && $ids['region_id']) {
                    $aff->region_id = $ids['region_id'];
                }
            }
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
