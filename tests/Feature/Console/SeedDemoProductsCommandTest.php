<?php

namespace Tests\Feature\Console;

use App\Enums\StockLevel;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog\ProductCatalogService;
use App\Support\Demo\DemoProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contains_exactly_100_definitions_with_expected_distribution(): void
    {
        $definitions = DemoProductCatalog::definitions();

        $this->assertCount(100, $definitions);
        $this->assertSame([
            'Computer Accessories' => 20,
            'Networking Equipment' => 15,
            'Office Supplies' => 15,
            'Storage Devices' => 15,
            'Audio & Video' => 10,
            'Cables & Adapters' => 10,
            'Printing Supplies' => 10,
            'Power & Charging' => 5,
        ], DemoProductCatalog::categoryDistribution());
        $this->assertSame([
            'in_stock' => 60,
            'low_stock' => 25,
            'out_of_stock' => 15,
        ], DemoProductCatalog::stockTierDistribution());

        $skus = DemoProductCatalog::skus();
        $this->assertCount(100, array_unique($skus));

        foreach ($definitions as $definition) {
            $this->assertStringStartsWith(DemoProductCatalog::SKU_PREFIX, $definition['sku']);
            $this->assertGreaterThan(0, (float) $definition['unit_price']);
            $this->assertGreaterThan(0, (float) $definition['cost_price']);
            $this->assertLessThan((float) $definition['unit_price'], (float) $definition['cost_price']);
        }
    }

    public function test_dry_run_creates_no_records(): void
    {
        $user = User::factory()->create();
        $beforeProducts = Product::query()->count();
        $beforeMovements = StockMovement::query()->count();
        $beforeCategories = Category::query()->count();

        $this->artisan('stockpilot:demo-products', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run only')
            ->assertSuccessful();

        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame($beforeCategories, Category::query()->count());
        $this->assertTrue($user->exists());
    }

    public function test_command_seeds_demo_products_with_stock_distribution_and_preserves_existing(): void
    {
        $user = User::factory()->create();
        $existing = app(ProductCatalogService::class)->create(
            [
                'name' => 'Wireless Mouse',
                'sku' => 'MOUSE-001',
                'description' => 'Existing product',
                'unit_price' => '899.00',
                'cost_price' => '450.00',
                'reorder_level' => 5,
                'is_active' => true,
            ],
            [],
            $user,
            openingStock: 32,
        );

        $existingMovements = StockMovement::query()->where('product_id', $existing->id)->count();
        $existingQty = (int) $existing->quantity_on_hand;

        $this->artisan('stockpilot:demo-products', ['--force' => true])
            ->expectsOutputToContain('Created products: 100')
            ->assertSuccessful();

        $this->assertSame(101, Product::query()->count());
        $this->assertSame($existingQty, (int) $existing->fresh()->quantity_on_hand);
        $this->assertSame($existingMovements, StockMovement::query()->where('product_id', $existing->id)->count());
        $this->assertSame('MOUSE-001', $existing->fresh()->sku);

        $demoProducts = Product::query()
            ->where('sku', 'like', DemoProductCatalog::SKU_PREFIX.'%')
            ->with('categories')
            ->get();

        $this->assertCount(100, $demoProducts);

        $tierCounts = [
            StockLevel::InStock->value => 0,
            StockLevel::LowStock->value => 0,
            StockLevel::OutOfStock->value => 0,
        ];

        foreach ($demoProducts as $product) {
            $tierCounts[$product->stockLevel()->value]++;
        }

        $this->assertSame(60, $tierCounts[StockLevel::InStock->value]);
        $this->assertSame(25, $tierCounts[StockLevel::LowStock->value]);
        $this->assertSame(15, $tierCounts[StockLevel::OutOfStock->value]);

        $nonzero = $demoProducts->filter(fn (Product $product) => (int) $product->quantity_on_hand > 0);
        $this->assertCount(85, $nonzero);

        foreach ($nonzero as $product) {
            $movement = StockMovement::query()
                ->where('product_id', $product->id)
                ->where('type', StockMovementType::StockIn)
                ->sole();

            $this->assertSame(0, $movement->quantity_before);
            $this->assertSame((int) $product->quantity_on_hand, $movement->quantity_after);
            $this->assertSame((int) $product->quantity_on_hand, $movement->quantity_change);
            $this->assertSame(DemoProductCatalog::OPENING_REASON, $movement->reason);
            $this->assertSame($user->id, $movement->user_id);
        }

        $zeroStock = $demoProducts->filter(fn (Product $product) => (int) $product->quantity_on_hand === 0);
        $this->assertCount(15, $zeroStock);

        foreach ($zeroStock as $product) {
            $this->assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());
        }

        $this->assertSame(20, Product::query()
            ->where('sku', 'like', 'DEMO-ACC-%')
            ->whereHas('categories', fn ($q) => $q->where('name', 'Computer Accessories'))
            ->count());
        $this->assertSame(5, Product::query()
            ->where('sku', 'like', 'DEMO-PWR-%')
            ->whereHas('categories', fn ($q) => $q->where('name', 'Power & Charging'))
            ->count());
    }

    public function test_command_is_idempotent_on_rerun(): void
    {
        User::factory()->create();

        $this->artisan('stockpilot:demo-products', ['--force' => true])->assertSuccessful();
        $productCount = Product::query()->count();
        $movementCount = StockMovement::query()->count();
        $categoryCount = Category::query()->count();

        $this->artisan('stockpilot:demo-products', ['--force' => true])
            ->expectsOutputToContain('Nothing to insert')
            ->assertSuccessful();

        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame($categoryCount, Category::query()->count());
        $this->assertSame(100, Product::query()->where('sku', 'like', DemoProductCatalog::SKU_PREFIX.'%')->count());
    }

    public function test_production_environment_is_refused(): void
    {
        User::factory()->create();
        $this->app['env'] = 'production';

        $this->artisan('stockpilot:demo-products', ['--force' => true])
            ->expectsOutputToContain('Refusing to run')
            ->assertFailed();

        $this->assertSame(0, Product::query()->count());
    }

    public function test_existing_demo_sku_is_skipped_without_overwrite(): void
    {
        $user = User::factory()->create();
        $original = app(ProductCatalogService::class)->create(
            [
                'name' => 'Preexisting Demo Item',
                'sku' => 'DEMO-ACC-001',
                'description' => 'Must not be overwritten',
                'unit_price' => '10.00',
                'cost_price' => '5.00',
                'reorder_level' => 2,
                'is_active' => true,
            ],
            [],
            $user,
            openingStock: 4,
        );

        $this->artisan('stockpilot:demo-products', ['--force' => true])
            ->expectsOutputToContain('Created products: 99')
            ->assertSuccessful();

        $original->refresh();
        $this->assertSame('Preexisting Demo Item', $original->name);
        $this->assertSame(4, (int) $original->quantity_on_hand);
        $this->assertSame(100, Product::query()->where('sku', 'like', DemoProductCatalog::SKU_PREFIX.'%')->count());
    }

    public function test_command_fails_without_users(): void
    {
        $this->artisan('stockpilot:demo-products', ['--force' => true])
            ->expectsOutputToContain('No users found')
            ->assertFailed();

        $this->assertSame(0, Product::query()->count());
    }
}
