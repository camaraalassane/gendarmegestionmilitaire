<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alerte extends Model
{
    use HasFactory;

    protected $fillable = [
        'militaire_id',
        'type_alerte',
        'message',
        'date_echeance',
        'est_vue',
    ];

    protected $casts = [
        'date_echeance' => 'date',
        'est_vue' => 'integer',
    ];

    public function militaire()
    {
        return $this->belongsTo(Militaire::class);
    }

    /**
     * Invalidation automatique des compteurs d'alertes en cache lors de toute modification
     */
    protected static function booted()
    {
        $purge = function () {
            \Illuminate\Support\Facades\Cache::forget('alertes:non_vues_count');
            \Illuminate\Support\Facades\Cache::forget('alertes:non_vues_count_gendarme');
            \Illuminate\Support\Facades\Cache::forget('dashboard:statistiques_cards');
            \Illuminate\Support\Facades\Cache::forget('alertes:statistiques_globales');
        };

        static::saved($purge);
        static::deleted($purge);
    }
}