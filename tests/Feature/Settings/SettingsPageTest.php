<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\AppDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_settings(): void
    {
        $this->get(route('settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_settings_defaults_and_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Settings', false)
            ->assertSee('Business profile', false)
            ->assertSee('Inventory preferences', false)
            ->assertSee('Display preferences', false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.index'), false)
            ->assertSee('StockPilot', false);

        $settings = app(SettingsService::class)->current();
        $this->assertSame('StockPilot', $settings->business_name);
        $this->assertSame(0, (int) $settings->default_reorder_level);
        $this->assertSame(10, (int) $settings->default_per_page);
        $this->assertSame('Y-m-d', $settings->date_format);
        $this->assertSame('$', $settings->currency_symbol);
        $this->assertFalse($settings->exists);
    }

    public function test_settings_can_be_persisted_and_are_audited(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'Acme Warehouse')
            ->set('business_email', 'ops@acme.test')
            ->set('business_phone', '555-0100')
            ->set('business_address', '12 Dock Road')
            ->set('default_reorder_level', '7')
            ->set('default_per_page', '25')
            ->set('date_format', 'd/m/Y')
            ->set('currency_symbol', '₱')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Settings saved', false);

        $this->assertDatabaseHas('settings', [
            'business_name' => 'Acme Warehouse',
            'business_email' => 'ops@acme.test',
            'default_reorder_level' => 7,
            'default_per_page' => 25,
            'date_format' => 'd/m/Y',
            'currency_symbol' => '₱',
        ]);

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Setting::class)
                ->where('action', AuditAction::Created)
                ->exists()
        );

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'Acme Warehouse Co')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Setting::class)
                ->where('action', AuditAction::Updated)
                ->exists()
        );

        app(SettingsService::class)->forgetCache();
        $this->assertSame('Acme Warehouse Co', app(SettingsService::class)->businessName());
        $this->assertSame('₱12.50', app(AppDisplay::class)->money('12.50'));
        $this->assertSame('19/10/2026', app(AppDisplay::class)->date('2026-10-19'));
    }

    public function test_settings_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', '')
            ->set('business_email', 'not-an-email')
            ->set('default_reorder_level', '-1')
            ->set('default_per_page', '99')
            ->set('date_format', 'invalid')
            ->set('currency_symbol', '')
            ->call('save')
            ->assertHasErrors([
                'business_name',
                'business_email',
                'default_reorder_level',
                'default_per_page',
                'date_format',
                'currency_symbol',
            ]);

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_inventory_preferences_do_not_rewrite_existing_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['reorder_level' => 3]);
        $product->forceFill(['quantity_on_hand' => 9])->save();

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'StockPilot')
            ->set('default_reorder_level', '20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3, $product->fresh()->reorder_level);
        $this->assertSame(9, $product->fresh()->quantity_on_hand);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->assertSet('reorder_level', '20');
    }

    public function test_display_preferences_do_not_change_stored_money_or_timestamps(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['cost_price' => '12.50']);
        $originalUpdatedAt = (string) $product->updated_at;

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('business_name', 'StockPilot')
            ->set('currency_symbol', '€')
            ->set('date_format', 'm/d/Y')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('12.50', (string) $product->fresh()->cost_price);
        $this->assertSame($originalUpdatedAt, (string) $product->fresh()->updated_at);
        $this->assertSame('€12.50', app(AppDisplay::class)->money($product->fresh()->cost_price));
    }

    public function test_invalid_stored_settings_fall_back_safely(): void
    {
        Setting::query()->create([
            'business_name' => 'Broken Co',
            'default_reorder_level' => 0,
            'default_per_page' => 999,
            'date_format' => 'nope',
            'currency_symbol' => '',
        ]);

        $service = app(SettingsService::class);
        $service->forgetCache();

        $this->assertSame(10, $service->defaultPerPage());
        $this->assertSame('Y-m-d', $service->dateFormat());
        $this->assertSame('$', $service->currencySymbol());
        $this->assertSame('Broken Co', $service->businessName());
    }

    public function test_branding_assets_and_wordmark_are_present(): void
    {
        $user = User::factory()->create();

        $this->assertFileExists(public_path('favicon.svg'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('favicon.svg', false)
            ->assertSee('Inventory', false);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('StockPilot', false)
            ->assertSee('favicon.svg', false)
            ->assertDontSee('Laravel', false);
    }
}
