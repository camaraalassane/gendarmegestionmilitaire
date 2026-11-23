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
        $devUsers = [
            ['email' => 'admin@gendarmerie.ml', 'name' => 'Super Administrateur', 'role' => 1, 'password' => 'password123'],
            ['email' => 'gestionnaire@gendarmerie.ml', 'name' => 'Gestionnaire Gendarmerie', 'role' => 2, 'password' => 'password123'],
            ['email' => 'admin@fama.ml', 'name' => 'Administrateur FAMa', 'role' => 1, 'password' => '12345678'],
            ['email' => 'admin@example.test', 'name' => 'Super Administrateur (dev)', 'role' => 1, 'password' => 'password123'],
            ['email' => 'gestionnaire@example.test', 'name' => 'Gestionnaire (dev)', 'role' => 2, 'password' => 'password123'],
        ];

        foreach ($devUsers as $u) {
            $user = User::where('email', $u['email'])->first();
            if (!$user) {
                $user = new User();
                $user->email = $u['email'];
                $user->name = $u['name'];
                $user->password = \Illuminate\Support\Facades\Hash::make($u['password']);
                $user->role = $u['role'];
                $user->save();
            } else {
                $user->password = \Illuminate\Support\Facades\Hash::make($u['password']);
                $user->save();
            }
        }
    }
}