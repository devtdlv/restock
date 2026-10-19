<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyOpeningBalanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_creates_legacy_opening_balance_without_changing_quantity_or_user(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-LEGACY-1']);
        $product->forceFill(['quantity_on_hand' => 30])->save();

        $this->artisan('inventory:reconcile-legacy-opening-balances')
            ->assertSuccessful()
            ->expectsOutputToContain('Created 1 legacy opening balance movement(s).');

        $product->refresh();
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();

        $this->assertSame(30, $product->quantity_on_hand);
        $this->assertSame(StockMovementType::LegacyOpening, $movement->type);
        $this->assertSame(0, $movement->quantity_before);
        $this->assertSame(30, $movement->quantity_change);
        $this->assertSame(30, $movement->quantity_after);
        $this->assertNull($movement->user_id);
        $this->assertSame(StockMovementService::LEGACY_OPENING_REASON, $movement->reason);
    }

    public function test_reconciliation_is_idempotent_and_skips_products_with_history(): void
    {
        $user = User::factory()->create();
        $legacy = Product::factory()->create(['sku' => 'SKU-LEGACY-2']);
        $legacy->forceFill(['quantity_on_hand' => 12])->save();

        $tracked = Product::factory()->create(['sku' => 'SKU-TRACKED']);
        app(StockMovementService::class)->apply(
            $tracked,
            StockMovementType::StockIn,
            4,
            $user,
            'Already tracked',
        );

        $zeroStock = Product::factory()->create(['sku' => 'SKU-ZERO']);

        $this->artisan('inventory:reconcile-legacy-opening-balances')->assertSuccessful();
        $this->artisan('inventory:reconcile-legacy-opening-balances')
            ->assertSuccessful()
            ->expectsOutputToContain('No products required legacy opening balance reconciliation.');

        $this->assertSame(1, StockMovement::query()->where('product_id', $legacy->id)->count());
        $this->assertSame(12, $legacy->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::query()->where('product_id', $tracked->id)->count());
        $this->assertSame(StockMovementType::StockIn, $tracked->stockMovements()->first()->type);
        $this->assertSame(0, StockMovement::query()->where('product_id', $zeroStock->id)->count());
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $legacy->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_service_level_legacy_opening_preserves_quantity_and_avoids_duplicates(): void
    {
        $service = app(StockMovementService::class);
        $product = Product::factory()->create();
        $product->forceFill(['quantity_on_hand' => 8])->save();

        $first = $service->recordLegacyOpeningBalance($product);
        $second = $service->recordLegacyOpeningBalance($product->fresh());

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(8, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        $this->assertNull($first->user_id);
    }
}
