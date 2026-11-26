<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Version des données stockée EN BASE : survit à cache:clear, au redémarrage du cache et aux déploiements.
        Schema::create('cache_versions', function (Blueprint $table) {
            $table->string('cle', 100)->primary();
            $table->unsignedBigInteger('valeur')->default(1);
        });
        DB::table('cache_versions')->insert(['cle' => 'militaires', 'valeur' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_versions');
    }
};
