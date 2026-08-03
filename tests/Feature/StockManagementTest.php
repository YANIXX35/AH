<?php

namespace Tests\Feature;

use App\Models\StockProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/stock')->assertRedirect('/login');
    }

    public function test_user_can_create_a_product_starting_at_zero_stock(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/stock', [
            'name' => 'Sac de riz 25kg',
            'unit' => 'sac',
            'sale_price' => 15000,
        ]);

        $product = StockProduct::where('name', 'Sac de riz 25kg')->firstOrFail();
        $response->assertRedirect(route('stock.show', $product));
        $this->assertSame(0.0, (float) $product->quantity_on_hand);
        $this->assertSame(0.0, (float) $product->average_cost);
        $this->assertTrue($product->is_active);
    }

    public function test_product_name_must_be_unique_per_workspace(): void
    {
        $user = User::factory()->create();
        StockProduct::create([
            'user_id' => $user->id,
            'name' => 'Sac de riz 25kg',
            'unit' => 'sac',
            'quantity_on_hand' => 0,
            'average_cost' => 0,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/stock', ['name' => 'Sac de riz 25kg'])
            ->assertSessionHasErrors('name');
    }

    public function test_user_cannot_view_another_users_product(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = StockProduct::create([
            'user_id' => $owner->id,
            'name' => 'Produit privé',
            'unit' => 'unité',
            'quantity_on_hand' => 0,
            'average_cost' => 0,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($intruder)
            ->get("/stock/{$product->id}")
            ->assertForbidden();
    }

    public function test_recording_an_entree_updates_quantity_and_weighted_average_cost(): void
    {
        $user = User::factory()->create();
        $product = StockProduct::create([
            'user_id' => $user->id,
            'name' => 'Sac de ciment',
            'unit' => 'sac',
            'quantity_on_hand' => 10,
            'average_cost' => 100,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post("/stock/{$product->id}/movements", [
            'type' => 'entree',
            'quantity' => 10,
            'unit_cost' => 200,
            'movement_date' => now()->toDateString(),
        ])->assertRedirect();

        $product->refresh();
        // (10 * 100 + 10 * 200) / 20 = 150 (CUMP)
        $this->assertSame(20.0, (float) $product->quantity_on_hand);
        $this->assertSame(150.0, (float) $product->average_cost);
    }

    public function test_a_sortie_cannot_exceed_available_stock(): void
    {
        $user = User::factory()->create();
        $product = StockProduct::create([
            'user_id' => $user->id,
            'name' => 'Sac de ciment',
            'unit' => 'sac',
            'quantity_on_hand' => 5,
            'average_cost' => 100,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post("/stock/{$product->id}/movements", [
            'type' => 'sortie',
            'quantity' => 8,
            'movement_date' => now()->toDateString(),
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(5.0, (float) $product->fresh()->quantity_on_hand);
    }

    public function test_deleting_a_product_without_movements_removes_it(): void
    {
        $user = User::factory()->create();
        $product = StockProduct::create([
            'user_id' => $user->id,
            'name' => 'Produit jamais mouvementé',
            'unit' => 'unité',
            'quantity_on_hand' => 0,
            'average_cost' => 0,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)->delete("/stock/{$product->id}")
            ->assertRedirect(route('stock.index'));

        $this->assertDatabaseMissing('stock_products', ['id' => $product->id]);
    }

    public function test_deleting_a_product_with_movements_archives_it_instead(): void
    {
        $user = User::factory()->create();
        $product = StockProduct::create([
            'user_id' => $user->id,
            'name' => 'Produit mouvementé',
            'unit' => 'unité',
            'quantity_on_hand' => 5,
            'average_cost' => 100,
            'sale_price' => 0,
            'is_active' => true,
        ]);
        $this->actingAs($user)->post("/stock/{$product->id}/movements", [
            'type' => 'entree',
            'quantity' => 5,
            'unit_cost' => 100,
            'movement_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->delete("/stock/{$product->id}")
            ->assertRedirect(route('stock.index'));

        $this->assertDatabaseHas('stock_products', ['id' => $product->id, 'is_active' => false]);
    }
}
