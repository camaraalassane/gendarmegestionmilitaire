<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Militaire;
use App\Models\Grade;
use App\Models\Alerte;
use App\Models\Certificat;
use App\Models\Contrat;
use App\Models\CertificatDocument;
use App\Models\ActivityLog;
use App\Models\Region;
use App\Models\Unite;
use App\Models\Position;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MilitairesExport;
use App\Imports\MilitairesImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Support\Effectif;

class MilitaireController extends Controller
{
    public function index(Request $request)
    {
        $query = Militaire::query();

        if ($request->filled('search')) {
            $search = preg_replace('/\s+/', ' ', trim($request->search));
            $searchTerms = explode(' ', $search);
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $t = mb_strtolower(trim($term));
                    if (!empty($t)) {
                        $q->where(function($subQ) use ($t) {
                            $subQ->where('militaires.recherche', 'LIKE', "%{$t}%");
                            if (mb_stripos($t, 'assign') !== false) {
                                $subQ->orWhereNull('militaires.position_actuelle')
                                     ->orWhere('militaires.position_actuelle', '');
                            }
                        });
                    }
                }
            });
        }

        if ($request->filled('grade')) {
            $query->where('militaires.grade_actuel', $request->grade);
        }

        if ($request->filled('region')) {
            $reg = $request->region;
            if (is_numeric($reg)) {
                $query->where('militaires.region_id', (int) $reg);
            } else {
                $query->where(function ($q) use ($reg) {
                    $q->where('militaires.region', $reg)
                      ->orWhere('militaires.region_id', function ($sub) use ($reg) {
                          $sub->select('id')->from('regions')->where('nom', $reg)->orWhere('code', $reg)->limit(1);
                      });
                });
            }
        }

        if ($request->filled('unite')) {
            $query->where('militaires.unite', 'LIKE', "%{$request->unite}%");
        }

        if ($request->filled('position')) {
            $pos = $request->position;
            if ($pos === 'Non assigné / En attente' || $pos === 'non_assigne') {
                $query->where(function ($q) {
                    $q->whereNull('militaires.position_actuelle')->orWhere('militaires.position_actuelle', '');
                });
            } else {
                $query->where('militaires.position_actuelle', 'LIKE', "%{$pos}%");
            }
        }

        $anneeN = Carbon::now()->year;
        $statutFilter = $request->input('statut', 'actif');

        if ($request->boolean('non_rattaches') || $request->input('non_rattaches') === '1') {
            $statutFilter = 'non_rattaches';
        }

        if ($statutFilter === 'all') {
            // Afficher tous les militaires (sans restriction de statut)
        } elseif ($statutFilter === 'non_rattaches') {
            $query->whereNull('militaires.region_id')
                  ->where(function ($q) {
                      $q->whereNull('militaires.unite_id')->orWhere('militaires.unite_id', 0);
                  });
        } elseif ($statutFilter === 'actif') {
            // Actifs, détachés, en détachement, en stage, en formation, en mission : tous comptés en activité (codes 1 à 4)
            $query->whereBetween('militaires.statut_code', [1, 4]);
        } elseif ($statutFilter === 'actif_strict') {
            $query->where('militaires.statut_code', 1);
        } elseif ($statutFilter === 'otage') {
            $query->where(function($q) {
                $q->whereRaw("LOWER(COALESCE(militaires.position_actuelle, '')) LIKE ?", ['%otage%'])
                  ->orWhereRaw("LOWER(COALESCE(militaires.observations, '')) LIKE ?", ['%otage%']);
            });
        } elseif ($statutFilter === 'retraites_n') {
            $query->whereIn('militaires.id', $this->getIdsRetraitePourAnnee($anneeN));
        } elseif ($statutFilter === 'retraites_n1') {
            $query->whereIn('militaires.id', $this->getIdsRetraitePourAnnee($anneeN + 1));
        } elseif ($statutFilter === 'decedes_annee_n') {
            $debutAnnee = Carbon::create($anneeN, 1, 1)->startOfDay();
            $finAnnee = Carbon::create($anneeN, 12, 31)->endOfDay();
            $query->where(function($q) {
                $q->where('militaires.statut_code', 9)
                  ->orWhereNotNull('militaires.date_deces');
            })->where(function($q) use ($debutAnnee, $finAnnee) {
                $q->whereBetween('militaires.date_deces', [$debutAnnee, $finAnnee])
                  ->orWhere(function($sub) use ($debutAnnee, $finAnnee) {
                      $sub->whereNull('militaires.date_deces')->whereBetween('militaires.updated_at', [$debutAnnee, $finAnnee]);
                  });
            });
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'deser')) {
            $query->where(function($q) {
                $q->where('militaires.statut_code', 6)
                  ->orWhere('militaires.motif_radiation', 'LIKE', '%desert%');
            });
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'retrait')) {
            $query->where('militaires.statut_code', 7);
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'radi')) {
            $query->where('militaires.statut_code', 8);
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'd_tach') || str_starts_with(mb_strtolower($statutFilter), 'detach') || str_starts_with(mb_strtolower($statutFilter), 'détach')) {
            $query->where('militaires.statut_code', 2);
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'miss')) {
            $query->where('militaires.statut_code', 3);
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'stag') || str_starts_with(mb_strtolower($statutFilter), 'format')) {
            $query->where('militaires.statut_code', 4);
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'dec') || str_starts_with(mb_strtolower($statutFilter), 'déc')) {
            $query->where('militaires.statut_code', 9);
        } elseif (!empty($statutFilter)) {
            $code = \App\Enums\StatutMilitaire::depuisTexte($statutFilter)?->value;
            if ($code !== null) {
                $query->where('militaires.statut_code', $code);
            } else {
                $query->where('militaires.statut', $statutFilter);
            }
        }

        $v = Militaire::getCacheVersion();
        $statistiques = Cache::remember("v{$v}:militaires:stats_summary", 600, function () use ($anneeN) {
            return [
                'total'         => Militaire::count(),
                'actifs'        => Militaire::whereRaw(Effectif::sqlActivite('statut'))->count(),
                'non_rattaches' => Militaire::whereRaw(Effectif::sqlActivite('statut'))
                    ->whereNull('region_id')
                    ->where(function ($q) {
                        $q->whereNull('unite_id')->orWhere('unite_id', 0);
                    })->count(),
                'retraites_n'   => count($this->getIdsRetraitePourAnnee($anneeN)),
                'retraites_n1'  => count($this->getIdsRetraitePourAnnee($anneeN + 1)),
            ];
        });

        $query->orderBy('militaires.grade_ordre', 'desc')
              ->orderBy('militaires.nom', 'asc')
              ->orderBy('militaires.prenom', 'asc');

        $militaires = $query->withCount(['alertes' => fn($q) => $q->where('est_vue', false)])
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($militaire) => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'mle_grm' => $militaire->mle_grm,
                'numero_info' => $militaire->numero_info,
                'photo_url' => $militaire->photo_url,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'grade_actuel' => $militaire->grade_actuel,
                'categorie' => $militaire->categorie,
                'unite' => $militaire->unite,
                'region' => $militaire->region,
                'date_entree_service' => $militaire->date_entree_service?->format('d/m/Y'),
                'date_derniere_promotion' => $militaire->date_derniere_promotion?->format('d/m/Y'),
                'specialite' => $militaire->specialite,
                'position_actuelle' => $militaire->position_actuelle,
                'fonction_passee' => $militaire->fonction_passee,
                'fonction_actuelle' => $militaire->fonction_actuelle,
                'telephone' => $militaire->telephone,
                'sexe' => $militaire->sexe,
                'groupe_sanguin' => $militaire->groupe_sanguin,
                'status_sanitaire' => $militaire->status_sanitaire,
                'engagement_ops' => $militaire->engagement_ops,
                'personne_a_contacter' => $militaire->personne_a_contacter,
                'telephone_personne_contacter' => $militaire->telephone_personne_contacter,
                'statut' => $militaire->statut,
                'observations' => $militaire->observations,
                'is_otage' => $militaire->isOtage(),
                'age' => $militaire->age,
                'anciennete' => $militaire->anciennete,
                'anciennete_grade' => $militaire->ancienneteGrade,
                'a_permis_conduire' => $militaire->a_permis_conduire,
                'alertes_count' => (int) ($militaire->alertes_count ?? 0),
                'est_eligible_retraite' => $militaire->estEligibleRetraite(),
                'date_retraite' => $militaire->calculerDateRetraite()?->format('d/m/Y'),
            ]);

        $grades = Grade::orderBy('ordre', 'desc')->get();
        $regions = Region::orderByRaw('CASE WHEN ordre IS NULL OR ordre = 0 THEN 999 ELSE ordre END ASC')
            ->orderBy('nom')
            ->pluck('nom')
            ->values();
        $positions = Position::orderBy('nom')->pluck('nom')->values();

        return Inertia::render('militaires/index', [
            'militaires'   => $militaires,
            'statistiques' => $statistiques,
            'filters'      => [
                'search'        => $request->search,
                'grade'         => $request->grade,
                'statut'        => $statutFilter,
                'region'        => $request->region,
                'unite'         => $request->unite,
                'position'      => $request->position,
                'non_rattaches' => $request->boolean('non_rattaches') || $request->input('non_rattaches') === '1' || $statutFilter === 'non_rattaches',
            ],
            'grades'       => $grades,
            'regions'      => $regions,
            'positions'    => $positions,
        ]);
    }

    public function create()
    {
        $grades = Cache::remember('grades_edit_cached', 3600, function () {
            return Grade::orderBy('ordre')->get()->map(fn ($grade) => [
                'id' => $grade->id,
                'nom_grade' => $grade->nom_grade,
                'code_grade' => $grade->code_grade,
                'ordre' => $grade->ordre,
                'type_grade' => $grade->type_grade,
                'retraite_obligatoire' => $grade->retraite_obligatoire,
                'categorie_statutaire' => $grade->categorie_statutaire,
            ]);
        });

        $certificats = Cache::remember('certificats_edit_cached', 3600, function () {
            return Certificat::orderBy('id')->get()->map(fn ($certificat) => [
                'id' => $certificat->id,
                'nom_certificat' => $certificat->nom_certificat,
                'niveau_certificat' => $certificat->niveau_certificat,
            ]);
        });

        $regions = Region::orderBy('ordre')->orderBy('nom')->get();
        $unites = Unite::with('region')->orderBy('nom')->get();
        $positions = Position::orderBy('nom')->get();

        return Inertia::render('militaires/create', [
            'grades' => $grades,
            'certificats' => $certificats,
            'regions' => $regions,
            'unites' => $unites,
            'positions' => $positions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'matricule' => 'required|string|unique:militaires',
            'mle_grm' => 'nullable|string|max:100',
            'numero_info' => 'nullable|string|max:100',
            'numero_nina' => 'nullable|string|max:100',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:255',
            'date_entree_service' => 'nullable|date|before_or_equal:today',
            'grade_actuel' => 'required|string',
            'categorie' => 'nullable|string|max:100',
            'date_derniere_promotion' => 'nullable|date|before_or_equal:today',
            'specialite' => 'nullable|string|max:200',
            'origine_formation' => 'nullable|string|max:255',
            'position_id' => 'nullable|exists:positions,id',
            'position_actuelle' => 'nullable|string|max:255',
            'unite_id' => 'nullable|exists:unites,id',
            'unite' => 'nullable|string|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'region' => 'nullable|string|max:100',
            'date_mutation' => 'nullable|date',
            'fonction_passee' => 'nullable|string|max:255',
            'fonction_actuelle' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'sexe' => 'nullable|string|max:10',
            'groupe_sanguin' => 'nullable|string|max:10',
            'taille' => 'nullable|string|max:30',
            'status_sanitaire' => 'nullable|string',
            'engagement_ops' => 'nullable|string|max:255',
            'personne_a_contacter' => 'nullable|string|max:255',
            'telephone_personne_contacter' => 'nullable|string|max:20',
            'statut' => 'required|string',
            'a_permis_conduire' => 'nullable',
            'antecedent_judiciaire' => 'nullable',
            'antecedent_disciplinaire' => 'nullable',
            'decorations' => 'nullable|string',
            'diplomes_academiques' => 'nullable|string',
            'observations' => 'nullable|string',
            'date_deces' => 'nullable|date',
            'circonstances_deces' => 'nullable|string',
            'motif_radiation' => 'nullable|string|max:255',
            'reference_decision' => 'nullable|string|max:255',
            'unite_transfert' => 'nullable|string|max:255',
            'motif_transfert' => 'nullable|string|max:255',
        ]);

        $data = $this->extractData($request);
        $militaire = DB::transaction(function () use ($data, $request) {
            $militaire = Militaire::create($data);

            if (auth()->check()) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'Création',
                    'description' => 'A ajouté le militaire : ' . $militaire->nom . ' ' . $militaire->prenom . ' (' . $militaire->matricule . ')',
                    'ip_address' => request()->ip()
                ]);
            }

            if ($request->has('certificats')) {
                $this->syncCertificatsWithDocuments($militaire, $request->certificats);
            }

            // Synchronisation des affectations multiples
            $this->syncAffectations($militaire, $request);

            return $militaire;
        });

        $militaire->load('certificats', 'affectations');

        $this->verifierAlertes($militaire);

        $message = ($militaire->statut === 'retraité' && $request->input('statut') === 'actif')
            ? 'Militaire ajouté avec le statut "Retraité" (limite d\'âge atteinte/dépassée).'
            : 'Militaire ajouté avec succès.';

        return redirect()->route('militaires.index')
            ->with('success', $message);
    }

    public function show(Militaire $militaire)
    {
        $militaire->load(['certificats', 'affectations' => function($q) {
            $q->orderBy('date_mutation', 'desc')->orderBy('id', 'desc');
        }, 'alertes' => function($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }]);

        $plusRecenteAff = $militaire->affectations->sortByDesc('date_mutation')->first();

        $contratActif = $militaire->contrats()->where('statut', 'actif')->latest('date_debut')->first();

        // Trier les affectations de la plus récente à la plus ancienne
        $affectationsList = $militaire->affectations->sortByDesc('date_mutation')->values()->map(fn ($aff) => [
            'id' => $aff->id,
            'unite' => $aff->unite,
            'region' => $aff->region,
            'date_mutation' => $aff->date_mutation?->format('d/m/Y'),
            'date_mutation_raw' => $aff->date_mutation?->format('Y-m-d'),
        ])->toArray();

        if (empty($affectationsList) && ($militaire->unite || $militaire->region || $militaire->date_mutation)) {
            $affectationsList[] = [
                'id' => null,
                'unite' => $militaire->unite,
                'region' => $militaire->region,
                'date_mutation' => $militaire->date_mutation?->format('d/m/Y'),
                'date_mutation_raw' => $militaire->date_mutation?->format('Y-m-d'),
            ];
        }

        $alertes = $militaire->alertes->map(fn ($alerte) => [
            'id' => $alerte->id,
            'type_alerte' => $alerte->type_alerte,
            'message' => $alerte->message,
            'date_echeance' => $alerte->date_echeance?->format('d/m/Y'),
            'est_vue' => $alerte->est_vue,
            'created_at' => $alerte->created_at?->format('d/m/Y H:i'),
        ]);

        // Précharger les documents de certificats pour éliminer les requêtes N+1
        $pivotIds = $militaire->certificats->pluck('pivot.id')->filter()->all();
        $documentsByPivot = !empty($pivotIds)
            ? CertificatDocument::whereIn('militaire_certificat_id', $pivotIds)->get()->keyBy('militaire_certificat_id')
            : collect();

        $certificats = $militaire->certificats->map(function ($certificat) use ($documentsByPivot) {
            $document = $documentsByPivot->get($certificat->pivot->id);

            return [
                'id' => $certificat->id,
                'nom_certificat' => $certificat->nom_certificat,
                'niveau_certificat' => $certificat->niveau_certificat,
                'date_obtention' => $certificat->pivot->date_obtention
                    ? Carbon::parse($certificat->pivot->date_obtention)->format('d/m/Y')
                    : null,
                'document_id' => $document?->id,
                'document_nom' => $document?->nom_fichier,
            ];
        });

        $dateRetraite = $militaire->calculerDateRetraite();

        $militaireData = [
            'id' => $militaire->id,
            'matricule' => $militaire->matricule,
            'mle_grm' => $militaire->mle_grm,
            'numero_info' => $militaire->numero_info,
            'numero_nina' => $militaire->numero_nina,
            'photo' => $militaire->photo,
            'photo_url' => $militaire->photo_url,
            'nom' => $militaire->nom,
            'prenom' => $militaire->prenom,
            'date_naissance' => $militaire->date_naissance?->format('d/m/Y'),
            'lieu_naissance' => $militaire->lieu_naissance,
            'date_entree_service' => $militaire->date_entree_service?->format('d/m/Y'),
            'date_retraite' => $dateRetraite?->format('d/m/Y'),
            'grade_actuel' => $militaire->grade_actuel,
            'categorie' => $militaire->categorie,
            'date_derniere_promotion' => $militaire->date_derniere_promotion?->format('d/m/Y'),
            'specialite' => $militaire->specialite,
            'origine_formation' => $militaire->origine_formation,
            'position_actuelle' => $militaire->position_actuelle,
            'unite' => $militaire->unite,
            'region' => $militaire->region,
            'date_mutation' => $militaire->date_mutation?->format('d/m/Y'),
            'fonction_passee' => $militaire->fonction_passee,
            'fonction_actuelle' => $militaire->fonction_actuelle,
            'telephone' => $militaire->telephone,
            'sexe' => $militaire->sexe,
            'groupe_sanguin' => $militaire->groupe_sanguin,
            'taille' => $militaire->taille,
            'status_sanitaire' => $militaire->status_sanitaire,
            'engagement_ops' => $militaire->engagement_ops,
            'personne_a_contacter' => $militaire->personne_a_contacter,
            'telephone_personne_contacter' => $militaire->telephone_personne_contacter,
            'statut' => $militaire->statut,
            'a_permis_conduire' => $militaire->a_permis_conduire,
            'antecedent_judiciaire' => $militaire->antecedent_judiciaire,
            'antecedent_disciplinaire' => $militaire->antecedent_disciplinaire,
            'decorations' => $militaire->decorations,
            'diplomes_academiques' => $militaire->diplomes_academiques,
            'observations' => $militaire->observations,
            'date_deces' => $militaire->date_deces?->format('d/m/Y'),
            'circonstances_deces' => $militaire->circonstances_deces,
            'motif_radiation' => $militaire->motif_radiation,
            'reference_decision' => $militaire->reference_decision,
            'unite_transfert' => $militaire->unite_transfert,
            'motif_transfert' => $militaire->motif_transfert,
            'age' => $militaire->age,
            'anciennete' => $militaire->anciennete,
            'anciennete_grade' => $militaire->ancienneteGrade,
            'est_eligible_retraite' => $militaire->estEligibleRetraite(),
        ];

        return Inertia::render('militaires/show', [
            'militaire' => $militaireData,
            'affectations' => $affectationsList,
            'certificats' => $certificats,
            'alertes' => $alertes,
            'contratActif' => $contratActif ? [
                'id' => $contratActif->id,
                'date_debut' => $contratActif->date_debut?->format('d/m/Y'),
                'date_fin' => $contratActif->date_fin?->format('d/m/Y'),
                'duree_annees' => $contratActif->duree_annees,
                'statut' => $contratActif->statut,
            ] : null,
        ]);
    }

    public function edit(Militaire $militaire)
    {
        $militaire->load(['certificats', 'affectations']);

        $grades = Cache::remember('grades_edit_cached', 3600, function () {
            return Grade::orderBy('ordre')->get()->map(fn ($grade) => [
                'id' => $grade->id,
                'nom_grade' => $grade->nom_grade,
                'code_grade' => $grade->code_grade,
                'ordre' => $grade->ordre,
                'type_grade' => $grade->type_grade,
                'retraite_obligatoire' => $grade->retraite_obligatoire,
                'categorie_statutaire' => $grade->categorie_statutaire,
            ]);
        });

        $certificats = Cache::remember('certificats_edit_cached', 3600, function () {
            return Certificat::orderBy('id')->get()->map(fn ($certificat) => [
                'id' => $certificat->id,
                'nom_certificat' => $certificat->nom_certificat,
                'niveau_certificat' => $certificat->niveau_certificat,
            ]);
        });

        $certificatsDuMilitaire = [];
        $pivotIds = $militaire->certificats->pluck('pivot.id')->filter()->all();
        $documentsByPivot = !empty($pivotIds)
            ? CertificatDocument::whereIn('militaire_certificat_id', $pivotIds)->get()->keyBy('militaire_certificat_id')
            : collect();

        foreach ($militaire->certificats as $certificat) {
            $document = $documentsByPivot->get($certificat->pivot->id);

            $certificatsDuMilitaire[$certificat->id] = [
                'obtenu' => true,
                'date_obtention' => $certificat->pivot->date_obtention
                    ? Carbon::parse($certificat->pivot->date_obtention)->format('Y-m-d')
                    : null,
                'document' => $document ? [
                    'id' => $document->id,
                    'nom_fichier' => $document->nom_fichier,
                ] : null,
            ];
        }

        $affectationsList = $militaire->affectations->map(fn ($aff) => [
            'region_id' => $aff->region_id,
            'unite_id' => $aff->unite_id,
            'unite' => $aff->unite ?? '',
            'region' => $aff->region ?? '',
            'date_mutation' => $aff->date_mutation?->format('Y-m-d') ?? '',
        ])->toArray();

        if (empty($affectationsList)) {
            $affectationsList = [
                [
                    'region_id' => $militaire->region_id,
                    'unite_id' => $militaire->unite_id,
                    'unite' => $militaire->unite ?? '',
                    'region' => $militaire->region ?? '',
                    'date_mutation' => $militaire->date_mutation?->format('Y-m-d') ?? '',
                ]
            ];
        }

        $regions = Region::orderBy('ordre')->orderBy('nom')->get();
        $unites = Unite::with('region')->orderBy('nom')->get();
        $positions = Position::orderBy('nom')->get();

        return Inertia::render('militaires/edit', [
            'militaire' => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'mle_grm' => $militaire->mle_grm,
                'numero_info' => $militaire->numero_info,
                'numero_nina' => $militaire->numero_nina,
                'photo' => $militaire->photo,
                'photo_url' => $militaire->photo_url,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'date_naissance' => $militaire->date_naissance?->format('Y-m-d'),
                'lieu_naissance' => $militaire->lieu_naissance,
                'date_entree_service' => $militaire->date_entree_service?->format('Y-m-d'),
                'grade_actuel' => $militaire->grade_actuel,
                'categorie' => $militaire->categorie,
                'date_derniere_promotion' => $militaire->date_derniere_promotion?->format('Y-m-d'),
                'specialite' => $militaire->specialite,
                'origine_formation' => $militaire->origine_formation,
                'position_id' => $militaire->position_id,
                'position_actuelle' => $militaire->position_actuelle,
                'unite_id' => $militaire->unite_id,
                'unite' => $militaire->unite,
                'region_id' => $militaire->region_id,
                'region' => $militaire->region,
                'date_mutation' => $militaire->date_mutation?->format('Y-m-d'),
                'fonction_passee' => $militaire->fonction_passee,
                'fonction_actuelle' => $militaire->fonction_actuelle,
                'telephone' => $militaire->telephone,
                'sexe' => $militaire->sexe,
                'groupe_sanguin' => $militaire->groupe_sanguin,
                'status_sanitaire' => $militaire->status_sanitaire,
                'engagement_ops' => $militaire->engagement_ops,
                'personne_a_contacter' => $militaire->personne_a_contacter,
                'telephone_personne_contacter' => $militaire->telephone_personne_contacter,
                'statut' => $militaire->statut,
                'a_permis_conduire' => $militaire->a_permis_conduire,
                'antecedent_judiciaire' => $militaire->antecedent_judiciaire,
                'antecedent_disciplinaire' => $militaire->antecedent_disciplinaire,
                'decorations' => $militaire->decorations,
                'diplomes_academiques' => $militaire->diplomes_academiques,
                'observations' => $militaire->observations,
                'date_deces' => $militaire->date_deces?->format('Y-m-d'),
                'circonstances_deces' => $militaire->circonstances_deces,
                'motif_radiation' => $militaire->motif_radiation,
                'reference_decision' => $militaire->reference_decision,
                'unite_transfert' => $militaire->unite_transfert,
                'motif_transfert' => $militaire->motif_transfert,
            ],
            'grades' => $grades,
            'affectations' => $affectationsList,
            'certificats' => $certificats,
            'certificats_du_militaire' => $certificatsDuMilitaire,
            'regions' => $regions,
            'unites' => $unites,
            'positions' => $positions,
        ]);
    }

    public function update(Request $request, Militaire $militaire)
    {
        $validated = $request->validate([
            'matricule' => 'required|string|unique:militaires,matricule,' . $militaire->id,
            'mle_grm' => 'nullable|string|max:100',
            'numero_info' => 'nullable|string|max:100',
            'numero_nina' => 'nullable|string|max:100',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'supprimer_photo' => 'nullable|boolean',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:255',
            'date_entree_service' => 'nullable|date',
            'grade_actuel' => 'required|string',
            'categorie' => 'nullable|string|max:100',
            'date_derniere_promotion' => 'nullable|date',
            'specialite' => 'nullable|string|max:200',
            'origine_formation' => 'nullable|string|max:255',
            'position_id' => 'nullable|exists:positions,id',
            'position_actuelle' => 'nullable|string|max:255',
            'unite_id' => 'nullable|exists:unites,id',
            'unite' => 'nullable|string|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'region' => 'nullable|string|max:100',
            'date_mutation' => 'nullable|date',
            'fonction_passee' => 'nullable|string|max:255',
            'fonction_actuelle' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'sexe' => 'nullable|string|max:10',
            'groupe_sanguin' => 'nullable|string|max:10',
            'taille' => 'nullable|string|max:30',
            'status_sanitaire' => 'nullable|string',
            'engagement_ops' => 'nullable|string|max:255',
            'personne_a_contacter' => 'nullable|string|max:255',
            'telephone_personne_contacter' => 'nullable|string|max:20',
            'statut' => 'required|string',
            'a_permis_conduire' => 'nullable',
            'antecedent_judiciaire' => 'nullable',
            'antecedent_disciplinaire' => 'nullable',
            'decorations' => 'nullable|string',
            'diplomes_academiques' => 'nullable|string',
            'observations' => 'nullable|string',
            'date_deces' => 'nullable|date',
            'circonstances_deces' => 'nullable|string',
            'motif_radiation' => 'nullable|string|max:255',
            'reference_decision' => 'nullable|string|max:255',
            'unite_transfert' => 'nullable|string|max:255',
            'motif_transfert' => 'nullable|string|max:255',
        ]);

        $data = $this->extractData($request, $militaire);

        DB::transaction(function () use ($militaire, $data, $request) {
            $militaire->update($data);

            if ($request->has('certificats')) {
                $this->syncCertificatsWithDocuments($militaire, $request->certificats);
            }

            // Synchronisation des affectations multiples
            $this->syncAffectations($militaire, $request);

            if (auth()->check()) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'Modification',
                    'description' => 'A modifié le militaire : ' . $militaire->nom . ' ' . $militaire->prenom . ' (' . $militaire->matricule . ')',
                    'ip_address' => request()->ip()
                ]);
            }
        });

        $militaire->load('certificats', 'affectations');

        $this->verifierAlertes($militaire);

        $message = ($militaire->statut === 'retraité' && $request->input('statut') === 'actif')
            ? 'Militaire mis à jour avec le statut "Retraité" (limite d\'âge atteinte/dépassée).'
            : 'Militaire mis à jour avec succès.';

        return redirect()->route('militaires.show', $militaire)
            ->with('success', $message);
    }

    public function destroy(Militaire $militaire)
    {
        $nom_complet = $militaire->nom . ' ' . $militaire->prenom . ' (' . $militaire->matricule . ')';
        $militaire->delete();
        
        // Enregistrer l'action dans le journal
        if (auth()->check()) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'Suppression',
                'description' => 'A supprimé le militaire : ' . $nom_complet,
                'ip_address' => request()->ip()
            ]);
        }
        
        return redirect()->route('militaires.index')
            ->with('success', 'Militaire supprimé avec succès.');
    }



    public function exportTemplate()
    {
        $headers = \App\Imports\MilitairesImport::getTemplateHeaders();
        $exampleRow = \App\Imports\MilitairesImport::getTemplateExampleRow();

        return Excel::download(
            new \App\Exports\MilitairesTemplateExport($headers, $exampleRow),
            'modele_militaires.xlsx'
        );
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $grade = $request->input('grade');
        $statut = $request->input('statut');

        \Log::info('📊 Export reçu - paramètres bruts :', $request->all());

        $search = ($search && $search !== 'null') ? $search : null;
        $grade = ($grade && $grade !== 'null') ? $grade : null;
        $statut = ($statut && $statut !== 'null') ? $statut : null;

        \Log::info('🎯 Paramètres après nettoyage :', [
            'search' => $search,
            'grade' => $grade,
            'statut' => $statut
        ]);

        if ($grade) {
            $count = Militaire::where('grade_actuel', $grade)->count();
            \Log::info('📊 Nombre de militaires avec grade "' . $grade . '" : ' . $count);
        }

        $anneeN = \Carbon\Carbon::now()->year;
        $anneeN1 = $anneeN + 1;
        $idsN = $this->getIdsRetraitePourAnnee($anneeN);
        $idsN1 = $this->getIdsRetraitePourAnnee($anneeN1);

        return Excel::download(
            new MilitairesExport($search, $grade, $statut, $idsN, $idsN1),
            'militaires_' . date('Y-m-d') . '.xlsx'
        );
    }

    // =========================================================
    // MÉTHODES PRIVÉES
    // =========================================================

    private function extractData(Request $request, ?Militaire $militaire = null): array
    {
        $fields = [
            'matricule', 'mle_grm', 'numero_info', 'numero_nina',
            'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'date_entree_service',
            'grade_actuel', 'categorie', 'date_derniere_promotion',
            'specialite', 'origine_formation', 'statut',
            'position_id', 'position_actuelle', 'unite_id', 'unite', 'region_id', 'region', 'date_mutation',
            'fonction_passee', 'fonction_actuelle',
            'telephone', 'sexe', 'groupe_sanguin', 'taille', 'status_sanitaire', 'engagement_ops',
            'personne_a_contacter', 'telephone_personne_contacter',
            'decorations', 'diplomes_academiques', 'observations',
            'date_deces', 'circonstances_deces', 'motif_radiation', 'reference_decision',
            'unite_transfert', 'motif_transfert'
        ];

        $data = [];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                if (in_array($field, ['decorations', 'diplomes_academiques']) && is_array($val)) {
                    $clean = array_values(array_filter(array_map('trim', $val)));
                    $data[$field] = !empty($clean) ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
                } else {
                    $data[$field] = ($val === '' || $val === 'null' || $val === null) ? null : $val;
                }
            }
        }

        // Gestion de la photo d'identité
        if ($request->hasFile('photo')) {
            if ($militaire && $militaire->photo && Storage::disk('public')->exists($militaire->photo)) {
                Storage::disk('public')->delete($militaire->photo);
            }
            $data['photo'] = $request->file('photo')->store('militaires/photos', 'public');
        } elseif ($request->boolean('supprimer_photo') && $militaire && $militaire->photo) {
            if (Storage::disk('public')->exists($militaire->photo)) {
                Storage::disk('public')->delete($militaire->photo);
            }
            $data['photo'] = null;
        }

        $booleanFields = [
            'a_fait_cat1', 'a_fait_cat2', 'a_fait_cia', 'a_fait_ba1', 'a_fait_ba2',
            'a_fait_bmp1', 'a_fait_bmp2', 'a_fait_bs', 'a_fait_ct2',
            'a_fait_apli', 'a_fait_cfcu', 'a_fait_cem',
            'a_fait_certificat_etat_major', 'a_fait_ecole_guerre',
            'a_permis_conduire', 'antecedent_judiciaire', 'antecedent_disciplinaire'
        ];

        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->boolean($field);
            }
        }

        // Déduction automatique de la catégorie statutaire si le grade est connu et la catégorie non fournie
        if (empty($data['categorie']) && !empty($data['grade_actuel'])) {
            $grade = Grade::where('nom_grade', $data['grade_actuel'])
                ->orWhere('code_grade', $data['grade_actuel'])
                ->first();
            $data['categorie'] = $grade ? $grade->categorie_statutaire : Grade::determinerCategorie($data['grade_actuel']);
        }

        return $data;
    }

    /**
     * Synchronise les affectations multiples du militaire.
     */
    private function syncAffectations(Militaire $militaire, Request $request): void
    {
        $affectationsData = $request->input('affectations');
        if (is_string($affectationsData)) {
            $affectationsData = json_decode($affectationsData, true);
        }

        if (is_array($affectationsData) && !empty($affectationsData)) {
            $militaire->affectations()->delete();
            $createdAffectations = [];
            foreach ($affectationsData as $item) {
                if (empty($item['unite']) && empty($item['region']) && empty($item['unite_id']) && empty($item['region_id']) && empty($item['date_mutation'])) {
                    continue;
                }
                $aff = $militaire->affectations()->create([
                    'region_id' => !empty($item['region_id']) ? $item['region_id'] : null,
                    'unite_id' => !empty($item['unite_id']) ? $item['unite_id'] : null,
                    'unite' => !empty($item['unite']) ? $item['unite'] : null,
                    'region' => !empty($item['region']) ? $item['region'] : null,
                    'date_mutation' => !empty($item['date_mutation']) ? Carbon::parse($item['date_mutation']) : null,
                ]);
                $createdAffectations[] = $aff;
            }

            // Déterminer l'affectation la plus récente (par date_mutation DESC)
            $affectationPlusRecente = null;
            if (!empty($createdAffectations)) {
                usort($createdAffectations, function ($a, $b) {
                    if (!$a->date_mutation && !$b->date_mutation) return 0;
                    if (!$a->date_mutation) return 1;
                    if (!$b->date_mutation) return -1;
                    return $b->date_mutation->timestamp <=> $a->date_mutation->timestamp;
                });
                $affectationPlusRecente = $createdAffectations[0];
            }

            if ($affectationPlusRecente) {
                $militaire->update([
                    'region_id' => $affectationPlusRecente->region_id,
                    'unite_id' => $affectationPlusRecente->unite_id,
                    'unite' => $affectationPlusRecente->unite,
                    'region' => $affectationPlusRecente->region,
                    'date_mutation' => $affectationPlusRecente->date_mutation,
                ]);
            }
        } elseif ($militaire->unite || $militaire->region || $militaire->unite_id || $militaire->region_id || $militaire->date_mutation) {
            if ($militaire->affectations()->count() === 0) {
                $militaire->affectations()->create([
                    'region_id' => $militaire->region_id,
                    'unite_id' => $militaire->unite_id,
                    'unite' => $militaire->unite,
                    'region' => $militaire->region,
                    'date_mutation' => $militaire->date_mutation,
                ]);
            }
        }
    }

    /**
     * Synchronise les certificats et gère les documents de manière sécurisée sans supprimer les ID pivot existants.
     */
    private function syncCertificatsWithDocuments(Militaire $militaire, array $certificatsData)
    {
        $existingCertificats = \DB::table('certificat_militaire')
            ->where('militaire_id', $militaire->id)
            ->pluck('id', 'certificat_id')
            ->toArray();

        foreach ($certificatsData as $certificatId => $data) {
            $certificatId = (int) $certificatId;
            $obtenu = isset($data['obtenu']) && ($data['obtenu'] === '1' || $data['obtenu'] === 1 || $data['obtenu'] === true || $data['obtenu'] === 'true');

            if ($obtenu) {
                $dateObtention = (!empty($data['date_obtention']) && $data['date_obtention'] !== 'null') ? $data['date_obtention'] : null;

                if (isset($existingCertificats[$certificatId])) {
                    $pivotId = $existingCertificats[$certificatId];
                    \DB::table('certificat_militaire')
                        ->where('id', $pivotId)
                        ->update([
                            'date_obtention' => $dateObtention,
                            'updated_at' => now(),
                        ]);
                } else {
                    $militaire->certificats()->attach($certificatId, [
                        'date_obtention' => $dateObtention,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $pivotId = \DB::table('certificat_militaire')
                        ->where('militaire_id', $militaire->id)
                        ->where('certificat_id', $certificatId)
                        ->value('id');
                }

                if (isset($data['document']) && $data['document']) {
                    $this->handleCertificatDocument($pivotId, $data['document']);
                }
            } else {
                if (isset($existingCertificats[$certificatId])) {
                    $pivotId = $existingCertificats[$certificatId];
                    $existingDoc = CertificatDocument::where('militaire_certificat_id', $pivotId)->first();
                    if ($existingDoc) {
                        if (Storage::disk('local')->exists($existingDoc->chemin_fichier)) {
                            Storage::disk('local')->delete($existingDoc->chemin_fichier);
                        }
                        if (Storage::disk('public')->exists($existingDoc->chemin_fichier)) {
                            Storage::disk('public')->delete($existingDoc->chemin_fichier);
                        }
                        $existingDoc->delete();
                    }
                    \DB::table('certificat_militaire')->where('id', $pivotId)->delete();
                }
            }
        }
    }

    /**
     * Gère l'upload/suppression des documents de façon sécurisée (stockage privé et validation)
     */
    private function handleCertificatDocument($pivotId, $documentData)
    {
        // Si le document est null ou false, supprimer l'existant
        if (!$documentData) {
            $existingDoc = CertificatDocument::where('militaire_certificat_id', $pivotId)->first();
            if ($existingDoc) {
                if (Storage::disk('local')->exists($existingDoc->chemin_fichier)) {
                    Storage::disk('local')->delete($existingDoc->chemin_fichier);
                }
                if (Storage::disk('public')->exists($existingDoc->chemin_fichier)) {
                    Storage::disk('public')->delete($existingDoc->chemin_fichier);
                }
                $existingDoc->delete();
            }
            return;
        }

        // Si c'est un fichier uploadé
        if (is_array($documentData) && isset($documentData['file'])) {
            $file = $documentData['file'];
        } elseif ($documentData instanceof \Illuminate\Http\UploadedFile) {
            $file = $documentData;
        } else {
            return;
        }

        // Validation défensive du type et de la taille du fichier
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream'
        ];

        if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'certificats' => "Type de document non autorisé ({$ext}). Seuls les formats PDF, JPG, PNG et DOCX sont autorisés."
            ]);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'certificats' => "Le document dépasse la taille maximale autorisée de 10 Mo."
            ]);
        }

        // Supprimer l'ancien document s'il existe
        $existingDoc = CertificatDocument::where('militaire_certificat_id', $pivotId)->first();
        if ($existingDoc) {
            if (Storage::disk('local')->exists($existingDoc->chemin_fichier)) {
                Storage::disk('local')->delete($existingDoc->chemin_fichier);
            }
            if (Storage::disk('public')->exists($existingDoc->chemin_fichier)) {
                Storage::disk('public')->delete($existingDoc->chemin_fichier);
            }
            $existingDoc->delete();
        }

        // Enregistrer le nouveau document dans le stockage local PRIVÉ (non exposé publiquement)
        $path = $file->store('certificats_documents', 'local');

        CertificatDocument::create([
            'militaire_certificat_id' => $pivotId,
            'nom_fichier' => $file->getClientOriginalName(),
            'chemin_fichier' => $path,
            'type_fichier' => $file->getMimeType(),
            'taille' => $file->getSize(),
        ]);
    }

    /**
     * Nettoie les documents orphelins
     */
    public function verifierAlertes(Militaire $militaire)
    {
        $this->verifierPromotions($militaire);
        $this->verifierFormations($militaire);
        $this->verifierRetraite($militaire);
        $this->verifierAlerteContrat($militaire);
        $this->verifierAlerteMutation($militaire);
    }

    /**
     * Vérifier et créer l'alerte mutation (durée d'affectation >= 3 ans)
     */
    private function verifierAlerteMutation(Militaire $militaire): void
    {
        if ($militaire->statut !== 'actif') {
            Alerte::where('militaire_id', $militaire->id)->where('type_alerte', 'mutation')->delete();
            return;
        }

        // Récupérer l'affectation ayant la date de mutation la plus récente
        $plusRecenteAff = $militaire->affectations()
            ->whereNotNull('date_mutation')
            ->orderBy('date_mutation', 'desc')
            ->first();

        $dateMutation = $plusRecenteAff?->date_mutation 
            ?? $militaire->date_mutation 
            ?? $militaire->date_entree_service;

        if (!$dateMutation) {
            return;
        }

        // Synchroniser le militaire si nécessaire avec sa plus récente affectation
        if ($plusRecenteAff && ($militaire->date_mutation != $plusRecenteAff->date_mutation || $militaire->unite != $plusRecenteAff->unite)) {
            $militaire->update([
                'unite' => $plusRecenteAff->unite,
                'region' => $plusRecenteAff->region,
                'date_mutation' => $plusRecenteAff->date_mutation,
            ]);
        }

        $anneesMutation = floor(Carbon::parse($dateMutation)->diffInYears(now()));

        if ($anneesMutation < 3) {
            Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'mutation')
                ->delete();
            return;
        }

        $debutMois = now()->startOfMonth();
        $finMois = now()->endOfMonth();
        $alerteExiste = Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', 'mutation')
            ->where('est_vue', false)
            ->whereBetween('created_at', [$debutMois, $finMois])
            ->exists();

        if (!$alerteExiste) {
            $unite = $plusRecenteAff?->unite ?? $militaire->unite ?? 'Unité non précisée';
            $region = $plusRecenteAff?->region ?? $militaire->region ?? '-';
            $dateMutFormatted = Carbon::parse($dateMutation)->format('d/m/Y');

            Alerte::create([
                'militaire_id' => $militaire->id,
                'type_alerte' => 'mutation',
                'message' => "Alerte Mutation : {$militaire->grade_actuel} {$militaire->prenom} {$militaire->nom} en poste depuis {$anneesMutation} ans à {$unite} ({$region}) (dernière mutation le {$dateMutFormatted})",
                'date_echeance' => now()->endOfMonth(),
                'est_vue' => false,
            ]);
        }
    }

    /**
     * Vérifier et créer l'alerte contrat pour un militaire
     */
    private function verifierAlerteContrat(Militaire $militaire): void
    {
        if ($militaire->statut !== 'actif') {
            return;
        }

        $gradesEligibles = [
            'Soldat 2', 'Soldat 1', 'Caporal', 'Caporal-chef',
            'Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef', 'Major',
            'Sapeur 1ère classe'
        ];

        if (!in_array($militaire->grade_actuel, $gradesEligibles)) {
            return;
        }

        if (!$militaire->date_entree_service) {
            return;
        }

        $serviceYears = floor($militaire->date_entree_service->diffInYears(now()));

        if ($serviceYears < 5) {
            Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'contrat')
                ->delete();
            return;
        }

        Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', 'contrat')
            ->delete();

        $message = "Renouvellement de contrat requis pour {$militaire->prenom} {$militaire->nom} ({$serviceYears} ans de service) - Grade: {$militaire->grade_actuel}";

        Alerte::create([
            'militaire_id' => $militaire->id,
            'type_alerte' => 'contrat',
            'message' => $message,
            'date_echeance' => now()->addMonths(6),
            'est_vue' => false,
        ]);
    }

    private function getDateProposition()
    {
        $annee = Carbon::now()->year;
        return Carbon::create($annee, 12, 31, 23, 59, 59);
    }

    private function getDateAnciennetePromotion(Militaire $militaire, string $gradeCible)
    {
        $gradeActuel = $militaire->grade_actuel;
        // Normalisation pour équivalences de grades
        if ($gradeActuel === 'Sapeur 1ère classe') {
            $gradeActuel = 'Soldat 1';
        } elseif ($gradeActuel === 'Commissaire') {
            $gradeActuel = 'Lieutenant';
        }
        $dateDernierePromotion = $militaire->date_derniere_promotion;
        $dateEntreeService = $militaire->date_entree_service;

        $regles = [
            'Caporal'          => ['grade' => 'Soldat 1',         'annees' => 5, 'base' => 'entree'],
            'Sergent'          => ['grade' => 'Caporal',          'annees' => 3, 'base' => 'promotion'],
            'Sergent-Chef'     => ['grade' => 'Sergent',          'annees' => 2, 'base' => 'promotion'],
            'Adjudant'         => ['grade' => 'Sergent-Chef',     'annees' => 3, 'base' => 'promotion'],
            'Adjudant-Chef'    => ['grade' => 'Adjudant',         'annees' => 2, 'base' => 'promotion'],
            'Lieutenant'       => ['grade' => 'Sous-lieutenant',  'annees' => 2, 'base' => 'promotion'],
            'Capitaine'        => ['grade' => 'Lieutenant',       'annees' => 3, 'base' => 'promotion'],
            'Commandant'       => ['grade' => 'Capitaine',        'annees' => 3, 'base' => 'promotion'],
            'Lieutenant-colonel' => ['grade' => 'Commandant',    'annees' => 3, 'base' => 'promotion'],
            'Colonel'          => ['grade' => 'Lieutenant-colonel','annees' => 3, 'base' => 'promotion'],
            'Colonel-Major'    => ['grade' => 'Colonel',          'annees' => 6, 'base' => 'promotion'],
        ];

        foreach ($regles as $cible => $regle) {
            if ($cible === $gradeCible && $regle['grade'] === $gradeActuel) {
                if ($regle['base'] === 'entree' && $dateEntreeService) {
                    return Carbon::parse($dateEntreeService)->addYears($regle['annees']);
                } elseif ($regle['base'] === 'promotion' && $dateDernierePromotion) {
                    return Carbon::parse($dateDernierePromotion)->addYears($regle['annees']);
                }
            }
        }

        return null;
    }

    /**
     * Vérifie les promotions et supprime les alertes si la promotion est obtenue
     */
    private function verifierPromotions(Militaire $militaire): void
    {
        if ($militaire->statut !== 'actif') return;

        $grade = $militaire->grade_actuel;
        // Normalisation pour équivalences de grades
        if ($grade === 'Sapeur 1ère classe') {
            $grade = 'Soldat 1';
        } elseif ($grade === 'Commissaire') {
            $grade = 'Lieutenant';
        }
        
        $age = $militaire->age;
        $ancienneteGrade = $militaire->ancienneteGrade;
        $conditionsBase = !$militaire->antecedent_judiciaire && !$militaire->antecedent_disciplinaire;
        $certificatsObtenus = $militaire->certificats->pluck('niveau_certificat')->toArray();

        // Suppression en lot uniquement des alertes de promotion non lues pour préserver celles déjà consultées
        Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', 'promotion')
            ->where('est_vue', false)
            ->delete();

        $dateProposition = $this->getDateProposition();

        // Créer les alertes de promotion
        if ($grade == 'Soldat 1' && in_array('CAT1', $certificatsObtenus) && $conditionsBase && $militaire->anciennete >= 5) {
            $dateAnciennete = Carbon::parse($militaire->date_entree_service)->addYears(5);
            $this->creerAlertePromotion($militaire, 'Caporal', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && in_array('CAT2', $certificatsObtenus) && $conditionsBase) {
            $certifCAT1 = $militaire->certificats->where('niveau_certificat', 'CAT1')->first();
            $dateAnciennete = null;
            if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                $dateAnciennete = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
            }
            $this->creerAlertePromotion($militaire, 'Sergent', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && !in_array('CAT2', $certificatsObtenus) && $age >= 47 && $ancienneteGrade >= 3 && $conditionsBase) {
            $dateAnciennete = $militaire->date_derniere_promotion
                ? Carbon::parse($militaire->date_derniere_promotion)->addYears(3)
                : null;
            $this->creerAlertePromotion($militaire, 'Caporal-chef', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Sergent' && $conditionsBase && $ancienneteGrade >= 2 && $militaire->anciennete >= 5) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $this->creerAlertePromotion($militaire, 'Sergent-Chef', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Sergent-Chef' && $conditionsBase && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $this->creerAlertePromotion($militaire, 'Adjudant', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Adjudant' && $conditionsBase && $ancienneteGrade >= 2) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $this->creerAlertePromotion($militaire, 'Adjudant-Chef', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Sous-lieutenant' && $ancienneteGrade >= 2) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(2);
            $this->creerAlertePromotion($militaire, 'Lieutenant', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Lieutenant' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $this->creerAlertePromotion($militaire, 'Capitaine', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Capitaine' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $this->creerAlertePromotion($militaire, 'Commandant', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Commandant' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $this->creerAlertePromotion($militaire, 'Lieutenant-colonel', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Lieutenant-colonel' && $ancienneteGrade >= 3) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(3);
            $this->creerAlertePromotion($militaire, 'Colonel', $dateProposition, $dateAnciennete);
        }

        if ($grade == 'Colonel' && $ancienneteGrade >= 6) {
            $dateAnciennete = Carbon::parse($militaire->date_derniere_promotion)->addYears(6);
            $this->creerAlertePromotion($militaire, 'Colonel-Major', $dateProposition, $dateAnciennete);
        }
    }

    /**
     * Vérifie les formations et crée les alertes nécessaires (préserve les alertes déjà vues)
     */
    private function verifierFormations(Militaire $militaire): void
    {
        if ($militaire->statut !== 'actif') return;

        $grade = $militaire->grade_actuel;
        $age = $militaire->age;
        $anciennete = $militaire->anciennete;
        $ancienneteGrade = $militaire->ancienneteGrade;
        $certificatsObtenus = $militaire->certificats->pluck('niveau_certificat')->toArray();
        $conditionsBase = !$militaire->antecedent_judiciaire && !$militaire->antecedent_disciplinaire;
        $dateProposition = $this->getDateProposition();

        // Suppression en lot uniquement des alertes de formation non lues
        Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', 'formation')
            ->where('est_vue', false)
            ->delete();

        // 1. APLI
        if (!in_array('APLI', $certificatsObtenus)) {
            if (in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine']) && !in_array('CFCU', $certificatsObtenus) && $age <= 50) {
                $this->creerAlerteFormation($militaire, 'APLI', "Cour d'Application", $dateProposition, null);
            }
        }

        // 2. CFCU
        if (!in_array('CFCU', $certificatsObtenus)) {
            if (in_array($grade, ['Lieutenant', 'Capitaine'])) {
                if ($grade == 'Capitaine' || in_array('APLI', $certificatsObtenus)) {
                    $this->creerAlerteFormation($militaire, 'CFCU', "Cour des Futurs Commandants d'Unité", $dateProposition, null);
                }
            }
        }

        // 3. CAT1
        if (!in_array('CAT1', $certificatsObtenus)) {
            if (in_array($grade, ['Soldat 2', 'Soldat 1']) && $ancienneteGrade >= 5 && $conditionsBase) {
                $dateConditions = Carbon::parse($militaire->date_entree_service)->addYears(5);
                $this->creerAlerteFormation($militaire, 'CAT1', "Certificat d'Aptitude Technique Niveau 1", $dateProposition, $dateConditions);
            }
        }

        // 4. CAT2
        if (!in_array('CAT2', $certificatsObtenus)) {
            if ($grade == 'Caporal' && $age < 47 && $ancienneteGrade >= 3 && $conditionsBase && in_array('CAT1', $certificatsObtenus)) {
                $certifCAT1 = $militaire->certificats->where('niveau_certificat', 'CAT1')->first();
                $dateConditions = null;
                if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'CAT2', "Certificat d'Aptitude Technique Niveau 2", $dateProposition, $dateConditions);
            }
        }

        // 5. CIA
        if (!in_array('CIA', $certificatsObtenus)) {
            if (in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && $conditionsBase && $militaire->a_permis_conduire) {
                $this->creerAlerteFormation($militaire, 'CIA', "Certificat Interarmes (CIA)", $dateProposition, null);
            }
        }

        // 6. BA1
        if (!in_array('BA1', $certificatsObtenus)) {
            if (in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && in_array('CIA', $certificatsObtenus) && $conditionsBase && $anciennete >= 8) {
                $certifCIA = $militaire->certificats->where('niveau_certificat', 'CIA')->first();
                $dateConditions = null;
                if ($certifCIA && $certifCIA->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCIA->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'BA1', "Brevet d'Arme N1", $dateProposition, $dateConditions);
            }
        }

        // 7. BA2
        if (!in_array('BA2', $certificatsObtenus)) {
            if (in_array($grade, ['Adjudant', 'Adjudant-Chef']) && in_array('BA1', $certificatsObtenus) && $conditionsBase) {
                $certifBA1 = $militaire->certificats->where('niveau_certificat', 'BA1')->first();
                $dateConditions = null;
                if ($certifBA1 && $certifBA1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifBA1->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'BA2', "Brevet d'Arme N2", $dateProposition, $dateConditions);
            }
        }

        // 9. CERT_EM
        if (!in_array('CERT_EM', $certificatsObtenus)) {
            if ($grade == 'Commandant' && $age > 45) {
                $this->creerAlerteFormation($militaire, 'CERT_EM', "Certificat d'État-Major", $dateProposition, null);
            }
        }

        // 10. ECOLE_GUERRE
        if (!in_array('ECOLE_GUERRE', $certificatsObtenus)) {
            if (in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major']) && $ancienneteGrade >= 2 && $age <= 53) {
                $this->creerAlerteFormation($militaire, 'ECOLE_GUERRE', "École de Guerre", $dateProposition, null);
            }
        }
    }

    private function creerAlertePromotion(Militaire $militaire, string $gradeCible, $dateProposition, $dateAnciennete): void
    {
        $anneeProposition = $dateProposition->format('Y');
        $dateAncienneteTexte = $dateAnciennete ? $dateAnciennete->format('d/m/Y') : 'conditions remplies';
        $message = "Proposable pour {$anneeProposition} (ancienneté atteinte le {$dateAncienneteTexte}) - Promotion à {$gradeCible}";
        $this->creerAlerte($militaire, 'promotion', $message, $dateProposition);
    }

    private function creerAlerteFormation(Militaire $militaire, string $formation, string $nomFormation, $dateProposition, $dateConditions): void
    {
        $anneeProposition = $dateProposition->format('Y');
        $dateConditionsTexte = $dateConditions ? $dateConditions->format('d/m/Y') : 'conditions remplies';
        $message = "Proposable pour {$anneeProposition} (conditions remplies le {$dateConditionsTexte}) - Formation {$nomFormation}";
        $this->creerAlerte($militaire, 'formation', $message, $dateProposition);
    }

    private function verifierRetraite(Militaire $militaire): void
    {
        $dateRetraite = $militaire->calculerDateRetraite();

        if ($dateRetraite) {
            $diffJours = Carbon::now()->startOfDay()->diffInDays($dateRetraite);
            $moisRestants = floor($diffJours / 30);

            if ($moisRestants <= 12 && $diffJours >= 0) {
                $message = "Retraite dans {$moisRestants} mois (le " . $dateRetraite->format('d/m/Y') . ")";
                $this->creerAlerte($militaire, 'retraite', $message, $dateRetraite);
            }
        }
    }

    private function creerAlerte(Militaire $militaire, string $type, string $message, $dateEcheance = null): void
    {
        // Vérifie l'existence sans filtrer sur est_vue pour éviter de dupliquer une alerte déjà lue
        $existe = Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', $type)
            ->where('message', $message)
            ->exists();

        if (!$existe) {
            $echeance = $dateEcheance ?? Carbon::now()->addDays(2);
            Alerte::create([
                'militaire_id' => $militaire->id,
                'type_alerte'  => $type,
                'message'      => $message,
                'date_echeance' => $echeance,
            ]);
        }
    }

    /**
     * Retourne la liste des IDs des militaires partant à la retraite pour une année cible.
     */
    private function getIdsRetraitePourAnnee(int $anneeCible): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:militaires:ids_retraite_{$anneeCible}", 1800, function () use ($anneeCible) {
            $militaires = Militaire::whereNotNull('date_naissance')
                ->whereNotNull('grade_actuel')
                ->select('id', 'date_naissance', 'grade_actuel', 'statut')
                ->get();

            $ids = [];
            $anneeCourante = (int) Carbon::now()->year;

            foreach ($militaires as $m) {
                $ageRetraite = Grade::getRetraiteForGrade($m->grade_actuel);
                if (!$ageRetraite) continue;

                $anneeRetraite = (int) (Carbon::parse($m->date_naissance)->year + $ageRetraite);
                $estActif = str_starts_with(mb_strtolower($m->statut ?? ''), 'actif');

                if ($anneeCible === $anneeCourante) {
                    if ($anneeRetraite === $anneeCible || ($estActif && $anneeRetraite < $anneeCible)) {
                        $ids[] = $m->id;
                    }
                } else {
                    if ($estActif && $anneeRetraite === $anneeCible) {
                        $ids[] = $m->id;
                    }
                }
            }

            return $ids;
        });
    }
}
