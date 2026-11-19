<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise a Malaysian phone number to international digits only,
     * e.g. "012-345 6789" → "60123456789".
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '60') => $digits,
            str_starts_with($digits, '0') => '6'.$digits,
            default => $digits,
        };
    }

    /**
     * Hide the middle of a number for display on shared screens.
     */
    public static function mask(string $phone): string
    {
        $length = strlen($phone);

        return $length <= 6 ? $phone : substr($phone, 0, 4).str_repeat('•', $length - 7).substr($phone, -3);
    }
}
