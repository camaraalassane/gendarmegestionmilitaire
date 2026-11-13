<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE militaires ALTER COLUMN statut TYPE VARCHAR(255) USING statut::varchar');
            DB::statement("ALTER TABLE militaires ALTER COLUMN statut SET DEFAULT 'actif'");
            return;
        }

        Schema::table('militaires', function (Blueprint $table) {
            $table->string('statut')->default('actif')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->string('statut')->default('actif')->change();
        });
    }
};
