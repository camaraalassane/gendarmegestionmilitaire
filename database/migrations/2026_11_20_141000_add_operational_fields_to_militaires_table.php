<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->unsignedBigInteger('organisme_detachement_id')->nullable()->after('unite_id');
            $table->smallInteger('sexe_code')->nullable()->after('sexe');
            $table->boolean('est_blesse_guerre')->default(false);
            $table->boolean('est_non_mobilisable')->default(false);
            $table->boolean('est_disparu')->default(false);
            $table->smallInteger('code_inaptitude')->nullable();

            $table->index('organisme_detachement_id');
            $table->index('sexe_code');
            $table->index('est_blesse_guerre');
            $table->index('est_non_mobilisable');
            $table->index('est_disparu');
            $table->index('code_inaptitude');

            $table->foreign('organisme_detachement_id')->references('id')->on('organismes_detachement')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('militaires', function (Blueprint $table) {
            $table->dropForeign(['organisme_detachement_id']);
            $table->dropIndex(['organisme_detachement_id']);
            $table->dropIndex(['sexe_code']);
            $table->dropIndex(['est_blesse_guerre']);
            $table->dropIndex(['est_non_mobilisable']);
            $table->dropIndex(['est_disparu']);
            $table->dropIndex(['code_inaptitude']);

            $table->dropColumn([
                'organisme_detachement_id',
                'sexe_code',
                'est_blesse_guerre',
                'est_non_mobilisable',
                'est_disparu',
                'code_inaptitude',
            ]);
        });
    }
};
