<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Militaire;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class CartePvcApiController extends Controller
{
    protected static ?bool $hasMleGrm = null;
    protected static ?bool $hasNumeroInfo = null;

    protected function hasMleGrmColumn(): bool
    {
        return static::$hasMleGrm ??= Schema::hasColumn('militaires', 'mle_grm');
    }

    protected function hasNumeroInfoColumn(): bool
    {
        return static::$hasNumeroInfo ??= Schema::hasColumn('militaires', 'numero_info');
    }

    /**
     * Test de connectivité (Ping) pour valider la communication avec Angular.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'ok',
            'service' => 'API Cartes PVC',
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Liste paginée avec recherche pour l'interface de sélection Angular.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Militaire::query();

        // 1. Recherche globale (matricule, nom, prénom, NINA, etc.)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('matricule', 'LIKE', "%{$search}%")
                  ->orWhere('nom', 'LIKE', "%{$search}%")
                  ->orWhere('prenom', 'LIKE', "%{$search}%")
                  ->orWhere('numero_nina', 'LIKE', "%{$search}%");

                if ($this->hasMleGrmColumn()) {
                    $q->orWhere('mle_grm', 'LIKE', "%{$search}%");
                }
                if ($this->hasNumeroInfoColumn()) {
                    $q->orWhere('numero_info', 'LIKE', "%{$search}%");
                }
            });
        }

        // 2. Filtre par grade
        if ($request->filled('grade')) {
            $query->where('grade_actuel', $request->grade);
        }

        // 3. Filtre par unité / corps
        if ($request->filled('unite')) {
            $query->where('unite', 'LIKE', "%{$request->unite}%");
        }

        // 4. Filtre par région
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        // 5. Filtre par statut (défaut : 'actif', ou 'all' pour tous)
        $statut = $request->input('statut', 'actif');
        if ($statut !== 'all' && !empty($statut)) {
            $query->where('statut', 'LIKE', "{$statut}%");
        }

        // 6. Filtre uniquement militaires avec photo
        if ($request->boolean('has_photo')) {
            $query->whereNotNull('photo')->where('photo', '!=', '');
        }

        // Tri
        $query->orderBy('nom')->orderBy('prenom');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        $withBase64 = $request->boolean('with_base64', false);

        $data = $paginator->getCollection()->map(function ($m) use ($withBase64) {
            return $this->formatCarteMilitaire($m, $withBase64);
        });

        return response()->json([
            'success' => true,
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'data' => $data,
        ]);
    }

    /**
     * Récupère la fiche complète d'une carte d'identité par ID ou par Matricule.
     */
    public function show($idOrMatricule): JsonResponse
    {
        $militaire = Militaire::where('id', $idOrMatricule)
            ->orWhere('matricule', $idOrMatricule)
            ->first();

        if (!$militaire) {
            return response()->json([
                'success' => false,
                'message' => "Militaire introuvable avec l'identifiant ou matricule : {$idOrMatricule}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCarteMilitaire($militaire, true),
        ]);
    }

    /**
     * Recherche directe par matricule (utile pour scanner un code-barre).
     */
    public function showByMatricule(string $matricule): JsonResponse
    {
        $militaire = Militaire::where('matricule', trim($matricule))->first();

        if (!$militaire) {
            return response()->json([
                'success' => false,
                'message' => "Aucun militaire trouvé avec le matricule '{$matricule}'",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCarteMilitaire($militaire, true),
        ]);
    }

    /**
     * Récupération par lot pour l'impression multiple de cartes PVC.
     * Accepte un tableau d'IDs ou de Matricules.
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'nullable|array|max:200',
            'ids.*' => 'integer',
            'matricules' => 'nullable|array|max:200',
            'matricules.*' => 'string',
        ]);

        $ids = $request->input('ids', []);
        $matricules = $request->input('matricules', []);

        if (empty($ids) && empty($matricules)) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez fournir au moins un ID ou un matricule dans le tableau "ids" ou "matricules".',
            ], 422);
        }

        $query = Militaire::query();

        if (!empty($ids) && !empty($matricules)) {
            $query->where(function ($q) use ($ids, $matricules) {
                $q->whereIn('id', $ids)->orWhereIn('matricule', $matricules);
            });
        } elseif (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('matricule', $matricules);
        }

        $withBase64 = $request->boolean('with_base64', false);
        $queryLimit = $withBase64 ? 50 : 200;
        $militaires = $query->take($queryLimit)->get();

        $cartes = $militaires->map(function ($m) use ($withBase64) {
            return $this->formatCarteMilitaire($m, $withBase64);
        });

        return response()->json([
            'success' => true,
            'count' => $cartes->count(),
            'data' => $cartes,
        ]);
    }

    /**
     * Liste des grades disponibles pour les filtres Angular.
     */
    public function grades(): JsonResponse
    {
        $grades = Grade::orderBy('ordre')
            ->get(['id', 'nom_grade', 'code_grade', 'type_grade', 'ordre', 'retraite_obligatoire'])
            ->map(function ($g) {
                return [
                    'id' => $g->id,
                    'nom_grade' => $g->nom_grade,
                    'code_grade' => $g->code_grade ?? '',
                    'type_grade' => $g->type_grade ?? '',
                    'ordre' => $g->ordre,
                    'categorie' => Grade::determinerCategorie($g->nom_grade, $g->code_grade, $g->type_grade),
                    'retraite_obligatoire' => $g->retraite_obligatoire,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $grades,
        ]);
    }

    /**
     * Statistiques globales sur les cartes PVC à imprimer.
     */
    public function stats(): JsonResponse
    {
        $total = Militaire::count();
        $actifs = Militaire::where('statut', 'LIKE', 'actif%')->count();
        $retraites = Militaire::where('statut', 'LIKE', 'retrait%')->count();
        $avecPhoto = Militaire::whereNotNull('photo')->where('photo', '!=', '')->count();
        $sansPhoto = $total - $avecPhoto;

        return response()->json([
            'success' => true,
            'statistiques' => [
                'total_personnel' => $total,
                'actifs' => $actifs,
                'retraites' => $retraites,
                'avec_photo' => $avecPhoto,
                'sans_photo' => $sansPhoto,
                'taux_couverture_photos' => $total > 0 ? round(($avecPhoto / $total) * 100, 1) . '%' : '0%',
            ],
        ]);
    }

    // =========================================================================
    // MÉTHODES DE FORMATAGE CARTE D'IDENTITÉ MILITAIRE PVC
    // =========================================================================

    /**
     * Cache mémoire des grades pour éviter le problème N+1 requêtes sur les lots
     */
    private ?\Illuminate\Support\Collection $cachedGrades = null;

    private function getGradeForMilitaire(Militaire $m): ?Grade
    {
        if ($this->cachedGrades === null) {
            $this->cachedGrades = Grade::all();
        }

        return $this->cachedGrades->first(function ($g) use ($m) {
            return $g->nom_grade === $m->grade_actuel || $g->code_grade === $m->grade_actuel;
        });
    }

    /**
     * Formate un militaire en objet prêt pour l'impression de carte PVC (Recto & Verso).
     */
    private function formatCarteMilitaire(Militaire $m, bool $includeBase64 = false): array
    {
        $gradeObj = $this->getGradeForMilitaire($m);

        $codeGrade = $gradeObj?->code_grade ?? $m->grade_actuel;
        $categorie = Grade::determinerCategorie($m->grade_actuel, $gradeObj?->code_grade, $gradeObj?->type_grade);

        $now = Carbon::now();
        $dateDelivrance = $now->format('d/m/Y');
        $dateExpirationCarbon = $now->copy()->addYears(5);

        // Si date de retraite connue et inférieure à 5 ans, aligner l'expiration
        $dateRetraite = $m->calculerDateRetraite();
        if ($dateRetraite && $dateRetraite->isFuture() && $dateRetraite->lessThan($dateExpirationCarbon)) {
            $dateExpirationCarbon = $dateRetraite;
        }
        $dateExpiration = $dateExpirationCarbon->format('d/m/Y');

        // Photo (URL + Base64)
        $hasPhoto = !empty($m->photo);
        $photoUrl = $m->photo_url;
        $photoBase64 = null;

        if ($includeBase64 && $hasPhoto) {
            $photoBase64 = $this->getPhotoBase64($m->photo);
        }

        // Détection institution (FAMa vs Gendarmerie)
        $isGendarmerie = $this->hasMleGrmColumn() && !empty($m->mle_grm);

        $institution = $isGendarmerie ? [
            'republique' => 'RÉPUBLIQUE DU MALI',
            'devise' => 'Un Peuple - Un But - Une Foi',
            'ministere' => 'MINISTÈRE DE LA SÉCURITÉ ET DE LA PROTECTION CIVILE',
            'direction' => 'DIRECTION GÉNÉRALE DE LA GENDARMERIE NATIONALE',
            'corps' => 'GENDARMERIE NATIONALE',
            'type_carte' => 'CARTE D\'IDENTITÉ PROFESSIONNELLE',
        ] : [
            'republique' => 'RÉPUBLIQUE DU MALI',
            'devise' => 'Un Peuple - Un But - Une Foi',
            'ministere' => 'MINISTÈRE DE LA DÉFENSE ET DES ANCIENS COMBATTANTS',
            'etat_major' => 'ÉTAT-MAJOR GÉNÉRAL DES ARMÉES',
            'corps' => 'FORCES ARMÉES MALIENNES (FAMa)',
            'type_carte' => 'CARTE D\'IDENTITÉ MILITAIRE',
        ];

        // Format code-barre et QR-code
        $codeBarre = (string) $m->matricule;
        $qrCodeData = implode('|', [
            $isGendarmerie ? 'CIP-GRM' : 'CIM-FAMA',
            $m->matricule,
            mb_strtoupper((string) $m->nom),
            $m->prenom,
            $codeGrade,
            $m->groupe_sanguin ?? 'N/D',
            $m->date_naissance?->format('Y-m-d') ?? '',
            $dateExpirationCarbon->format('Y-m-d'),
        ]);

        return [
            // Identifiants
            'id' => $m->id,
            'matricule' => $m->matricule,
            'mle_grm' => $m->mle_grm ?? null,
            'numero_info' => $m->numero_info ?? null,
            'numero_nina' => $m->numero_nina,
            'numero_carte' => ($isGendarmerie ? 'CIP-' : 'CIM-') . str_pad($m->matricule, 6, '0', STR_PAD_LEFT) . '-' . $now->year,

            // État Civil (Recto)
            'nom' => mb_strtoupper((string) $m->nom),
            'prenom' => $m->prenom,
            'nom_complet' => mb_strtoupper((string) $m->nom) . ' ' . $m->prenom,
            'sexe' => $m->sexe,
            'sexe_court' => str_starts_with(mb_strtolower($m->sexe ?? ''), 'f') ? 'F' : 'M',
            'date_naissance' => $m->date_naissance?->format('Y-m-d'),
            'date_naissance_fr' => $m->date_naissance?->format('d/m/Y'),
            'lieu_naissance' => $m->lieu_naissance,
            'nationalite' => 'MALIENNE',
            'groupe_sanguin' => $m->groupe_sanguin ?: 'N/D',
            'taille' => $m->taille ? (int) $m->taille : null,
            'taille_formatted' => $m->taille ? ($m->taille > 100 ? number_format($m->taille / 100, 2) . ' m' : $m->taille . ' cm') : null,

            // Grade & Corps (Recto / Verso)
            'grade' => [
                'nom' => $m->grade_actuel,
                'code' => $codeGrade,
                'type' => $gradeObj?->type_grade ?? '',
                'categorie' => $categorie,
            ],

            // Carrière & Affectation (Verso)
            'carriere' => [
                'date_entree_service' => $m->date_entree_service?->format('Y-m-d'),
                'date_entree_service_fr' => $m->date_entree_service?->format('d/m/Y'),
                'date_derniere_promotion' => $m->date_derniere_promotion?->format('Y-m-d'),
                'date_derniere_promotion_fr' => $m->date_derniere_promotion?->format('d/m/Y'),
                'unite' => $m->unite ?: 'Non assigné',
                'region' => $m->region ?: 'Bamako',
                'fonction' => $m->fonction_actuelle ?: $m->position_actuelle,
                'specialite' => $m->specialite,
                'statut' => $m->statut,
            ],

            // Contacts & Urgence (Verso)
            'contact' => [
                'telephone' => $m->telephone,
                'personne_a_contacter' => $m->personne_a_contacter,
                'telephone_urgence' => $m->telephone_personne_contacter ?: $m->telephone,
            ],

            // Photo
            'photo' => [
                'has_photo' => $hasPhoto,
                'url' => $photoUrl,
                'base64' => $photoBase64,
            ],

            // Métadonnées d'impression Carte PVC (Recto / Verso)
            'carte_pvc' => [
                'institution' => $institution,
                'date_delivrance' => $now->format('Y-m-d'),
                'date_delivrance_fr' => $dateDelivrance,
                'date_expiration' => $dateExpirationCarbon->format('Y-m-d'),
                'date_expiration_fr' => $dateExpiration,
                'duree_validite_ans' => 5,
                'code_barre' => $codeBarre,
                'qr_code_data' => $qrCodeData,
                'signature_autorite' => $isGendarmerie ? 'Le Directeur Général de la Gendarmerie' : 'Le Chef d\'État-Major Général des Armées',
                'mention_legale' => $isGendarmerie
                    ? 'La présente carte d\'identité professionnelle atteste de la qualité de militaire de la Gendarmerie Nationale. En cas de perte, prière de la rapporter à la brigade ou unité de gendarmerie la plus proche.'
                    : 'La présente carte d\'identité militaire atteste de la qualité de militaire d\'active des Forces Armées Maliennes. En cas de perte, prière de la rapporter à l\'autorité militaire la plus proche.',
            ],
        ];
    }

    /**
     * Lit une photo sur le disque et la transforme en chaîne data URI Base64.
     */
    private function getPhotoBase64(?string $photoPath): ?string
    {
        if (empty($photoPath)) return null;

        // Chemins possibles
        $candidates = [
            storage_path('app/public/' . $photoPath),
            public_path('storage/' . $photoPath),
            public_path($photoPath),
        ];

        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $mime = mime_content_type($filePath) ?: 'image/jpeg';
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($content);
                }
            }
        }

        return null;
    }
}
