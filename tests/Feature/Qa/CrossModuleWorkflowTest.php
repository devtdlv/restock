<?php

namespace Tests\Feature\Qa;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Livewire\Dashboard;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\LowStock as LowStockReport;
use App\Livewire\Reports\Purchasing as PurchasingReport;
use App\Livewire\Reports\StockMovements as StockMovementReport;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Catalog\SupplierCatalogService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Reports\InventoryReportService;
use App\Services\Settings\SettingsService;
use App\Services\Stock\StockMovementService;
use App\Support\AppDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrossModuleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_stock_movement_and_reports_workflow(): void
    {
        $user = User::factory()->create();
        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Workflow Widget',
                'sku' => 'SKU-WF-1',
                'unit_price' => '20.00',
                'cost_price' => '8.00',
                'reorder_level' => 5,
                'is_active' => true,
            ],
            [],
            $user,
            10,
        );

        $stock = app(StockMovementService::class);
        $stock->apply($product->fresh(), StockMovementType::StockIn, 5, $user, 'Extra receipt');
        $stock->apply($product->fresh(), StockMovementType::StockOut, 3, $user, 'Pick ticket');
        $stock->apply($product->fresh(), StockMovementType::Adjustment, -1, $user, 'Damage write-off');

        $product->refresh();
        $this->assertSame(11, $product->quantity_on_hand);
        $this->assertSame(4, StockMovement::query()->where('product_id', $product->id)->count());
        $this->assertSame(
            11,
            (int) StockMovement::query()->where('product_id', $product->id)->sum('quantity_change'),
        );

        $metrics = app(InventoryReportService::class)->overviewMetrics();
        $this->assertSame(1, $metrics['active_products']);
        $this->assertSame(11, $metrics['total_units_on_hand']);
        $this->assertSame('88.00', $metrics['inventory_value_at_cost']);
        $this->assertSame(0, $metrics['low_stock_products']);

        Livewire::actingAs($user)
            ->test(ReportsIndex::class)
            ->assertSee('$88.00', false)
            ->assertSee('Active products', false);

        Livewire::actingAs($user)
            ->test(StockMovementReport::class)
            ->assertSee('Extra receipt', false)
            ->assertSee('Pick ticket', false)
            ->assertSee('Damage write-off', false)
            ->assertSee('+5', false);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('$88.00', false)
            ->assertSee('11', false);
    }

    public function test_supplier_purchase_order_receiving_stock_and_reports_workflow(): void
    {
        $user = User::factory()->create();
        $supplier = app(SupplierCatalogService::class)->create([
            'name' => 'North Supply',
            'code' => 'NORTH-1',
            'is_active' => true,
        ], $user);

        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'PO Widget',
                'sku' => 'SKU-PO-1',
                'unit_price' => '15.00',
                'cost_price' => '9.00',
                'reorder_level' => 2,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $purchasing = app(PurchaseOrderService::class);
        $order = $purchasing->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 20, 'unit_cost' => '7.50'],
        ], '2026-10-01');
        $order = $purchasing->markOrdered($order, $user);
        $itemId = $order->items->first()->id;

        $purchasing->receive($order, [$itemId => 8], $user, 'qa-receipt-partial');
        $order->refresh()->load('items');
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->status);
        $this->assertSame(8, $product->fresh()->quantity_on_hand);
        $this->assertSame(12, $order->items->first()->outstandingQuantity());

        $product->update(['cost_price' => '99.00']);

        $purchasing->receive($order->fresh(), [$itemId => 12], $user, 'qa-receipt-final');
        $order->refresh()->load('items');
        $this->assertSame(PurchaseOrderStatus::Received, $order->status);
        $this->assertSame(20, $product->fresh()->quantity_on_hand);
        $this->assertSame(0, $order->items->first()->outstandingQuantity());
        $this->assertSame('7.50', (string) $order->items->first()->unit_cost);
        $this->assertSame('150.00', $order->totalAmount());

        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::StockIn)->count());
        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', PurchaseOrder::class)
                ->where('entity_id', $order->id)
                ->exists()
        );

        Livewire::actingAs($user)
            ->test(PurchasingReport::class)
            ->assertSee($order->number, false)
            ->assertSee('North Supply', false)
            ->assertSee('$150.00', false)
            ->assertSee('Fully received', false);

        Livewire::actingAs($user)
            ->test(StockMovementReport::class)
            ->set('productId', (string) $product->id)
            ->assertSee('PO Widget', false)
            ->assertSee('Received '.$order->number, false);

        $metrics = app(InventoryReportService::class)->overviewMetrics();
        // Valuation uses current product cost, not historical PO cost.
        $this->assertSame('1980.00', $metrics['inventory_value_at_cost']);
    }

    public function test_settings_drive_new_product_defaults_pagination_and_display(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'Harbor Inventory')
            ->set('default_reorder_level', '12')
            ->set('default_per_page', '15')
            ->set('date_format', 'd/m/Y')
            ->set('currency_symbol', '€')
            ->call('save')
            ->assertHasNoErrors();

        app(SettingsService::class)->forgetCache();
        $this->assertSame(12, app(SettingsService::class)->defaultReorderLevel());
        $this->assertSame(15, app(SettingsService::class)->defaultPerPage());
        $this->assertSame('€12.50', app(AppDisplay::class)->money('12.50'));
        $this->assertSame('19/10/2026', app(AppDisplay::class)->date('2026-10-19'));

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->assertSet('reorder_level', '12');

        for ($i = 1; $i <= 16; $i++) {
            Product::factory()->create([
                'name' => sprintf('Paged Product %02d', $i),
                'sku' => 'SKU-PAGE-'.$i,
                'reorder_level' => 100,
            ])->forceFill(['quantity_on_hand' => 0])->save();
        }

        Livewire::actingAs($user)
            ->test(LowStockReport::class)
            ->assertSee('Paged Product 01', false)
            ->assertDontSee('Paged Product 16', false)
            ->assertSee('aria-label="Pagination Navigation"', false);

        Livewire::actingAs($user)
            ->test(ReportsIndex::class)
            ->assertSee('€0.00', false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Harbor Inventory', false);
    }

    public function test_catalog_changes_appear_in_audit_logs(): void
    {
        $user = User::factory()->create(['name' => 'Audit Clerk']);
        $products = app(ProductCatalogService::class);
        $product = $products->create(
            [
                'name' => 'Audited Item',
                'sku' => 'SKU-AUD-1',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 1,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $products->update($product, [
            'name' => 'Audited Item Renamed',
            'sku' => 'SKU-AUD-1',
            'unit_price' => '1.00',
            'cost_price' => '0.50',
            'reorder_level' => 1,
            'is_active' => true,
        ], [], $user);

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Created)
                ->exists()
        );
        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Updated)
                ->exists()
        );

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Audited Item Renamed', false)
            ->assertSee('Audit Clerk', false)
            ->assertSee('Settings', false);

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'Audited Settings Co')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Setting::class)
                ->where('action', AuditAction::Created)
                ->exists()
        );
    }

    public function test_mass_assignment_cannot_change_quantity_and_reports_stay_read_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['cost_price' => '5.00', 'reorder_level' => 1]);
        $product->forceFill(['quantity_on_hand' => 4])->save();

        $product->update(['quantity_on_hand' => 999, 'name' => $product->name]);
        $this->assertSame(4, $product->fresh()->quantity_on_hand);

        $beforeMovements = StockMovement::query()->count();
        $beforeOrders = PurchaseOrder::query()->count();

        Livewire::actingAs($user)->test(ReportsIndex::class)->assertOk();
        Livewire::actingAs($user)->test(LowStockReport::class)->assertOk();
        Livewire::actingAs($user)->test(StockMovementReport::class)->assertOk();
        Livewire::actingAs($user)->test(PurchasingReport::class)->assertOk();

        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame($beforeOrders, PurchaseOrder::query()->count());
        $this->assertSame(4, $product->fresh()->quantity_on_hand);
    }
}
