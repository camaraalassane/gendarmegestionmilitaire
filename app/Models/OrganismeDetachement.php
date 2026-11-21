<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganismeDetachement extends Model
{
    use HasFactory;

    protected $table = 'organismes_detachement';

    protected $fillable = [
        'code',
        'nom',
        'ordre',
        'est_actif',
        'est_organique',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'est_actif' => 'integer',
        'est_organique' => 'integer',
    ];

    public function militaires(): HasMany
    {
        return $this->hasMany(Militaire::class, 'organisme_detachement_id');
    }
}
