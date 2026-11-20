<?php

use App\Enums\UserRole;
use App\Support\BrandPalette;
use App\Support\Settings;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $brand
 * @return array<string, mixed>
 */
function themePayload(array $brand): array
{
    return settingsPayload(['brand' => $brand]);
}

beforeEach(fn () => $this->actingAs(userWithRole(UserRole::Admin)));

it('is dark with the default colours on a fresh install', function () {
    $palette = app(BrandPalette::class);

    expect($palette->theme())->toBe('dark')
        ->and($palette->css())->toContain('color-scheme:dark', '--color-brand-500:#ee2b32', '--color-flame-400:#ffb938')
        // The dark greys are the defaults in app.css: nothing overrides them.
        ->and($palette->css())->not->toContain('--color-ink-', '--color-strong');

    $this->get(route('menu.index'))
        ->assertSee('<style id="brand-palette">', false)
        ->assertSee('<meta name="theme-color" content="#09090b">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('theme.mode', 'dark')
            ->where('theme.css', $palette->css())
            ->where('theme.page_color', '#09090b'));
});

it('switches the whole interface to the light theme', function () {
    $this->put(route('admin.settings.update'), themePayload(['theme' => 'light', 'primary_color' => '#15803D', 'accent_color' => '#f59e0b']))
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $css = app(BrandPalette::class)->css();

    expect(app(Settings::class)->string('brand.theme'))->toBe('light')
        ->and($css)->toContain(
            'color-scheme:light',
            '--color-ink-950:#f4f4f6',   // page
            '--color-ink-900:#ffffff',   // surfaces
            '--color-ink-100:#1d1d22',   // body text
            '--color-strong:#0c0c0f',    // headings
            '--color-brand-500:#15803d',
        );

    // Customers and staff get it on their next page, with no reload needed.
    foreach (['menu.index', 'admin.dashboard', 'kitchen.index', 'pos.index'] as $route) {
        $this->get(route($route))
            ->assertSee('<meta name="theme-color" content="#f4f4f6">', false)
            ->assertInertia(fn (Assert $page) => $page->where('theme.mode', 'light')->where('theme.css', $css));
    }

    $this->get(route('admin.settings.edit'))->assertInertia(fn (Assert $page) => $page->where('settings.brand.theme', 'light'));
});

it('keeps text readable on the light theme whatever accent is picked', function (string $accent) {
    $variables = BrandPalette::variables('light', '#dc2626', $accent);

    // Prices, links and red text all sit on white.
    foreach (['--color-flame-400', '--color-flame-300', '--color-flame-500', '--color-brand-400', '--color-danger-400', '--color-go-400', '--color-info-400', '--color-ink-400', '--color-ink-100', '--color-strong'] as $text) {
        expect(BrandPalette::contrast($variables[$text], '#ffffff'))->toBeGreaterThanOrEqual(4.5, "{$text} on white");
    }

    // Tinted panels stay pale enough for the text on them.
    expect(BrandPalette::contrast($variables['--color-flame-300'], $variables['--color-flame-900']))->toBeGreaterThanOrEqual(4.5)
        ->and(BrandPalette::contrast($variables['--color-brand-400'], $variables['--color-brand-900']))->toBeGreaterThanOrEqual(4)
        ->and(BrandPalette::contrast($variables['--color-go-400'], $variables['--color-go-900']))->toBeGreaterThanOrEqual(4)
        ->and(BrandPalette::contrast($variables['--color-info-400'], $variables['--color-info-900']))->toBeGreaterThanOrEqual(4);
})->with(['#ffb938', '#fde047', '#f59e0b', '#38bdf8', '#111827', '#ffffff']);

it('accepts on the light theme an accent that would be unreadable on the dark one', function () {
    $brand = ['primary_color' => '#7c4a2d', 'accent_color' => '#3f2a14'];

    $this->put(route('admin.settings.update'), themePayload([...$brand, 'theme' => 'dark']))->assertSessionHasErrors('brand.accent_color');
    $this->put(route('admin.settings.update'), themePayload([...$brand, 'theme' => 'light']))->assertSessionHasNoErrors();

    // A main colour too pale for white button text is refused on both.
    $this->put(route('admin.settings.update'), themePayload(['theme' => 'light', 'primary_color' => '#fde68a']))->assertSessionHasErrors('brand.primary_color');
});

it('refuses a theme it does not know and falls back to dark for a bad stored value', function () {
    $this->put(route('admin.settings.update'), themePayload(['theme' => 'neon']))->assertSessionHasErrors('brand.theme');

    $payload = settingsPayload();
    unset($payload['brand']['theme']);
    $this->put(route('admin.settings.update'), $payload)->assertSessionHasErrors('brand.theme');

    app(Settings::class)->put(['brand.theme' => 'neon']);
    expect(app(BrandPalette::class)->theme())->toBe('dark');
});

it('goes back to the dark theme', function () {
    app(Settings::class)->put(['brand.theme' => 'light']);

    $this->put(route('admin.settings.update'), themePayload(['theme' => 'dark']))->assertSessionHas('success');

    expect(app(BrandPalette::class)->css())->toContain('color-scheme:dark')->not->toContain('--color-ink-');
});
