<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Region;
use App\Models\Unite;
use App\Http\Controllers\CarteController;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfigurationController extends Controller
{
    /**
     * Affiche la page principale de configuration.
     */
    public function index(Request $request)
    {
        $regions = Region::withCount('unites')
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get();

        $unitesQuery = Unite::with('region')->orderBy('nom');

        if ($request->filled('region_id')) {
            $unitesQuery->where('region_id', $request->region_id);
        }

        if ($request->filled('search_unite')) {
            $search = trim($request->search_unite);
            $unitesQuery->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $unites = $unitesQuery->get();
        $positions = Position::orderBy('nom')->get();

        // Statistiques globales
        $stats = [
            'total_regions' => $regions->count(),
            'total_unites' => Unite::count(),
            'unites_operationnelles' => Unite::where('est_operationnelle', true)->count(),
            'unites_non_operationnelles' => Unite::where('est_operationnelle', false)->count(),
            'total_positions' => $positions->count(),
        ];

        return Inertia::render('Configurations/Index', [
            'regions' => $regions,
            'unites' => $unites,
            'positions' => $positions,
            'stats' => $stats,
            'filters' => $request->only(['region_id', 'search_unite']),
        ]);
    }

    // ==========================================
    // RÉGIONS
    // ==========================================

    public function storeRegion(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:regions,code',
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'chef_lieu' => 'nullable|string|max:255',
            'ordre' => 'nullable|integer',
        ], [
            'code.unique' => 'Ce code de région existe déjà.',
            'code.required' => 'Le code de la région est obligatoire.',
            'nom.required' => 'Le nom de la région est obligatoire.',
        ]);

        $region = Region::create([
            'code' => trim($validated['code']),
            'nom' => trim($validated['nom']),
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'chef_lieu' => !empty($validated['chef_lieu']) ? trim($validated['chef_lieu']) : null,
            'ordre' => $validated['ordre'] ?? 0,
        ]);

        CarteController::flush();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Région créée avec succès.',
                'region' => $region,
            ]);
        }

        return redirect()->back()->with('success', 'Région enregistrée avec succès.');
    }

    public function updateRegion(Request $request, Region $region)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:regions,code,' . $region->id,
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'chef_lieu' => 'nullable|string|max:255',
            'ordre' => 'nullable|integer',
        ], [
            'code.unique' => 'Ce code de région est déjà utilisé.',
            'code.required' => 'Le code de la région est obligatoire.',
            'nom.required' => 'Le nom de la région est obligatoire.',
        ]);

        $region->update([
            'code' => trim($validated['code']),
            'nom' => trim($validated['nom']),
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'chef_lieu' => !empty($validated['chef_lieu']) ? trim($validated['chef_lieu']) : null,
            'ordre' => $validated['ordre'] ?? 0,
        ]);

        CarteController::flush();

        return redirect()->back()->with('success', 'Région modifiée avec succès.');
    }

    public function destroyRegion(Region $region)
    {
        $region->delete();
        CarteController::flush();
        return redirect()->back()->with('success', 'Région supprimée avec succès.');
    }

    // ==========================================
    // UNITÉS
    // ==========================================

    public function storeUnite(Request $request)
    {
        $validated = $request->validate([
            'region_id' => 'required|exists:regions,id',
            'nom' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'compagnie' => 'nullable|string|max:255',
            'groupement' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'ville' => 'nullable|string|max:255',
            'est_operationnelle' => 'nullable|boolean',
        ], [
            'region_id.required' => 'Veuillez sélectionner la région de rattachement.',
            'region_id.exists' => 'La région sélectionnée n\'existe pas.',
            'nom.required' => 'Le nom de l\'unité est obligatoire.',
        ]);

        $unite = Unite::create([
            'region_id' => $validated['region_id'],
            'nom' => trim($validated['nom']),
            'code' => !empty($validated['code']) ? trim($validated['code']) : null,
            'type' => $validated['type'] ?? 'Autre',
            'compagnie' => $validated['compagnie'] ?? null,
            'groupement' => $validated['groupement'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'ville' => !empty($validated['ville']) ? trim($validated['ville']) : null,
            'est_operationnelle' => $validated['est_operationnelle'] ?? true,
        ]);

        $unite->load('region');

        CarteController::flush();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unité créée avec succès.',
                'unite' => $unite,
            ]);
        }

        return redirect()->back()->with('success', 'Unité enregistrée avec succès.');
    }

    public function updateUnite(Request $request, Unite $unite)
    {
        $validated = $request->validate([
            'region_id' => 'required|exists:regions,id',
            'nom' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'compagnie' => 'nullable|string|max:255',
            'groupement' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'ville' => 'nullable|string|max:255',
            'est_operationnelle' => 'nullable|boolean',
        ], [
            'region_id.required' => 'Veuillez sélectionner la région de rattachement.',
            'nom.required' => 'Le nom de l\'unité est obligatoire.',
        ]);

        $unite->update([
            'region_id' => $validated['region_id'],
            'nom' => trim($validated['nom']),
            'code' => !empty($validated['code']) ? trim($validated['code']) : null,
            'type' => $validated['type'] ?? 'Autre',
            'compagnie' => $validated['compagnie'] ?? null,
            'groupement' => $validated['groupement'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'ville' => !empty($validated['ville']) ? trim($validated['ville']) : null,
            'est_operationnelle' => $validated['est_operationnelle'] ?? true,
        ]);

        CarteController::flush();

        return redirect()->back()->with('success', 'Unité modifiée avec succès.');
    }

    public function destroyUnite(Unite $unite)
    {
        $unite->delete();
        CarteController::flush();
        return redirect()->back()->with('success', 'Unité supprimée avec succès.');
    }

    // ==========================================
    // POSITIONS
    // ==========================================

    public function storePosition(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:positions,nom',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ], [
            'nom.unique' => 'Cette position existe déjà.',
            'nom.required' => 'Le nom de la position est obligatoire.',
        ]);

        $position = Position::create([
            'nom' => trim($validated['nom']),
            'code' => !empty($validated['code']) ? trim($validated['code']) : null,
            'type' => $validated['type'] ?? 'administrative',
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Position créée avec succès.',
                'position' => $position,
            ]);
        }

        return redirect()->back()->with('success', 'Position enregistrée avec succès.');
    }

    public function updatePosition(Request $request, Position $position)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:positions,nom,' . $position->id,
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ], [
            'nom.unique' => 'Ce nom de position est déjà utilisé.',
            'nom.required' => 'Le nom de la position est obligatoire.',
        ]);

        $position->update([
            'nom' => trim($validated['nom']),
            'code' => !empty($validated['code']) ? trim($validated['code']) : null,
            'type' => $validated['type'] ?? 'administrative',
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Position modifiée avec succès.');
    }

    public function destroyPosition(Position $position)
    {
        $position->delete();
        return redirect()->back()->with('success', 'Position supprimée avec succès.');
    }
}
