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
        if (Schema::hasTable('grades') && !Schema::hasColumn('grades', 'description')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->text('description')->nullable()->after('retraite_obligatoire');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('grades') && Schema::hasColumn('grades', 'description')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
