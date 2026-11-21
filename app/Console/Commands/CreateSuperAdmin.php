<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-super-admin {email} {--name=Super Administrateur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée (ou promeut) un Super Administrateur avec un mot de passe robuste saisi de façon interactive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $password = $this->secret('Mot de passe (14 caractères min. avec majuscules, minuscules, chiffres, symboles)');
        if (!$password) {
            $this->error('Le mot de passe ne peut pas être vide.');
            return self::FAILURE;
        }

        $confirmation = $this->secret('Confirmer le mot de passe');
        if ($password !== $confirmation) {
            $this->error('Les mots de passe ne correspondent pas.');
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => 'required|email',
                'password' => ['required', Password::min(14)->mixedCase()->numbers()->symbols()],
            ]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->password = $password; // hashed par casts()
            $user->role = 1;
            if ($this->option('name') && $this->option('name') !== 'Super Administrateur') {
                $user->name = $this->option('name');
            }
            $user->save();
            $this->info("L'utilisateur existant {$user->email} a été promu Super Administrateur avec son nouveau mot de passe.");
        } else {
            $user = new User();
            $user->email = $email;
            $user->name = $this->option('name') ?: 'Super Administrateur';
            $user->password = $password;
            $user->role = 1;
            $user->save();
            $this->info("Le Super Administrateur {$user->email} a été créé avec succès.");
        }

        return self::SUCCESS;
    }
}
