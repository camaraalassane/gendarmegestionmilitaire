<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GradeController extends Controller
{
    /**
     * Afficher la liste des grades.
     */
    public function index(Request $request)
    {
        $query = Grade::query();

        // RECHERCHE AMÉLIORÉE AVEC GESTION DES ESPACES (CORRECTION SERVEUR)
        if ($request->filled('search')) {
            // Supprime les espaces multiples et nettoie les bords
            $search = preg_replace('/\s+/', ' ', $request->search);
            $searchTerms = explode(' ', trim($search));
            
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    if (!empty($term)) {
                        // Force l'application du AND entre chaque mot, mais OR entre les colonnes
                        $q->where(function($subQ) use ($term) {
                            $subQ->where('nom_grade', 'like', "%{$term}%")
                                 ->orWhere('code_grade', 'like', "%{$term}%")
                                 ->orWhere('type_grade', 'like', "%{$term}%");
                        });
                    }
                }
            });
        }

        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type_grade', $request->type);
        }

        $grades = $query->withCount([
                'militaires as effectif_total',
                'militaires as effectif_actif' => fn ($q) => $q->whereBetween('statut_code', [1, 4]),
            ])
            ->orderByDesc('ordre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($grade) => [
                'id' => $grade->id,
                'code_grade' => $grade->code_grade,
                'nom_grade' => $grade->nom_grade,
                'type_grade' => $grade->type_grade,
                'ordre' => $grade->ordre,
                'effectif_actif' => $grade->effectif_actif,
                'effectif_total' => $grade->effectif_total,
            ]);

        // Statistiques globales
        $statistiques = [
            'total_grades' => Grade::count(),
            'types_grades' => Grade::distinct('type_grade')->count('type_grade'),
            'total_militaires' => \App\Models\Militaire::whereBetween('statut_code', [1, 4])->count(),
        ];

        // Options pour les filtres
        $typesGrades = Grade::distinct('type_grade')
            ->pluck('type_grade')
            ->map(fn ($type) => ['label' => $type, 'value' => $type])
            ->toArray();

        return Inertia::render('grades/index', [
            'grades' => $grades,
            'statistiques' => $statistiques,
            'filters' => $request->only(['search', 'type']),
            'typesGrades' => $typesGrades,
        ]);
    }

    /**
     * Afficher les détails d'un grade.
     */
    public function show(Request $request, Grade $grade)
    {
        // Récupérer les militaires ayant ce grade avec recherche optimisée par index
        $militairesQuery = $grade->militaires()->whereBetween('statut_code', [1, 4]);
        
        if ($request->filled('search')) {
            $search = trim(preg_replace('/\s+/', ' ', $request->search));
            if (ctype_digit($search)) {
                $militairesQuery->where(function($q) use ($search) {
                    $q->where('id', (int) $search)
                      ->orWhere('matricule', $search);
                });
            } else {
                $militairesQuery->where('recherche', 'like', "%{$search}%");
            }
        }
        
        $militaires = $militairesQuery
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($militaire) => [
                'id' => $militaire->id,
                'matricule' => $militaire->matricule,
                'nom' => $militaire->nom,
                'prenom' => $militaire->prenom,
                'date_entree_service' => $militaire->date_entree_service?->format('d/m/Y'),
                'date_retraite' => $militaire->calculerDateRetraite()?->format('d/m/Y'),
                'specialite' => $militaire->specialite,
                'age' => $militaire->age,
                'anciennete' => $militaire->anciennete,
            ]);

        // Statistiques du grade (Index statut_code + grade_id instantanés)
        $effectif_total = $grade->militaires()->count();
        $effectif_actif = $grade->militaires()->whereBetween('statut_code', [1, 4])->count();
        $effectif_retraite = $grade->militaires()->where('statut_code', 7)->count();

        return Inertia::render('grades/show', [
            'grade' => [
                'id' => $grade->id,
                'code_grade' => $grade->code_grade,
                'nom_grade' => $grade->nom_grade,
                'type_grade' => $grade->type_grade,
                'ordre' => $grade->ordre,
                'description' => $grade->description,
            ],
            'militaires' => $militaires,
            'statistiques' => [
                'effectif_total' => $effectif_total,
                'effectif_actif' => $effectif_actif,
                'effectif_retraite' => $effectif_retraite,
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau grade.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code_grade' => 'required|string|max:20|unique:grades,code_grade',
            'nom_grade'  => 'required|string|max:100',
            'type_grade' => 'required|string|max:50',
            'ordre'      => 'required|integer|min:0|unique:grades,ordre',
            'description'=> 'nullable|string',
        ]);

        $grade = Grade::create($validated);

        return redirect()->route('grades.index')
                         ->with('success', 'Grade créé avec succès.');
    }

    /**
     * Mettre à jour un grade existant.
     */
    public function update(Request $request, Grade $grade)
    {
        $validated = $request->validate([
            'code_grade' => 'required|string|max:20|unique:grades,code_grade,' . $grade->id,
            'nom_grade'  => 'required|string|max:100',
            'type_grade' => 'required|string|max:50',
            'ordre'      => 'required|integer|min:0|unique:grades,ordre,' . $grade->id,
            'description'=> 'nullable|string',
        ]);

        $grade->update($validated);

        return redirect()->route('grades.index')
                         ->with('success', 'Grade mis à jour avec succès.');
    }
}