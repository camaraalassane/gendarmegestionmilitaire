<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\EligibiliteController;

class RebuildEligibilitesTableJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $version) {}

    public function handle(EligibiliteController $controller): void
    {
        $v = $this->version;

        try {
            // Nettoyage préventif d'une éventuelle tentative inachevée sur cette version
            DB::table('eligibilites_calculees')->where('version', $v)->delete();

            // Calcul par lots de 2 000 militaires avec insertion continue dans eligibilites_calculees
            $meta = $controller->computeAll($v);

            Cache::forever("eligibilites:meta:v{$v}", $meta);
            Cache::forever('eligibilites:table_version', $v);

            // Nettoyage progressif des anciennes versions par petits lots
            do {
                $n = DB::table('eligibilites_calculees')->where('version', '<>', $v)->limit(5000)->delete();
            } while ($n > 0);

            Log::info("RebuildEligibilitesTableJob : table pré-calculée (v{$v}) reconstruite avec succès.");
        } catch (\Throwable $e) {
            Log::error("RebuildEligibilitesTableJob : échec de reconstruction v{$v} : " . $e->getMessage(), [
                'exception' => $e
            ]);
        } finally {
            Cache::forget('eligibilites:rebuild:lock');
        }
    }
}
