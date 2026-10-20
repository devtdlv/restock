<?php

namespace Tests\Unit\Enums;

use App\Enums\StockLevel;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_level_rules_match_reorder_threshold(): void
    {
        $out = Product::factory()->create(['reorder_level' => 5]);
        $out->forceFill(['quantity_on_hand' => 0])->save();

        $low = Product::factory()->create(['reorder_level' => 5]);
        $low->forceFill(['quantity_on_hand' => 5])->save();

        $ok = Product::factory()->create(['reorder_level' => 5]);
        $ok->forceFill(['quantity_on_hand' => 6])->save();

        $zeroReorder = Product::factory()->create(['reorder_level' => 0]);
        $zeroReorder->forceFill(['quantity_on_hand' => 2])->save();

        $this->assertSame(StockLevel::OutOfStock, $out->stockLevel());
        $this->assertSame(StockLevel::LowStock, $low->stockLevel());
        $this->assertSame(StockLevel::InStock, $ok->stockLevel());
        $this->assertSame(StockLevel::InStock, $zeroReorder->stockLevel());

        $this->assertSame('Out of stock', StockLevel::OutOfStock->label());
        $this->assertSame('danger', StockLevel::OutOfStock->badgeVariant());
        $this->assertSame('warning', StockLevel::LowStock->badgeVariant());
        $this->assertSame('success', StockLevel::InStock->badgeVariant());
    }
}
