<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Militaire;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibiliteExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_export_mutations_eligibilites(): void
    {
        $user = User::factory()->create();

        $militaire = Militaire::create([
            'matricule' => 'C999',
            'nom' => 'Konate',
            'prenom' => 'Moussa',
            'date_naissance' => '1990-01-01',
            'date_entree_service' => '2015-01-01',
            'grade_actuel' => 'Caporal',
            'statut' => 'actif',
            'unite' => 'Brigade Centrale',
            'region' => 'Bamako',
            'date_derniere_mutation' => Carbon::now()->subYears(4)->format('Y-m-d'),
        ]);

        Affectation::create([
            'militaire_id' => $militaire->id,
            'unite' => 'Brigade Centrale',
            'region' => 'Bamako',
            'date_debut' => Carbon::now()->subYears(4)->format('Y-m-d'),
            'est_actuelle' => true,
        ]);

        $response = $this->actingAs($user)->get(route('eligibilites.export', [
            'type' => 'mutations',
            'export_all' => 'false',
        ]));

        $response->assertOk();
        $this->assertTrue(
            str_contains($response->headers->get('content-disposition') ?? '', 'eligibilites_mutations_')
        );
    }

    public function test_can_export_all_eligibilites(): void
    {
        $user = User::factory()->create();

        $militaire = Militaire::create([
            'matricule' => 'C998',
            'nom' => 'Keita',
            'prenom' => 'Ibrahim',
            'date_naissance' => '1988-01-01',
            'date_entree_service' => '2012-01-01',
            'grade_actuel' => 'Sergent',
            'statut' => 'actif',
            'unite' => 'Escadron 2',
            'region' => 'Kayes',
            'date_derniere_mutation' => Carbon::now()->subYears(3)->format('Y-m-d'),
        ]);

        Affectation::create([
            'militaire_id' => $militaire->id,
            'unite' => 'Escadron 2',
            'region' => 'Kayes',
            'date_debut' => Carbon::now()->subYears(3)->format('Y-m-d'),
            'est_actuelle' => true,
        ]);

        $response = $this->actingAs($user)->get(route('eligibilites.export', [
            'type' => 'all',
            'export_all' => 'true',
        ]));

        $response->assertOk();
    }
}
