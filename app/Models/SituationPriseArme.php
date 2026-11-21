<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SituationPriseArme extends Model
{
    use HasFactory;

    protected $table = 'situations_prises_armes';

    protected $fillable = [
        'periode_mois',
        'periode_annee',
        'titre_periode',
        'reference_numero',
        'total_hommes',
        'total_dames',
        'total_general',
        'donnees_matrice',
        'donnees_regions',
        'signataire_nom',
        'signataire_titre',
        'signataire_decoration',
    ];

    protected $casts = [
        'periode_mois' => 'integer',
        'periode_annee' => 'integer',
        'total_hommes' => 'integer',
        'total_dames' => 'integer',
        'total_general' => 'integer',
        'donnees_matrice' => 'array',
        'donnees_regions' => 'array',
    ];
}
