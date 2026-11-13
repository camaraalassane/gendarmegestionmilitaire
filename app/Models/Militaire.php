<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Militaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'matricule',
        'mle_grm',
        'numero_info',
        'numero_nina',
        'nom',
        'prenom',
        'photo',
        'date_naissance',
        'lieu_naissance',
        'date_entree_service',
        'grade_actuel',
        'categorie',
        'date_derniere_promotion',
        'specialite',
        'origine_formation',
        'position_actuelle',
        'unite',
        'region',
        'date_mutation',
        'fonction_passee',
        'fonction_actuelle',
        'telephone',
        'sexe',
        'groupe_sanguin',
        'taille',
        'status_sanitaire',
        'engagement_ops',
        'personne_a_contacter',
        'telephone_personne_contacter',
        'statut',
        'a_permis_conduire',
        'antecedent_judiciaire',
        'antecedent_disciplinaire',
        'decorations',
        'diplomes_academiques',
        'observations',
        'date_deces',
        'circonstances_deces',
        'motif_radiation',
        'reference_decision',
        'unite_transfert',
        'motif_transfert',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_entree_service' => 'date',
        'date_derniere_promotion' => 'date',
        'date_mutation' => 'date',
        'date_deces' => 'date',
        'a_permis_conduire' => 'boolean',
        'antecedent_judiciaire' => 'boolean',
        'antecedent_disciplinaire' => 'boolean',
    ];

    /**
     * Actions automatiques du modèle.
     */
    protected static function booted(): void
    {
        static::saving(function (Militaire $militaire) {
            // Si le militaire est actif mais que son âge limite de retraite est déjà dépassé,
            // on le bascule automatiquement en statut 'retraité'.
            if ($militaire->statut === 'actif' && $militaire->estRetraiteDepassee()) {
                $militaire->statut = 'retraité';
            }

            // Déduction automatique de la catégorie statutaire si le grade est connu
            if (empty($militaire->categorie) && !empty($militaire->grade_actuel)) {
                $grade = Grade::where('nom_grade', $militaire->grade_actuel)
                    ->orWhere('code_grade', $militaire->grade_actuel)
                    ->first();
                $militaire->categorie = $grade ? $grade->categorie_statutaire : Grade::determinerCategorie($militaire->grade_actuel);
            }
        });

        static::saved(function () {
            static::incrementCacheVersion();
        });

        static::deleted(function () {
            static::incrementCacheVersion();
        });
    }

    /**
     * Numéro de version global du cache pour invalidation atomique en O(1).
     */
    public static function getCacheVersion(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get('militaires:cache_v', 1);
    }

    /**
     * Incrémente la version de cache et purge les clés directes critiques.
     */
    public static function incrementCacheVersion(): void
    {
        if (!\Illuminate\Support\Facades\Cache::has('militaires:cache_v')) {
            \Illuminate\Support\Facades\Cache::forever('militaires:cache_v', 2);
        } else {
            \Illuminate\Support\Facades\Cache::increment('militaires:cache_v');
        }

        // Purge explicite des clés directes non versionnées
        \Illuminate\Support\Facades\Cache::forget('alertes:non_vues_count');
        \Illuminate\Support\Facades\Cache::forget('alertes:non_vues_count_gendarme');
        \Illuminate\Support\Facades\Cache::forget('grades_edit_cached');
        \Illuminate\Support\Facades\Cache::forget('certificats_edit_cached');
    }

    // =========================================================
    // RELATIONS
    // =========================================================

    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
    }

    /**
     * Relation Many-to-Many avec Certificat
     * Table pivot : certificat_militaire
     */
    public function certificats(): BelongsToMany
    {
        return $this->belongsToMany(Certificat::class, 'certificat_militaire')
                    ->withPivot('id', 'date_obtention')
                    ->withTimestamps();
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class)->orderBy('date_mutation', 'desc')->orderBy('id', 'desc');
    }

    public function contratActif(): HasOne
    {
        return $this->hasOne(Contrat::class)->where('statut', 'actif')->latest('date_debut');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_actuel', 'nom_grade');
    }

    public function avancements(): HasMany
    {
        return $this->hasMany(Avancement::class);
    }

    // =========================================================
    // MÉTHODES D'ANCIENNETÉ
    // =========================================================

    private static ?Carbon $cachedDateReference = null;

    private static function getDateReference(): Carbon
    {
        if (self::$cachedDateReference === null) {
            $currentYear = Carbon::now()->year;
            self::$cachedDateReference = Carbon::create($currentYear, 12, 31, 23, 59, 59);
        }
        return self::$cachedDateReference;
    }

    public function getAgeAttribute(): int
    {
        if (!$this->date_naissance) return 0;
        return $this->date_naissance instanceof Carbon 
            ? $this->date_naissance->age 
            : Carbon::parse($this->date_naissance)->age;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo) {
            if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
                return $this->photo;
            }
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    public function getAncienneteAttribute(): int
    {
        if (!$this->date_entree_service) return 0;
        $dateReference = self::getDateReference();
        $dateEntree = $this->date_entree_service instanceof Carbon 
            ? $this->date_entree_service 
            : Carbon::parse($this->date_entree_service);
        return (int) floor($dateEntree->diffInYears($dateReference));
    }

    public function getAncienneteGradeAttribute(): int
    {
        $dateReference = self::getDateReference();

        if ($this->date_derniere_promotion) {
            $datePromo = $this->date_derniere_promotion instanceof Carbon 
                ? $this->date_derniere_promotion 
                : Carbon::parse($this->date_derniere_promotion);
            return (int) floor($datePromo->diffInYears($dateReference));
        }
        return $this->getAncienneteAttribute();
    }

    public function getAncienneteDetailleeAttribute(): string
    {
        if (!$this->date_entree_service) {
            return '';
        }

        $dateReference = self::getDateReference();
        $diff = Carbon::parse($this->date_entree_service)->diff($dateReference);
        $years = $diff->y;
        $months = $diff->m;

        return $years . ' ans ' . ($months > 0 ? $months . ' mois' : '');
    }

    public function getAncienneteGradeDetailleeAttribute(): string
    {
        $dateReference = self::getDateReference();

        if ($this->date_derniere_promotion) {
            $diff = Carbon::parse($this->date_derniere_promotion)->diff($dateReference);
            $years = $diff->y;
            $months = $diff->m;
            return $years . ' ans ' . ($months > 0 ? $months . ' mois' : '');
        }

        return $this->getAncienneteDetailleeAttribute();
    }

    // =========================================================
    // MÉTHODES RETRAITE
    // =========================================================

    public function calculerDateRetraite(): ?Carbon
    {
        if (!$this->date_naissance || !$this->grade_actuel) return null;
        
        $ageRetraite = Grade::getRetraiteForGrade($this->grade_actuel);

        if ($ageRetraite) {
            return Carbon::parse($this->date_naissance)->addYears($ageRetraite);
        }

        return null;
    }

    /**
     * Vérifie si le militaire a dépassé sa limite d'âge de départ à la retraite.
     * Pour les militaires en activité, la radiation des contrôles pour limite d'âge
     * intervient officiellement au terme de l'année civile où l'âge limite est atteint (31 décembre).
     */
    public function estRetraiteDepassee(): bool
    {
        $ageRetraite = Grade::getRetraiteForGrade($this->grade_actuel);
        if (!$ageRetraite || !$this->date_naissance) {
            return false;
        }

        $anneeRetraite = Carbon::parse($this->date_naissance)->year + $ageRetraite;
        $anneeCourante = Carbon::now()->year;

        // Si l'année limite de retraite est antérieure à l'année courante, elle est dépassée
        if ($anneeRetraite < $anneeCourante) {
            return true;
        }

        // Si l'année limite est l'année courante, elle est dépassée après le 31 décembre de cette année
        if ($anneeRetraite === $anneeCourante) {
            return Carbon::now()->gt(Carbon::create($anneeCourante, 12, 31, 23, 59, 59));
        }

        return false;
    }

    /**
     * Vérifie si le militaire est éligible au départ à la retraite dans les 6 mois à venir.
     */
    public function estEligibleRetraite(): bool
    {
        $dateRetraite = $this->calculerDateRetraite();
        if ($dateRetraite) {
            $now = Carbon::now();
            return $dateRetraite->isFuture() && $now->diffInMonths($dateRetraite) <= 6;
        }
        return false;
    }

    // =========================================================
    // MÉTHODES DE VÉRIFICATION
    // =========================================================

    public function aAncienneteGradeMin(int $annees): bool
    {
        $dateReference = self::getDateReference();

        if (!$this->date_derniere_promotion) {
            return false;
        }

        $datePromotion = Carbon::parse($this->date_derniere_promotion);
        $ageEnAnnees = $datePromotion->diffInYears($dateReference);

        $moisJourPromotion = $datePromotion->format('m-d');
        $moisJourReference = $dateReference->format('m-d');

        if ($moisJourPromotion > $moisJourReference) {
            $ageEnAnnees--;
        }

        return $ageEnAnnees >= $annees;
    }

    public function aAncienneteMin(int $annees): bool
    {
        $dateReference = self::getDateReference();
        $dateEntree = Carbon::parse($this->date_entree_service);
        $ageEnAnnees = $dateEntree->diffInYears($dateReference);

        $moisJourEntree = $dateEntree->format('m-d');
        $moisJourReference = $dateReference->format('m-d');

        if ($moisJourEntree > $moisJourReference) {
            $ageEnAnnees--;
        }

        return $ageEnAnnees >= $annees;
    }

    public function aCinqAnsService(): bool
    {
        if (!$this->date_entree_service) {
            return false;
        }

        $dateEntree = Carbon::parse($this->date_entree_service);
        $serviceYears = $dateEntree->diffInYears(now());

        return $serviceYears >= 5;
    }

    public function getContratActifAttribute(): ?Contrat
    {
        return $this->contrats()->where('statut', 'actif')->latest('date_debut')->first();
    }
}
