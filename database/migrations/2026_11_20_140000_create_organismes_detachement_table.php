<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organismes_detachement', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 255);
            $table->integer('ordre')->default(0)->index();
            $table->boolean('est_actif')->default(true)->index();
            $table->boolean('est_organique')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organismes_detachement');
    }
};
