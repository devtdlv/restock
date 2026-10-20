<?php

namespace App\Enums;

enum StockMovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Adjustment = 'adjustment';
    case LegacyOpening = 'legacy_opening';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Movement types available in the Adjust Stock UI.
     *
     * @return list<self>
     */
    public static function adjustableCases(): array
    {
        return [
            self::StockIn,
            self::StockOut,
            self::Adjustment,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::LegacyOpening => 'Legacy Opening Balance',
        };
    }

    /**
     * Badge variant for presentation components.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::StockIn => 'success',
            self::StockOut => 'danger',
            self::Adjustment => 'warning',
            self::LegacyOpening => 'neutral',
        };
    }
}
