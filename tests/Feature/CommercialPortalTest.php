<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommercialPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_non_commercials_cannot_access_commercial_dashboard(): void
    {
        // Invités bloqués
        $response = $this->get('/commercial/dashboard');
        $response->assertRedirect('/login');

        // Utilisateurs ordinaires (sans role commercial) bloqués
        $user = User::factory()->create(['role_key' => 'manager']);
        $response = $this->actingAs($user)->get('/commercial/dashboard');
        $response->assertStatus(403);
    }

    public function test_commercial_can_access_dashboard_and_see_clients(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client1 = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'company_name' => 'Client A',
        ]);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Client A');
    }

    public function test_commercial_can_create_client_with_one_month_trial(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->post('/commercial/clients', [
            'name' => 'Nouveau Client',
            'email' => 'client@sitiame.ci',
            'company_name' => 'Client SAS',
            'password' => 'Sitiame2026!',
        ]);

        $response->assertRedirect();

        // Vérification en BDD
        $client = User::where('email', 'client@sitiame.ci')->first();
        $this->assertNotNull($client);
        $this->assertSame($commercial->id, $client->created_by_user_id);
        $this->assertSame('manager', $client->role_key);
        $this->assertTrue($client->is_premium);
        $this->assertTrue($client->premium_ends_at->isFuture());
        // Vérifier que la date de fin est à environ 30 jours
        $this->assertEquals(30, round(now()->diffInDays($client->premium_ends_at)));
    }

    public function test_commercial_can_update_their_client(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'name' => 'Ancien Nom',
            'company_name' => 'Ancien Etablissement',
        ]);

        $response = $this->actingAs($commercial)->put("/commercial/clients/{$client->id}", [
            'name' => 'Nouveau Nom',
            'email' => $client->email,
            'company_name' => 'Nouveau Etablissement',
            'password' => '', // Pas de changement de mot de passe
        ]);

        $response->assertRedirect();
        $client->refresh();
        $this->assertSame('Nouveau Nom', $client->name);
        $this->assertSame('Nouveau Etablissement', $client->company_name);
    }

    public function test_commercial_cannot_update_other_commercials_client(): void
    {
        $commercial1 = User::factory()->create(['role_key' => 'commercial']);
        $commercial2 = User::factory()->create(['role_key' => 'commercial']);
        $clientOfCommercial2 = User::factory()->create([
            'created_by_user_id' => $commercial2->id,
            'name' => 'Client de Comm2',
        ]);

        $response = $this->actingAs($commercial1)->put("/commercial/clients/{$clientOfCommercial2->id}", [
            'name' => 'Piratage',
            'email' => $clientOfCommercial2->email,
            'company_name' => 'Piratage SA',
        ]);

        $response->assertStatus(403);
    }

    public function test_commercial_can_delete_their_client(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create(['created_by_user_id' => $commercial->id]);

        $response = $this->actingAs($commercial)->delete("/commercial/clients/{$client->id}");

        $response->assertRedirect();
        $this->assertNull(User::find($client->id));
    }

    public function test_admin_can_access_commercial_tracking(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $commercial = User::factory()->create(['role_key' => 'commercial', 'name' => 'Sales Guy']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'company_name' => 'Acheté par Sales Guy',
        ]);

        $response = $this->actingAs($admin)->get('/admin/commerciale');

        $response->assertStatus(200);
        $response->assertSee('Sales Guy');
        $response->assertSee('Acheté par Sales Guy');
    }

    public function test_guests_and_non_commercials_cannot_access_commercial_portefeuille(): void
    {
        // Invités bloqués
        $response = $this->get('/commercial/portefeuille');
        $response->assertRedirect('/login');

        // Utilisateurs ordinaires (sans role commercial) bloqués
        $user = User::factory()->create(['role_key' => 'manager']);
        $response = $this->actingAs($user)->get('/commercial/portefeuille');
        $response->assertStatus(403);
    }

    public function test_commercial_can_access_portefeuille_and_see_clients(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $client = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'name' => 'Client de Test Portefeuille',
            'company_name' => 'Test Entreprise',
        ]);

        $response = $this->actingAs($commercial)->get('/commercial/portefeuille');

        $response->assertStatus(200);
        $response->assertSee('Client de Test Portefeuille');
        $response->assertSee('Test Entreprise');
    }

    public function test_commercial_can_search_clients_in_portefeuille(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $clientMatch = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'name' => 'Jean-Pierre Client',
            'company_name' => 'JP Comp',
        ]);
        $clientNoMatch = User::factory()->create([
            'created_by_user_id' => $commercial->id,
            'name' => 'Marc Dupont',
            'company_name' => 'MD Inc',
        ]);

        // Recherche d'un mot-clé correspondant au premier client
        $response = $this->actingAs($commercial)->get('/commercial/portefeuille?search=Jean-Pierre');

        $response->assertStatus(200);
        $response->assertSee('Jean-Pierre Client');
        $response->assertDontSee('Marc Dupont');
    }

    public function test_commercial_can_parse_company_document_and_extract_fields(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);
        $file = UploadedFile::fake()->createWithContent(
            'fiche_entreprise.txt',
            "Raison Sociale: Ivoire Agro SARL\nDirigeant: Jean Kouassi\nEmail: contact@iagro.ci\nTelephone: +2250700001122\nNIF: 1234567A\nVille: Abidjan"
        );

        $response = $this->actingAs($commercial)->postJson('/commercial/parse-company-document', [
            'document' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'filename' => 'fiche_entreprise.txt',
            'extracted' => [
                'name' => 'Jean Kouassi',
                'email' => 'contact@iagro.ci',
                'company_name' => 'Ivoire Agro SARL',
                'city' => 'Abidjan',
            ],
        ]);
    }
}
