<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Ordered',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Badge variant for presentation components.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Ordered => 'info',
            self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsReceiving(): bool
    {
        return $this === self::Ordered || $this === self::PartiallyReceived;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Draft || $this === self::Ordered;
    }
}
