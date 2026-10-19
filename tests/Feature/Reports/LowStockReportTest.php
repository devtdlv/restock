<?php

namespace Tests\Feature\Reports;

use App\Livewire\Reports\LowStock;
use App\Models\Product;
use App\Models\User;
use App\Services\Reports\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LowStockReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_low_stock_report(): void
    {
        $this->get(route('reports.low-stock'))
            ->assertRedirect(route('login'));
    }

    public function test_low_stock_includes_threshold_and_out_of_stock_with_suggested_replenishment(): void
    {
        $user = User::factory()->create();
        $service = app(InventoryReportService::class);

        $out = Product::factory()->create([
            'name' => 'Out Item',
            'sku' => 'SKU-OUT',
            'reorder_level' => 8,
            'is_active' => true,
        ]);
        $out->forceFill(['quantity_on_hand' => 0])->save();

        $low = Product::factory()->create([
            'name' => 'Low Item',
            'sku' => 'SKU-LOW',
            'reorder_level' => 10,
            'is_active' => true,
        ]);
        $low->forceFill(['quantity_on_hand' => 4])->save();

        $healthy = Product::factory()->create([
            'name' => 'Healthy Item',
            'sku' => 'SKU-OK',
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $healthy->forceFill(['quantity_on_hand' => 20])->save();

        $this->assertSame(8, $service->suggestedReplenishment($out->fresh()));
        $this->assertSame(6, $service->suggestedReplenishment($low->fresh()));
        $this->assertSame(0, $service->suggestedReplenishment($healthy->fresh()));

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->assertSee('Out Item', false)
            ->assertSee('Low Item', false)
            ->assertDontSee('Healthy Item', false)
            ->assertSee('Suggested replenishment', false)
            ->assertSee('shortage gap only', false);
    }

    public function test_low_stock_search_status_filter_and_urgency_sort(): void
    {
        $user = User::factory()->create();

        $inactiveLow = Product::factory()->inactive()->create([
            'name' => 'Inactive Low',
            'sku' => 'SKU-INACTIVE',
            'reorder_level' => 5,
        ]);
        $inactiveLow->forceFill(['quantity_on_hand' => 1])->save();

        $out = Product::factory()->create([
            'name' => 'Urgent Out',
            'sku' => 'SKU-URGENT',
            'reorder_level' => 3,
        ]);
        $out->forceFill(['quantity_on_hand' => 0])->save();

        $mild = Product::factory()->create([
            'name' => 'Mild Low',
            'sku' => 'SKU-MILD',
            'reorder_level' => 10,
        ]);
        $mild->forceFill(['quantity_on_hand' => 9])->save();

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->assertSee('Urgent Out', false)
            ->assertSee('Mild Low', false)
            ->assertDontSee('Inactive Low', false);

        $html = Livewire::actingAs($user)->test(LowStock::class)->html();
        $this->assertTrue(
            strpos($html, 'Urgent Out') < strpos($html, 'Mild Low'),
            'Out-of-stock items should sort before in-stock low items.',
        );

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->set('status', 'inactive')
            ->assertSee('Inactive Low', false)
            ->assertDontSee('Urgent Out', false);

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->set('status', 'all')
            ->set('search', 'SKU-MILD')
            ->assertSee('Mild Low', false)
            ->assertDontSee('Urgent Out', false)
            ->assertDontSee('Inactive Low', false);
    }

    public function test_low_stock_report_is_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 16; $i++) {
            $product = Product::factory()->create([
                'name' => sprintf('Low Product %02d', $i),
                'sku' => 'SKU-LOW-PAGE-'.$i,
                'reorder_level' => 5,
            ]);
            $product->forceFill(['quantity_on_hand' => 1])->save();
        }

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->assertSee('Low Product 01', false)
            ->assertDontSee('Low Product 16', false)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertSee('Low Product 16', false);
    }

    public function test_low_stock_empty_state(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['reorder_level' => 1]);
        $product->forceFill(['quantity_on_hand' => 50])->save();

        Livewire::actingAs($user)
            ->test(LowStock::class)
            ->assertSee('No low-stock products match', false);
    }

    public function test_low_stock_report_does_not_create_purchase_orders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['reorder_level' => 10]);
        $product->forceFill(['quantity_on_hand' => 0])->save();

        Livewire::actingAs($user)->test(LowStock::class)->assertOk();

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
    }
}
