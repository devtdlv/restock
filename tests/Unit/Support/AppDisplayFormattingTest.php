<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\AppDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDisplayFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_and_quantity_formatting_respect_settings(): void
    {
        $user = User::factory()->create();

        app(SettingsService::class)->update([
            'business_name' => 'StockPilot',
            'currency_symbol' => '€',
            'default_reorder_level' => 0,
            'default_per_page' => 10,
            'date_format' => 'Y-m-d',
        ], $user);

        $display = app(AppDisplay::class);

        $this->assertSame('€12.50', $display->money('12.50'));
        $this->assertSame('€14,400.00', $display->money('14400.00'));
        $this->assertSame('1,250', $display->quantity(1250));
        $this->assertSame('+25', $display->quantityChange(25));
        $this->assertSame('-3', $display->quantityChange(-3));
    }
}
