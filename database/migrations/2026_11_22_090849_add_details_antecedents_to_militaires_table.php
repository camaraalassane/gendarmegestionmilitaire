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
        Schema::table('militaires', function (Blueprint $table) {
            $table->text('details_antecedent_judiciaire')->nullable()->after('antecedent_judiciaire');
            $table->text('details_antecedent_disciplinaire')->nullable()->after('antecedent_disciplinaire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropColumn(['details_antecedent_judiciaire', 'details_antecedent_disciplinaire']);
        });
    }
};
