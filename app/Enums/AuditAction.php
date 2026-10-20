<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Badge variant for presentation components.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Deleted => 'danger',
        };
    }
}
