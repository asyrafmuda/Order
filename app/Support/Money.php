<?php

namespace App\Support;

/**
 * All monetary amounts are stored and calculated as integer sen.
 */
final class Money
{
    public static function format(int $sen, bool $withSymbol = true): string
    {
        $formatted = number_format($sen / 100, 2);

        return $withSymbol ? 'RM '.$formatted : $formatted;
    }

    /**
     * Convert a ringgit amount typed by a human ("12.90") to sen.
     */
    public static function toSen(int|float|string $ringgit): int
    {
        return (int) round(((float) $ringgit) * 100);
    }

    /**
     * Tax portion of an amount.
     *
     * @return int Sen
     */
    public static function tax(int $amount, float $ratePercent, bool $inclusive): int
    {
        if ($ratePercent <= 0) {
            return 0;
        }

        return $inclusive
            ? (int) round($amount * $ratePercent / (100 + $ratePercent))
            : (int) round($amount * $ratePercent / 100);
    }
}
