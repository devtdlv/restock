<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\StockMovements\Index as StockMovementsIndex;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_updates_quantity_and_appears_in_history(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Stock In Product', 'sku' => 'SKU-IN-1']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::StockIn->value)
            ->set('adjustmentQuantity', '10')
            ->set('adjustmentReason', 'Received delivery')
            ->call('applyStockAdjustment')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Stock updated for Stock In Product. New quantity: 10.');

        $this->assertSame(10, $product->fresh()->quantity_on_hand);

        Livewire::actingAs($user)
            ->test(StockMovementsIndex::class)
            ->assertSee('Stock In Product', false)
            ->assertSee('Received delivery', false)
            ->assertSee('+10', false);
    }

    public function test_stock_out_decreases_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Stock Out Product']);
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            20,
            $user,
            'Seed',
        );

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::StockOut->value)
            ->set('adjustmentQuantity', '6')
            ->set('adjustmentReason', 'Customer order')
            ->call('applyStockAdjustment')
            ->assertHasNoErrors();

        $this->assertSame(14, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::StockOut->value,
            'quantity_change' => -6,
            'quantity_after' => 14,
        ]);
    }

    public function test_positive_and_negative_adjustments_work(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            10,
            $user,
            'Seed',
        );

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::Adjustment->value)
            ->set('adjustmentQuantity', '3')
            ->set('adjustmentReason', 'Found units')
            ->call('applyStockAdjustment')
            ->assertHasNoErrors();

        $this->assertSame(13, $product->fresh()->quantity_on_hand);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::Adjustment->value)
            ->set('adjustmentQuantity', '-4')
            ->set('adjustmentReason', 'Damaged units')
            ->call('applyStockAdjustment')
            ->assertHasNoErrors();

        $this->assertSame(9, $product->fresh()->quantity_on_hand);
    }

    public function test_insufficient_stock_is_validated(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            2,
            $user,
            'Seed',
        );

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::StockOut->value)
            ->set('adjustmentQuantity', '5')
            ->set('adjustmentReason', 'Too many')
            ->call('applyStockAdjustment')
            ->assertHasErrors(['adjustmentQuantity']);

        $this->assertSame(2, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_reason_is_required_for_stock_adjustments(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->set('adjustmentType', StockMovementType::StockIn->value)
            ->set('adjustmentQuantity', '4')
            ->set('adjustmentReason', '')
            ->call('applyStockAdjustment')
            ->assertHasErrors(['adjustmentReason']);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_guests_cannot_apply_stock_adjustments_through_livewire(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->assertForbidden();

        Livewire::test(ProductsIndex::class)
            ->set('adjustingProductId', $product->id)
            ->set('adjustmentType', StockMovementType::StockIn->value)
            ->set('adjustmentQuantity', '5')
            ->set('adjustmentReason', 'Unauthorized')
            ->call('applyStockAdjustment')
            ->assertForbidden();

        $this->assertSame(0, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_products_page_and_history_stay_synchronized_after_adjustment(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Synced Product', 'sku' => 'SKU-SYNC']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->assertSet('adjustingCurrentQuantity', 0)
            ->set('adjustmentType', StockMovementType::StockIn->value)
            ->set('adjustmentQuantity', '7')
            ->set('adjustmentReason', 'Sync check')
            ->call('applyStockAdjustment')
            ->assertHasNoErrors();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertSee('Synced Product', false)
            ->assertSee('SKU-SYNC', false);

        $movement = StockMovement::query()->where('product_id', $product->id)->sole();

        $this->assertSame(0, $movement->quantity_before);
        $this->assertSame(7, $movement->quantity_after);
        $this->assertSame(7, $product->fresh()->quantity_on_hand);

        Livewire::actingAs($user)
            ->test(StockMovementsIndex::class)
            ->assertSee('Synced Product', false)
            ->assertSee('Sync check', false);
    }
}
