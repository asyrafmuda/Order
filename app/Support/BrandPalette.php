<?php

namespace App\Support;

/**
 * Turns the theme (dark or light) and the two brand colours chosen in
 * Admin → Settings into the shades the interface uses, as CSS custom
 * properties that override the defaults in resources/css/app.css.
 */
final class BrandPalette
{
    public const DARK = 'dark';

    public const LIGHT = 'light';

    public const THEMES = [self::DARK, self::LIGHT];

    /** The surface most text sits on in each theme (cards, the header). */
    public const SURFACES = [self::DARK => '#0f0f12', self::LIGHT => '#ffffff'];

    /** The page behind the cards. */
    public const PAGES = [self::DARK => '#09090b', self::LIGHT => '#f4f4f6'];

    /**
     * The light theme's greys. The scale keeps its meaning: 950 is the page,
     * 900–850 are surfaces, 700–600 borders, 400 secondary text, 100 body text.
     */
    private const LIGHT_INK = [
        '950' => '#f4f4f6', '900' => '#ffffff', '850' => '#ffffff', '800' => '#eeeef1', '750' => '#e8e8ec', '700' => '#dfdfe5',
        '600' => '#c9c9d1', '500' => '#8f8f9b', '400' => '#666672', '300' => '#4a4a55', '200' => '#34343c', '100' => '#1d1d22',
    ];

    public function __construct(private readonly Settings $settings) {}

    public function theme(): string
    {
        $theme = $this->settings->string('brand.theme');

        return in_array($theme, self::THEMES, true) ? $theme : self::DARK;
    }

    public function primary(): string
    {
        return self::valid($this->settings->string('brand.primary_color')) ?? '#ee2b32';
    }

    public function accent(): string
    {
        return self::valid($this->settings->string('brand.accent_color')) ?? '#ffb938';
    }

    /**
     * The colour of the page background, for the browser's own chrome.
     */
    public function pageColor(): string
    {
        return self::PAGES[$this->theme()];
    }

    /**
     * CSS for a <style> tag in the page head.
     */
    public function css(): string
    {
        $variables = self::variables($this->theme(), $this->primary(), $this->accent());

        return ':root{'.implode('', array_map(fn ($name, $value) => "{$name}:{$value};", array_keys($variables), $variables)).'}';
    }

    /**
     * Every custom property a theme sets, for the given brand colours.
     *
     * @return array<string, string>
     */
    public static function variables(string $theme, string $primary, string $accent): array
    {
        $surface = self::SURFACES[$theme] ?? self::SURFACES[self::DARK];

        if ($theme === self::LIGHT) {
            // On a light surface the accent is text (prices), so it is darkened until it reads well.
            $accentText = self::readableOn($accent, $surface);

            return [
                'color-scheme' => 'light',
                ...array_combine(array_map(fn ($step) => "--color-ink-{$step}", array_keys(self::LIGHT_INK)), self::LIGHT_INK),
                // "Strong" text: headings and figures, white on the dark theme.
                '--color-strong' => '#0c0c0f',
                '--color-brand-400' => self::readableOn(self::mix($primary, '#000000', 0.08), $surface),
                '--color-brand-500' => $primary,
                '--color-brand-600' => self::mix($primary, '#000000', 0.10),
                '--color-brand-700' => self::mix($primary, '#000000', 0.26),
                '--color-brand-900' => self::mix($primary, $surface, 0.90),
                '--color-flame-300' => self::mix($accentText, '#000000', 0.18),
                '--color-flame-400' => $accentText,
                '--color-flame-500' => self::mix($accentText, '#000000', 0.08),
                '--color-flame-900' => self::mix($accent, $surface, 0.86),
                '--color-danger-400' => '#c81e25',
                '--color-danger-500' => '#dc2626',
                '--color-danger-600' => '#c81e25',
                '--color-danger-700' => '#a3181e',
                '--color-danger-900' => '#fdeaeb',
                '--color-go-400' => '#047857',
                '--color-go-500' => '#059669',
                '--color-go-900' => '#dcf5ea',
                '--color-info-400' => '#0369a1',
                '--color-info-900' => '#e0f2fe',
            ];
        }

        return [
            'color-scheme' => 'dark',
            '--color-brand-400' => self::mix($primary, '#ffffff', 0.22),
            '--color-brand-500' => $primary,
            '--color-brand-600' => self::mix($primary, '#000000', 0.10),
            '--color-brand-700' => self::mix($primary, '#000000', 0.26),
            '--color-brand-900' => self::mix($primary, $surface, 0.80),
            '--color-flame-300' => self::mix($accent, '#ffffff', 0.32),
            '--color-flame-400' => $accent,
            '--color-flame-500' => self::mix($accent, '#000000', 0.10),
            '--color-flame-900' => self::mix($accent, $surface, 0.82),
        ];
    }

    /**
     * The colour itself when it already reads well on the background,
     * otherwise the nearest darker shade that does (contrast 4.5:1).
     */
    public static function readableOn(string $colour, string $background): string
    {
        for ($amount = 0.0; $amount < 1.0; $amount += 0.04) {
            $shade = self::mix($colour, '#000000', $amount);

            if (self::contrast($shade, $background) >= 4.5) {
                return $shade;
            }
        }

        return '#000000';
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
