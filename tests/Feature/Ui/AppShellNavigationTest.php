<?php

namespace Tests\Feature\Ui;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_shell_exposes_grouped_navigation_and_skip_link(): void
    {
        $user = User::factory()->create(['name' => 'Shell Tester']);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Skip to main content', false)
            ->assertSee('aria-controls="app-sidebar"', false)
            ->assertSee('Overview', false)
            ->assertSee('Inventory', false)
            ->assertSee('Purchasing', false)
            ->assertSee('Insights', false)
            ->assertSee('Administration', false)
            ->assertSee('Toggle navigation', false)
            ->assertSee('Log out', false)
            ->getContent();

        foreach ([
            'Dashboard',
            'Products',
            'Categories',
            'Stock Movements',
            'Suppliers',
            'Purchase Orders',
            'Reports',
            'Audit Logs',
            'Settings',
        ] as $label) {
            $this->assertStringContainsString('>'.$label.'</span>', $html);
        }

        foreach ([
            route('dashboard'),
            route('products.index'),
            route('categories.index'),
            route('stock-movements.index'),
            route('suppliers.index'),
            route('purchase-orders.index'),
            route('reports.index'),
            route('audit-logs.index'),
            route('settings.index'),
        ] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $html);
        }
    }

    public function test_page_title_appears_in_mobile_header_and_main_heading(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Products', false)
            ->getContent();

        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertStringContainsString('hidden min-w-0 lg:block', $html);
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*Products\s*<\/h1>/',
            $html,
        );
    }

    public function test_dashboard_nav_item_is_marked_current_on_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeHtml('aria-current="page"')
            ->assertSee('Dashboard', false);
    }

    public function test_purchase_order_detail_keeps_purchase_orders_nav_active(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.50'],
        ]);

        $this->actingAs($user)
            ->get(route('purchase-orders.show', $order))
            ->assertOk()
            ->assertSeeHtml('aria-current="page"')
            ->assertSee('Purchase Orders', false)
            ->assertSee($order->number, false);
    }

    public function test_guests_still_redirect_from_authenticated_shell_routes(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }
}
