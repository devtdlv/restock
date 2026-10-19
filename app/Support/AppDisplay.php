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

        return $symbol.$normalized;
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
