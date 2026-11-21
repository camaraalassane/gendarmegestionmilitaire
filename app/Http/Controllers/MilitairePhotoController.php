<?php

namespace App\Http\Controllers;

use App\Models\Militaire;
use Illuminate\Support\Facades\Storage;

class MilitairePhotoController extends Controller
{
    /**
     * Affiche de manière sécurisée la photo d'identité d'un militaire aux utilisateurs authentifiés et habilités.
     */
    public function show(Militaire $militaire)
    {
        $path = $militaire->photo;

        if (!$path) {
            abort(404);
        }

        // Vérification sur le disque privé local en priorité, avec fallback public transitoire
        $disk = null;
        if (Storage::disk('local')->exists($path)) {
            $disk = Storage::disk('local');
        } elseif (Storage::disk('public')->exists($path)) {
            $disk = Storage::disk('public');
        } else {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
