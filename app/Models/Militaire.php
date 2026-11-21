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
        'grade_id',
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
        'sexe_code',
        'groupe_sanguin',
        'taille',
        'status_sanitaire',
        'engagement_ops',
        'est_blesse_guerre',
        'est_non_mobilisable',
        'est_disparu',
        'code_inaptitude',
        'organisme_detachement_id',
        'theatre_id',
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
        'a_permis_conduire' => 'integer',
        'antecedent_judiciaire' => 'integer',
        'antecedent_disciplinaire' => 'integer',
        'statut_code' => 'integer',
        'grade_ordre' => 'integer',
        'sexe_code' => 'integer',
        'organisme_detachement_id' => 'integer',
        'est_blesse_guerre' => 'integer',
        'est_non_mobilisable' => 'integer',
        'est_disparu' => 'integer',
        'code_inaptitude' => 'integer',
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
                // On ne ressuscite jamais un décédé, radié ou déserteur : seulement vide ou retraité → actif
                if (empty($militaire->statut_code) || $militaire->statut_code === 7) {
                    $militaire->statut = 'actif';
                    $militaire->statut_code = 1;
                }
            } elseif ($militaire->statut === 'actif' && $militaire->estRetraiteDepassee()) {
                $militaire->statut = 'retraité';
                $militaire->statut_code = 7;
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

            // Synchronisation grade_id <-> grade_actuel <-> grade_ordre <-> catégorie
            if ($militaire->grade_id && (!$militaire->grade_actuel || $militaire->isDirty('grade_id'))) {
                $grade = Grade::find($militaire->grade_id);
                if ($grade) {
                    $militaire->grade_actuel = $grade->nom_grade;
                    $militaire->grade_ordre = $grade->ordre ?? 0;
                    if (empty($militaire->categorie)) {
                        $militaire->categorie = $grade->categorie_statutaire;
                    }
                }
            } elseif (!empty($militaire->grade_actuel)) {
                $grade = Grade::where('nom_grade', $militaire->grade_actuel)
                    ->orWhere('code_grade', $militaire->grade_actuel)
                    ->first();
                if ($grade) {
                    $militaire->grade_id = $grade->id;
                    $militaire->grade_ordre = $grade->ordre ?? 0;
                    if (empty($militaire->categorie)) {
                        $militaire->categorie = $grade->categorie_statutaire;
                    }
                } else {
                    if (empty($militaire->categorie)) {
                        $militaire->categorie = Grade::determinerCategorie($militaire->grade_actuel);
                    }
                }
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

            // Synchronisation sexe (texte) <-> sexe_code (1: Homme, 2: Dame)
            if (!empty($militaire->sexe)) {
                $sLower = mb_strtolower(trim($militaire->sexe));
                if (str_starts_with($sLower, 'f') || str_starts_with($sLower, 'd')) {
                    $militaire->sexe_code = 2; // Dame / Féminin
                } else {
                    $militaire->sexe_code = 1; // Homme / Masculin
                }
            } elseif (!empty($militaire->sexe_code)) {
                $militaire->sexe = $militaire->sexe_code === 2 ? 'Féminin' : 'Masculin';
            }

            // Synchronisation organisme_detachement_id (GRM par défaut si actif régulier non détaché)
            $isDetache = str_contains(mb_strtolower((string) $militaire->position_actuelle), 'détach')
                || str_contains(mb_strtolower((string) $militaire->position_actuelle), 'detach')
                || $militaire->statut === 'détaché'
                || $militaire->statut_code === 2;
            if (!$isDetache && empty($militaire->organisme_detachement_id)) {
                $grmId = \App\Models\OrganismeDetachement::where('code', 'GRM')->value('id') ?? 1;
                $militaire->organisme_detachement_id = $grmId;
            }

            // Synchronisation statut sanitaire -> drapeaux opérationnels (codes numériques stricts 1 / 0)
            $sanitaireLower = mb_strtolower(trim((string) $militaire->status_sanitaire));
            if (str_contains($sanitaireLower, 'bless') || str_contains($sanitaireLower, 'amput')) {
                $militaire->est_blesse_guerre = 1;
                $militaire->est_non_mobilisable = 0;
            } elseif (
                str_contains($sanitaireLower, 'inapte') ||
                str_contains($sanitaireLower, 'malad') ||
                str_contains($sanitaireLower, 'exemp') ||
                str_contains($sanitaireLower, 'convalesc') ||
                str_contains($sanitaireLower, 'réform') ||
                str_contains($sanitaireLower, 'reform') ||
                str_contains($sanitaireLower, 'patholog')
            ) {
                if ($militaire->est_blesse_guerre !== 1) {
                    $militaire->est_non_mobilisable = 1;
                }
            } elseif (str_contains($sanitaireLower, 'apte') || empty($sanitaireLower)) {
                if ($militaire->isDirty('status_sanitaire')) {
                    $militaire->est_blesse_guerre = 0;
                    $militaire->est_non_mobilisable = 0;
                }
            }

            if (!empty($militaire->is_otage) || str_contains(mb_strtolower((string) $militaire->position_actuelle), 'otage') || str_contains(mb_strtolower((string) $militaire->position_actuelle), 'disparu')) {
                $militaire->est_disparu = 1;
            }

            // Construction du texte consolidé de recherche plein texte (minuscules regroupées)
            $militaire->recherche = $militaire->construireTexteRecherche();
        });

        static::saved(function ($militaire) {
            static::incrementCacheVersion();
            \App\Http\Controllers\CarteController::flush();
            $militaire->synchroniserRegistreOperationnel();
        });

        static::deleted(function ($militaire) {
            static::incrementCacheVersion();
            \App\Http\Controllers\CarteController::flush();
            RegistreOperationnel::where('militaire_id', $militaire->id)->delete();
        });
    }

    /**
     * Synchronise ce militaire avec la table registres_operationnels
     * selon son statut sanitaire, sa position ou son statut administratif.
     */
    public function synchroniserRegistreOperationnel(): void
    {
        $sanitaireLower = mb_strtolower(trim((string) $this->status_sanitaire));
        $positionLower = mb_strtolower(trim((string) $this->position_actuelle));

        $reg = RegistreOperationnel::where('militaire_id', $this->id)
            ->orWhere(function ($q) {
                if (!empty($this->matricule)) {
                    $q->where('matricule', trim($this->matricule));
                }
            })
            ->first();

        // 1. Cas BLESSÉ DE GUERRE
        if ($this->est_blesse_guerre || str_contains($sanitaireLower, 'bless')) {
            $data = [
                'type_registre_code' => RegistreOperationnel::TYPE_BLESSE,
                'militaire_id' => $this->id,
                'matricule' => $this->matricule,
                'mle_grm' => $this->mle_grm,
                'nom' => $this->nom,
                'prenom' => $this->prenom,
                'grade_id' => $this->grade_id,
                'grade_ordre' => $this->grade_ordre,
                'grade_texte' => $this->grade_actuel,
                'sexe_code' => $this->sexe_code ?? 1,
                'region_id' => $this->region_id,
                'unite_id' => $this->unite_id,
                'unite_texte' => $this->unite,
                'circonstances' => $this->observations ?: 'Blessé de guerre en service commandé',
                'nature_pathologie' => 'Blessure de guerre / OPEX',
                'decision_aptitude' => $this->status_sanitaire ?: 'Blessé de guerre',
                'est_actif_fichier' => 1,
            ];
            if ($reg) {
                $reg->update($data);
            } else {
                RegistreOperationnel::create($data);
            }
            return;
        }

        // 2. Cas GRAND MALADE / INAPTE SÉDENTAIRE
        if ($this->est_non_mobilisable || (
            str_contains($sanitaireLower, 'inapte') ||
            str_contains($sanitaireLower, 'malad') ||
            str_contains($sanitaireLower, 'exemp') ||
            str_contains($sanitaireLower, 'convalesc') ||
            str_contains($sanitaireLower, 'réform') ||
            str_contains($sanitaireLower, 'reform') ||
            str_contains($sanitaireLower, 'patholog')
        )) {
            $data = [
                'type_registre_code' => RegistreOperationnel::TYPE_GRAND_MALADE,
                'militaire_id' => $this->id,
                'matricule' => $this->matricule,
                'mle_grm' => $this->mle_grm,
                'nom' => $this->nom,
                'prenom' => $this->prenom,
                'grade_id' => $this->grade_id,
                'grade_ordre' => $this->grade_ordre,
                'grade_texte' => $this->grade_actuel,
                'sexe_code' => $this->sexe_code ?? 1,
                'region_id' => $this->region_id,
                'unite_id' => $this->unite_id,
                'unite_texte' => $this->unite,
                'circonstances' => $this->observations ?: 'Inaptitude physique / Grand malade',
                'nature_pathologie' => $this->status_sanitaire ?: 'Inaptitude constatée médicalement',
                'decision_aptitude' => $this->status_sanitaire ?: 'Inapte temporaire',
                'est_actif_fichier' => 1,
            ];
            if ($reg) {
                $reg->update($data);
            } else {
                RegistreOperationnel::create($data);
            }
            return;
        }

        // 3. Cas DISPARU / OTAGE
        if ($this->est_disparu || !empty($this->is_otage) || str_contains($positionLower, 'otage') || str_contains($positionLower, 'disparu')) {
            $data = [
                'type_registre_code' => RegistreOperationnel::TYPE_DISPARU,
                'militaire_id' => $this->id,
                'matricule' => $this->matricule,
                'mle_grm' => $this->mle_grm,
                'nom' => $this->nom,
                'prenom' => $this->prenom,
                'grade_id' => $this->grade_id,
                'grade_ordre' => $this->grade_ordre,
                'grade_texte' => $this->grade_actuel,
                'sexe_code' => $this->sexe_code ?? 1,
                'region_id' => $this->region_id,
                'unite_id' => $this->unite_id,
                'unite_texte' => $this->unite,
                'circonstances' => $this->observations ?: 'Militaire pris en otage en zone d\'opérations',
                'decision_aptitude' => 'Maintenu au fichier actif',
                'est_actif_fichier' => 1,
            ];
            if ($reg) {
                $reg->update($data);
            } else {
                RegistreOperationnel::create($data);
            }
            return;
        }

        // 4. Si redevenu Apte ou sans situation d'exception, nettoyer tout registre sanitaire obsolète
        if ($reg && in_array($reg->type_registre_code, [RegistreOperationnel::TYPE_BLESSE, RegistreOperationnel::TYPE_GRAND_MALADE])) {
            $reg->delete();
        }
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
     * Vraie relation vers l'Organisme de détachement (si détaché).
     */
    public function organismeDetachement(): BelongsTo
    {
        return $this->belongsTo(OrganismeDetachement::class, 'organisme_detachement_id');
    }

    /**
     * Théâtre d'opérations d'engagement opérationnel (si déployé).
     */
    public function theatre(): BelongsTo
    {
        return $this->belongsTo(Theatre::class, 'theatre_id');
    }

    /**
     * Registres opérationnels (blessures, disparitions, inaptitudes, etc.).
     */
    public function registresOperationnels(): HasMany
    {
        return $this->hasMany(RegistreOperationnel::class, 'militaire_id');
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
        return $this->belongsTo(Grade::class, 'grade_id');
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
        if ($this->isOtage()) {
            return null;
        }
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
        // Seule la position administrative fait foi (une note « ancien otage libéré » ne doit pas déclencher la règle)
        return str_contains(mb_strtolower((string) $this->position_actuelle), 'otage');
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
     * Vérifie si le militaire est en activité (statut_code 1 à 4, ou statuts textuels équivalents).
     */
    public function estEnActivite(): bool
    {
        if ($this->statut_code !== null) {
            $code = (int) $this->statut_code;
            return $code >= 1 && $code <= 4;
        }
        return in_array(strtolower((string) $this->statut), ['actif', 'détaché', 'detache', 'en stage', 'en mission']);
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
