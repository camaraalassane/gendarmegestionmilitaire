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
            // Identifiants Gendarmerie & État civil
            $table->string('mle_grm')->nullable()->index()->after('matricule');
            $table->string('numero_info')->nullable()->after('mle_grm');
            $table->string('numero_nina')->nullable()->after('numero_info');
            $table->string('categorie')->nullable()->after('grade_actuel');

            // Affectation & Maillage territorial
            $table->string('unite')->nullable()->index()->after('position_actuelle');
            $table->string('region')->nullable()->index()->after('unite');
            $table->date('date_mutation')->nullable()->after('region');

            // Santé & Théâtres d'opérations
            $table->text('status_sanitaire')->nullable()->after('groupe_sanguin');
            $table->string('engagement_ops')->nullable()->after('status_sanitaire');

            // Carrière, Formation & Décorations
            $table->string('origine_formation')->nullable()->after('specialite');
            $table->text('decorations')->nullable()->after('antecedent_disciplinaire');
            $table->text('diplomes_academiques')->nullable()->after('decorations');
            $table->text('observations')->nullable()->after('diplomes_academiques');

            // Circonstances spécifiques (Décès, Radiation, Transfert)
            $table->date('date_deces')->nullable()->after('observations');
            $table->text('circonstances_deces')->nullable()->after('date_deces');
            $table->string('motif_radiation')->nullable()->after('circonstances_deces');
            $table->string('reference_decision')->nullable()->after('motif_radiation');
            $table->string('unite_transfert')->nullable()->after('reference_decision');
            $table->string('motif_transfert')->nullable()->after('unite_transfert');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropColumn([
                'mle_grm',
                'numero_info',
                'numero_nina',
                'categorie',
                'unite',
                'region',
                'date_mutation',
                'status_sanitaire',
                'engagement_ops',
                'origine_formation',
                'decorations',
                'diplomes_academiques',
                'observations',
                'date_deces',
                'circonstances_deces',
                'motif_radiation',
                'reference_decision',
                'unite_transfert',
                'motif_transfert',
            ]);
        });
    }
};
