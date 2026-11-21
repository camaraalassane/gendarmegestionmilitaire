<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistreOperationnel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'registres_operationnels';

    public const TYPE_BLESSE = 1;
    public const TYPE_DISPARU = 2;
    public const TYPE_GRAND_MALADE = 3;
    public const TYPE_DECEDE = 4;
    public const TYPE_DESERTEUR = 5;

    public const TYPES_LIBELLES = [
        self::TYPE_BLESSE => 'Blessé de guerre / opération',
        self::TYPE_DISPARU => 'Porté disparu / Otage',
        self::TYPE_GRAND_MALADE => 'Non mobilisable / Grand malade',
        self::TYPE_DECEDE => 'Décédé en mission / opération',
        self::TYPE_DESERTEUR => 'Déserteur',
    ];

    protected $fillable = [
        'type_registre_code',
        'numero_ordre',
        'matricule',
        'mle_grm',
        'nom',
        'prenom',
        'grade_id',
        'grade_ordre',
        'grade_texte',
        'sexe_code',
        'region_id',
        'unite_id',
        'unite_texte',
        'date_evenement',
        'annee_evenement',
        'type_circonstance_code',
        'circonstances',
        'nature_pathologie',
        'decision_aptitude',
        'observations',
        'est_actif_fichier',
        'militaire_id',
    ];

    protected $casts = [
        'type_registre_code' => 'integer',
        'numero_ordre' => 'integer',
        'grade_ordre' => 'integer',
        'sexe_code' => 'integer',
        'date_evenement' => 'date',
        'annee_evenement' => 'integer',
        'type_circonstance_code' => 'integer',
        'est_actif_fichier' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (RegistreOperationnel $item) {
            if ($item->date_evenement && empty($item->annee_evenement)) {
                $item->annee_evenement = (int) $item->date_evenement->format('Y');
            }

            // Synchroniser le grade si l'ID est fourni
            if ($item->grade_id && (!$item->grade_texte || $item->isDirty('grade_id'))) {
                $grade = Grade::find($item->grade_id);
                if ($grade) {
                    $item->grade_texte = $grade->nom_grade;
                    $item->grade_ordre = $grade->ordre ?? 0;
                }
            }

            // Synchroniser l'unité si l'ID est fourni
            if ($item->unite_id && (!$item->unite_texte || $item->isDirty('unite_id'))) {
                $unite = Unite::find($item->unite_id);
                if ($unite) {
                    $item->unite_texte = $unite->nom;
                    if (empty($item->region_id) && $unite->region_id) {
                        $item->region_id = $unite->region_id;
                    }
                }
            }

            // Tenter la liaison automatique avec un militaire par matricule
            if (empty($item->militaire_id) && !empty($item->matricule)) {
                $cleanMle = trim($item->matricule);
                $militaire = Militaire::where('matricule', $cleanMle)
                    ->orWhere('mle_grm', $cleanMle)
                    ->first();
                if ($militaire) {
                    $item->militaire_id = $militaire->id;
                    if (empty($item->region_id) && $militaire->region_id) {
                        $item->region_id = $militaire->region_id;
                    }
                    if (empty($item->unite_id) && $militaire->unite_id) {
                        $item->unite_id = $militaire->unite_id;
                    }
                }
            }
        });
    }

    public function militaire(): BelongsTo
    {
        return $this->belongsTo(Militaire::class, 'militaire_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    // Scopes numériques ultra-rapides
    public function scopeBlesses($query)
    {
        return $query->where('type_registre_code', self::TYPE_BLESSE);
    }

    public function scopeDisparus($query)
    {
        return $query->where('type_registre_code', self::TYPE_DISPARU);
    }

    public function scopeGrandsMalades($query)
    {
        return $query->where('type_registre_code', self::TYPE_GRAND_MALADE);
    }

    public function scopeDecedes($query)
    {
        return $query->where('type_registre_code', self::TYPE_DECEDE);
    }

    public function scopeDeserteurs($query)
    {
        return $query->where('type_registre_code', self::TYPE_DESERTEUR);
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::TYPES_LIBELLES[$this->type_registre_code] ?? 'Autre';
    }
}
