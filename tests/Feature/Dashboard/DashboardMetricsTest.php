<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PurchaseOrderStatus;
use App\Livewire\Dashboard;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_inventory_and_purchasing_metrics(): void
    {
        $user = User::factory()->create(['name' => 'Dana Operator']);
        $supplier = Supplier::factory()->create();

        $active = Product::factory()->create([
            'name' => 'Shelf Widget',
            'cost_price' => '10.00',
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $active->forceFill(['quantity_on_hand' => 4])->save();

        $healthy = Product::factory()->create([
            'cost_price' => '2.00',
            'reorder_level' => 0,
            'is_active' => true,
        ]);
        $healthy->forceFill(['quantity_on_hand' => 10])->save();

        $service = app(PurchaseOrderService::class);
        $ordered = $service->createDraft($user, $supplier->id, [
            ['product_id' => $healthy->id, 'ordered_quantity' => 3, 'unit_cost' => '1.00'],
        ]);
        $service->markOrdered($ordered, $user);

        $draft = $service->createDraft($user, $supplier->id, [
            ['product_id' => $healthy->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
        ]);
        $this->assertSame(PurchaseOrderStatus::Draft, $draft->status);

        app(SettingsService::class)->update([
            'business_name' => 'StockPilot',
            'currency_symbol' => '$',
            'default_reorder_level' => 0,
            'default_per_page' => 10,
            'date_format' => 'Y-m-d',
        ], $user);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Dana Operator', false)
            ->assertSee('Active products', false)
            ->assertSee('Units on hand', false)
            ->assertSee('Low-stock products', false)
            ->assertSee('Inventory value at cost', false)
            ->assertSee('Outstanding purchase orders', false)
            ->assertSee('$60.00', false)
            ->assertSee('Needs attention', false)
            ->assertSee('Recent stock movements', false)
            ->assertSee('Shelf Widget', false)
            ->assertSee('Low stock', false)
            ->assertSee(route('reports.low-stock'), false)
            ->assertSee(route('settings.index'), false);

        $this->assertSame(1, PurchaseOrder::query()->where('status', PurchaseOrderStatus::Ordered)->count());
    }
}
