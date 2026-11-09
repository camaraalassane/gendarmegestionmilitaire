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
        if (Schema::hasTable('affectations')) {
            Schema::table('affectations', function (Blueprint $table) {
                $table->index(['militaire_id', 'date_mutation'], 'idx_affectations_militaire_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('affectations')) {
            Schema::table('affectations', function (Blueprint $table) {
                $table->dropIndex('idx_affectations_militaire_date');
            });
        }
    }
};
