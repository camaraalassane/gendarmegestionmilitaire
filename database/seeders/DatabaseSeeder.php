<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GradeSeeder::class,
            CertificatSeeder::class,
        ]);

        // Les comptes de démonstration ne sont jamais créés hors environnement local/testing
        if (app()->environment('local', 'testing')) {
            $this->call(UserSeeder::class);
        }
    }
}