<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Livewire\StockMovements\Index;
use App\Models\Product;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_stock_movements_page(): void
    {
        $this->get(route('stock-movements.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_stock_movement_history(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Warehouse Lamp',
            'sku' => 'SKU-LAMP-1',
        ]);

        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            12,
            $user,
            'Initial receipt',
        );

        $this->actingAs($user)
            ->get(route('stock-movements.index'))
            ->assertOk()
            ->assertSee('Stock Movements', false)
            ->assertSee('Warehouse Lamp', false)
            ->assertSee('SKU-LAMP-1', false)
            ->assertSee('Initial receipt', false)
            ->assertSee($user->name, false);
    }

    public function test_stock_movements_can_be_searched_and_filtered_by_type(): void
    {
        $user = User::factory()->create();
        $alpha = Product::factory()->create(['name' => 'Alpha Widget', 'sku' => 'SKU-ALPHA']);
        $beta = Product::factory()->create(['name' => 'Beta Gadget', 'sku' => 'SKU-BETA']);
        $service = app(StockMovementService::class);

        $service->apply($alpha, StockMovementType::StockIn, 5, $user, 'Alpha in');
        $service->apply($beta, StockMovementType::StockIn, 3, $user, 'Beta in');
        $service->apply($beta, StockMovementType::StockOut, 1, $user, 'Beta out');
        $service->apply($alpha, StockMovementType::Adjustment, -1, $user, 'Alpha adjust');

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'SKU-ALPHA')
            ->assertSee('Alpha Widget', false)
            ->assertDontSee('Beta Gadget', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('type', StockMovementType::StockIn->value)
            ->assertSee('Alpha in', false)
            ->assertSee('Beta in', false)
            ->assertDontSee('Beta out', false)
            ->assertDontSee('Alpha adjust', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('type', StockMovementType::StockOut->value)
            ->assertSee('Beta out', false)
            ->assertDontSee('Alpha in', false)
            ->assertDontSee('Alpha adjust', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('type', StockMovementType::Adjustment->value)
            ->assertSee('Alpha adjust', false)
            ->assertDontSee('Alpha in', false)
            ->assertDontSee('Beta out', false);
    }

    public function test_stock_movements_are_ordered_newest_first_and_paginated(): void
    {
        $user = User::factory()->create();
        $service = app(StockMovementService::class);
        $product = Product::factory()->create(['name' => 'Paged Stock Product', 'sku' => 'SKU-PAGE-STOCK']);

        for ($i = 1; $i <= 16; $i++) {
            $service->apply(
                $product,
                StockMovementType::StockIn,
                1,
                $user,
                "Paged reason {$i}",
            );
        }

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Paged reason 16', false)
            ->assertDontSee('Paged reason 1</', false)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertSee('Paged reason 1', false);
    }

    public function test_stock_movements_ui_is_read_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            3,
            $user,
            'Read-only history check',
        );

        $component = Livewire::actingAs($user)->test(Index::class);

        $this->assertFalse(method_exists($component->instance(), 'delete'));
        $this->assertFalse(method_exists($component->instance(), 'save'));
        $this->assertFalse(method_exists($component->instance(), 'apply'));
        $this->assertFalse(method_exists($component->instance(), 'applyStockAdjustment'));

        $component
            ->assertSee('Audit Logs', false)
            ->assertDontSee('Confirm delete', false);

        $this->assertSame(1, \App\Models\StockMovement::query()->count());
    }
}
