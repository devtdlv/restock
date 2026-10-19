<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\Index;
use App\Models\Product;
use App\Models\User;
use App\Services\Reports\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_reports_overview(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_reports_navigation_and_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports & Analytics')
            ->assertSee('Inventory overview', false)
            ->assertSee('Low Stock Report', false)
            ->assertSee('Stock Movement Report', false)
            ->assertSee('Purchasing Report', false)
            ->assertSee(route('reports.low-stock'), false)
            ->assertSee(route('reports.stock-movements'), false)
            ->assertSee(route('reports.purchasing'), false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reports', false)
            ->assertSee(route('reports.index'), false);
    }

    public function test_inventory_overview_metrics_use_active_products_and_decimal_safe_value(): void
    {
        $user = User::factory()->create();

        $activeA = Product::factory()->create([
            'name' => 'Active Alpha',
            'cost_price' => '10.25',
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $activeA->forceFill(['quantity_on_hand' => 4])->save();

        $activeB = Product::factory()->create([
            'name' => 'Active Beta',
            'cost_price' => '2.50',
            'reorder_level' => 0,
            'is_active' => true,
        ]);
        $activeB->forceFill(['quantity_on_hand' => 10])->save();

        $outOfStock = Product::factory()->create([
            'name' => 'Active Empty',
            'cost_price' => '9.99',
            'reorder_level' => 3,
            'is_active' => true,
        ]);
        $outOfStock->forceFill(['quantity_on_hand' => 0])->save();

        $inactive = Product::factory()->inactive()->create([
            'name' => 'Inactive Stocked',
            'cost_price' => '100.00',
            'reorder_level' => 50,
        ]);
        $inactive->forceFill(['quantity_on_hand' => 100])->save();

        $metrics = app(InventoryReportService::class)->overviewMetrics();

        $this->assertSame(3, $metrics['active_products']);
        $this->assertSame(1, $metrics['inactive_products']);
        $this->assertSame(14, $metrics['total_units_on_hand']);
        // 4 * 10.25 + 10 * 2.50 + 0 * 9.99 = 41.00 + 25.00 = 66.00
        $this->assertSame('66.00', $metrics['inventory_value_at_cost']);
        $this->assertSame(2, $metrics['low_stock_products']);
        $this->assertSame(1, $metrics['out_of_stock_products']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Active products', false)
            ->assertSee('active products only', false)
            ->assertSee('66.00', false)
            ->assertSee('14', false);
    }

    public function test_empty_database_shows_zero_metrics_gracefully(): void
    {
        $user = User::factory()->create();
        $metrics = app(InventoryReportService::class)->overviewMetrics();

        $this->assertSame(0, $metrics['active_products']);
        $this->assertSame(0, $metrics['inactive_products']);
        $this->assertSame(0, $metrics['total_units_on_hand']);
        $this->assertSame('0.00', $metrics['inventory_value_at_cost']);
        $this->assertSame(0, $metrics['low_stock_products']);
        $this->assertSame(0, $metrics['out_of_stock_products']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('0.00', false)
            ->assertOk();
    }

    public function test_reports_overview_is_read_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['cost_price' => '5.00', 'reorder_level' => 1]);
        $product->forceFill(['quantity_on_hand' => 3])->save();

        Livewire::actingAs($user)->test(Index::class)->assertOk();

        $this->assertSame(3, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
