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
        $organismes = OrganismeDetachement::orderBy('ordre')->get();
        $regions = Region::orderBy('ordre')->get();
        $grades = Grade::orderByDesc('ordre')->get();

        // 1. STATS ET COMPTAGES 100% ISSUS DE LA BASE DE DONNÉES
        $stats = $spaService->calculerStatsGlobales();

        // 2. MATRICE DYNAMIQUE DE LA SITUATION (SPA) & 20 RÉGIONS DEPUIS LA BASE
        $matrice = $spaService->calculerMatriceDynamique();
        $regionsSituation = $spaService->calculerRegionsDynamique();

        // Compléter la situation par région pour les 20 régions (19 + 1 Bamako) si des clés manquent
        foreach ($regions as $r) {
            if (!isset($regionsSituation[$r->code])) {
                $regionsSituation[$r->code] = [
                    'id' => $r->id,
                    'nom' => $r->nom,
                    'code' => $r->code,
                    'GAR' => 0, 'GCA' => 0, 'GDD' => 0, 'GDB' => 0,
                    'CLM' => 0, 'COL' => 0, 'LCL' => 0, 'CES' => 0,
                    'CNE' => 0, 'LTN' => 0, 'SLT' => 0, 'ASP' => 0,
                    'EOA' => 0, 'MJR' => 0, 'ADC' => 0, 'ADJ' => 0,
                    'MDC' => 0, 'MDL' => 0, 'ESOA' => 0, 'MRG' => 0,
                    'total' => 0
                ];
            }
        }

        // 3. REQUÊTE SUR LES REGISTRES OPÉRATIONNELS (FILTRES STRICTEMENT NUMÉRIQUES)
        $registresQuery = RegistreOperationnel::with(['grade', 'region', 'unite', 'militaire'])
            ->orderBy('type_registre_code')
            ->orderBy('numero_ordre')
            ->orderBy('id');

        // Filtre par type_registre_code (1 à 5)
        if ($request->filled('type_registre_code') && is_numeric($request->type_registre_code)) {
            $registresQuery->where('type_registre_code', (int) $request->type_registre_code);
        }

        // Filtre par region_id (entier)
        if ($request->filled('region_id') && is_numeric($request->region_id)) {
            $registresQuery->where('region_id', (int) $request->region_id);
        }

        // Filtre par grade_id (entier)
        if ($request->filled('grade_id') && is_numeric($request->grade_id)) {
            $registresQuery->where('grade_id', (int) $request->grade_id);
        }

        // Filtre par annee_evenement (entier)
        if ($request->filled('annee_evenement') && is_numeric($request->annee_evenement)) {
            $registresQuery->where('annee_evenement', (int) $request->annee_evenement);
        }

        // Filtre par type_circonstance_code (entier)
        if ($request->filled('type_circonstance_code') && is_numeric($request->type_circonstance_code)) {
            $registresQuery->where('type_circonstance_code', (int) $request->type_circonstance_code);
        }

        // Recherche textuelle ciblée
        if ($request->filled('search')) {
            $s = trim($request->search);
            $registresQuery->where(function ($q) use ($s) {
                $q->where('matricule', 'like', "{$s}%")
                  ->orWhere('nom', 'ilike', "%{$s}%")
                  ->orWhere('prenom', 'ilike', "%{$s}%");
            });
        }

        $registres = $registresQuery->get();

        $militairesLookup = Militaire::whereNull('deleted_at')
            ->orderBy('nom')
            ->get(['id', 'matricule', 'nom', 'prenom', 'grade_id', 'region_id', 'unite_id', 'unite']);

        $unites = Unite::orderBy('nom')->get(['id', 'nom', 'code', 'region_id']);

        return Inertia::render('PriseArmes/Index', [
            'stats' => $stats,
            'organismes' => $organismes,
            'regions' => $regions,
            'grades' => $grades,
            'unites' => $unites,
            'matrice' => $matrice,
            'regionsSituation' => $regionsSituation,
            'registres' => $registres,
            'militairesLookup' => $militairesLookup,
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
