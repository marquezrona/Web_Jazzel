<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_product_without_a_barcode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Barang tanpa kode',
            'price' => 10000,
            'stock' => 5,
            'is_active' => true,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Barang tanpa kode',
            'barcode' => null,
        ]);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Barang dengan kode',
            'barcode' => '8991234567890',
            'price' => 15000,
            'stock' => 8,
            'is_active' => true,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Barang dengan kode',
            'barcode' => '8991234567890',
        ]);
    }

    public function test_product_barcodes_must_be_unique_and_can_be_kept_when_editing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstProduct = Product::query()->create([
            'name' => 'Barang pertama',
            'barcode' => '8991234567890',
            'price' => 10000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $secondProduct = Product::query()->create([
            'name' => 'Barang kedua',
            'price' => 12000,
            'stock' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('products.update', $secondProduct), [
            'name' => 'Barang kedua',
            'barcode' => $firstProduct->barcode,
            'price' => 12000,
            'stock' => 3,
            'is_active' => true,
        ])->assertSessionHasErrors('barcode');

        $this->actingAs($admin)->put(route('products.update', $firstProduct), [
            'name' => 'Barang pertama diperbarui',
            'barcode' => $firstProduct->barcode,
            'price' => 10000,
            'stock' => 5,
            'is_active' => true,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'name' => 'Barang pertama diperbarui',
            'barcode' => '8991234567890',
        ]);
    }

    public function test_cashier_page_includes_available_barcodes_and_a_scanner_input(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        Product::query()->create([
            'name' => 'Susu UHT',
            'barcode' => '8999876543210',
            'price' => 8000,
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($cashier)->get(route('cashier.index'))
            ->assertOk()
            ->assertSee('id="scanBarcode"', false)
            ->assertSee('8999876543210');
    }
}
