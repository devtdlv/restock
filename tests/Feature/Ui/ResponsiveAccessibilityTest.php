<?php

namespace Tests\Feature\Ui;

use App\Enums\AuditAction;
use App\Livewire\AuditLogs\Index as AuditLogsIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\PurchaseOrders\Show as PurchaseOrdersShow;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Purchasing as PurchasingReport;
use App\Livewire\Reports\StockMovements as StockMovementsReport;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResponsiveAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_shell_exposes_responsive_nav_accessibility_hooks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Skip to main content', false)
            ->assertSeeHtml('id="main-content"')
            ->assertSeeHtml('id="app-sidebar"')
            ->assertSeeHtml('aria-controls="app-sidebar"')
            ->assertSeeHtml(':aria-expanded="sidebarOpen.toString()"')
            ->assertSeeHtml(':aria-hidden="navDrawerHidden.toString()"')
            ->assertSeeHtml('x-ref="navToggle"')
            ->assertSeeHtml('x-ref="firstNavLink"');
    }

    public function test_guest_login_exposes_landmarks_and_toggleable_remember_me(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Skip to main content', false)
            ->assertSeeHtml('id="guest-content"')
            ->assertSeeHtml('wire:click="$toggle(\'remember\')"')
            ->assertDontSeeHtml('wire:model="remember"')
            ->assertSeeHtml('for="remember"');
    }

    public function test_product_actions_use_touch_friendly_stacking_classes(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Touch Target Product']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSee('Touch Target Product', false)
            ->assertSeeHtml('min-h-11')
            ->assertSeeHtml('flex-col items-stretch')
            ->assertSeeHtml('app-table-scroll')
            ->assertSeeHtml('caption class="sr-only">Products inventory listing');
    }

    public function test_dialog_markup_and_modal_scroll_constraints_render(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('confirmDeletion', $product->id)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('max-h-[min(90vh,40rem)]')
            ->assertSeeHtml('stockPilotDialog');
    }

    public function test_audit_logs_adopt_shared_table_filter_and_badge_presentation(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Audited Widget']);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => AuditAction::Created,
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'before_values' => null,
            'after_values' => ['name' => 'Audited Widget'],
        ]);

        Livewire::actingAs($user)
            ->test(AuditLogsIndex::class)
            ->assertSeeHtml('caption class="sr-only">Audit log history')
            ->assertSeeHtml('app-table-scroll')
            ->assertSee('Action', false)
            ->assertSee('Entity type', false)
            ->assertSee('Created', false)
            ->call('viewDetails', AuditLog::query()->first()->id)
            ->assertSee('Audit details', false)
            ->assertSeeHtml('id="audit-details-title"')
            ->assertSeeHtml('role="region"');
    }

    public function test_reports_hub_and_subpages_use_shared_presentation_components(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ReportsIndex::class)
            ->assertSee('Inventory overview', false)
            ->assertSee('Low-stock products', false)
            ->assertSee('Out-of-stock products', false)
            ->assertSee('Open report', false);

        Livewire::actingAs($user)
            ->test(StockMovementsReport::class)
            ->assertSee('Back to reports', false)
            ->assertSee('Stock Movement Report', false)
            ->assertSee('From date', false)
            ->assertSee('Movement type', false)
            ->assertSee('No stock movements match', false);

        Livewire::actingAs($user)
            ->test(PurchasingReport::class)
            ->assertSee('Committed ordered value', false)
            ->assertSee('Purchase orders', false)
            ->assertSee('Supplier summary', false)
            ->assertSee('Back to reports', false);
    }

    public function test_purchase_order_detail_header_keeps_actions_and_back_link(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => '2.00'],
        ]);

        Livewire::actingAs($user)
            ->test(PurchaseOrdersShow::class, ['purchaseOrder' => $order])
            ->assertSee($order->number, false)
            ->assertSee('Back to purchase orders', false)
            ->assertSee('Mark as Ordered', false)
            ->assertSee('Ordered items', false)
            ->assertSeeHtml('caption class="sr-only">Ordered items for '.$order->number)
            ->assertSeeHtml('min-h-11');
    }
}
