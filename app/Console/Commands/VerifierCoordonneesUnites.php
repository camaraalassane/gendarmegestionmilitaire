<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Unite;
use App\Models\Region;
use App\Services\RegionGeo;

class VerifierCoordonneesUnites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unites:verifier-coordonnees 
                            {--tolerance=0 : Tolérance frontalière en pixels SVG (défaut 0 = strict sans marge, 1 px ≈ 2.8 km)}
                            {--sans-gps : Afficher uniquement la liste des unités sans coordonnées GPS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifie que les coordonnées GPS des unités de gendarmerie se situent bien dans leur région de rattachement.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');
        $this->info("=== VÉRIFICATION GÉOGRAPHIQUE DES UNITÉS DE GENDARMERIE ===");
        $this->line("• Mode d'audit : " . ($tolerance <= 0 ? "<fg=yellow>Strict (Tolérance 0 km - aucun dépassement de frontière permis)</>" : "<fg=cyan>Tolérance {$tolerance} px (~" . round($tolerance * 2.78, 1) . " km)</>"));
        $this->newLine();

        $unites = Unite::with('region')->orderBy('region_id')->orderBy('nom')->get();
        $total = $unites->count();

        $valides = 0;
        $sansGps = [];
        $anomalies = [];

        foreach ($unites as $u) {
            if ($u->latitude === null || $u->longitude === null) {
                $sansGps[] = [
                    'id' => $u->id,
                    'nom' => $u->nom,
                    'region' => $u->region ? "{$u->region->nom} ({$u->region->code})" : 'Non rattachée',
                    'ville' => $u->ville ?? '—',
                ];
                continue;
            }

            $r = $u->region;
            if (!$r) {
                continue;
            }

            $lat = (float) $u->latitude;
            $lon = (float) $u->longitude;

            if (RegionGeo::contient($r->code, $lat, $lon, $tolerance)) {
                $valides++;
            } else {
                $reelCode = RegionGeo::trouverRegion($lat, $lon, $tolerance);
                $reelNom = $reelCode ? (Region::where('code', $reelCode)->value('nom') ?? $reelCode) : 'HORS DU MALI';

                $anomalies[] = [
                    'id' => $u->id,
                    'nom' => $u->nom,
                    'region_declaree' => "{$r->nom} ({$r->code})",
                    'coordonnees' => "{$lat}, {$lon}",
                    'region_reelle' => $reelNom,
                ];
            }
        }

        $this->line("• Total des unités répertoriées : <fg=cyan>{$total}</>");
        $this->line("• Unités géolocalisées conformes : <fg=green>{$valides}</>");
        $this->line("• Unités sans coordonnées GPS : <fg=yellow>" . count($sansGps) . "</>");
        $this->line("• Anomalies géographiques détectées : " . (count($anomalies) > 0 ? "<fg=red>" . count($anomalies) . "</>" : "<fg=green>0</>"));
        $this->newLine();

        if (count($anomalies) > 0) {
            $this->error("ATTENTION : Des unités ont des coordonnées situées en dehors de leur région déclarée :");
            $this->table(
                ['ID', 'Unité', 'Région Déclarée', 'Coordonnées GPS', 'Région Réelle Détectée'],
                $anomalies
            );
            $this->newLine();
        }

        if (count($sansGps) > 0 && ($this->option('sans-gps') || count($anomalies) === 0)) {
            $this->comment("Unités sans coordonnées GPS (affichées en position approximative) :");
            $this->table(
                ['ID', 'Unité', 'Région', 'Ville'],
                $sansGps
            );
            $this->newLine();
        }

        if (count($anomalies) === 0) {
            $this->info("✓ Toutes les unités géolocalisées sont strictement conformes à leur découpage territorial.");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
