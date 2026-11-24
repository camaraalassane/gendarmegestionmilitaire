<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permet de lire instantanément la dernière version complète (ligne 'meta') sans parcourir la table
        Schema::table('eligibilites_calculees', function (Blueprint $table) {
            $table->index(['type', 'version'], 'elig_calc_meta_idx');
        });
    }

    public function down(): void
    {
        Schema::table('eligibilites_calculees', function (Blueprint $table) {
            $table->dropIndex('elig_calc_meta_idx');
        });
    }
};
