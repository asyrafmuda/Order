<?php

namespace App\Support;

/**
 * Turns the two brand colours chosen in Admin → Settings into the shades the
 * interface uses, as CSS custom properties that override the theme defaults.
 */
final class BrandPalette
{
    private const DARK_SURFACE = '#0f0f12';

    public function __construct(private readonly Settings $settings) {}

    public function primary(): string
    {
        return self::valid($this->settings->string('brand.primary_color')) ?? '#ee2b32';
    }

    public function accent(): string
    {
        return self::valid($this->settings->string('brand.accent_color')) ?? '#ffb938';
    }

    /**
     * CSS for a <style> tag in the page head.
     */
    public function css(): string
    {
        $primary = $this->primary();
        $accent = $this->accent();

        $variables = [
            '--color-brand-400' => self::mix($primary, '#ffffff', 0.22),
            '--color-brand-500' => $primary,
            '--color-brand-600' => self::mix($primary, '#000000', 0.10),
            '--color-brand-700' => self::mix($primary, '#000000', 0.26),
            '--color-brand-900' => self::mix($primary, self::DARK_SURFACE, 0.80),
            '--color-flame-300' => self::mix($accent, '#ffffff', 0.32),
            '--color-flame-400' => $accent,
            '--color-flame-500' => self::mix($accent, '#000000', 0.10),
            '--color-flame-900' => self::mix($accent, self::DARK_SURFACE, 0.82),
        ];

        return ':root{'.implode('', array_map(fn ($name, $value) => "{$name}:{$value};", array_keys($variables), $variables)).'}';
    }

    /**
     * WCAG contrast ratio between two hex colours (1 – 21).
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Blend $amount (0–1) of $with into $colour.
     */
    public static function mix(string $colour, string $with, float $amount): string
    {
        [$r1, $g1, $b1] = self::rgb($colour);
        [$r2, $g2, $b2] = self::rgb($with);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 + ($r2 - $r1) * $amount),
            (int) round($g1 + ($g2 - $g1) * $amount),
            (int) round($b1 + ($b2 - $b1) * $amount),
        );
    }

    private static function valid(string $hex): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $value) {
            $channel = $value / 255;

            return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
