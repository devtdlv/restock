<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Read-only presentation helpers for audit log UI.
 */
class AuditPresenter
{
    /**
     * @var list<string>
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'token',
        'secret',
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    public static function entityTypeOptions(): array
    {
        return [
            Product::class => 'Product',
            Category::class => 'Category',
        ];
    }

    public static function entityTypeLabel(?string $entityType): string
    {
        if ($entityType === null) {
            return 'Unknown';
        }

        return self::entityTypeOptions()[$entityType] ?? class_basename($entityType);
    }

    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'category_ids' => 'Categories',
            'unit_price' => 'Unit price',
            'cost_price' => 'Cost price',
            'quantity_on_hand' => 'Quantity on hand',
            'reorder_level' => 'Reorder level',
            'is_active' => 'Active',
            default => Str::of($field)->replace('_', ' ')->title()->toString(),
        };
    }

    public function entityLabel(AuditLog $audit): string
    {
        $name = $this->entityName($audit);

        if ($name !== null) {
            return self::entityTypeLabel($audit->entity_type).' "'.$name.'" (#'.$audit->entity_id.')';
        }

        return self::entityTypeLabel($audit->entity_type).' #'.$audit->entity_id;
    }

    public function entityName(AuditLog $audit): ?string
    {
        $values = $this->safeValues($audit->after_values ?: $audit->before_values);
        $name = $values['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function changeSummary(AuditLog $audit): string
    {
        return match ($audit->action) {
            AuditAction::Created => 'Created '.$this->entityLabel($audit),
            AuditAction::Deleted => 'Deleted '.$this->entityLabel($audit),
            AuditAction::Updated => $this->updatedSummary($audit),
        };
    }

    /**
     * @return list<array{field: string, label: string, before: mixed, after: mixed}>
     */
    public function changedFields(AuditLog $audit): array
    {
        $before = $this->safeValues($audit->before_values);
        $after = $this->safeValues($audit->after_values);
        $keys = collect(array_unique(array_merge(array_keys($before), array_keys($after))))->sort()->values();

        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if ($this->valuesAreEqual($beforeValue, $afterValue)) {
                continue;
            }

            $changes[] = [
                'field' => $key,
                'label' => self::fieldLabel($key),
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    public function safeBefore(AuditLog $audit): array
    {
        return $this->safeValues($audit->before_values);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeAfter(AuditLog $audit): array
    {
        return $this->safeValues($audit->after_values);
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'None';
            }

            return collect($value)
                ->map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item))
                ->implode(', ');
        }

        return (string) $value;
    }

    protected function updatedSummary(AuditLog $audit): string
    {
        $changes = $this->changedFields($audit);
        $subject = $this->entityLabel($audit);

        if ($changes === []) {
            return 'Updated '.$subject.' (no field differences recorded)';
        }

        $labels = collect($changes)->pluck('label')->take(4)->implode(', ');
        $suffix = count($changes) > 4 ? ', …' : '';

        return 'Updated '.$subject.': changed '.$labels.$suffix;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    protected function safeValues(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        return collect($values)
            ->reject(fn ($value, $key) => in_array((string) $key, self::SENSITIVE_KEYS, true))
            ->sortKeys()
            ->all();
    }

    protected function valuesAreEqual(mixed $before, mixed $after): bool
    {
        return json_encode($before) === json_encode($after);
    }
}
