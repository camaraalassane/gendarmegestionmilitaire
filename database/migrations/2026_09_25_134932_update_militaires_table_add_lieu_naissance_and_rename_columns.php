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
            $table->string('lieu_naissance')->nullable();
            $table->renameColumn('a_fait_justice', 'antecedent_judiciaire');
            $table->renameColumn('a_fait_discipline', 'antecedent_disciplinaire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropColumn('lieu_naissance');
            $table->renameColumn('antecedent_judiciaire', 'a_fait_justice');
            $table->renameColumn('antecedent_disciplinaire', 'a_fait_discipline');
        });
    }
};
