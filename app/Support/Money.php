<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Two-decimal currency helpers using Brick Math (no floating-point money math).
 */
class Money
{
    public static function of(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
    }

    public static function multiply(string $amount, int $quantity): string
    {
        return (string) BigDecimal::of($amount)
            ->multipliedBy($quantity)
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public static function add(string ...$amounts): string
    {
        $sum = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $sum = $sum->plus(BigDecimal::of($amount));
        }

        return (string) $sum->toScale(2, RoundingMode::HALF_UP);
    }

    public static function isNegative(string $amount): bool
    {
        return BigDecimal::of($amount)->isNegative();
    }

    public static function isNegativeOrZero(string $amount): bool
    {
        return ! BigDecimal::of($amount)->isPositive();
    }
}
