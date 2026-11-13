<?php

namespace App\Support;

/**
 * Exact money arithmetic on DECIMAL(15,2) values.
 *
 * All monetary work is done in integer cents — never float — so totals,
 * differences and percentage thresholds are exact and safe to compare.
 */
class Money
{
    /**
     * Parse a DECIMAL(15,2) value (string|float|int|null) into integer cents.
     */
    public static function toCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) $value * 100);
    }

    /**
     * Format integer cents back into a DECIMAL(15,2) string ("123.45").
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        $abs = abs($cents);

        return $sign. intdiv($abs, 100). '.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Sum an iterable of DECIMAL(15,2) values into a DECIMAL(15,2) string.
     *
     * @param iterable<mixed> $values
     */
    public static function sum(iterable $values): string
    {
        $total = 0;

        foreach ($values as $value) {
            $total += self::toCents($value);
        }

        return self::format($total);
    }

    /**
     * Subtracts b from a, both DECIMAL(15,2) values; result is a DECIMAL
     * string (may be negative).
     */
    public static function subtract(mixed $a, mixed $b): string
    {
        return self::format(self::toCents($a) - self::toCents($b));
    }

    /**
     * Is $a >= $b (exact cent comparison)?
     */
    public static function isAtLeast(mixed $a, mixed $b): bool
    {
        return self::toCents($a) >= self::toCents($b);
    }

    /**
     * $a as a percentage of $b, floored to 2 decimals as a string.
     */
    public static function percentOf(mixed $a, mixed $b): string
    {
        $bCents = self::toCents($b);

        if ($bCents === 0) {
            return '0.00';
        }

        return number_format(self::toCents($a) * 100 / $bCents, 2, '.', '');
    }
}
