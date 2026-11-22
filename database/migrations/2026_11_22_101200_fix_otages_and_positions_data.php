<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Note : La synchronisation ponctuelle des observations et statuts des otages/disparus
     * a été transférée dans la commande Artisan : php artisan gendarmerie:sync-otages
     */
    public function up(): void
    {
        // Données opérationnelles gérées via la commande Artisan dédiée
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
