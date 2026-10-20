<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Shared presentation helpers driven by application settings.
 *
 * Formatting only. Never mutates stored money, quantities, or timestamps.
 */
class AppDisplay
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function money(mixed $amount): string
    {
        $normalized = Money::of($amount);
        $symbol = $this->settings->currencySymbol();

        $negative = str_starts_with($normalized, '-');
        $unsigned = $negative ? substr($normalized, 1) : $normalized;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '00');

        $formatted = number_format((int) $whole, 0, '.', ',').'.'.$fraction;

        return $symbol.($negative ? '-' : '').$formatted;
    }

    /**
     * Format whole-unit quantities for display (presentation only).
     */
    public function quantity(int|string|null $value): string
    {
        return number_format((int) $value, 0, '.', ',');
    }

    /**
     * Format a signed quantity change for movement history.
     */
    public function quantityChange(int $value): string
    {
        $formatted = $this->quantity(abs($value));

        if ($value > 0) {
            return '+'.$formatted;
        }

        if ($value < 0) {
            return '-'.$formatted;
        }

        return $formatted;
    }

    public function date(null|CarbonInterface|string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $carbon = $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);

        return $carbon->timezone(config('app.timezone'))->format($this->settings->dateFormat());
    }

    public function dateTime(null|CarbonInterface|string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $carbon = $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);

        return $carbon->timezone(config('app.timezone'))
            ->format($this->settings->dateFormat().' H:i');
    }

    public function businessName(): string
    {
        return $this->settings->businessName();
    }
}
