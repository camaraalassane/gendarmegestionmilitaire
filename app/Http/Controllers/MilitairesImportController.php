<?php

namespace App\Http\Controllers;

use App\Imports\MilitairesImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;
use Illuminate\Support\Facades\Response;

class MilitairesImportController extends Controller
{
    public function showForm()
    {
        return Inertia::render('militaires/import');
    }

    public function process(Request $request)
    {
        // Augmenter le temps d'exécution et la mémoire pour les très gros fichiers (10 000+ lignes)
        set_time_limit(1800);
        ini_set('max_execution_time', '1800');
        ini_set('memory_limit', '1024M');

        $request->validate([
            'fichier' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'duplicate_action' => 'sometimes|in:ignore,update',
        ]);

        $file = $request->file('fichier');
        $duplicateAction = $request->input('duplicate_action', 'ignore');

        $realPath = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension());
        $targetSheet = null;
        $boldMatricules = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($realPath);
                $worksheetInfo = $reader->listWorksheetInfo($realPath);

                // Auto-détection de la feuille principale (celle contenant le plus grand nombre de lignes)
                $maxRows = 0;
                foreach ($worksheetInfo as $info) {
                    if ($info['totalRows'] > $maxRows) {
                        $maxRows = $info['totalRows'];
                        $targetSheet = $info['worksheetName'];
                    }
                }

                // Pré-scan ultra-rapide des matricules en gras (ex: "tout ce qui sont en gras sont des feminin")
                $spreadsheet = $reader->load($realPath);
                $sheet = $targetSheet ? $spreadsheet->getSheetByName($targetSheet) : $spreadsheet->getActiveSheet();
                if ($sheet) {
                    $highestRow = $sheet->getHighestRow();
                    for ($r = 2; $r <= $highestRow; $r++) {
                        $isBold = $sheet->getStyle('B' . $r)->getFont()->getBold()
                               || $sheet->getStyle('C' . $r)->getFont()->getBold()
                               || $sheet->getStyle('A' . $r)->getFont()->getBold()
                               || $sheet->getStyle('D' . $r)->getFont()->getBold();
                        if ($isBold) {
                            $mat = trim((string)$sheet->getCell('B' . $r)->getValue());
                            if (empty($mat)) $mat = trim((string)$sheet->getCell('A' . $r)->getValue());
                            if (!empty($mat)) {
                                $boldMatricules[$mat] = true;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Inspection des feuilles ou styles Excel: ' . $e->getMessage());
            }
        }

        \App\Models\Militaire::$isImporting = true;
        try {
            $import = new MilitairesImport($duplicateAction, $targetSheet, $boldMatricules);

            if (in_array($ext, ['xlsx', 'xls']) && isset($spreadsheet) && isset($sheet)) {
                $rows = $sheet->toArray(null, true, false, true);
                $header = array_shift($rows);
                $chunks = array_chunk($rows, 1000, true);

                foreach ($chunks as $chunkRows) {
                    $chunkCollection = collect($chunkRows)->map(function ($r) use ($header) {
                        $row = [];
                        foreach ($header as $col => $name) {
                            $row[$name] = $r[$col] ?? null;
                        }
                        return $row;
                    });

                    $import->collection($chunkCollection);
                }
            } else {
                Excel::import($import, $file);
            }

            $summary = $import->getSummary();
            $duplicates = $import->getDuplicates();
            $errorDetails = $import->getErrorDetails();

            if (!empty($duplicates) && $duplicateAction === 'ignore') {
                return back()->with([
                    'duplicates' => $duplicates,
                    'flash' => [
                        'warning' => 'Des doublons ont été détectés et ignorés'
                    ]
                ]);
            }

            $message = "Importation terminée : ";
            if ($summary['created'] > 0) $message .= "{$summary['created']} créé(s) ";
            if ($summary['updated'] > 0) $message .= "{$summary['updated']} mis à jour ";
            if ($summary['skipped'] > 0) $message .= "{$summary['skipped']} ignoré(s) ";
            if ($summary['errors'] > 0) $message .= "⚠️ {$summary['errors']} erreur(s) ";

            if (auth()->check()) {
                \App\Models\ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'Importation Excel',
                    'description' => "Importation Excel : {$summary['created']} créé(s), {$summary['updated']} mis à jour, {$summary['skipped']} ignoré(s)",
                    'ip_address' => request()->ip()
                ]);
            }

            return redirect()->route('militaires.index')->with([
                'success' => trim($message),
                'import_summary' => $summary
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errMsg = config('app.debug')
                ? ('Erreur lors de l\'importation : ' . $e->getMessage())
                : 'Une erreur est survenue lors de l\'importation. Veuillez vérifier que le fichier correspond exactement au modèle fourni.';

            return back()->withErrors([
                'fichier' => $errMsg
            ]);
        } finally {
            \App\Models\Militaire::$isImporting = false;
            \App\Models\Militaire::incrementCacheVersion();
            \App\Http\Controllers\CarteController::flush();
            \App\Support\CarteRattachement::oublier();
        }
    }

    /**
     * Télécharger le modèle d'import au format Excel (XLSX)
     */
    public function downloadTemplateXlsx()
    {
        $headers = MilitairesImport::getTemplateHeaders();
        $exampleRow = MilitairesImport::getTemplateExampleRow();

        return Excel::download(
            new \App\Exports\MilitairesTemplateExport($headers, $exampleRow),
            'modele_import_militaires.xlsx'
        );
    }

    /**
     * Télécharger le modèle d'import au format CSV
     */
    public function downloadTemplateCsv()
    {
        $headers = MilitairesImport::getTemplateHeaders();
        $exampleRow = MilitairesImport::getTemplateExampleRow();

        // Protection contre l'injection de formules CSV
        $exampleRow = array_map(function ($val) {
            $str = (string) $val;
            return (preg_match('/^[=+\-@\t\r]/', $str) && !is_numeric($str)) ? "'" . $str : $str;
        }, $exampleRow);

        $csvContent = implode(';', $headers) . "\n";
        $csvContent .= implode(';', $exampleRow) . "\n";

        // Ajout du BOM UTF-8 pour les caractères français
        $csvContent = "\xEF\xBB\xBF" . $csvContent;

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele_import_militaires.csv"',
        ]);
    }
}
