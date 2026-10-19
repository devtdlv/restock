<?php

namespace Tests\Feature\Catalog;

use App\Enums\StockMovementType;
use App\Livewire\Products\Index;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_products_page(): void
    {
        $this->get(route('products.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_products_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Products', false);
    }

    public function test_products_can_be_created_without_categories(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Wireless Mouse')
            ->set('sku', 'SKU-MOUSE-1000')
            ->set('description', 'Ergonomic mouse')
            ->set('unit_price', '29.99')
            ->set('cost_price', '12.50')
            ->set('quantity_on_hand', '25')
            ->set('reorder_level', '5')
            ->set('is_active', true)
            ->set('categoryIds', [])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Product created successfully.');

        $product = Product::query()->where('sku', 'SKU-MOUSE-1000')->first();

        $this->assertNotNull($product);
        $this->assertSame('29.99', $product->unit_price);
        $this->assertSame('12.50', $product->cost_price);
        $this->assertSame(25, $product->quantity_on_hand);
        $this->assertCount(0, $product->categories);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::StockIn->value,
            'quantity_change' => 25,
            'quantity_before' => 0,
            'quantity_after' => 25,
            'reason' => StockMovementService::OPENING_STOCK_REASON,
        ]);
    }

    public function test_products_can_be_created_with_multiple_categories(): void
    {
        $user = User::factory()->create();
        $categories = Category::factory()->count(3)->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'USB Hub')
            ->set('sku', 'SKU-HUB-2000')
            ->set('unit_price', '19.00')
            ->set('cost_price', '8.00')
            ->set('quantity_on_hand', '10')
            ->set('reorder_level', '2')
            ->set('categoryIds', $categories->pluck('id')->all())
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'SKU-HUB-2000')->firstOrFail();

        $this->assertCount(3, $product->categories);
        $this->assertEqualsCanonicalizing(
            $categories->pluck('id')->all(),
            $product->categories->pluck('id')->all()
        );
    }

    public function test_product_category_assignments_can_be_updated_and_removed(): void
    {
        $user = User::factory()->create();
        $keep = Category::factory()->create(['name' => 'Keep']);
        $remove = Category::factory()->create(['name' => 'Remove']);
        $add = Category::factory()->create(['name' => 'Add']);
        $product = Product::factory()->create(['sku' => 'SKU-SYNC-3000']);
        $product->categories()->attach([$keep->id, $remove->id]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $product->id)
            ->set('categoryIds', [$keep->id, $add->id])
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertEqualsCanonicalizing(
            [$keep->id, $add->id],
            $product->categories->pluck('id')->all()
        );

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $product->id)
            ->set('categoryIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(0, $product->fresh()->categories);
        $this->assertDatabaseHas('categories', ['id' => $keep->id]);
        $this->assertDatabaseHas('categories', ['id' => $add->id]);
    }

    public function test_products_can_be_updated(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Old Name',
            'sku' => 'SKU-OLD-4000',
            'unit_price' => '10.00',
        ]);

        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            12,
            $user,
            'Seed stock',
        );

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $product->id)
            ->set('name', 'New Name')
            ->set('unit_price', '15.25')
            ->set('quantity_on_hand', '999')
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New Name',
            'sku' => 'SKU-OLD-4000',
            'unit_price' => '15.25',
            'quantity_on_hand' => 12,
        ]);
        $this->assertSame(12, $product->quantity_on_hand);
        $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_direct_quantity_edits_are_ignored_on_update(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sku' => 'SKU-QTY-LOCK']);

        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            8,
            $user,
            'Initial',
        );

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $product->id)
            ->set('quantity_on_hand', '100')
            ->set('name', 'Quantity Locked Product')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'quantity_after' => 100,
        ]);
    }

    public function test_product_validation_and_unique_sku_constraints_are_enforced(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'SKU-DUP-5000']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', '')
            ->set('sku', '')
            ->set('unit_price', '')
            ->call('save')
            ->assertHasErrors(['name', 'sku', 'unit_price']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Duplicate SKU Product')
            ->set('sku', 'SKU-DUP-5000')
            ->set('unit_price', '1.00')
            ->set('cost_price', '0.50')
            ->set('quantity_on_hand', '1')
            ->set('reorder_level', '0')
            ->call('save')
            ->assertHasErrors(['sku']);
    }

    public function test_product_unique_sku_rule_ignores_the_current_record_when_editing(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sku' => 'SKU-KEEP-6000']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $product->id)
            ->set('description', 'Updated description')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'SKU-KEEP-6000',
            'description' => 'Updated description',
        ]);
    }

    public function test_negative_prices_and_quantities_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Bad Product')
            ->set('sku', 'SKU-BAD-7000')
            ->set('unit_price', '-1.00')
            ->set('cost_price', '-0.01')
            ->set('quantity_on_hand', '-3')
            ->set('reorder_level', '-1')
            ->call('save')
            ->assertHasErrors(['unit_price', 'cost_price', 'quantity_on_hand', 'reorder_level']);
    }

    public function test_invalid_category_ids_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Invalid Categories Product')
            ->set('sku', 'SKU-CAT-8000')
            ->set('unit_price', '5.00')
            ->set('cost_price', '2.00')
            ->set('quantity_on_hand', '1')
            ->set('reorder_level', '0')
            ->set('categoryIds', [999999])
            ->call('save')
            ->assertHasErrors(['categoryIds.0']);
    }

    public function test_products_can_be_deleted_with_confirmation_and_categories_are_preserved(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create();
        $product->categories()->attach($category->id);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('confirmDeletion', $product->id)
            ->assertSet('confirmingDeletionId', $product->id)
            ->call('delete')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Product deleted successfully.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('category_product', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_products_can_be_searched_and_paginated(): void
    {
        $user = User::factory()->create();
        Product::factory()->create([
            'name' => 'Alpha Unique Product',
            'sku' => 'SKU-ALPHA-9000',
        ]);
        Product::factory()->create([
            'name' => 'Beta Other Product',
            'sku' => 'SKU-BETA-9001',
        ]);
        Product::factory()->count(10)->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'SKU-ALPHA')
            ->assertSee('Alpha Unique Product', false)
            ->assertDontSee('Beta Other Product', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertHasNoErrors();
    }

    public function test_guests_cannot_mutate_products_through_livewire(): void
    {
        Livewire::test(Index::class)
            ->call('create')
            ->assertForbidden();

        Livewire::test(Index::class)
            ->set('name', 'Blocked')
            ->set('sku', 'SKU-BLOCKED')
            ->set('unit_price', '1.00')
            ->set('cost_price', '0.50')
            ->set('quantity_on_hand', '1')
            ->set('reorder_level', '0')
            ->call('save')
            ->assertForbidden();
    }
}
