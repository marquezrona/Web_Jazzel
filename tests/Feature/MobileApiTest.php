<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_cashier_receive_products_allowed_by_their_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $active = $this->createProduct('Barang Aktif', 4, true);
        $inactive = $this->createProduct('Barang Nonaktif', 8, false);
        $empty = $this->createProduct('Barang Habis', 0, true);

        $this->actingAs($admin)->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $inactive->id]);

        $this->actingAs($cashier)->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id])
            ->assertJsonMissing(['id' => $empty->id]);
    }

    public function test_only_admin_can_manage_products_through_the_mobile_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $payload = [
            'name' => 'Barang dari Mobile',
            'category' => 'Sembako',
            'price' => 12000,
            'stock' => 9,
        ];

        $this->actingAs($cashier)->postJson('/api/products', $payload)->assertForbidden();

        $this->actingAs($admin)->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Barang dari Mobile')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_mobile_checkout_is_idempotent_when_an_offline_sale_is_retried(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $product = $this->createProduct('Kopi', 4, true);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax' => 0,
            'other_fee' => 0,
            'paid' => 20000,
            'payment_method' => 'tunai',
            'client_transaction_id' => '69cceacd-4a0b-4430-a830-1609e15a2bd7',
        ];

        $first = $this->actingAs($cashier)->postJson('/api/checkout', $payload)->assertCreated();
        $second = $this->actingAs($cashier)->postJson('/api/checkout', $payload)->assertCreated();

        $this->assertSame($first->json('sale_id'), $second->json('sale_id'));
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
    }

    public function test_cashier_can_register_a_member_top_up_a_card_and_check_its_balance(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);

        $created = $this->actingAs($cashier)->postJson('/api/customers', [
            'name' => 'Pelanggan Mobile',
            'phone' => '08123456789',
            'card_uid' => 'AB12CD34',
        ])->assertCreated();
        $customerId = $created->json('data.id');

        $this->postJson("/api/customers/{$customerId}/top-up", [
            'amount' => 50000,
            'payment_method' => 'tunai',
        ])->assertOk()->assertJsonPath('data.nfc_card.balance', '50000.00');

        $this->postJson('/api/nfc/balance', ['card_uid' => 'ab12cd34'])
            ->assertOk()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('member_name', 'Pelanggan Mobile')
            ->assertJsonPath('balance', '50000.00');
    }

    public function test_admin_can_create_cashiers_and_see_members_and_dashboard_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::query()->create(['name' => 'Member Satu', 'phone' => '08123456789']);

        $this->actingAs($admin)->postJson('/api/admin/cashiers', [
            'name' => 'Kasir Mobile',
            'email' => 'mobile-cashier@example.test',
            'password' => 'password123',
        ])->assertCreated()->assertJsonPath('data.role', 'kasir');

        $this->getJson('/api/admin/members')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Member Satu']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('role', 'admin')
            ->assertJsonPath('product_count', 0);
    }

    public function test_mobile_api_rejects_unauthenticated_requests_and_wrong_roles(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();

        $cashier = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($cashier)->getJson('/api/admin/members')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/api/checkout', [])->assertForbidden();
    }

    private function createProduct(string $name, int $stock, bool $isActive): Product
    {
        return Product::query()->create([
            'name' => $name,
            'category' => 'Sembako',
            'price' => 10000,
            'stock' => $stock,
            'is_active' => $isActive,
        ]);
    }
}
