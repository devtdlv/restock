<?php

namespace App\Services\Settings;

use App\Enums\AuditAction;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Request-scoped application settings with safe defaults.
 *
 * Display preferences never rewrite historical inventory or purchasing data.
 */
class SettingsService
{
    protected ?Setting $cached = null;

    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    public function current(): Setting
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (! $this->tableReady()) {
            $this->cached = Setting::make(Setting::defaults());

            return $this->cached;
        }

        $stored = Setting::query()->orderBy('id')->first();

        if ($stored === null) {
            $this->cached = Setting::make(Setting::defaults());

            return $this->cached;
        }

        $this->cached = $this->normalize($stored);

        return $this->cached;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, User $user): Setting
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'business_name' => 'Settings storage is not available yet. Run database migrations before saving.',
            ]);
        }

        $normalized = $this->validatedAttributes($attributes);

        return DB::transaction(function () use ($normalized, $user) {
            $setting = Setting::query()->orderBy('id')->lockForUpdate()->first();
            $isCreate = $setting === null;

            if ($isCreate) {
                $setting = Setting::query()->create($normalized);
                $this->auditLogger->record(
                    AuditAction::Created,
                    $setting,
                    null,
                    $this->snapshot($setting),
                    $user,
                );
            } else {
                $before = $this->snapshot($setting);
                $setting->fill($normalized);
                $setting->save();
                $this->auditLogger->record(
                    AuditAction::Updated,
                    $setting,
                    $before,
                    $this->snapshot($setting->fresh()),
                    $user,
                );
            }

            $this->cached = $this->normalize($setting->fresh());

            return $this->cached;
        });
    }

    public function forgetCache(): void
    {
        $this->cached = null;
    }

    public function defaultReorderLevel(): int
    {
        return max(0, (int) $this->current()->default_reorder_level);
    }

    public function defaultPerPage(): int
    {
        $value = (int) $this->current()->default_per_page;

        return in_array($value, Setting::allowedPerPageOptions(), true)
            ? $value
            : (int) Setting::defaults()['default_per_page'];
    }

    public function dateFormat(): string
    {
        $format = (string) $this->current()->date_format;

        return array_key_exists($format, Setting::allowedDateFormats())
            ? $format
            : (string) Setting::defaults()['date_format'];
    }

    public function currencySymbol(): string
    {
        $symbol = trim((string) $this->current()->currency_symbol);

        return $symbol !== '' ? $symbol : (string) Setting::defaults()['currency_symbol'];
    }

    public function businessName(): string
    {
        $name = trim((string) $this->current()->business_name);

        return $name !== '' ? $name : (string) Setting::defaults()['business_name'];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Setting $setting): array
    {
        return [
            'business_name' => $setting->business_name,
            'business_email' => $setting->business_email,
            'business_phone' => $setting->business_phone,
            'business_address' => $setting->business_address,
            'default_reorder_level' => (int) $setting->default_reorder_level,
            'default_per_page' => (int) $setting->default_per_page,
            'date_format' => $setting->date_format,
            'currency_symbol' => $setting->currency_symbol,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function validatedAttributes(array $attributes): array
    {
        $defaults = Setting::defaults();

        $businessName = trim((string) ($attributes['business_name'] ?? ''));
        if ($businessName === '' || mb_strlen($businessName) > 255) {
            throw ValidationException::withMessages([
                'business_name' => 'Business name is required and may not exceed 255 characters.',
            ]);
        }

        $email = $this->nullableString($attributes['business_email'] ?? null);
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'business_email' => 'Business email must be a valid email address.',
            ]);
        }

        $phone = $this->nullableString($attributes['business_phone'] ?? null);
        if ($phone !== null && mb_strlen($phone) > 50) {
            throw ValidationException::withMessages([
                'business_phone' => 'Business phone may not exceed 50 characters.',
            ]);
        }

        $address = $this->nullableString($attributes['business_address'] ?? null);
        if ($address !== null && mb_strlen($address) > 2000) {
            throw ValidationException::withMessages([
                'business_address' => 'Business address may not exceed 2000 characters.',
            ]);
        }

        $reorder = (int) ($attributes['default_reorder_level'] ?? $defaults['default_reorder_level']);
        if ($reorder < 0) {
            throw ValidationException::withMessages([
                'default_reorder_level' => 'Default reorder level must be zero or greater.',
            ]);
        }

        $perPage = (int) ($attributes['default_per_page'] ?? $defaults['default_per_page']);
        if (! in_array($perPage, Setting::allowedPerPageOptions(), true)) {
            throw ValidationException::withMessages([
                'default_per_page' => 'Default items per page is invalid.',
            ]);
        }

        $dateFormat = (string) ($attributes['date_format'] ?? $defaults['date_format']);
        if (! array_key_exists($dateFormat, Setting::allowedDateFormats())) {
            throw ValidationException::withMessages([
                'date_format' => 'Date display format is invalid.',
            ]);
        }

        $currency = trim((string) ($attributes['currency_symbol'] ?? $defaults['currency_symbol']));
        if ($currency === '' || mb_strlen($currency) > 8) {
            throw ValidationException::withMessages([
                'currency_symbol' => 'Currency symbol is required and may not exceed 8 characters.',
            ]);
        }

        return [
            'business_name' => $businessName,
            'business_email' => $email,
            'business_phone' => $phone,
            'business_address' => $address,
            'default_reorder_level' => $reorder,
            'default_per_page' => $perPage,
            'date_format' => $dateFormat,
            'currency_symbol' => $currency,
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    protected function tableReady(): bool
    {
        return Schema::hasTable('settings');
    }

    protected function normalize(Setting $setting): Setting
    {
        $defaults = Setting::defaults();

        if (! array_key_exists((string) $setting->date_format, Setting::allowedDateFormats())) {
            $setting->date_format = $defaults['date_format'];
        }

        if (! in_array((int) $setting->default_per_page, Setting::allowedPerPageOptions(), true)) {
            $setting->default_per_page = $defaults['default_per_page'];
        }

        if ((int) $setting->default_reorder_level < 0) {
            $setting->default_reorder_level = 0;
        }

        if (trim((string) $setting->currency_symbol) === '') {
            $setting->currency_symbol = $defaults['currency_symbol'];
        }

        if (trim((string) $setting->business_name) === '') {
            $setting->business_name = $defaults['business_name'];
        }

        return $setting;
    }
}
