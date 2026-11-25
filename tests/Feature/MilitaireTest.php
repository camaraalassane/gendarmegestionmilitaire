<?php

namespace Tests\Feature;

use App\Models\Certificat;
use App\Models\CertificatDocument;
use App\Models\Grade;
use App\Models\Militaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MilitaireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Grade::create([
            'nom_grade' => 'Soldat 2',
            'code_grade' => 'S2',
            'ordre' => 1,
            'type_grade' => 'militaire du rang',
            'retraite_obligatoire' => 50,
        ]);

        Grade::create([
            'nom_grade' => 'Sergent',
            'code_grade' => 'SGT',
            'ordre' => 5,
            'type_grade' => 'sous-officier',
            'retraite_obligatoire' => 53,
        ]);
    }

    public function test_can_create_a_militaire(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'MIL-10001',
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'date_naissance' => '1995-05-15',
            'date_entree_service' => '2015-01-10',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
            'telephone' => '+2250102030405',
            'sexe' => 'M',
            'groupe_sanguin' => 'O+',
        ]);

        $response->assertRedirect(route('militaires.index'));

        $this->assertDatabaseHas('militaires', [
            'matricule' => 'MIL-10001',
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'statut' => 'actif',
        ]);
    }

    public function test_can_update_a_militaire_including_statut_formation_and_stage(): void
    {
        $user = User::factory()->create();

        $militaire = Militaire::create([
            'matricule' => 'MIL-10002',
            'nom' => 'Yao',
            'prenom' => 'Paul',
            'date_naissance' => '1990-08-20',
            'date_entree_service' => '2012-03-01',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
        ]);

        $response = $this->actingAs($user)->put(route('militaires.update', $militaire->id), [
            'matricule' => 'MIL-10002',
            'nom' => 'Yao',
            'prenom' => 'Paul Henri',
            'date_naissance' => '1990-08-20',
            'date_entree_service' => '2012-03-01',
            'grade_actuel' => 'Sergent',
            'statut' => 'formation',
            'specialite' => 'Transmission',
            'sexe' => 'M',
            'groupe_sanguin' => 'A+',
        ]);

        $response->assertRedirect(route('militaires.show', $militaire->id));

        $this->assertDatabaseHas('militaires', [
            'id' => $militaire->id,
            'prenom' => 'Paul Henri',
            'grade_actuel' => 'Sergent',
            'statut' => 'formation',
            'specialite' => 'Transmission',
        ]);
    }

    public function test_syncing_certificats_preserves_existing_pivot_ids_and_documents(): void
    {
        $user = User::factory()->create();

        $militaire = Militaire::create([
            'matricule' => 'MIL-10003',
            'nom' => 'Konan',
            'prenom' => 'Yves',
            'date_naissance' => '1992-02-10',
            'date_entree_service' => '2014-04-01',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
        ]);

        $certificat = Certificat::create([
            'nom_certificat' => 'CAT1',
            'niveau_certificat' => 'CAT1',
            'grade_associe' => 'Soldat 2',
            'conditions' => 'Aucune',
        ]);

        // Attacher le certificat initial
        $militaire->certificats()->attach($certificat->id, ['date_obtention' => '2020-01-01']);

        $pivotId = \DB::table('certificat_militaire')
            ->where('militaire_id', $militaire->id)
            ->where('certificat_id', $certificat->id)
            ->value('id');

        $doc = CertificatDocument::create([
            'militaire_certificat_id' => $pivotId,
            'nom_fichier' => 'test_certif.pdf',
            'chemin_fichier' => 'certificats_documents/test.pdf',
            'type_fichier' => 'application/pdf',
            'taille' => 1024,
        ]);

        // Mise à jour du militaire avec ré-envoi du certificat
        $response = $this->actingAs($user)->put(route('militaires.update', $militaire->id), [
            'matricule' => 'MIL-10003',
            'nom' => 'Konan',
            'prenom' => 'Yves Modified',
            'date_naissance' => '1992-02-10',
            'date_entree_service' => '2014-04-01',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
            'certificats' => [
                $certificat->id => [
                    'obtenu' => '1',
                    'date_obtention' => '2020-01-01',
                ]
            ]
        ]);

        $response->assertRedirect(route('militaires.show', $militaire->id));

        // Vérifier que le document n'a PAS été supprimé
        $this->assertDatabaseHas('certificat_documents', [
            'id' => $doc->id,
            'militaire_certificat_id' => $pivotId,
        ]);
    }

    public function test_militaire_whose_retirement_age_is_exceeded_is_automatically_saved_as_retraite(): void
    {
        $user = User::factory()->create();

        // Soldat 2 a une limite de 50 ans.
        // Né en 1970 -> retraite en 2020 (dans le passé).
        $response = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'MIL-OLD-999',
            'nom' => 'Traoré',
            'prenom' => 'Amadou',
            'date_naissance' => '1970-01-01',
            'date_entree_service' => '1990-01-01',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif', // L'utilisateur indique actif
            'telephone' => '+2250102030405',
        ]);

        $response->assertRedirect(route('militaires.index'));

        // Vérifier que le statut a été automatiquement basculé en 'retraité'
        $this->assertDatabaseHas('militaires', [
            'matricule' => 'MIL-OLD-999',
            'statut' => 'retraité',
        ]);
    }

    public function test_can_create_militaire_with_photo_and_gendarmerie_fields(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create();

        $photo = UploadedFile::fake()->image('identite.jpg', 600, 800);

        $response = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'GRM-8899',
            'mle_grm' => 'GRM9272M006871',
            'numero_info' => 'INFO-9922',
            'numero_nina' => 'NINA-123456789',
            'nom' => 'Diallo',
            'prenom' => 'Moussa',
            'date_naissance' => '1992-04-12',
            'lieu_naissance' => 'Bamako',
            'date_entree_service' => '2014-06-01',
            'grade_actuel' => 'Sergent',
            'categorie' => 'Sous-Officiers',
            'unite' => 'Escadron 2/1',
            'region' => 'Bamako',
            'status_sanitaire' => 'Apte TAP',
            'engagement_ops' => 'Opération Maliko (Secteur Centre)',
            'decorations' => 'Médaille de la Défense Nationale',
            'diplomes_academiques' => 'Licence en Droit',
            'statut' => 'actif',
            'photo' => $photo,
        ]);

        $response->assertRedirect(route('militaires.index'));

        $militaire = Militaire::where('matricule', 'GRM-8899')->firstOrFail();
        $this->assertNotNull($militaire->photo);
        $this->assertEquals('GRM9272M006871', $militaire->mle_grm);
        $this->assertEquals('INFO-9922', $militaire->numero_info);
        $this->assertEquals('Escadron 2/1', $militaire->unite);
        $this->assertTrue(in_array($militaire->region, ['Bamako', 'DISTRICT DE BAMAKO']));
        $this->assertEquals('Apte TAP', $militaire->status_sanitaire);
        $this->assertStringContainsString('/militaires/' . $militaire->id . '/photo', $militaire->photo_url);

        Storage::disk('local')->assertExists($militaire->photo);
    }

    public function test_can_delete_photo_on_update(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $fakePath = 'militaires/photos/test_photo.jpg';
        Storage::disk('public')->put($fakePath, 'dummy content');

        $militaire = Militaire::create([
            'matricule' => 'GRM-PHOTO-DEL',
            'nom' => 'Keita',
            'prenom' => 'Ousmane',
            'grade_actuel' => 'Sergent',
            'statut' => 'actif',
            'photo' => $fakePath,
        ]);

        $response = $this->actingAs($user)->put(route('militaires.update', $militaire->id), [
            'matricule' => 'GRM-PHOTO-DEL',
            'nom' => 'Keita',
            'prenom' => 'Ousmane',
            'grade_actuel' => 'Sergent',
            'statut' => 'actif',
            'supprimer_photo' => 1,
        ]);

        $response->assertRedirect(route('militaires.show', $militaire->id));

        $militaire->refresh();
        $this->assertNull($militaire->photo);
        $this->assertNull($militaire->photo_url);
        Storage::disk('public')->assertMissing($fakePath);
    }

    public function test_categorie_statutaire_is_automatically_assigned_from_grade(): void
    {
        $user = User::factory()->create();

        Grade::create([
            'nom_grade' => 'Adjudant-Chef',
            'code_grade' => 'ADC',
            'ordre' => 9,
            'type_grade' => 'sous-officier',
            'retraite_obligatoire' => 55,
        ]);

        Grade::create([
            'nom_grade' => 'Capitaine',
            'code_grade' => 'CNE',
            'ordre' => 14,
            'type_grade' => 'officier subalterne',
            'retraite_obligatoire' => 58,
        ]);

        // 1. Soldat 2 -> 'Militaire du Rang'
        $response1 = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'CAT-TEST-1',
            'nom' => 'Touré',
            'prenom' => 'Bakary',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
        ]);
        $response1->assertRedirect(route('militaires.index'));
        $this->assertDatabaseHas('militaires', [
            'matricule' => 'CAT-TEST-1',
            'categorie' => 'Militaire du Rang',
        ]);

        // 2. Sergent -> 'Sous-officier Subalterne'
        $response2 = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'CAT-TEST-2',
            'nom' => 'Konaté',
            'prenom' => 'Ibrahim',
            'grade_actuel' => 'Sergent',
            'statut' => 'actif',
        ]);
        $response2->assertRedirect(route('militaires.index'));
        $this->assertDatabaseHas('militaires', [
            'matricule' => 'CAT-TEST-2',
            'categorie' => 'Sous-officier Subalterne',
        ]);

        // 3. Adjudant-Chef -> 'Sous-officier Supérieur'
        $response3 = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'CAT-TEST-3',
            'nom' => 'Diarra',
            'prenom' => 'Souleymane',
            'grade_actuel' => 'Adjudant-Chef',
            'statut' => 'actif',
        ]);
        $response3->assertRedirect(route('militaires.index'));
        $this->assertDatabaseHas('militaires', [
            'matricule' => 'CAT-TEST-3',
            'categorie' => 'Sous-officier Supérieur',
        ]);

        // 4. Capitaine -> 'Officier Subalterne'
        $response4 = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'CAT-TEST-4',
            'nom' => 'Camara',
            'prenom' => 'Modibo',
            'grade_actuel' => 'Capitaine',
            'statut' => 'actif',
        ]);
        $response4->assertRedirect(route('militaires.index'));
        $this->assertDatabaseHas('militaires', [
            'matricule' => 'CAT-TEST-4',
            'categorie' => 'Officier Subalterne',
        ]);
    }

    public function test_can_create_militaire_with_multiple_affectations_and_mutation_alert(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('militaires.store'), [
            'matricule' => 'AFF-001',
            'nom' => 'Keita',
            'prenom' => 'Sekou',
            'grade_actuel' => 'Soldat 2',
            'statut' => 'actif',
            'date_mutation' => now()->subYears(4)->format('Y-m-d'),
            'unite' => 'Brigade Kati',
            'region' => 'REGION N°1',
            'affectations' => [
                [
                    'unite' => 'Brigade Kati',
                    'region' => 'REGION N°1',
                    'date_mutation' => now()->subYears(4)->format('Y-m-d')
                ],
                [
                    'unite' => 'Escadron Bamako',
                    'region' => 'REGION N°2',
                    'date_mutation' => now()->subYears(6)->format('Y-m-d')
                ]
            ]
        ]);

        $response->assertRedirect(route('militaires.index'));

        $militaire = Militaire::where('matricule', 'AFF-001')->first();
        $this->assertNotNull($militaire);
        $this->assertEquals(2, $militaire->affectations()->count());

        // Alerte mutation créée car >= 3 ans
        $this->assertDatabaseHas('alertes', [
            'militaire_id' => $militaire->id,
            'type_alerte' => 'mutation',
        ]);
    }
}
