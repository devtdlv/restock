<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockMovementException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockMovementService::class);
    }

    public function test_stock_in_increases_quantity_with_accurate_before_and_after(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $movement = $this->service->apply(
            $product,
            StockMovementType::StockIn,
            15,
            $user,
            'Received PO-1',
        );

        $this->assertSame(0, $movement->quantity_before);
        $this->assertSame(15, $movement->quantity_change);
        $this->assertSame(15, $movement->quantity_after);
        $this->assertSame(15, $product->fresh()->quantity_on_hand);
        $this->assertSame(StockMovementType::StockIn, $movement->type);
    }

    public function test_stock_out_decreases_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->service->apply($product, StockMovementType::StockIn, 20, $user, 'Seed');

        $movement = $this->service->apply(
            $product,
            StockMovementType::StockOut,
            7,
            $user,
            'Sold units',
        );

        $this->assertSame(20, $movement->quantity_before);
        $this->assertSame(-7, $movement->quantity_change);
        $this->assertSame(13, $movement->quantity_after);
        $this->assertSame(13, $product->fresh()->quantity_on_hand);
    }

    public function test_positive_and_negative_adjustments_require_reason(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->service->apply($product, StockMovementType::StockIn, 10, $user, 'Seed');

        $up = $this->service->apply(
            $product,
            StockMovementType::Adjustment,
            4,
            $user,
            'Cycle count gain',
        );

        $this->assertSame(14, $product->fresh()->quantity_on_hand);
        $this->assertSame(4, $up->quantity_change);

        $down = $this->service->apply(
            $product,
            StockMovementType::Adjustment,
            -3,
            $user,
            'Cycle count loss',
        );

        $this->assertSame(11, $product->fresh()->quantity_on_hand);
        $this->assertSame(-3, $down->quantity_change);

        $this->expectException(InvalidStockMovementException::class);

        $this->service->apply(
            $product,
            StockMovementType::Adjustment,
            1,
            $user,
            null,
        );
    }

    public function test_insufficient_stock_is_rejected_and_rolled_back(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->service->apply($product, StockMovementType::StockIn, 5, $user, 'Seed');

        try {
            $this->service->apply(
                $product,
                StockMovementType::StockOut,
                9,
                $user,
                'Too many',
            );
            $this->fail('Expected insufficient stock rejection.');
        } catch (InsufficientStockException) {
            $this->assertSame(5, $product->fresh()->quantity_on_hand);
            $this->assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        }
    }

    public function test_zero_quantity_movements_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(InvalidStockMovementException::class);

        $this->service->apply(
            $product,
            StockMovementType::StockIn,
            0,
            $user,
            'Empty',
        );
    }

    public function test_invalid_signs_for_stock_in_and_stock_out_are_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        try {
            $this->service->apply(
                $product,
                StockMovementType::StockIn,
                -5,
                $user,
                'Bad in',
            );
            $this->fail('Negative stock in should fail.');
        } catch (InvalidStockMovementException) {
            $this->assertTrue(true);
        }

        try {
            $this->service->apply(
                $product,
                StockMovementType::StockOut,
                -2,
                $user,
                'Bad out',
            );
            $this->fail('Negative absolute stock out should fail.');
        } catch (InvalidStockMovementException) {
            $this->assertSame(0, $product->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_zero_adjustment_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(InvalidStockMovementException::class);

        $this->service->apply(
            $product,
            StockMovementType::Adjustment,
            0,
            $user,
            'No change',
        );
    }

    public function test_multiple_sequential_movements_keep_running_balance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->service->apply($product, StockMovementType::StockIn, 10, $user, 'A');
        $this->service->apply($product, StockMovementType::StockOut, 3, $user, 'B');
        $this->service->apply($product, StockMovementType::StockIn, 5, $user, 'C');
        $final = $this->service->apply($product, StockMovementType::Adjustment, -2, $user, 'D');

        $this->assertSame(10, $product->fresh()->quantity_on_hand);
        $this->assertSame(12, $final->quantity_before);
        $this->assertSame(10, $final->quantity_after);
        $this->assertSame(4, StockMovement::query()->where('product_id', $product->id)->count());
    }

    public function test_opening_stock_creates_traceable_stock_in_movement(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $movement = $this->service->recordOpeningStock($product, 18, $user);

        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::StockIn, $movement->type);
        $this->assertSame(18, $movement->quantity_change);
        $this->assertSame(StockMovementService::OPENING_STOCK_REASON, $movement->reason);
        $this->assertSame(18, $product->fresh()->quantity_on_hand);
    }

    public function test_opening_stock_of_zero_creates_no_movement(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->assertNull($this->service->recordOpeningStock($product, 0, $user));
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
    }

    public function test_failed_operations_roll_back_quantity_and_movement_records(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->service->apply($product, StockMovementType::StockIn, 6, $user, 'Seed');

        $beforeCount = StockMovement::query()->where('product_id', $product->id)->count();
        $beforeQty = $product->fresh()->quantity_on_hand;

        try {
            $this->service->apply(
                $product,
                StockMovementType::StockOut,
                $beforeQty + 100,
                $user,
                'Impossible',
            );
            $this->fail('Expected insufficient stock rejection.');
        } catch (InsufficientStockException) {
            $this->assertSame($beforeQty, $product->fresh()->quantity_on_hand);
            $this->assertSame(
                $beforeCount,
                StockMovement::query()->where('product_id', $product->id)->count()
            );
        }
    }

    public function test_mass_assignment_cannot_change_quantity_on_hand(): void
    {
        $product = Product::factory()->create();

        $product->update([
            'name' => 'Renamed',
            'quantity_on_hand' => 50,
        ]);

        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
    }
}
