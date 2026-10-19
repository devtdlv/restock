<?php

namespace Tests\Feature\Reports;

use App\Enums\StockMovementType;
use App\Livewire\Reports\StockMovements;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_stock_movement_report(): void
    {
        $this->get(route('reports.stock-movements'))
            ->assertRedirect(route('login'));
    }

    public function test_stock_movement_report_shows_exact_history_including_opening_balances(): void
    {
        $user = User::factory()->create(['name' => 'Movement Clerk']);
        $product = Product::factory()->create([
            'name' => 'Tracked Widget',
            'sku' => 'SKU-TRACK',
        ]);
        $service = app(StockMovementService::class);

        $legacyProduct = Product::factory()->create([
            'name' => 'Legacy Widget',
            'sku' => 'SKU-LEGACY',
        ]);
        $legacyProduct->forceFill(['quantity_on_hand' => 6])->save();
        $service->recordLegacyOpeningBalance($legacyProduct);

        $service->apply($product, StockMovementType::StockIn, 10, $user, 'Inbound lot');
        $service->apply($product->fresh(), StockMovementType::StockOut, 3, $user, 'Outbound pick');
        $service->apply($product->fresh(), StockMovementType::Adjustment, -1, $user, 'Cycle count');

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->assertSee('Tracked Widget', false)
            ->assertSee('SKU-TRACK', false)
            ->assertSee('Inbound lot', false)
            ->assertSee('Outbound pick', false)
            ->assertSee('Cycle count', false)
            ->assertSee('Legacy Widget', false)
            ->assertSee('Legacy Opening Balance', false)
            ->assertSee('Movement Clerk', false)
            ->assertSee('+10', false);
    }

    public function test_stock_movement_filters_by_date_product_and_type(): void
    {
        $user = User::factory()->create();
        $alpha = Product::factory()->create(['name' => 'Alpha Filter', 'sku' => 'SKU-AF']);
        $beta = Product::factory()->create(['name' => 'Beta Filter', 'sku' => 'SKU-BF']);
        $service = app(StockMovementService::class);

        Carbon::setTestNow('2026-10-10 12:00:00');
        $service->apply($alpha, StockMovementType::StockIn, 5, $user, 'Alpha early');

        Carbon::setTestNow('2026-10-15 12:00:00');
        $service->apply($beta, StockMovementType::StockIn, 4, $user, 'Beta seed');
        $service->apply($beta->fresh(), StockMovementType::StockOut, 1, $user, 'Beta middle');

        Carbon::setTestNow('2026-10-20 12:00:00');
        $service->apply($alpha->fresh(), StockMovementType::Adjustment, 2, $user, 'Alpha late');

        Carbon::setTestNow();

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('dateFrom', '2026-10-15')
            ->set('dateTo', '2026-10-15')
            ->assertSee('Beta middle', false)
            ->assertDontSee('Alpha early', false)
            ->assertDontSee('Alpha late', false);

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('productId', (string) $alpha->id)
            ->assertSee('Alpha early', false)
            ->assertSee('Alpha late', false)
            ->assertDontSee('Beta middle', false);

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('type', StockMovementType::StockOut->value)
            ->assertSee('Beta middle', false)
            ->assertDontSee('Alpha early', false)
            ->assertDontSee('Alpha late', false);

        // Inclusive date boundaries
        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('dateFrom', '2026-10-10')
            ->set('dateTo', '2026-10-20')
            ->assertSee('Alpha early', false)
            ->assertSee('Beta middle', false)
            ->assertSee('Alpha late', false);
    }

    public function test_stock_movement_report_paginates_and_empty_state(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Paged Report Product']);
        $service = app(StockMovementService::class);

        for ($i = 1; $i <= 21; $i++) {
            $service->apply(
                $product->fresh(),
                StockMovementType::StockIn,
                1,
                $user,
                "Report reason {$i}",
            );
        }

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->assertSee('Report reason 21', false)
            ->assertDontSee('Report reason 1</', false)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertSee('Report reason 1', false);

        Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('dateFrom', '2099-01-01')
            ->assertSee('No stock movements match', false);
    }

    public function test_stock_movement_report_is_read_only(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            7,
            $user,
            'Seed movement',
        );

        $beforeCount = StockMovement::query()->count();
        $beforeQty = $product->fresh()->quantity_on_hand;

        Livewire::actingAs($user)->test(StockMovements::class)->assertOk();

        $this->assertSame($beforeCount, StockMovement::query()->count());
        $this->assertSame($beforeQty, $product->fresh()->quantity_on_hand);
    }
}
