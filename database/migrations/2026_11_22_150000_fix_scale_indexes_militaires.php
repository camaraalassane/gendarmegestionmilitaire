<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Requis pour CREATE / DROP INDEX CONCURRENTLY (pas de verrou d'écriture sur la table)
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Remplace les index créés le 22/11 dont les colonnes ne servaient pas les requêtes visées
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_api_tri_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_statut_tri_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_updated_idx');

        // Tri alphabétique + pagination par curseur (nom, prenom, id)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS militaires_api_tri_idx
            ON militaires (nom, prenom, id) WHERE deleted_at IS NULL');

        // Filtre par statut (même rare) + tri alphabétique : 177 ms -> 1,5 ms à 300 000 lignes
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS militaires_statut_tri_idx
            ON militaires (statut_code, nom, prenom, id) WHERE deleted_at IS NULL');

        // Synchronisation incrémentale (modifiés depuis T)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS militaires_updated_idx
            ON militaires (updated_at, id)');

        // Conserve l'index utile à la liste web (tri par grade_ordre desc, nom)
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS militaires_liste_tri_idx
            ON militaires (statut_code, grade_ordre, nom, prenom) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_liste_tri_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_updated_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_statut_tri_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS militaires_api_tri_idx');
    }
};
