<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\Militaire;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlerteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_alertes_by_military_type_message_and_date(): void
    {
        $user = User::factory()->create();

        $militaireA = Militaire::create([
            'matricule' => 'M1001',
            'nom' => 'Traore',
            'prenom' => 'Sekou',
            'date_naissance' => '1990-01-01',
            'date_entree_service' => '2010-01-01',
            'grade_actuel' => 'Caporal',
            'statut' => 'actif',
        ]);

        $militaireB = Militaire::create([
            'matricule' => 'M2002',
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'date_naissance' => '1985-05-15',
            'date_entree_service' => '2008-01-01',
            'grade_actuel' => 'Sergent',
            'statut' => 'actif',
        ]);

        $alertePromotion = Alerte::create([
            'militaire_id' => $militaireA->id,
            'type_alerte' => 'promotion',
            'message' => 'Proposable au grade de Caporal-chef',
            'date_echeance' => '2026-12-31',
            'est_vue' => false,
        ]);

        $alerteMutation = Alerte::create([
            'militaire_id' => $militaireB->id,
            'type_alerte' => 'mutation',
            'message' => 'Affectation actuelle de 3 ans révolus requérant examen de mutation',
            'date_echeance' => '2027-01-15',
            'est_vue' => false,
        ]);

        // 1. Search by Militaire name
        $response = $this->actingAs($user)->get(route('alertes.index', ['search' => 'Traore']));
        $response->assertOk();
        $response->assertSee('M1001');

        // 2. Search by Type 'mutation'
        $response = $this->actingAs($user)->get(route('alertes.index', ['search' => 'mutation']));
        $response->assertOk();
        $response->assertSee('M2002');

        // 3. Search by Message keyword
        $response = $this->actingAs($user)->get(route('alertes.index', ['search' => 'examen']));
        $response->assertOk();
        $response->assertSee('M2002');

        // 4. Search by Echeance
        $response = $this->actingAs($user)->get(route('alertes.index', ['search' => '2026-12-31']));
        $response->assertOk();
        $response->assertSee('M1001');
    }
}
