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
use Illuminate\Validation\Rule;
use App\Support\Effectif;

class MilitaireController extends Controller
{
    public function index(Request $request)
    {
        $query = Militaire::query();

        if ($request->filled('search')) {
            $search = preg_replace('/\s+/', ' ', trim($request->search));
            $searchTerms = explode(' ', $search);
            $query->where(function($q) use ($search, $searchTerms) {
                // Recherche directe et instantanée par ID (clé primaire) ou matricule exact si purement numérique
                if (ctype_digit($search)) {
                    $searchInt = (int) $search;
                    $q->where('militaires.id', $searchInt)
                      ->orWhere('militaires.matricule', $search);
                }
                // Tous les mots doivent être présents (ET), comme avant
                $q->orWhere(function($tous) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $t = mb_strtolower(trim($term));
                        if ($t === '') continue;
                        $tous->where(function($subQ) use ($t) {
                            $subQ->where('militaires.recherche', 'LIKE', "%{$t}%");
                            if (mb_stripos($t, 'assign') !== false) {
                                $subQ->orWhereNull('militaires.position_id')
                                     ->orWhere('militaires.position_id', 0)
                                     ->orWhereNull('militaires.position_actuelle')
                                     ->orWhere('militaires.position_actuelle', '');
                            }
                        });
                    }
                });
            });
        }

        // Filtre par Grade (Priorité stricte à l'ID entier indexé avec fallback sur le nom et code de grade)
        if ($request->filled('grade_id') || $request->filled('grade')) {
            $gradeVal = $request->input('grade_id') ?? $request->input('grade');

            $gradeObj = is_numeric($gradeVal)
                ? Grade::find((int) $gradeVal)
                : Grade::whereRaw('TRIM(LOWER(nom_grade)) = ?', [mb_strtolower(trim($gradeVal))])
                    ->orWhereRaw('TRIM(LOWER(code_grade)) = ?', [mb_strtolower(trim($gradeVal))])
                    ->first();

            $nomGrade = $gradeObj ? trim($gradeObj->nom_grade) : (is_numeric($gradeVal) ? null : trim($gradeVal));
            $codeGrade = $gradeObj?->code_grade ? trim($gradeObj->code_grade) : null;
            $gradeId = $gradeObj ? $gradeObj->id : (is_numeric($gradeVal) ? (int) $gradeVal : null);

            $query->where(function($q) use ($gradeId, $nomGrade, $codeGrade) {
                $hasCondition = false;
                if ($gradeId) {
                    $q->where('militaires.grade_id', $gradeId);
                    $hasCondition = true;
                }
                if ($nomGrade) {
                    $method = $hasCondition ? 'orWhereRaw' : 'whereRaw';
                    $q->$method('TRIM(LOWER(militaires.grade_actuel)) = ?', [mb_strtolower($nomGrade)]);
                    $q->orWhereRaw("REPLACE(REPLACE(TRIM(LOWER(militaires.grade_actuel)), '\r', ''), '\n', '') = ?", [mb_strtolower($nomGrade)]);
                    $hasCondition = true;
                }
                if ($codeGrade) {
                    $q->orWhereRaw('TRIM(LOWER(militaires.grade_actuel)) = ?', [mb_strtolower($codeGrade)]);
                }
            });
        }

        // Filtre par Région (Priorité stricte à l'ID entier indexé)
        if ($request->filled('region_id') || $request->filled('region')) {
            $reg = $request->input('region_id') ?? $request->input('region');
            if (is_numeric($reg)) {
                $query->where('militaires.region_id', (int) $reg);
            } else {
                $regId = Region::where('nom', $reg)->orWhere('code', $reg)->value('id');
                if ($regId) {
                    $query->where('militaires.region_id', $regId);
                } else {
                    $query->where('militaires.region', $reg);
                }
            }
        }

        // Filtre par Unité (Priorité stricte à l'ID entier indexé)
        if ($request->filled('unite_id') || $request->filled('unite')) {
            $unitVal = $request->input('unite_id') ?? $request->input('unite');
            if (is_numeric($unitVal)) {
                $query->where('militaires.unite_id', (int) $unitVal);
            } else {
                $unitId = Unite::where('nom', $unitVal)->orWhere('code', $unitVal)->value('id');
                if ($unitId) {
                    $query->where('militaires.unite_id', $unitId);
                } else {
                    $query->where('militaires.unite', 'LIKE', "%{$unitVal}%");
                }
            }
        }

        // Filtre par Position (Priorité stricte à l'ID entier indexé)
        if ($request->filled('position_id') || $request->filled('position')) {
            $pos = $request->input('position_id') ?? $request->input('position');
            if ($pos === 'Non assigné / En attente' || $pos === 'non_assigne' || $pos === '0') {
                $query->where(function ($q) {
                    $q->whereNull('militaires.position_id')
                      ->orWhere('militaires.position_id', 0)
                      ->orWhereNull('militaires.position_actuelle')
                      ->orWhere('militaires.position_actuelle', '');
                });
            } elseif (is_numeric($pos)) {
                $query->where('militaires.position_id', (int) $pos);
            } else {
                $posId = Position::where('nom', $pos)->value('id');
                if ($posId) {
                    $query->where('militaires.position_id', $posId);
                } else {
                    $query->where('militaires.position_actuelle', 'LIKE', "%{$pos}%");
                }
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
            // Actifs sous les drapeaux (y compris détachés, stages, missions)
            $query->whereRaw(Effectif::sqlActivite('militaires.statut'));
        } elseif ($statutFilter === 'actif_strict') {
            // Uniquement personnels en service régulier dans les corps de troupe Gendarmerie (hors détachements)
            Effectif::appliquerFiltreActifsStricts($query, 'militaires.');
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'd_tach') || str_starts_with(mb_strtolower($statutFilter), 'detach') || str_starts_with(mb_strtolower($statutFilter), 'détach')) {
            // Militaires placés en détachement (ministères, institutions d'accueil)
            Effectif::appliquerFiltreDetaches($query, 'militaires.');
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'miss')) {
            // Militaires déployés en mission
            Effectif::appliquerFiltreMissions($query, 'militaires.');
        } elseif (str_starts_with(mb_strtolower($statutFilter), 'stag') || str_starts_with(mb_strtolower($statutFilter), 'format')) {
            // Militaires en stage / formation / école militaire
            Effectif::appliquerFiltreStages($query, 'militaires.');
        } elseif ($statutFilter === 'otage') {
            $query->where(function($q) {
                $q->whereRaw("LOWER(COALESCE(militaires.position_actuelle, '')) LIKE ?", ['%otage%'])
                  ->orWhereRaw("LOWER(COALESCE(militaires.observations, '')) LIKE ?", ['%otage%']);
            });
        } elseif ($statutFilter === 'retraites_n') {
            $this->appliquerFiltreRetraite($query, $anneeN);
        } elseif ($statutFilter === 'retraites_n1') {
            $this->appliquerFiltreRetraite($query, $anneeN + 1);
        } elseif ($statutFilter === 'decedes_annee_n') {
            $debutAnnee = Carbon::create($anneeN, 1, 1)->startOfDay();
            $finAnnee = Carbon::create($anneeN, 12, 31)->endOfDay();
            $query->where(function($q) {
                $q->where('militaires.statut_code', 9)
                  ->orWhereNotNull('militaires.date_deces');
            })->where(function($q) use ($debutAnnee, $finAnnee) {
                $q->whereBetween('militaires.date_deces', [$debutAnnee, $finAnnee]);
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
        $statsCacheKey = "v{$v}:militaires:stats_summary";
        $computeStats = function () use ($anneeN) {
            $total = (int) Militaire::whereNull('deleted_at')->count();
            $actifs = (int) Militaire::whereNull('deleted_at')->whereRaw(Effectif::sqlActivite('statut'))->count();
            $actifsStricts = (int) Effectif::appliquerFiltreActifsStricts(Militaire::whereNull('deleted_at'))->count();
            $detaches = (int) Effectif::appliquerFiltreDetaches(Militaire::whereNull('deleted_at'))->count();
            $nonRattaches = (int) Militaire::whereNull('deleted_at')
                ->whereRaw(Effectif::sqlActivite('statut'))
                ->where(function ($q) {
                    $q->whereNull('region_id')->orWhere('region_id', 0);
                })
                ->where(function ($q) {
                    $q->whereNull('unite_id')->orWhere('unite_id', 0);
                })->count();

            $baseRetraiteQuery = fn() => DB::table('militaires')
                ->join('grades', 'militaires.grade_id', '=', 'grades.id')
                ->whereNotNull('militaires.date_naissance')
                ->whereNotNull('grades.retraite_obligatoire')
                ->where(function ($q) {
                    $q->whereNull('militaires.position_actuelle')
                      ->orWhere('militaires.position_actuelle', 'not ilike', '%otage%');
                })
                ->where(function ($q) {
                    $q->whereNull('militaires.observations')
                      ->orWhere('militaires.observations', 'not ilike', '%otage%');
                })
                ->where('militaires.est_disparu', 0)
                ->whereNull('militaires.deleted_at');

            $retraitesN = (int) $baseRetraiteQuery()
                ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$anneeN})")
                ->count();

            $retraitesN1 = (int) $baseRetraiteQuery()
                ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = " . ($anneeN + 1) . ")")
                ->count();

            return [
                'total'          => $total,
                'actifs'         => $actifs,
                'actifs_stricts' => $actifsStricts,
                'detaches'       => $detaches,
                'non_rattaches'  => $nonRattaches,
                'retraites_n'    => $retraitesN,
                'retraites_n1'   => $retraitesN1,
            ];
        };

        $statistiques = Cache::has($statsCacheKey)
            ? Cache::get($statsCacheKey)
            : Inertia::defer(fn() => Cache::remember($statsCacheKey, 86400, $computeStats));

        $query->orderBy('militaires.grade_ordre', 'desc')
              ->orderBy('militaires.nom', 'asc')
              ->orderBy('militaires.prenom', 'asc');

        $now = \Carbon\Carbon::now();
        $retraitesMap = Cache::remember('ref:retraites_par_grade_map', 86400, function() {
            $map = [];
            foreach (Grade::all(['nom_grade', 'code_grade', 'retraite_obligatoire']) as $g) {
                if ($g->retraite_obligatoire !== null) {
                    $age = (int) $g->retraite_obligatoire;
                    if ($g->nom_grade) {
                        $map[trim($g->nom_grade)] = $age;
                        $map[mb_strtolower(trim($g->nom_grade))] = $age;
                    }
                    if ($g->code_grade) {
                        $map[trim($g->code_grade)] = $age;
                        $map[mb_strtolower(trim($g->code_grade))] = $age;
                        $map[mb_strtoupper(trim($g->code_grade))] = $age;
                    }
                }
            }
            return $map;
        });

        $militaires = $query->with(['theatre:id,code,nom'])
            ->withCount(['alertes' => fn($q) => $q->where('est_vue', false)])
            ->paginate(20)
            ->withQueryString()
            ->through(function ($militaire) use ($retraitesMap, $now) {
                $isOtage = str_contains(mb_strtolower((string) $militaire->position_actuelle), 'otage');
                $dateRetraite = null;
                $estEligibleRetraite = false;

                if (!$isOtage && $militaire->date_naissance && $militaire->grade_actuel) {
                    $gKey = trim($militaire->grade_actuel);
                    $ageRetraite = $retraitesMap[$gKey] ?? $retraitesMap[mb_strtolower($gKey)] ?? null;
                    if ($ageRetraite) {
                        $dateRetraite = $militaire->date_naissance->copy()->addYears($ageRetraite);
                        $estEligibleRetraite = $dateRetraite->isFuture() && $now->diffInMonths($dateRetraite) <= 6;
                    }
                }

                return [
                    'id' => $militaire->id,
                    'grade_id' => $militaire->grade_id,
                    'region_id' => $militaire->region_id,
                    'unite_id' => $militaire->unite_id,
                    'position_id' => $militaire->position_id,
                    'statut_code' => $militaire->statut_code,
                    'sexe_code' => $militaire->sexe_code,
                    'theatre_id' => $militaire->theatre_id,
                    'est_disparu' => $militaire->est_disparu,
                    'est_blesse_guerre' => (bool) $militaire->est_blesse_guerre,
                    'est_non_mobilisable' => (bool) $militaire->est_non_mobilisable,
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
                    'is_otage' => $isOtage,
                    'theatre_id' => $militaire->theatre_id,
                    'theatre_code' => $militaire->theatre?->code,
                    'theatre_nom' => $militaire->theatre?->nom,
                    'age' => $militaire->age,
                    'anciennete' => $militaire->anciennete,
                    'anciennete_grade' => $militaire->ancienneteGrade,
                    'a_permis_conduire' => $militaire->a_permis_conduire,
                    'alertes_count' => (int) ($militaire->alertes_count ?? 0),
                    'est_eligible_retraite' => $estEligibleRetraite,
                    'date_retraite' => $dateRetraite?->format('d/m/Y'),
                ];
            });

        $filterRefs = Cache::remember('ref:militaires_filter_options', 86400, function() {
            return [
                'grades' => Grade::orderBy('ordre', 'desc')->select('id', 'nom_grade', 'code_grade', 'ordre')->get(),
                'regions' => Region::orderByRaw('CASE WHEN ordre IS NULL OR ordre = 0 THEN 999 ELSE ordre END ASC')
                    ->orderBy('nom')
                    ->select('id', 'nom', 'code')
                    ->get(),
                'positions' => Position::orderBy('nom')->select('id', 'nom')->get(),
                'theatres' => \App\Models\Theatre::orderBy('nom')->select('id', 'nom', 'code', 'couleur', 'pc_lieu')->get(),
                'unites' => Unite::orderBy('nom')->select('id', 'nom', 'code', 'region_id')->get(),
            ];
        });

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
            'grades'       => $filterRefs['grades'],
            'regions'      => $filterRefs['regions'],
            'positions'    => $filterRefs['positions'],
            'theatres'     => $filterRefs['theatres'],
            'unites'       => $filterRefs['unites'],
        ]);
    }

    /**
     * Recherche rapide de personnels pour l'affectation collective
     */
    public function searchSelection(Request $request)
    {
        $query = Militaire::query()->with('theatre:id,code,nom');

        if ($request->filled('search')) {
            $search = preg_replace('/\s+/', ' ', trim($request->search));
            $searchTerms = explode(' ', $search);
            $query->where(function($q) use ($search, $searchTerms) {
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search)->orWhere('matricule', $search);
                }
                $q->orWhere(function($tous) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        $t = mb_strtolower(trim($term));
                        if ($t === '') continue;
                        $tous->where('recherche', 'LIKE', "%{$t}%");
                    }
                });
            });
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', (int) $request->grade_id);
        }

        if ($request->filled('categorie')) {
            $cat = $request->input('categorie');
            if ($cat === 'officiers') {
                $query->where('grade_ordre', '>=', 11);
            } elseif ($cat === 'sous_officiers') {
                $query->whereBetween('grade_ordre', [5, 10]);
            } elseif ($cat === 'mdr') {
                $query->where('grade_ordre', '<=', 4);
            }
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', (int) $request->region_id);
        }

        if ($request->filled('theatre_id')) {
            $thId = $request->input('theatre_id');
            if ($thId === 'none') {
                $query->whereNull('theatre_id');
            } else {
                $query->where('theatre_id', (int) $thId);
            }
        }

        $results = $query->orderBy('grade_ordre', 'desc')
            ->orderBy('nom')
            ->limit(150)
            ->get([
                'id',
                'matricule',
                'mle_grm',
                'nom',
                'prenom',
                'grade_actuel',
                'grade_ordre',
                'region_id',
                'region',
                'unite_id',
                'unite',
                'theatre_id',
                'est_blesse_guerre'
            ])
            ->map(fn($m) => [
                'id' => $m->id,
                'matricule' => $m->matricule,
                'mle_grm' => $m->mle_grm,
                'nom' => $m->nom,
                'prenom' => $m->prenom,
                'grade' => $m->grade_actuel,
                'grade_ordre' => $m->grade_ordre,
                'region' => $m->region,
                'unite' => $m->unite,
                'theatre_id' => $m->theatre_id,
                'theatre_code' => $m->theatre?->code,
                'theatre_nom' => $m->theatre?->nom,
                'est_blesse_guerre' => (bool) $m->est_blesse_guerre,
            ]);

        return response()->json($results);
    }

    /**
     * Affectation / Déploiement collectif en masse (Théâtre, Région, Unité)
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'militaire_ids' => 'required|array|min:1',
            'militaire_ids.*' => 'integer|exists:militaires,id',
            'theatre_id' => 'nullable',
            'region_id' => 'nullable|integer|exists:regions,id',
            'unite_id' => 'nullable|integer|exists:unites,id',
            'position_id' => 'nullable|integer',
            'clear_theatre' => 'nullable|boolean',
        ]);

        $ids = $request->input('militaire_ids');
        $theatreId = $request->input('theatre_id');
        $regionId = $request->input('region_id');
        $uniteId = $request->input('unite_id');
        $positionId = $request->input('position_id');
        $clearTheatre = $request->boolean('clear_theatre');

        $theatre = ($theatreId && $theatreId !== 'remove' && is_numeric($theatreId))
            ? \App\Models\Theatre::find((int) $theatreId)
            : null;
        $region = $regionId ? Region::find($regionId) : null;
        $unite = $uniteId ? Unite::find($uniteId) : null;
        $position = $positionId ? Position::find($positionId) : null;

        DB::transaction(function () use ($ids, $theatre, $region, $unite, $position, $clearTheatre, $theatreId) {
            $updateData = [];

            if ($clearTheatre || $theatreId === 'remove') {
                $updateData['theatre_id'] = null;
            } elseif ($theatre) {
                $updateData['theatre_id'] = $theatre->id;
            }

            if ($region) {
                $updateData['region_id'] = $region->id;
                $updateData['region'] = $region->nom;
            }

            if ($unite) {
                $updateData['unite_id'] = $unite->id;
                $updateData['unite'] = $unite->nom;
                $updateData['date_mutation'] = now()->toDateString();
                if (!$region && $unite->region_id) {
                    $updateData['region_id'] = $unite->region_id;
                    $reg = Region::find($unite->region_id);
                    if ($reg) $updateData['region'] = $reg->nom;
                }
            }

            if ($position) {
                $updateData['position_id'] = $position->id;
                $updateData['position_actuelle'] = $position->nom;
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                Militaire::whereIn('id', $ids)->update($updateData);

                // Mettre à jour la recherche textuelle
                $militaires = Militaire::whereIn('id', $ids)->get();
                foreach ($militaires as $m) {
                    $m->recherche = $m->construireTexteRecherche();
                    $m->saveQuietly();
                }
            }
        });

        \App\Http\Controllers\CarteController::flush();
        Militaire::incrementCacheVersion();

        $count = count($ids);
        $destination = [];
        if ($theatre) $destination[] = "le Théâtre {$theatre->code} ({$theatre->nom})";
        elseif ($clearTheatre || $theatreId === 'remove') $destination[] = "retirés du théâtre opérationnel";
        if ($unite) $destination[] = "l'unité {$unite->nom}";
        elseif ($region) $destination[] = "la {$region->nom}";

        $destStr = !empty($destination) ? implode(' et ', $destination) : 'la destination choisie';

        return back()->with('success', "{$count} militaire(s) affecté(s) avec succès vers {$destStr}.");
    }

    public function create()
    {
        $grades = Cache::remember('grades_edit_cached', 3600, function () {
            return Grade::orderByDesc('ordre')->get()->map(fn ($grade) => [
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
        $organismes = \App\Models\OrganismeDetachement::where('est_actif', true)->where('code', '!=', 'GRM')->orderBy('ordre')->get(['id', 'code', 'nom']);
        $theatres = \App\Models\Theatre::where('statut', 'actif')->orderBy('nom')->get(['id', 'code', 'nom', 'pc_lieu']);
        $theatres = \App\Models\Theatre::where('statut', 'actif')->orderBy('nom')->get(['id', 'code', 'nom', 'pc_lieu']);

        return Inertia::render('militaires/create', [
            'grades' => $grades,
            'certificats' => $certificats,
            'regions' => $regions,
            'unites' => $unites,
            'positions' => $positions,
            'organismes_detachement' => $organismes,
            'theatres' => $theatres,
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
            'region_id' => 'nullable|exists:regions,id',
            'unite_id' => [
                'nullable',
                Rule::exists('unites', 'id')->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->region_id)),
            ],
            'unite' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'date_mutation' => 'nullable|date',
            'fonction_passee' => 'nullable|string|max:255',
            'fonction_actuelle' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'sexe' => 'nullable|string|max:10',
            'groupe_sanguin' => 'nullable|string|max:10',
            'taille' => 'nullable|string|max:30',
            'status_sanitaire' => 'nullable|string',
            'est_blesse_guerre' => 'nullable',
            'est_non_mobilisable' => 'nullable',
            'engagement_ops' => 'nullable|string|max:255',
            'personne_a_contacter' => 'nullable|string|max:255',
            'telephone_personne_contacter' => 'nullable|string|max:20',
            'statut' => 'required|string',
            'organisme_detachement_id' => 'nullable|integer|exists:organismes_detachement,id',
            'a_permis_conduire' => 'nullable',
            'antecedent_judiciaire' => 'nullable',
            'details_antecedent_judiciaire' => 'nullable|string',
            'antecedent_disciplinaire' => 'nullable',
            'details_antecedent_disciplinaire' => 'nullable|string',
            'decorations' => 'nullable|string',
            'diplomes_academiques' => 'nullable|string',
            'observations' => 'nullable|string',
            'date_deces' => 'nullable|date',
            'circonstances_deces' => 'nullable|string',
            'motif_radiation' => 'nullable|string|max:255',
            'reference_decision' => 'nullable|string|max:255',
            'unite_transfert' => 'nullable|string|max:255',
            'motif_transfert' => 'nullable|string|max:255',
        ], [
            'unite_id.required_with' => 'L\'unité d\'affectation est obligatoire lorsqu\'une région est sélectionnée.',
            'unite_id.exists' => 'L\'unité sélectionnée n\'existe pas ou n\'appartient pas à la région choisie.',
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
        }, 'organismeDetachement']);

        $grmId = Effectif::idOrganismeGrm();
        $isDetache = (bool) (
            ($militaire->organisme_detachement_id && (int)$militaire->organisme_detachement_id !== $grmId) ||
            stripos((string)$militaire->position_actuelle, 'détach') !== false ||
            stripos((string)$militaire->position_actuelle, 'detach') !== false ||
            $militaire->statut === 'détaché' ||
            (int)$militaire->statut_code === 2 ||
            (int)$militaire->position_id === 26
        );

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

        if (!$isDetache && empty($affectationsList) && ($militaire->unite || $militaire->region || $militaire->date_mutation)) {
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
            'is_detache' => $isDetache,
            'organisme_detachement_id' => $militaire->organisme_detachement_id,
            'organisme_detachement' => $militaire->organismeDetachement?->nom ?? ($isDetache ? $militaire->unite : null),
            'organisme_detachement_code' => $militaire->organismeDetachement?->code,
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
            'details_antecedent_judiciaire' => $militaire->details_antecedent_judiciaire,
            'antecedent_disciplinaire' => $militaire->antecedent_disciplinaire,
            'details_antecedent_disciplinaire' => $militaire->details_antecedent_disciplinaire,
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
            return Grade::orderByDesc('ordre')->get()->map(fn ($grade) => [
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
        $organismes = \App\Models\OrganismeDetachement::where('est_actif', true)->where('code', '!=', 'GRM')->orderBy('ordre')->get(['id', 'code', 'nom']);
        $theatres = \App\Models\Theatre::where('statut', 'actif')->orderBy('nom')->get(['id', 'code', 'nom', 'pc_lieu']);

        $uniteNom = $militaire->unite;
        $uniteId = $militaire->unite_id;
        $isDetache = ($militaire->position_actuelle && (stripos($militaire->position_actuelle, 'détach') !== false || stripos($militaire->position_actuelle, 'detach') !== false))
            || $militaire->position_id === 26;
        if ($isDetache && $militaire->organisme_detachement_id) {
            $orgDetache = $organismes->firstWhere('id', $militaire->organisme_detachement_id);
            if ($orgDetache) {
                $uniteNom = $orgDetache->nom;
                $uniteId = null;
            }
        }

        return Inertia::render('militaires/edit', [
            'militaire' => [
                'id' => $militaire->id,
                'grade_id' => $militaire->grade_id,
                'region_id' => $militaire->region_id,
                'unite_id' => $uniteId,
                'position_id' => $militaire->position_id,
                'organisme_detachement_id' => $militaire->organisme_detachement_id,
                'statut_code' => $militaire->statut_code,
                'sexe_code' => $militaire->sexe_code,
                'theatre_id' => $militaire->theatre_id,
                'est_disparu' => $militaire->est_disparu,
                'est_blesse_guerre' => (bool) $militaire->est_blesse_guerre,
                'est_non_mobilisable' => (bool) $militaire->est_non_mobilisable,
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
                'unite_id' => $uniteId,
                'unite' => $uniteNom,
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
                'details_antecedent_judiciaire' => $militaire->details_antecedent_judiciaire,
                'antecedent_disciplinaire' => $militaire->antecedent_disciplinaire,
                'details_antecedent_disciplinaire' => $militaire->details_antecedent_disciplinaire,
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
            'organismes_detachement' => $organismes,
            'theatres' => $theatres,
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
            'region_id' => 'nullable|exists:regions,id',
            'unite_id' => [
                'nullable',
                Rule::exists('unites', 'id')->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->region_id)),
            ],
            'unite' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'date_mutation' => 'nullable|date',
            'fonction_passee' => 'nullable|string|max:255',
            'fonction_actuelle' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'sexe' => 'nullable|string|max:10',
            'groupe_sanguin' => 'nullable|string|max:10',
            'taille' => 'nullable|string|max:30',
            'status_sanitaire' => 'nullable|string',
            'est_blesse_guerre' => 'nullable',
            'est_non_mobilisable' => 'nullable',
            'engagement_ops' => 'nullable|string|max:255',
            'personne_a_contacter' => 'nullable|string|max:255',
            'telephone_personne_contacter' => 'nullable|string|max:20',
            'statut' => 'required|string',
            'organisme_detachement_id' => 'nullable|integer|exists:organismes_detachement,id',
            'a_permis_conduire' => 'nullable',
            'antecedent_judiciaire' => 'nullable',
            'details_antecedent_judiciaire' => 'nullable|string',
            'antecedent_disciplinaire' => 'nullable',
            'details_antecedent_disciplinaire' => 'nullable|string',
            'decorations' => 'nullable|string',
            'diplomes_academiques' => 'nullable|string',
            'observations' => 'nullable|string',
            'date_deces' => 'nullable|date',
            'circonstances_deces' => 'nullable|string',
            'motif_radiation' => 'nullable|string|max:255',
            'reference_decision' => 'nullable|string|max:255',
            'unite_transfert' => 'nullable|string|max:255',
            'motif_transfert' => 'nullable|string|max:255',
        ], [
            'unite_id.required_with' => 'L\'unité d\'affectation est obligatoire lorsqu\'une région est sélectionnée.',
            'unite_id.exists' => 'L\'unité sélectionnée n\'existe pas ou n\'appartient pas à la région choisie.',
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
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $search = $request->input('search');
        $grade = $request->input('grade');
        $statut = $request->input('statut');

        $search = ($search && $search !== 'null') ? $search : null;
        $grade = ($grade && $grade !== 'null') ? $grade : null;
        $statut = ($statut && $statut !== 'null') ? $statut : null;

        if (auth()->check()) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'Export',
                'description' => "Export Excel du personnel (statut=" . ($statut ?? 'tous') . ", grade=" . ($grade ?? 'tous') . ")",
                'ip_address' => request()->ip(),
            ]);
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

    /**
     * Export CSV en flux de la liste du personnel : adapté aux très gros effectifs (300 000 fiches).
     * Les lignes sont lues par lots de 2 000 (keyset sur l'id) puis écrites immédiatement : mémoire constante.
     * Colonnes à plat uniquement (sans certificats ni historique d'affectations) ; ordre : id croissant.
     * Pour l'export enrichi (styles, certificats, affectations) utiliser l'export Excel, de préférence filtré.
     */
    public function exportCsv(Request $request)
    {
        $clean = fn ($v) => ($v && $v !== 'null') ? $v : null;
        $search = $clean($request->input('search'));
        $grade = $clean($request->input('grade'));
        $statut = $clean($request->input('statut'));

        if (auth()->check()) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'Export',
                'description' => "Export CSV du personnel (statut=" . ($statut ?? 'tous') . ", grade=" . ($grade ?? 'tous') . ")",
                'ip_address' => $request->ip(),
            ]);
        }

        $query = Militaire::query();

        if ($search) {
            foreach (array_filter(explode(' ', mb_strtolower(trim(preg_replace('/\s+/', ' ', $search))))) as $term) {
                $term = addcslashes($term, '\\%_');
                $query->where('militaires.recherche', 'like', "%{$term}%"); // index trigramme
            }
        }

        if ($grade) {
            if (is_numeric($grade)) {
                $query->where('militaires.grade_id', (int) $grade);
            } else {
                $gradeId = Grade::where('nom_grade', $grade)->orWhere('code_grade', $grade)->value('id');
                $gradeId
                    ? $query->where('militaires.grade_id', $gradeId)
                    : $query->where('militaires.grade_actuel', $grade);
            }
        }

        if ($statut === 'retraites_n') {
            $this->appliquerFiltreRetraite($query, (int) Carbon::now()->year);
        } elseif ($statut === 'retraites_n1') {
            $this->appliquerFiltreRetraite($query, (int) Carbon::now()->year + 1);
        } elseif ($statut === 'actif') {
            $query->whereBetween('militaires.statut_code', [1, 4]);
        } elseif ($statut && $statut !== 'all') {
            $code = \App\Enums\StatutMilitaire::depuisTexte($statut)?->value;
            $code !== null
                ? $query->where('militaires.statut_code', $code)
                : $query->where('militaires.statut', $statut);
        }

        $colonnes = [
            'matricule' => 'Matricule (FAMA)',
            'mle_grm' => 'Matricule Gendarmerie (GRM)',
            'numero_nina' => 'N° NINA',
            'nom' => 'Nom',
            'prenom' => 'Prénom',
            'sexe' => 'Sexe',
            'date_naissance' => 'Date de naissance',
            'lieu_naissance' => 'Lieu de naissance',
            'telephone' => 'Téléphone',
            'grade_actuel' => 'Grade actuel',
            'categorie' => 'Catégorie',
            'date_entree_service' => 'Date entrée en service',
            'date_derniere_promotion' => 'Date dernière promotion',
            'specialite' => 'Spécialité',
            'statut' => 'Statut administratif',
            'position_actuelle' => 'Position administrative',
            'fonction_actuelle' => 'Fonction actuelle',
            'unite' => 'Unité',
            'region' => 'Région',
            'date_mutation' => 'Date dernière mutation',
            'status_sanitaire' => 'Statut sanitaire',
            'a_permis_conduire' => 'Permis de conduire',
            'antecedent_judiciaire' => 'Antécédent judiciaire',
            'antecedent_disciplinaire' => 'Antécédent disciplinaire',
            'date_deces' => 'Date décès',
            'motif_radiation' => 'Motif radiation',
        ];
        $dates = ['date_naissance', 'date_entree_service', 'date_derniere_promotion', 'date_mutation', 'date_deces'];
        $oui = ['a_permis_conduire', 'antecedent_judiciaire', 'antecedent_disciplinaire'];

        // Neutralise l'injection de formules (=, +, -, @) à l'ouverture dans Excel
        $protege = static function ($v) {
            $v = (string) $v;
            return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
        };

        return response()->streamDownload(function () use ($query, $colonnes, $dates, $oui, $protege) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : accents corrects dans Excel
            fputcsv($out, array_values($colonnes), ';');

            $query->select(array_map(fn ($c) => "militaires.{$c}", array_keys($colonnes)) + ['id' => 'militaires.id', 'statut_code' => 'militaires.statut_code'])
                ->chunkById(2000, function ($lot) use ($out, $colonnes, $dates, $oui, $protege) {
                    foreach ($lot as $m) {
                        $ligne = [];
                        foreach (array_keys($colonnes) as $c) {
                            $v = $m->getRawOriginal($c);
                            if ($c === 'statut') {
                                $rawS = mb_strtolower(trim((string)$v));
                                if (in_array($rawS, ['détaché', 'detache', 'en détachement', 'mission', 'en mission', 'stage', 'en stage / formation']) || in_array((int)$m->statut_code, [2, 3, 4])) {
                                    $v = 'actif';
                                }
                            } elseif (in_array($c, $dates, true)) {
                                $v = $v ? Carbon::parse($v)->format('d/m/Y') : '';
                            } elseif (in_array($c, $oui, true)) {
                                $v = $v ? 'Oui' : 'Non';
                            }
                            $ligne[] = $protege($v);
                        }
                        fputcsv($out, $ligne, ';');
                    }
                    fflush($out);
                }, 'militaires.id', 'id');

            fclose($out);
        }, 'militaires_' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            'specialite', 'origine_formation', 'statut', 'organisme_detachement_id',
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

        // Gestion de la photo d'identité (stockage privé sécurisé)
        if ($request->hasFile('photo')) {
            if ($militaire && $militaire->photo) {
                if (Storage::disk('local')->exists($militaire->photo)) {
                    Storage::disk('local')->delete($militaire->photo);
                }
                if (Storage::disk('public')->exists($militaire->photo)) {
                    Storage::disk('public')->delete($militaire->photo);
                }
            }
            $data['photo'] = $request->file('photo')->store('militaires/photos', 'local');
        } elseif ($request->boolean('supprimer_photo') && $militaire && $militaire->photo) {
            if (Storage::disk('local')->exists($militaire->photo)) {
                Storage::disk('local')->delete($militaire->photo);
            }
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
            'a_permis_conduire', 'antecedent_judiciaire', 'antecedent_disciplinaire',
            'est_blesse_guerre', 'est_non_mobilisable'
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

        // Règle stricte : Soit dans une région (territoriale), soit détaché (organisme extérieur hors région)
        $grmId = Effectif::idOrganismeGrm();
        $isDetache = (!empty($data['organisme_detachement_id']) && (int)$data['organisme_detachement_id'] !== $grmId)
            || (!empty($data['position_actuelle']) && (stripos($data['position_actuelle'], 'détach') !== false || stripos($data['position_actuelle'], 'detach') !== false))
            || (!empty($data['position_id']) && (int) $data['position_id'] === 26)
            || (!empty($data['statut']) && in_array(mb_strtolower(trim($data['statut'])), ['détaché', 'detache']))
            || (!empty($data['statut_code']) && (int) $data['statut_code'] === 2);

        if ($isDetache) {
            $data['unite_id'] = null;
            $data['unite'] = null;
            $data['region_id'] = null;
            $data['region'] = null;
            $data['theatre_id'] = null;
        } else {
            // Synchronisation du theatre_id avec les théâtres d'opérations officiels
            if ($request->has('theatre_id')) {
                $thVal = $request->input('theatre_id');
                $data['theatre_id'] = ($thVal && $thVal !== 'remove') ? (int) $thVal : null;
            } elseif (!empty($data['engagement_ops'])) {
                $th = \App\Models\Theatre::get()->first(function ($t) use ($data) {
                    return str_contains(mb_strtolower($data['engagement_ops']), mb_strtolower($t->nom))
                        || str_contains(mb_strtolower($data['engagement_ops']), mb_strtolower($t->code))
                        || str_contains(mb_strtolower($data['engagement_ops']), mb_strtolower($t->pc_lieu));
                });
                $data['theatre_id'] = $th ? $th->id : null;
            }
        }

        return $data;
    }

    /**
     * Synchronise les affectations multiples du militaire.
     */
    private function syncAffectations(Militaire $militaire, Request $request): void
    {
        $grmId = Effectif::idOrganismeGrm();
        $isDetache = (bool) (
            ($militaire->organisme_detachement_id && (int)$militaire->organisme_detachement_id !== $grmId) ||
            stripos((string)$militaire->position_actuelle, 'détach') !== false ||
            stripos((string)$militaire->position_actuelle, 'detach') !== false ||
            $militaire->statut === 'détaché' ||
            (int)$militaire->statut_code === 2 ||
            (int)$militaire->position_id === 26
        );

        if ($isDetache) {
            $militaire->affectations()->delete();
            return;
        }

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

        Militaire::incrementCacheVersion();
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
    public function verifierAlertes(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null)
    {
        $this->verifierPromotions($militaire, $enLot, $buffer, $now);
        $this->verifierFormations($militaire, $enLot, $buffer, $now);
        $this->verifierRetraite($militaire, $enLot, $buffer, $now);
        $this->verifierAlerteContrat($militaire, $enLot, $buffer, $now);
        $this->verifierAlerteMutation($militaire, $enLot, $buffer, $now);
    }

    /**
     * Vérifier et créer l'alerte mutation (durée d'affectation >= 3 ans)
     */
    private function verifierAlerteMutation(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        if (!$militaire->estEnActivite()) {
            if (!$enLot) {
                Alerte::where('militaire_id', $militaire->id)->where('type_alerte', 'mutation')->delete();
            }
            return;
        }

        // Récupérer l'affectation ayant la date de mutation la plus récente
        $plusRecenteAff = $militaire->relationLoaded('affectations')
            ? $militaire->affectations->whereNotNull('date_mutation')->sortByDesc('date_mutation')->first()
            : $militaire->affectations()->whereNotNull('date_mutation')->orderBy('date_mutation', 'desc')->first();

        $dateMutation = $plusRecenteAff?->date_mutation 
            ?? $militaire->date_mutation 
            ?? $militaire->date_entree_service;

        if (!$dateMutation) {
            return;
        }

        // Synchroniser le militaire si nécessaire avec sa plus récente affectation (hors mode lot pour éviter tempêtes d'événements)
        if (!$enLot && $plusRecenteAff && ($militaire->date_mutation != $plusRecenteAff->date_mutation || $militaire->unite != $plusRecenteAff->unite)) {
            $militaire->update([
                'unite' => $plusRecenteAff->unite,
                'region' => $plusRecenteAff->region,
                'date_mutation' => $plusRecenteAff->date_mutation,
            ]);
        }

        $anneesMutation = floor(Carbon::parse($dateMutation)->diffInYears(now()));

        if ($anneesMutation < 3) {
            if (!$enLot) {
                Alerte::where('militaire_id', $militaire->id)
                    ->where('type_alerte', 'mutation')
                    ->delete();
            }
            return;
        }

        $unite = $plusRecenteAff?->unite ?? $militaire->unite ?? 'Unité non précisée';
        $region = $plusRecenteAff?->region ?? $militaire->region ?? '-';
        $dateMutFormatted = Carbon::parse($dateMutation)->format('d/m/Y');
        $message = "Alerte Mutation : {$militaire->grade_actuel} {$militaire->prenom} {$militaire->nom} en poste depuis {$anneesMutation} ans à {$unite} ({$region}) (dernière mutation le {$dateMutFormatted})";

        $this->creerAlerte($militaire, 'mutation', $message, now()->endOfMonth(), $enLot, $buffer, $now);
    }

    /**
     * Vérifier et créer l'alerte contrat pour un militaire
     */
    private function verifierAlerteContrat(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        if (!$militaire->estEnActivite()) {
            if (!$enLot) {
                Alerte::where('militaire_id', $militaire->id)->where('type_alerte', 'contrat')->delete();
            }
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

        $currentNow = $now ?? now();

        // Si le militaire a un contrat actif dont la fin est dans plus de 6 mois, pas d'alerte de renouvellement
        $contratActif = $militaire->relationLoaded('contratActif') && $militaire->contratActif
            ? $militaire->contratActif
            : ($militaire->relationLoaded('contrats')
                ? $militaire->contrats->filter(fn($c) => trim(strtolower($c->statut ?? '')) === 'actif')->sortByDesc('date_debut')->first()
                : $militaire->contrats()->where('statut', 'actif')->latest('date_debut')->first());

        if ($contratActif && $contratActif->date_fin) {
            $dateFin = $contratActif->date_fin instanceof Carbon ? $contratActif->date_fin : Carbon::parse($contratActif->date_fin);
            if ($dateFin->isFuture() && $dateFin->diffInDays($currentNow) > 180) {
                if (!$enLot) {
                    Alerte::where('militaire_id', $militaire->id)
                        ->where('type_alerte', 'contrat')
                        ->delete();
                }
                return;
            }
        }

        // Calcul des années de service si date d'entrée présente
        $dateEntree = $militaire->date_entree_service ? ($militaire->date_entree_service instanceof Carbon ? $militaire->date_entree_service : Carbon::parse($militaire->date_entree_service)) : null;
        $serviceYears = $dateEntree 
            ? (int) floor($dateEntree->diffInYears($currentNow))
            : null;

        // Si la date d'entrée est renseignée mais le militaire a moins de 5 ans de service et pas de contrat actif à renouveler
        if ($serviceYears !== null && $serviceYears < 5 && !$contratActif) {
            if (!$enLot) {
                Alerte::where('militaire_id', $militaire->id)
                    ->where('type_alerte', 'contrat')
                    ->delete();
            }
            return;
        }

        if (!$enLot) {
            Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'contrat')
                ->delete();
        }

        $message = ($serviceYears !== null && $serviceYears > 0)
            ? "Renouvellement de contrat requis pour {$militaire->prenom} {$militaire->nom} ({$serviceYears} ans de service) - Grade: {$militaire->grade_actuel}"
            : "Régularisation / Renouvellement de contrat requis pour {$militaire->prenom} {$militaire->nom} (sans contrat actif) - Grade: {$militaire->grade_actuel}";

        $this->creerAlerte($militaire, 'contrat', $message, $contratActif?->date_fin ?? $currentNow->copy()->addMonths(6), $enLot, $buffer, $currentNow);
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
    private function verifierPromotions(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        if (!$militaire->estEnActivite()) return;

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
        $certificatsObtenus = $militaire->relationLoaded('certificats')
            ? $militaire->certificats->pluck('niveau_certificat')->toArray()
            : $militaire->certificats()->pluck('niveau_certificat')->toArray();

        // Suppression en lot uniquement des alertes de promotion non lues pour préserver celles déjà consultées
        if (!$enLot) {
            Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'promotion')
                ->where('est_vue', false)
                ->delete();
        }

        $dateProposition = $this->getDateProposition();

        // Créer les alertes de promotion
        if ($grade == 'Soldat 1' && in_array('CAT1', $certificatsObtenus) && $conditionsBase && $militaire->anciennete >= 5) {
            $dateAnciennete = Carbon::parse($militaire->date_entree_service)->addYears(5);
            $this->creerAlertePromotion($militaire, 'Caporal', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && in_array('CAT2', $certificatsObtenus) && $conditionsBase) {
            $certifCAT1 = $militaire->relationLoaded('certificats')
                ? $militaire->certificats->where('niveau_certificat', 'CAT1')->first()
                : $militaire->certificats()->where('niveau_certificat', 'CAT1')->first();
            $dateAnciennete = null;
            if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                $dateAnciennete = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
            }
            $this->creerAlertePromotion($militaire, 'Sergent', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        $calcDateAnciennete = function ($annees) use ($militaire) {
            $ref = $militaire->date_derniere_promotion ?? $militaire->date_grade_actuel ?? $militaire->date_entree_service;
            return $ref ? Carbon::parse($ref)->addYears($annees) : null;
        };

        if ($grade == 'Caporal' && in_array('CAT1', $certificatsObtenus) && !in_array('CAT2', $certificatsObtenus) && $age >= 47 && $ancienneteGrade >= 3 && $conditionsBase) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Caporal-chef', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Sergent' && $conditionsBase && $ancienneteGrade >= 2 && $militaire->anciennete >= 5) {
            $dateAnciennete = $calcDateAnciennete(2);
            $this->creerAlertePromotion($militaire, 'Sergent-Chef', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Sergent-Chef' && $conditionsBase && $ancienneteGrade >= 3) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Adjudant', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Adjudant' && $conditionsBase && $ancienneteGrade >= 2) {
            $dateAnciennete = $calcDateAnciennete(2);
            $this->creerAlertePromotion($militaire, 'Adjudant-Chef', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Sous-lieutenant' && $ancienneteGrade >= 2) {
            $dateAnciennete = $calcDateAnciennete(2);
            $this->creerAlertePromotion($militaire, 'Lieutenant', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Lieutenant' && $ancienneteGrade >= 3) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Capitaine', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Capitaine' && $ancienneteGrade >= 3) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Commandant', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Commandant' && $ancienneteGrade >= 3) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Lieutenant-colonel', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Lieutenant-colonel' && $ancienneteGrade >= 3) {
            $dateAnciennete = $calcDateAnciennete(3);
            $this->creerAlertePromotion($militaire, 'Colonel', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }

        if ($grade == 'Colonel' && $ancienneteGrade >= 6) {
            $dateAnciennete = $calcDateAnciennete(6);
            $this->creerAlertePromotion($militaire, 'Colonel-Major', $dateProposition, $dateAnciennete, $enLot, $buffer, $now);
        }
    }

    /**
     * Vérifie les formations et crée les alertes nécessaires (préserve les alertes déjà vues)
     */
    private function verifierFormations(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        if (!$militaire->estEnActivite()) return;

        $grade = $militaire->grade_actuel;
        $age = $militaire->age;
        $anciennete = $militaire->anciennete;
        $ancienneteGrade = $militaire->ancienneteGrade;
        $certificatsObtenus = $militaire->relationLoaded('certificats')
            ? $militaire->certificats->pluck('niveau_certificat')->toArray()
            : $militaire->certificats()->pluck('niveau_certificat')->toArray();
        $conditionsBase = !$militaire->antecedent_judiciaire && !$militaire->antecedent_disciplinaire;
        $dateProposition = $this->getDateProposition();

        // Suppression en lot uniquement des alertes de formation non lues
        if (!$enLot) {
            Alerte::where('militaire_id', $militaire->id)
                ->where('type_alerte', 'formation')
                ->where('est_vue', false)
                ->delete();
        }

        // 1. APLI
        if (!in_array('APLI', $certificatsObtenus)) {
            if (in_array($grade, ['Sous-lieutenant', 'Lieutenant', 'Capitaine']) && !in_array('CFCU', $certificatsObtenus) && $age <= 50) {
                $this->creerAlerteFormation($militaire, 'APLI', "Cour d'Application", $dateProposition, null, $enLot, $buffer, $now);
            }
        }

        // 2. CFCU
        if (!in_array('CFCU', $certificatsObtenus)) {
            if (in_array($grade, ['Lieutenant', 'Capitaine'])) {
                if ($grade == 'Capitaine' || in_array('APLI', $certificatsObtenus)) {
                    $this->creerAlerteFormation($militaire, 'CFCU', "Cour des Futurs Commandants d'Unité", $dateProposition, null, $enLot, $buffer, $now);
                }
            }
        }

        // 3. CAT1
        if (!in_array('CAT1', $certificatsObtenus)) {
            if (in_array($grade, ['Soldat 2', 'Soldat 1']) && $ancienneteGrade >= 5 && $conditionsBase) {
                $dateConditions = Carbon::parse($militaire->date_entree_service)->addYears(5);
                $this->creerAlerteFormation($militaire, 'CAT1', "Certificat d'Aptitude Technique Niveau 1", $dateProposition, $dateConditions, $enLot, $buffer, $now);
            }
        }

        // 4. CAT2
        if (!in_array('CAT2', $certificatsObtenus)) {
            if ($grade == 'Caporal' && $age < 47 && $ancienneteGrade >= 3 && $conditionsBase && in_array('CAT1', $certificatsObtenus)) {
                $certifCAT1 = $militaire->relationLoaded('certificats')
                    ? $militaire->certificats->where('niveau_certificat', 'CAT1')->first()
                    : $militaire->certificats()->where('niveau_certificat', 'CAT1')->first();
                $dateConditions = null;
                if ($certifCAT1 && $certifCAT1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCAT1->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'CAT2', "Certificat d'Aptitude Technique Niveau 2", $dateProposition, $dateConditions, $enLot, $buffer, $now);
            }
        }

        // 5. CIA
        if (!in_array('CIA', $certificatsObtenus)) {
            if (in_array($grade, ['Sergent', 'Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && $conditionsBase && $militaire->a_permis_conduire) {
                $this->creerAlerteFormation($militaire, 'CIA', "Certificat Interarmes (CIA)", $dateProposition, null, $enLot, $buffer, $now);
            }
        }

        // 6. BA1
        if (!in_array('BA1', $certificatsObtenus)) {
            if (in_array($grade, ['Sergent-Chef', 'Adjudant', 'Adjudant-Chef']) && in_array('CIA', $certificatsObtenus) && $conditionsBase && $anciennete >= 8) {
                $certifCIA = $militaire->relationLoaded('certificats')
                    ? $militaire->certificats->where('niveau_certificat', 'CIA')->first()
                    : $militaire->certificats()->where('niveau_certificat', 'CIA')->first();
                $dateConditions = null;
                if ($certifCIA && $certifCIA->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifCIA->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'BA1', "Brevet d'Arme N1", $dateProposition, $dateConditions, $enLot, $buffer, $now);
            }
        }

        // 7. BA2
        if (!in_array('BA2', $certificatsObtenus)) {
            if (in_array($grade, ['Adjudant', 'Adjudant-Chef']) && in_array('BA1', $certificatsObtenus) && $conditionsBase) {
                $certifBA1 = $militaire->relationLoaded('certificats')
                    ? $militaire->certificats->where('niveau_certificat', 'BA1')->first()
                    : $militaire->certificats()->where('niveau_certificat', 'BA1')->first();
                $dateConditions = null;
                if ($certifBA1 && $certifBA1->pivot->date_obtention) {
                    $dateConditions = Carbon::parse($certifBA1->pivot->date_obtention)->addYears(3);
                }
                $this->creerAlerteFormation($militaire, 'BA2', "Brevet d'Arme N2", $dateProposition, $dateConditions, $enLot, $buffer, $now);
            }
        }

        // 8. CEM
        if (!in_array('CEM', $certificatsObtenus)) {
            if (in_array($grade, ['Capitaine', 'Commandant'])) {
                if (($grade === 'Capitaine' && $ancienneteGrade >= 3) || $grade === 'Commandant') {
                    if ($age <= 45) {
                        $this->creerAlerteFormation($militaire, 'CEM', "Cour d'État-Major", $dateProposition, null, $enLot, $buffer, $now);
                    }
                }
            }
        }

        // 9. CERT_EM
        if (!in_array('CERT_EM', $certificatsObtenus)) {
            if ($grade == 'Commandant' && $age > 45) {
                $this->creerAlerteFormation($militaire, 'CERT_EM', "Certificat d'État-Major", $dateProposition, null, $enLot, $buffer, $now);
            }
        }

        // 10. ECOLE_GUERRE
        if (!in_array('ECOLE_GUERRE', $certificatsObtenus)) {
            if (in_array($grade, ['Lieutenant-colonel', 'Colonel', 'Colonel-Major']) && $ancienneteGrade >= 2 && $age <= 53) {
                $this->creerAlerteFormation($militaire, 'ECOLE_GUERRE', "École de Guerre", $dateProposition, null, $enLot, $buffer, $now);
            }
        }
    }

    private function creerAlertePromotion(Militaire $militaire, string $gradeCible, $dateProposition, $dateAnciennete, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        $anneeProposition = $dateProposition->format('Y');
        $dateAncienneteTexte = $dateAnciennete ? $dateAnciennete->format('d/m/Y') : 'conditions remplies';
        $message = "Proposable pour {$anneeProposition} (ancienneté atteinte le {$dateAncienneteTexte}) - Promotion à {$gradeCible}";
        $this->creerAlerte($militaire, 'promotion', $message, $dateProposition, $enLot, $buffer, $now);
    }

    private function creerAlerteFormation(Militaire $militaire, string $formation, string $nomFormation, $dateProposition, $dateConditions, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        $anneeProposition = $dateProposition->format('Y');
        $dateConditionsTexte = $dateConditions ? $dateConditions->format('d/m/Y') : 'conditions remplies';
        $message = "Proposable pour {$anneeProposition} (conditions remplies le {$dateConditionsTexte}) - Formation {$nomFormation}";
        $this->creerAlerte($militaire, 'formation', $message, $dateProposition, $enLot, $buffer, $now);
    }

    private function verifierRetraite(Militaire $militaire, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        $dateRetraite = $militaire->calculerDateRetraite();

        if ($dateRetraite) {
            $diffJours = Carbon::now()->startOfDay()->diffInDays($dateRetraite);
            $moisRestants = floor($diffJours / 30);

            if ($moisRestants <= 12 && $diffJours >= 0) {
                $message = "Retraite dans {$moisRestants} mois (le " . $dateRetraite->format('d/m/Y') . ")";
                $this->creerAlerte($militaire, 'retraite', $message, $dateRetraite, $enLot, $buffer, $now);
            }
        }
    }

    private function creerAlerte(Militaire $militaire, string $type, string $message, $dateEcheance = null, bool $enLot = false, ?array &$buffer = null, $now = null): void
    {
        $echeance = $dateEcheance ?? Carbon::now()->addDays(2);

        if ($buffer !== null) {
            $buffer[] = [
                'militaire_id'  => $militaire->id,
                'type_alerte'   => $type,
                'message'       => $message,
                'date_echeance' => $echeance,
                'est_vue'       => false,
                'created_at'    => $now ?? now(),
                'updated_at'    => $now ?? now(),
            ];
            return;
        }

        if ($enLot) {
            Alerte::create([
                'militaire_id'  => $militaire->id,
                'type_alerte'   => $type,
                'message'       => $message,
                'date_echeance' => $echeance,
                'est_vue'       => false,
            ]);
            return;
        }

        $alerte = Alerte::where('militaire_id', $militaire->id)
            ->where('type_alerte', $type)
            ->where('message', $message)
            ->first();

        if ($alerte) {
            if ($alerte->est_vue) {
                $alerte->update(['est_vue' => false]);
            }
        } else {
            Alerte::create([
                'militaire_id'  => $militaire->id,
                'type_alerte'   => $type,
                'message'       => $message,
                'date_echeance' => $echeance,
                'est_vue'       => false,
            ]);
        }
    }

    /**
     * Applique directement le filtre des départs en retraite en SQL pur (PostgreSQL).
     */
    private function appliquerFiltreRetraite($query, int $anneeCible): void
    {
        $anneeCourante = (int) Carbon::now()->year;

        $query->join('grades as g_retraite', 'militaires.grade_id', '=', 'g_retraite.id')
              ->whereNotNull('militaires.date_naissance')
              ->whereNotNull('g_retraite.retraite_obligatoire')
              ->where(function ($q) {
                  $q->whereNull('militaires.position_actuelle')
                    ->orWhere('militaires.position_actuelle', 'not ilike', '%otage%');
              })
              ->where(function ($q) {
                  $q->whereNull('militaires.observations')
                    ->orWhere('militaires.observations', 'not ilike', '%otage%');
              })
              ->where('militaires.est_disparu', 0);

        $query->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + g_retraite.retraite_obligatoire = {$anneeCible})");
    }

    /**
     * Retourne la liste des IDs des militaires partant à la retraite pour une année cible.
     */
    private function getIdsRetraitePourAnnee(int $anneeCible): array
    {
        $v = Militaire::getCacheVersion();
        return Cache::remember("v{$v}:militaires:ids_retraite_{$anneeCible}", 1800, function () use ($anneeCible) {
            return DB::table('militaires')
                ->join('grades', 'militaires.grade_id', '=', 'grades.id')
                ->whereNotNull('militaires.date_naissance')
                ->whereNotNull('grades.retraite_obligatoire')
                ->where(function ($q) {
                    $q->whereNull('militaires.position_actuelle')
                      ->orWhere('militaires.position_actuelle', 'not ilike', '%otage%');
                })
                ->where(function ($q) {
                    $q->whereNull('militaires.observations')
                      ->orWhere('militaires.observations', 'not ilike', '%otage%');
                })
                ->where('militaires.est_disparu', 0)
                ->whereRaw("(EXTRACT(YEAR FROM militaires.date_naissance) + grades.retraite_obligatoire = {$anneeCible})")
                ->pluck('militaires.id')
                ->toArray();
        });
    }
}
