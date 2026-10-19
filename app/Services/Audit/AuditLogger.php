<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records meaningful create/update/delete audit entries for catalog entities.
 *
 * Public API:
 *
 *   $logger->record(AuditAction::Created, $product, null, $after, $user);
 *   $logger->record(AuditAction::Updated, $product, $before, $after, $user);
 *   $logger->record(AuditAction::Deleted, $product, $before, null, $user);
 *
 * Returns null when an update has no meaningful differences (no duplicate noise).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditAction $action,
        Model $entity,
        ?array $before,
        ?array $after,
        ?User $user = null,
    ): ?AuditLog {
        $before = $this->sanitize($before);
        $after = $this->sanitize($after);

        if ($action === AuditAction::Updated && $this->snapshotsAreEqual($before, $after)) {
            return null;
        }

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'before_values' => $before,
            'after_values' => $after,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $hidden = [
            'password',
            'password_confirmation',
            'remember_token',
            'api_token',
            'token',
            'secret',
        ];

        $clean = collect($values)
            ->reject(fn ($value, $key) => in_array((string) $key, $hidden, true))
            ->map(function ($value) {
                if (is_array($value)) {
                    return array_values(array_map(fn ($item) => is_int($item) || ctype_digit((string) $item) ? (int) $item : $item, $value));
                }

                return $value;
            })
            ->sortKeys()
            ->all();

        return $clean;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function snapshotsAreEqual(?array $before, ?array $after): bool
    {
        return json_encode($before) === json_encode($after);
    }
}
