<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // En développement uniquement : initialisation sécurisée de comptes de test
        $devUsers = [
            ['email' => 'admin@example.test', 'name' => 'Super Administrateur (dev)', 'role' => 1],
            ['email' => 'gestionnaire@example.test', 'name' => 'Gestionnaire (dev)', 'role' => 2],
        ];

        foreach ($devUsers as $u) {
            $user = User::where('email', $u['email'])->first();
            if (!$user) {
                $password = Str::random(24);
                $user = new User();
                $user->email = $u['email'];
                $user->name = $u['name'];
                $user->password = $password; // hashed par casts()
                $user->role = $u['role'];
                $user->save();
                $this->command?->warn("Compte dev créé: {$u['email']} | Mot de passe généré: {$password}");
            }
        }
    }
}