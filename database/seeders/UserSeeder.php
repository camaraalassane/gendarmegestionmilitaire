<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'id' => 1,
                'name' => 'Super Administrateur',
                'email' => 'admin@gendarmerie.ml',
                'email_verified_at' => null,
                'password' => Hash::make('password123'),
                'role' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'id' => 2,
                'name' => 'Administrateur FAMa',
                'email' => 'admin@fama.ml',
                'email_verified_at' => null,
                'password' => Hash::make('12345678'),
                'role' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'id' => 3,
                'name' => 'Gestionnaire Gendarmerie',
                'email' => 'gestionnaire@gendarmerie.ml',
                'email_verified_at' => null,
                'password' => Hash::make('password123'),
                'role' => 2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['id' => $user['id']],
                $user
            );
        }
    }
}