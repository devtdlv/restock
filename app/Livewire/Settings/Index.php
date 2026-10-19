<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings - StockPilot')]
class Index extends Component
{
    public string $business_name = '';

    public string $business_email = '';

    public string $business_phone = '';

    public string $business_address = '';

    public string $default_reorder_level = '0';

    public string $default_per_page = '10';

    public string $date_format = 'Y-m-d';

    public string $currency_symbol = '$';

    public ?string $statusMessage = null;

    public function mount(SettingsService $settings): void
    {
        $this->fillFromSettings($settings->current());
    }

    public function save(SettingsService $settings): void
    {
        abort_unless(auth()->check(), 403);
        $this->statusMessage = null;

        $validated = $this->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:2000'],
            'default_reorder_level' => ['required', 'integer', 'min:0'],
            'default_per_page' => ['required', 'integer', Rule::in(Setting::allowedPerPageOptions())],
            'date_format' => ['required', 'string', Rule::in(array_keys(Setting::allowedDateFormats()))],
            'currency_symbol' => ['required', 'string', 'max:8'],
        ]);

        try {
            $settings->update($validated, auth()->user());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->fillFromSettings($settings->current());
        $this->statusMessage = 'Settings saved. Display changes apply immediately; inventory history is unchanged.';
    }

    public function render()
    {
        return view('livewire.settings.index', [
            'dateFormats' => Setting::allowedDateFormats(),
            'perPageOptions' => Setting::allowedPerPageOptions(),
        ])->layoutData(['header' => 'Settings']);
    }

    protected function fillFromSettings(Setting $setting): void
    {
        $defaults = Setting::defaults();

        $this->business_name = (string) ($setting->business_name ?: $defaults['business_name']);
        $this->business_email = (string) ($setting->business_email ?? '');
        $this->business_phone = (string) ($setting->business_phone ?? '');
        $this->business_address = (string) ($setting->business_address ?? '');
        $this->default_reorder_level = (string) ($setting->default_reorder_level ?? $defaults['default_reorder_level']);
        $this->default_per_page = (string) ($setting->default_per_page ?? $defaults['default_per_page']);
        $this->date_format = (string) ($setting->date_format ?: $defaults['date_format']);
        $this->currency_symbol = (string) ($setting->currency_symbol ?: $defaults['currency_symbol']);
    }
}
