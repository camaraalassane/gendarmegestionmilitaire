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
use App\Enums\StatutMilitaire;

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
        'grade_ordre',
        'categorie',
        'date_derniere_promotion',
        'specialite',
        'origine_formation',
        'position_actuelle',
        'position_id',
        'unite',
        'region',
        'region_id',
        'unite_id',
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
        'statut_code',
        'a_permis_conduire',
        'antecedent_judiciaire',
        'antecedent_disciplinaire',
        'decorations',
        'diplomes_academiques',
        'observations',
        'recherche',
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
        'statut_code' => 'integer',
        'grade_ordre' => 'integer',
    ];

    /**
     * Actions automatiques du modèle.
     */
    protected static function booted(): void
    {
        static::saving(function (Militaire $militaire) {
            // Un militaire en otage reste TOUJOURS en activité / maintenu au fichier jusqu'à sa découverte.
            // Il ne doit JAMAIS être basculé automatiquement en retraité.
            if ($militaire->isOtage()) {
                if ($militaire->statut === 'retraité' || empty($militaire->statut)) {
                    $militaire->statut = 'actif';
                }
            } elseif ($militaire->statut === 'actif' && $militaire->estRetraiteDepassee()) {
                $militaire->statut = 'retraité';
            }

            // Déduction automatique de la position administrative pour les détachés si non renseignée
            if ($militaire->statut === 'détaché' && empty($militaire->position_actuelle) && empty($militaire->position_id)) {
                $militaire->position_actuelle = 'En détachement';
            } elseif (empty($militaire->position_actuelle) && empty($militaire->position_id)) {
                if (!empty($militaire->unite_id) || !empty($militaire->unite)) {
                    $militaire->position_actuelle = "À l'Unité / Au Poste";
                    $posId = Position::where('nom', "À l'Unité / Au Poste")->value('id');
                    if ($posId) {
                        $militaire->position_id = $posId;
                    }
                }
            }

            // Déduction automatique de la catégorie statutaire et de l'ordre hiérarchique du grade
            if (!empty($militaire->grade_actuel)) {
                $grade = Grade::where('nom_grade', $militaire->grade_actuel)
                    ->orWhere('code_grade', $militaire->grade_actuel)
                    ->first();
                if (empty($militaire->categorie)) {
                    $militaire->categorie = $grade ? $grade->categorie_statutaire : Grade::determinerCategorie($militaire->grade_actuel);
                }
                $militaire->grade_ordre = $grade?->ordre ?? 0;
            }

            if ($militaire->position_id && (!$militaire->position_actuelle || $militaire->isDirty('position_id'))) {
                $pos = Position::find($militaire->position_id);
                if ($pos) {
                    $militaire->position_actuelle = $pos->nom;
                }
            }

            // Synchronisation texte -> relation lieu si les IDs sont manquants
            if (!$militaire->position_id && !empty($militaire->position_actuelle)) {
                $posId = Position::where('nom', trim($militaire->position_actuelle))->value('id');
                if ($posId) {
                    $militaire->position_id = $posId;
                }
            }

            // Région, unité et textes cohérents entre eux (voir CarteRattachement::synchroniser)
            \App\Support\CarteRattachement::synchroniser($militaire);

            // Synchronisation statut (texte) <-> statut_code (numérique indexé)
            if (!empty($militaire->statut)) {
                $militaire->statut_code = StatutMilitaire::depuisTexte($militaire->statut)?->value;
            } elseif (!empty($militaire->statut_code)) {
                $militaire->statut = StatutMilitaire::tryFrom((int) $militaire->statut_code)?->label() ?? $militaire->statut;
            }

            // Construction du texte consolidé de recherche plein texte (minuscules regroupées)
            $militaire->recherche = $militaire->construireTexteRecherche();
        });

        static::saved(function () {
            static::incrementCacheVersion();
            \App\Http\Controllers\CarteController::flush();
        });

        static::deleted(function () {
            static::incrementCacheVersion();
            \App\Http\Controllers\CarteController::flush();
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
        \Illuminate\Support\Facades\Cache::forget('dashboard:statistiques');
        \Illuminate\Support\Facades\Cache::forget('dashboard:grades_distribution');
        \Illuminate\Support\Facades\Cache::forget('dashboard:statistiques_cards');
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

    /**
     * Vraie relation vers la Région de rattachement.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Vraie relation vers l'Unité d'affectation.
     */
    public function unite(): BelongsTo
    {
        return $this->belongsTo(Unite::class, 'unite_id');
    }

    /**
     * Vraie relation vers la Position géographique / administrative.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
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
     * Détermine si le militaire fait l'objet d'un cas de capture / prise d'otage.
     */
    public function isOtage(): bool
    {
        return str_contains(mb_strtolower((string) $this->position_actuelle), 'otage')
            || str_contains(mb_strtolower((string) $this->observations), 'otage');
    }

    /**
     * Vérifie si le militaire a dépassé sa limite d'âge de départ à la retraite.
     * Pour les militaires en activité, la radiation des contrôles pour limite d'âge
     * intervient officiellement au terme de l'année civile où l'âge limite est atteint (31 décembre).
     * Les militaires en otage sont expressément maintenus en activité dans le fichier.
     */
    public function estRetraiteDepassee(): bool
    {
        if ($this->isOtage()) {
            return false;
        }

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
        if ($this->isOtage()) {
            return false;
        }

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

    /**
     * Instance de l'Enum StatutMilitaire correspondant au code numérique.
     */
    public function getStatutEnumAttribute(): ?StatutMilitaire
    {
        return $this->statut_code ? StatutMilitaire::tryFrom((int) $this->statut_code) : null;
    }

    /**
     * Scope pour filtrer les militaires en activité (codes 1 à 4).
     */
    public function scopeEnActivite($query)
    {
        return $query->whereBetween('statut_code', [1, 4]);
    }

    /**
     * Scope pour filtrer par statut militaire (accepte Enum, entier ou texte).
     */
    public function scopeDuStatut($query, StatutMilitaire|int|string $statut)
    {
        if ($statut instanceof StatutMilitaire) {
            return $query->where('statut_code', $statut->value);
        }
        if (is_int($statut) || ctype_digit((string) $statut)) {
            return $query->where('statut_code', (int) $statut);
        }
        $code = StatutMilitaire::depuisTexte((string) $statut)?->value;
        return $code !== null ? $query->where('statut_code', $code) : $query->where('statut', $statut);
    }

    /**
     * Construit la chaîne de recherche consolidée en minuscules pour l'index GIN trgm.
     */
    public function construireTexteRecherche(): string
    {
        $elements = [
            $this->matricule,
            $this->mle_grm,
            $this->numero_info,
            $this->numero_nina,
            $this->nom,
            $this->prenom,
            $this->grade_actuel,
            $this->position_actuelle,
            $this->unite,
            $this->region,
            $this->specialite,
            $this->fonction_actuelle,
            $this->telephone,
        ];

        return mb_strtolower(trim(implode(' ', array_filter(array_map('trim', array_filter($elements, 'is_string'))))));
    }
}
