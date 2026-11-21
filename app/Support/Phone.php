<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise a Malaysian phone number to international digits only, so
     * every way people type it ends up the same:
     * "012-345 6789", "0123456789", "123456789", "+60 12-345 6789",
     * "60123456789" and "+60 012-345 6789" all become "60123456789".
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // Drop the country code and any trunk zeros, then put "60" back once.
        $local = str_starts_with($digits, '60') ? substr($digits, 2) : $digits;

        return '60'.ltrim($local, '0');
    }

    /**
     * Whether the number is a Malaysian mobile (01x-xxx xxxx or 011-xxxx xxxx),
     * in any of the formats normalize() accepts.
     */
    public static function isMobile(string $phone): bool
    {
        return (bool) preg_match('/^601\d{8,9}$/', self::normalize($phone));
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
