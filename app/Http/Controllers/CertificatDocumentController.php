<?php

namespace App\Http\Controllers;

use App\Models\CertificatDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatDocumentController extends Controller
{
    public function download($id): StreamedResponse
    {
        $document = CertificatDocument::findOrFail($id);

        // Vérifier d'abord sur le stockage privé sécurisé 'local', puis rétrocompatibilité 'public'
        $disk = Storage::disk('local')->exists($document->chemin_fichier)
            ? 'local'
            : (Storage::disk('public')->exists($document->chemin_fichier) ? 'public' : null);

        if (!$disk) {
            abort(404, 'Document non trouvé');
        }

        return Storage::disk($disk)->download(
            $document->chemin_fichier,
            $document->nom_fichier
        );
    }
}
