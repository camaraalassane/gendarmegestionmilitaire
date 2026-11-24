<?php

namespace App\Http\Controllers;

use App\Models\OrganismeDetachement;
use App\Models\RegistreOperationnel;
use App\Models\Grade;
use App\Models\Region;
use App\Models\Unite;
use App\Models\Militaire;
use App\Exports\SituationPriseArmesExport;
use App\Services\SituationPriseArmeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PriseArmeController extends Controller
{
    /**
     * Affiche la page de Situation de Prise d'Armes & Registres Opérationnels.
     * 100% calculé dynamiquement en temps réel depuis la base de données.
     */
    public function index(Request $request, SituationPriseArmeService $spaService)
    {
        $v = Militaire::getCacheVersion();

        // 1. Tables de référence en cache longue durée
        $organismes = Cache::remember('ref:organismes_detachement', 86400, fn() => OrganismeDetachement::orderBy('ordre')->get());
        $regions = Cache::remember('ref:regions_spa', 86400, fn() => Region::orderBy('ordre')->get());
        $grades = Cache::remember('ref:grades_desc', 86400, fn() => Grade::orderByDesc('ordre')->get());
        $unites = Cache::remember('ref:unites_spa', 86400, fn() => Unite::orderBy('nom')->get(['id', 'nom', 'code', 'region_id']));

        // 2. Calculs dynamiques de situation SPA (mis en cache rapide par version d'effectifs)
        // Calcul exécuté une seule fois, et seulement quand une propriété différée est demandée
        // (la page s'affiche donc avant le calcul, et non après).
        $spaData = null;
        $chargerSpa = function () use (&$spaData, $v, $spaService, $regions) {
            return $spaData ??= Cache::remember("v{$v}:spa:situation_complete", 3600, function() use ($spaService, $regions) {
                $stats = $spaService->calculerStatsGlobales();
                $matrice = $spaService->calculerMatriceDynamique();
                $regionsSituation = $spaService->calculerRegionsDynamique();
                $organismesCards = $spaService->calculerOrganismesCardsDynamique();

                foreach ($regions as $r) {
                    if (!isset($regionsSituation[$r->code])) {
                        $regionsSituation[$r->code] = [
                            'id' => $r->id, 'nom' => $r->nom, 'code' => $r->code,
                            'total' => 0, 'total_h' => 0, 'total_d' => 0, 'grades' => [],
                            'GAR' => 0, 'GCA' => 0, 'GDD' => 0, 'GDB' => 0,
                            'CLM' => 0, 'COL' => 0, 'LCL' => 0, 'CES' => 0,
                            'CNE' => 0, 'LTN' => 0, 'SLT' => 0, 'ASP' => 0,
                            'EOA' => 0, 'MJR' => 0, 'ADC' => 0, 'ADJ' => 0,
                            'MDC' => 0, 'MDL' => 0, 'ESOA' => 0, 'MRG' => 0,
                        ];
                    }
                }

                return compact('stats', 'matrice', 'regionsSituation', 'organismesCards');
            });
        };

        // 3. Registres opérationnels : requête aplatie ultra-légère (divise la charge payload par 4)
        $hasFilter = $request->filled('type_registre_code') || $request->filled('region_id')
            || $request->filled('grade_id') || $request->filled('annee_evenement')
            || $request->filled('type_circonstance_code') || $request->filled('search');

        $isRegistreTab = $request->filled('tab') && !in_array($request->input('tab'), ['spa', 'organismes']);

        // Sur l'onglet SPA par défaut, chargement à la demande (réduit le transfert de 1.2 Mo à 340 Ko et accélère l'affichage)
        $registres = ($hasFilter || $isRegistreTab)
            ? ($hasFilter ? $this->buildRegistresFlat($request) : Cache::remember("v{$v}:prise_armes:registres_flat", 3600, fn() => $this->buildRegistresFlat($request)))
            : [];

        return Inertia::render('PriseArmes/Index', [
            'stats' => Inertia::defer(fn() => $chargerSpa()['stats']),
            'organismes' => $organismes,
            'regions' => $regions,
            'grades' => $grades,
            'unites' => $unites,
            'matrice' => Inertia::defer(fn() => $chargerSpa()['matrice']),
            'regionsSituation' => Inertia::defer(fn() => $chargerSpa()['regionsSituation']),
            'organismesCards' => Inertia::defer(fn() => $chargerSpa()['organismesCards']),
            'registres' => $registres,
            'militairesLookup' => [],
            'filters' => $request->only([
                'type_registre_code',
                'region_id',
                'grade_id',
                'annee_evenement',
                'type_circonstance_code',
                'search',
            ]),
        ]);
    }

    /**
     * Endpoint API JSON : renvoie la liste aplatie des registres opérationnels (chargement à la demande).
     * GET /prise-armes/registres-data
     */
    public function registresData(Request $request)
    {
        $v = Cache::get('effectifs:ver', 1);
        $hasFilter = $request->filled('type_registre_code') || $request->filled('region_id')
            || $request->filled('grade_id') || $request->filled('annee_evenement')
            || $request->filled('type_circonstance_code') || $request->filled('search');

        $registres = $hasFilter
            ? $this->buildRegistresFlat($request)
            : Cache::remember("v{$v}:prise_armes:registres_flat", 3600, fn() => $this->buildRegistresFlat($request));

        return response()->json($registres);
    }

    /**
     * Construction de la requête aplatie ultra-légère des registres opérationnels.
     */
    public function buildRegistresFlat(Request $request)
    {
        $query = DB::table('registres_operationnels as ro')
            ->leftJoin('grades as g', 'g.id', '=', 'ro.grade_id')
            ->leftJoin('regions as r', 'r.id', '=', 'ro.region_id')
            ->leftJoin('unites as u', 'u.id', '=', 'ro.unite_id')
            ->leftJoin('regions as ur', 'ur.id', '=', 'u.region_id')
            ->select([
                'ro.id',
                'ro.numero_ordre',
                'ro.type_registre_code',
                'ro.militaire_id',
                'ro.grade_id',
                'ro.grade_texte',
                'g.nom_grade as grade_nom',
                'g.code_grade as grade_code',
                'ro.nom',
                'ro.prenom',
                'ro.matricule',
                'ro.region_id',
                'r.code as region_code',
                'r.nom as region_nom',
                'ro.unite_id',
                'ro.unite_texte',
                'u.nom as unite_nom',
                'ur.code as unite_region_code',
                'ur.nom as unite_region_nom',
                'ro.nature_pathologie',
                'ro.circonstances',
                'ro.type_circonstance_code',
                'ro.date_evenement',
                'ro.annee_evenement',
            ])
            ->orderBy('ro.type_registre_code')
            ->orderBy('ro.numero_ordre')
            ->orderBy('ro.id');

        if ($request->filled('type_registre_code') && is_numeric($request->type_registre_code)) {
            $query->where('ro.type_registre_code', (int) $request->type_registre_code);
        }
        if ($request->filled('region_id') && is_numeric($request->region_id)) {
            $query->where('ro.region_id', (int) $request->region_id);
        }
        if ($request->filled('grade_id') && is_numeric($request->grade_id)) {
            $query->where('ro.grade_id', (int) $request->grade_id);
        }
        if ($request->filled('annee_evenement') && is_numeric($request->annee_evenement)) {
            $query->where('ro.annee_evenement', (int) $request->annee_evenement);
        }
        if ($request->filled('type_circonstance_code') && is_numeric($request->type_circonstance_code)) {
            $query->where('ro.type_circonstance_code', (int) $request->type_circonstance_code);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('ro.matricule', 'like', "{$s}%")
                  ->orWhere('ro.nom', 'ilike', "%{$s}%")
                  ->orWhere('ro.prenom', 'ilike', "%{$s}%");
            });
        }

        return $query->get()->map(function($row) {
            $reg = null;
            if ($row->region_code || $row->region_nom) {
                $reg = ['code' => $row->region_code, 'nom' => $row->region_nom];
            } elseif ($row->unite_region_code || $row->unite_region_nom) {
                $reg = ['code' => $row->unite_region_code, 'nom' => $row->unite_region_nom];
            }
            return [
                'id' => $row->id,
                'numero_ordre' => $row->numero_ordre,
                'type_registre_code' => (int) $row->type_registre_code,
                'militaire_id' => $row->militaire_id,
                'grade_id' => $row->grade_id,
                'grade_texte' => $row->grade_texte ?: ($row->grade_nom ?: ($row->grade_code ?: '-')),
                'nom' => $row->nom,
                'prenom' => $row->prenom,
                'matricule' => $row->matricule,
                'region_id' => $row->region_id,
                'region' => $reg,
                'unite_id' => $row->unite_id,
                'unite_texte' => $row->unite_texte ?: $row->unite_nom,
                'unite' => $row->unite_nom ? ['nom' => $row->unite_nom, 'region' => $reg] : null,
                'nature_pathologie' => $row->nature_pathologie,
                'circonstances' => $row->circonstances,
                'type_circonstance_code' => $row->type_circonstance_code,
                'date_evenement' => $row->date_evenement ? substr((string)$row->date_evenement, 0, 10) : null,
                'annee_evenement' => $row->annee_evenement,
            ];
        })->all();
    }

    /**
     * Recherche dynamique de militaire pour auto-complétion du formulaire des registres (SPA).
     */
    public function lookupMilitaire(Request $request)
    {
        $search = trim($request->get('matricule') ?? $request->get('search') ?? '');
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $results = Militaire::whereNull('deleted_at')
            ->where(function ($q) use ($search) {
                $q->where('matricule', 'ilike', "{$search}%")
                  ->orWhere('nom', 'ilike', "%{$search}%")
                  ->orWhere('prenom', 'ilike', "%{$search}%");
            })
            ->limit(15)
            ->get(['id', 'matricule', 'nom', 'prenom', 'grade_id', 'grade_actuel', 'region_id', 'unite_id', 'unite']);

        return response()->json($results);
    }

    /**
     * Renvoie la liste nominale des militaires d'un grade donné pour une région ou un organisme (modale SPA).
     * Permet le filtrage Hommes / Dames et la recherche instantanée.
     */
    public function militairesGrade(Request $request)
    {
        $validated = $request->validate([
            'grade_col' => 'required|string',
            'region_id' => 'nullable|integer',
            'organisme_id' => 'nullable|integer',
            'sexe' => 'nullable|string|in:H,D',
            'search' => 'nullable|string',
        ]);

        $gradeCol = strtoupper(trim($validated['grade_col']));
        $gradeIds = SituationPriseArmeService::getGradeIdsForCol($gradeCol);

        $query = Militaire::whereIn('statut_code', [1, 2, 3, 4])
            ->whereNull('deleted_at')
            ->whereIn('grade_id', $gradeIds)
            ->with(['grade', 'region', 'unite']);

        $perimetreNom = 'Tous les effectifs';

        if (!empty($validated['region_id'])) {
            $reg = Region::find($validated['region_id']);
            if ($reg) {
                $query->where('region_id', $reg->id);
                $perimetreNom = "{$reg->nom} ({$reg->code})";
            }
        } elseif (!empty($validated['organisme_id'])) {
            $org = OrganismeDetachement::find($validated['organisme_id']);
            if ($org) {
                $grmId = OrganismeDetachement::where('code', 'GRM')->value('id') ?? 1;
                if ($org->id === $grmId) {
                    $query->where(function ($sub) use ($grmId) {
                        $sub->whereNull('organisme_detachement_id')->orWhere('organisme_detachement_id', $grmId);
                    });
                } else {
                    $query->where('organisme_detachement_id', $org->id);
                }
                $perimetreNom = "{$org->nom} ({$org->code})";
            }
        }

        $allMilitaires = (clone $query)->orderBy('nom')->orderBy('prenom')->get();
        $totalTous = $allMilitaires->count();
        $totalHommes = $allMilitaires->where(fn($m) => $m->sexe_code !== 2)->count();
        $totalDames = $allMilitaires->where(fn($m) => $m->sexe_code === 2)->count();

        $filtered = $allMilitaires;
        if (!empty($validated['sexe'])) {
            if ($validated['sexe'] === 'D') {
                $filtered = $filtered->where(fn($m) => $m->sexe_code === 2);
            } elseif ($validated['sexe'] === 'H') {
                $filtered = $filtered->where(fn($m) => $m->sexe_code !== 2);
            }
        }

        if (!empty($validated['search'])) {
            $s = mb_strtolower(trim($validated['search']));
            $filtered = $filtered->filter(function ($m) use ($s) {
                return str_contains(mb_strtolower($m->nom ?? ''), $s)
                    || str_contains(mb_strtolower($m->prenom ?? ''), $s)
                    || str_contains(mb_strtolower($m->matricule ?? ''), $s)
                    || str_contains(mb_strtolower($m->mle_grm ?? ''), $s);
            });
        }

        $list = $filtered->values()->map(function ($m) {
            $isDame = ($m->sexe_code === 2);
            return [
                'id' => $m->id,
                'matricule' => $m->matricule,
                'mle_grm' => $m->mle_grm,
                'nom' => $m->nom,
                'prenom' => $m->prenom,
                'nom_complet' => $m->nom . ' ' . $m->prenom,
                'grade' => $m->grade?->nom_grade ?? $m->grade_actuel ?? '-',
                'grade_code' => $m->grade?->code_grade ?? '',
                'sexe' => $isDame ? 'D' : 'H',
                'sexe_libelle' => $isDame ? 'Féminin' : 'Masculin',
                'unite_nom' => $m->unite?->nom ?? (is_string($m->unite) ? $m->unite : '-'),
                'region_nom' => $m->region?->nom ?? '-',
                'position' => $m->position_actuelle ?: ($m->statut ?: 'En activité'),
            ];
        });

        $gradeNom = SituationPriseArmeService::getGradeLibelleForCol($gradeCol);

        return response()->json([
            'grade_col' => $gradeCol,
            'grade_nom' => $gradeNom,
            'perimetre' => $perimetreNom,
            'total' => $totalTous,
            'total_h' => $totalHommes,
            'total_d' => $totalDames,
            'count_filtered' => $list->count(),
            'militaires' => $list,
        ]);
    }

    // ==========================================
    // GESTION CRUD REGISTRES OPÉRATIONNELS
    // ==========================================

    public function storeRegistre(Request $request)
    {
        $validated = $request->validate([
            'type_registre_code' => 'required|integer|between:1,5',
            'numero_ordre' => 'nullable|integer',
            'matricule' => 'nullable|string|max:50',
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'grade_id' => 'nullable|integer|exists:grades,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'unite_id' => 'nullable|integer|exists:unites,id',
            'unite_texte' => 'nullable|string|max:255',
            'date_evenement' => 'nullable|date',
            'annee_evenement' => 'nullable|integer',
            'type_circonstance_code' => 'nullable|integer',
            'circonstances' => 'nullable|string',
            'nature_pathologie' => 'nullable|string',
            'decision_aptitude' => 'nullable|string',
            'observations' => 'nullable|string',
            'est_actif_fichier' => 'nullable|integer',
        ]);

        if (!empty($validated['date_evenement']) && empty($validated['annee_evenement'])) {
            $validated['annee_evenement'] = (int) date('Y', strtotime($validated['date_evenement']));
        }
        if (!empty($validated['grade_id'])) {
            $grade = Grade::find($validated['grade_id']);
            if ($grade) {
                $validated['grade_texte'] = $grade->nom_grade;
                $validated['grade_ordre'] = (int) $grade->ordre;
            }
        }
        if (empty($validated['unite_id']) && !empty($validated['unite_texte'])) {
            $uniteFound = Unite::whereRaw('LOWER(nom) = ?', [mb_strtolower(trim($validated['unite_texte']))])->first();
            if ($uniteFound) {
                $validated['unite_id'] = $uniteFound->id;
                if (empty($validated['region_id']) && $uniteFound->region_id) {
                    $validated['region_id'] = $uniteFound->region_id;
                }
            }
        }

        $item = RegistreOperationnel::create($validated);

        // Synchronisation automatique bidirectionnelle avec la fiche du militaire
        $militaire = null;
        if (!empty($item->matricule)) {
            $militaire = Militaire::where('matricule', trim($item->matricule))->first();
        }
        if ($militaire) {
            $item->militaire_id = $militaire->id;
            $item->saveQuietly();
            $this->synchroniserMilitaireDepuisRegistre($militaire, $item);
        } else if (!empty($item->matricule)) {
            $grade = $item->grade_id ? Grade::find($item->grade_id) : null;
            $region = $item->region_id ? Region::find($item->region_id) : null;
            $militaire = Militaire::create([
                'matricule' => trim($item->matricule),
                'nom' => trim($item->nom),
                'prenom' => trim($item->prenom ?? ''),
                'grade_id' => $item->grade_id,
                'grade_actuel' => $grade?->nom_grade ?? 'Gendarme',
                'grade_ordre' => $grade?->ordre ?? 1,
                'categorie' => $grade?->categorie_statutaire ?? 'Militaire du Rang',
                'region_id' => $item->region_id,
                'region' => $region?->nom,
                'unite' => $item->unite_texte,
                'statut' => 'actif',
                'statut_code' => 1,
                'date_entree_service' => now()->toDateString(),
            ]);
            $item->militaire_id = $militaire->id;
            $item->saveQuietly();
            $this->synchroniserMilitaireDepuisRegistre($militaire, $item);
        }

        return redirect()->back()->with('success', 'Entrée enregistrée et synchronisée avec succès avec la fiche du militaire.');
    }

    public function updateRegistre(Request $request, RegistreOperationnel $registre)
    {
        $validated = $request->validate([
            'type_registre_code' => 'sometimes|integer|between:1,5',
            'numero_ordre' => 'nullable|integer',
            'matricule' => 'nullable|string|max:50',
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'grade_id' => 'nullable|integer|exists:grades,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'unite_id' => 'nullable|integer|exists:unites,id',
            'unite_texte' => 'nullable|string|max:255',
            'date_evenement' => 'nullable|date',
            'annee_evenement' => 'nullable|integer',
            'type_circonstance_code' => 'nullable|integer',
            'circonstances' => 'nullable|string',
            'nature_pathologie' => 'nullable|string',
            'decision_aptitude' => 'nullable|string',
            'observations' => 'nullable|string',
            'est_actif_fichier' => 'nullable|integer',
        ]);

        if (!empty($validated['date_evenement']) && empty($validated['annee_evenement'])) {
            $validated['annee_evenement'] = (int) date('Y', strtotime($validated['date_evenement']));
        }
        if (!empty($validated['grade_id'])) {
            $grade = Grade::find($validated['grade_id']);
            if ($grade) {
                $validated['grade_texte'] = $grade->nom_grade;
                $validated['grade_ordre'] = (int) $grade->ordre;
            }
        }
        if (empty($validated['unite_id']) && !empty($validated['unite_texte'])) {
            $uniteFound = Unite::whereRaw('LOWER(nom) = ?', [mb_strtolower(trim($validated['unite_texte']))])->first();
            if ($uniteFound) {
                $validated['unite_id'] = $uniteFound->id;
                if (empty($validated['region_id']) && $uniteFound->region_id) {
                    $validated['region_id'] = $uniteFound->region_id;
                }
            }
        }

        $registre->update($validated);

        // Synchronisation automatique bidirectionnelle avec la fiche du militaire
        $militaire = null;
        if ($registre->militaire_id) {
            $militaire = Militaire::find($registre->militaire_id);
        }
        if (!$militaire && !empty($registre->matricule)) {
            $militaire = Militaire::where('matricule', trim($registre->matricule))->first();
            if ($militaire) {
                $registre->militaire_id = $militaire->id;
                $registre->saveQuietly();
            }
        }

        if ($militaire) {
            $this->synchroniserMilitaireDepuisRegistre($militaire, $registre);
        }

        return redirect()->back()->with('success', 'Entrée mise à jour et synchronisée avec succès avec la fiche du militaire.');
    }

    public function destroyRegistre(RegistreOperationnel $registre)
    {
        $militaire = $registre->militaire_id ? Militaire::find($registre->militaire_id) : null;
        if (!$militaire && !empty($registre->matricule)) {
            $militaire = Militaire::where('matricule', trim($registre->matricule))->first();
        }

        if ($militaire) {
            if ($registre->type_registre_code === RegistreOperationnel::TYPE_BLESSE) {
                $militaire->est_blesse_guerre = 0;
                $militaire->status_sanitaire = 'Apte';
            } elseif ($registre->type_registre_code === RegistreOperationnel::TYPE_DISPARU) {
                $militaire->est_disparu = 0;
                if ($militaire->position_actuelle === "Cas d'otage" || $militaire->position_id === 31) {
                    $militaire->position_actuelle = "À l'Unité / Au Poste";
                    $militaire->position_id = 21;
                }
            } elseif ($registre->type_registre_code === RegistreOperationnel::TYPE_GRAND_MALADE) {
                $militaire->est_non_mobilisable = 0;
                $militaire->status_sanitaire = 'Apte';
            }
            $militaire->save();
        }

        $registre->delete();
        return redirect()->back()->with('success', 'Entrée supprimée du registre et statut du militaire réinitialisé.');
    }

    /**
     * Synchronise les informations du Registre vers la fiche individuelle du Militaire
     * exactement de la même manière que la création ou l'édition dans le formulaire Militaire.
     */
    protected function synchroniserMilitaireDepuisRegistre(Militaire $militaire, RegistreOperationnel $registre): void
    {
        // 1. Identité et grade
        if (!empty($registre->matricule)) {
            $militaire->matricule = trim($registre->matricule);
        }
        $militaire->nom = trim($registre->nom);
        if ($registre->prenom !== null && trim($registre->prenom) !== '') {
            $militaire->prenom = trim($registre->prenom);
        }

        if ($registre->grade_id) {
            $militaire->grade_id = (int) $registre->grade_id;
            $grade = Grade::find($registre->grade_id);
            if ($grade) {
                $militaire->grade_actuel = $grade->nom_grade;
                $militaire->grade_ordre = (int) $grade->ordre;
                $militaire->categorie = $grade->categorie_statutaire;
            }
        }

        // 2. Rattachement géographique et unité
        if ($registre->region_id) {
            $militaire->region_id = (int) $registre->region_id;
            $region = Region::find($registre->region_id);
            if ($region) {
                $militaire->region = $region->nom;
            }
        }

        if ($registre->unite_id) {
            $militaire->unite_id = (int) $registre->unite_id;
            $unite = Unite::find($registre->unite_id);
            if ($unite) {
                $militaire->unite = $unite->nom;
                if ($unite->region_id) {
                    $militaire->region_id = $unite->region_id;
                    $militaire->region = $unite->region?->nom ?? $militaire->region;
                }
            }
        } elseif (!empty($registre->unite_texte)) {
            $militaire->unite = trim($registre->unite_texte);
        }

        // 3. Statuts opérationnels, santé et positions selon le type de registre
        $type = (int) $registre->type_registre_code;

        if ($type === RegistreOperationnel::TYPE_BLESSE) {
            $militaire->est_blesse_guerre = 1;
            $militaire->est_non_mobilisable = 0;
            $militaire->est_disparu = 0;
            $militaire->status_sanitaire = 'Blessé de guerre';
        } elseif ($type === RegistreOperationnel::TYPE_DISPARU) {
            $militaire->est_disparu = 1;
            $militaire->est_blesse_guerre = 0;
            $militaire->est_non_mobilisable = 0;
            $militaire->position_actuelle = "Cas d'otage";
            $militaire->position_id = 31;
            $militaire->status_sanitaire = 'Porté disparu / Otage';
        } elseif ($type === RegistreOperationnel::TYPE_GRAND_MALADE) {
            $militaire->est_non_mobilisable = 1;
            $militaire->est_blesse_guerre = 0;
            $militaire->est_disparu = 0;
            $militaire->status_sanitaire = $registre->nature_pathologie ?: 'Inaptitude constatée médicalement';
        } elseif ($type === RegistreOperationnel::TYPE_DECEDE) {
            $militaire->statut = 'décédé';
            $militaire->statut_code = 5;
            $militaire->date_deces = $registre->date_evenement;
            $militaire->circonstances_deces = $registre->circonstances;
        } elseif ($type === RegistreOperationnel::TYPE_DESERTEUR) {
            $militaire->statut = 'radié';
            $militaire->statut_code = 6;
            $militaire->motif_radiation = 'Désertion';
        }

        if (!empty($registre->observations)) {
            $militaire->observations = trim($registre->observations);
        }

        $militaire->save();
    }

    // ==========================================
    // GESTION DES ORGANISMES DE DÉTACHEMENT
    // ==========================================

    public function storeOrganisme(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:organismes_detachement,code',
            'nom' => 'required|string|max:255',
            'ordre' => 'nullable|integer',
            'est_actif' => 'boolean',
            'est_organique' => 'boolean',
        ]);

        $organisme = OrganismeDetachement::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Organisme créé avec succès.',
                'organisme' => $organisme,
            ]);
        }

        return redirect()->back()->with('success', 'Organisme créé avec succès.');
    }

    public function updateOrganisme(Request $request, OrganismeDetachement $organisme)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:organismes_detachement,code,' . $organisme->id,
            'nom' => 'required|string|max:255',
            'ordre' => 'nullable|integer',
            'est_actif' => 'boolean',
            'est_organique' => 'boolean',
        ]);

        $organisme->update($validated);
        return redirect()->back()->with('success', 'Organisme mis à jour avec succès.');
    }

    public function destroyOrganisme(OrganismeDetachement $organisme)
    {
        if ($organisme->est_organique) {
            return redirect()->back()->with('error', 'Impossible de supprimer l\'organisme organique principal.');
        }
        $organisme->delete();
        return redirect()->back()->with('success', 'Organisme supprimé avec succès.');
    }

    // ==========================================
    // EXPORT EXCEL OFFICIEL IDENTIQUE (100% BASE DE DONNÉES)
    // ==========================================

    public function export(Request $request)
    {
        $nomFichier = sprintf('SITUATION_PRISE_ARMES_GRM_%s.xlsx', now()->format('Ymd_His'));
        return Excel::download(new SituationPriseArmesExport(), $nomFichier);
    }
}
