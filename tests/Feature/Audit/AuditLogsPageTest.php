<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\StockMovementType;
use App\Livewire\AuditLogs\Index;
use App\Livewire\StockMovements\Index as StockMovementsIndex;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_audit_logs_page(): void
    {
        $this->get(route('audit-logs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_audit_history(): void
    {
        $user = User::factory()->create(['name' => 'Auditor', 'email' => 'auditor@example.com']);
        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Logged Product',
                'sku' => 'SKU-LOG-1',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Logs', false)
            ->assertSee('Logged Product', false)
            ->assertSee('Created', false)
            ->assertSee('Auditor', false)
            ->assertSee('Stock Movements', false)
            ->assertDontSee('password', false);

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Created)
                ->exists()
        );
    }

    public function test_audit_logs_are_ordered_newest_first_and_paginated(): void
    {
        $user = User::factory()->create();
        $service = app(ProductCatalogService::class);

        for ($i = 1; $i <= 16; $i++) {
            $service->create(
                [
                    'name' => "Product {$i}",
                    'sku' => 'SKU-PAGE-'.$i,
                    'unit_price' => '1.00',
                    'cost_price' => '0.50',
                    'reorder_level' => 0,
                    'is_active' => true,
                ],
                [],
                $user,
                0,
            );
        }

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Product 16</div>', false)
            ->assertDontSee('Product 1</div>', false)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertSee('Product 1</div>', false);
    }

    public function test_audit_logs_can_be_filtered_and_searched(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Buyer', 'email' => 'bob@example.com']);

        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Filter Product',
                'sku' => 'SKU-FILTER',
                'description' => 'Original',
                'unit_price' => '2.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $alice,
            0,
        );

        app(ProductCatalogService::class)->update(
            $product,
            [
                'name' => 'Filter Product',
                'sku' => 'SKU-FILTER',
                'description' => 'Changed description',
                'unit_price' => '2.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $alice,
        );

        $doomed = app(ProductCatalogService::class)->create(
            [
                'name' => 'Doomed Filter Product',
                'sku' => 'SKU-FILTER-DEL',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $alice,
            0,
        );
        app(ProductCatalogService::class)->delete($doomed, $alice);

        app(CategoryCatalogService::class)->create([
            'name' => 'Filter Category',
            'slug' => 'filter-category',
            'description' => null,
            'is_active' => true,
        ], $bob);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('action', AuditAction::Created->value)
            ->assertSee('Filter Product</div>', false)
            ->assertSee('Filter Category</div>', false)
            ->assertSee('Doomed Filter Product</div>', false)
            ->assertDontSee('changed Description', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('action', AuditAction::Updated->value)
            ->assertSee('Filter Product', false)
            ->assertSee('Description', false)
            ->assertDontSee('Filter Category', false)
            ->assertDontSee('Doomed Filter Product</div>', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('action', AuditAction::Deleted->value)
            ->assertSee('Doomed Filter Product</div>', false)
            ->assertDontSee('Filter Category</div>', false)
            ->assertDontSee('changed Description', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('entityType', Category::class)
            ->assertSee('Filter Category', false)
            ->assertDontSee('Filter Product', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('userId', (string) $bob->id)
            ->assertSee('Filter Category', false)
            ->assertDontSee('Filter Product', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('entityType', Product::class)
            ->set('search', (string) $product->id)
            ->assertSee('Filter Product', false)
            ->assertDontSee('Filter Category', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('search', 'alice@example.com')
            ->assertSee('Filter Product', false)
            ->assertDontSee('Filter Category', false);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->set('search', 'Bob Buyer')
            ->assertSee('Filter Category', false)
            ->assertDontSee('Filter Product</div>', false);
    }

    public function test_guests_cannot_open_audit_details_through_livewire(): void
    {
        $user = User::factory()->create();
        app(ProductCatalogService::class)->create(
            [
                'name' => 'Guest Blocked Product',
                'sku' => 'SKU-GUEST-AUDIT',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $auditId = AuditLog::query()->value('id');

        Livewire::test(Index::class)
            ->call('viewDetails', $auditId)
            ->assertForbidden();
    }

    public function test_audit_details_show_accurate_before_and_after_values(): void
    {
        $user = User::factory()->create();
        $keep = Category::factory()->create(['name' => 'Keep']);
        $add = Category::factory()->create(['name' => 'Add']);

        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Detail Product',
                'sku' => 'SKU-DETAIL',
                'description' => 'Before text',
                'unit_price' => '5.00',
                'cost_price' => '2.00',
                'reorder_level' => 1,
                'is_active' => true,
            ],
            [$keep->id],
            $user,
            0,
        );

        app(ProductCatalogService::class)->update(
            $product,
            [
                'name' => 'Detail Product',
                'sku' => 'SKU-DETAIL',
                'description' => 'After text',
                'unit_price' => '5.00',
                'cost_price' => '2.00',
                'reorder_level' => 1,
                'is_active' => true,
            ],
            [$keep->id, $add->id],
            $user,
        );

        $audit = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Updated)
            ->sole();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('viewDetails', $audit->id)
            ->assertSet('selectedAuditId', $audit->id)
            ->assertSee('Audit details', false)
            ->assertSee('Before text', false)
            ->assertSee('After text', false)
            ->assertSee((string) $keep->id, false)
            ->assertSee((string) $add->id, false)
            ->assertSee('Categories', false)
            ->assertSee('Description', false);
    }

    public function test_deleted_entities_still_appear_in_audit_history(): void
    {
        $user = User::factory()->create();
        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Doomed Product',
                'sku' => 'SKU-DOOMED',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $productId = $product->id;
        app(ProductCatalogService::class)->delete($product, $user);

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Doomed Product', false)
            ->assertSee('Deleted', false)
            ->assertSee('ID '.$productId, false)
            ->call('viewDetails', AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $productId)
                ->where('action', AuditAction::Deleted)
                ->value('id'))
            ->assertSee('Doomed Product', false)
            ->assertSee('None', false);
    }

    public function test_sensitive_fields_are_not_displayed_in_audit_details(): void
    {
        $user = User::factory()->create();

        $audit = AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => AuditAction::Updated,
            'entity_type' => Product::class,
            'entity_id' => 999,
            'before_values' => [
                'name' => 'Secretive',
                'password' => 'super-secret-password',
                'remember_token' => 'token-value',
            ],
            'after_values' => [
                'name' => 'Secretive Updated',
                'password' => 'new-super-secret',
                'api_token' => 'api-token-value',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('viewDetails', $audit->id)
            ->assertSee('Secretive Updated', false)
            ->assertDontSee('super-secret-password', false)
            ->assertDontSee('new-super-secret', false)
            ->assertDontSee('token-value', false)
            ->assertDontSee('api-token-value', false);
    }

    public function test_audit_logs_ui_is_read_only(): void
    {
        $user = User::factory()->create();
        app(ProductCatalogService::class)->create(
            [
                'name' => 'Readonly Product',
                'sku' => 'SKU-RO',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $component = Livewire::actingAs($user)->test(Index::class);

        $this->assertFalse(method_exists($component->instance(), 'delete'));
        $this->assertFalse(method_exists($component->instance(), 'save'));
        $this->assertFalse(method_exists($component->instance(), 'update'));

        $auditId = AuditLog::query()->value('id');

        $component
            ->assertSee('Read-only history', false)
            ->call('viewDetails', $auditId)
            ->assertSee('cannot be edited or deleted', false)
            ->assertDontSee('Confirm delete', false);

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_stock_adjustments_do_not_create_product_audit_updates_and_stock_page_still_works(): void
    {
        $user = User::factory()->create();
        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Stock Product',
                'sku' => 'SKU-STOCK-UI',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
        );

        $beforeAuditCount = AuditLog::query()->count();

        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            4,
            $user,
            'Received for audit UI test',
        );

        $this->assertSame($beforeAuditCount, AuditLog::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::Updated)->count());

        Livewire::actingAs($user)
            ->test(StockMovementsIndex::class)
            ->assertSee('Stock Product', false)
            ->assertSee('Received for audit UI test', false)
            ->assertSee('Audit Logs', false);
    }
}
