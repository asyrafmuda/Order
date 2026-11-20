<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        @php($palette = app(\App\Support\BrandPalette::class))
        <meta name="theme-color" content="{{ $palette->pageColor() }}">
        {{-- The theme and brand colours chosen in Admin → Settings override the defaults in app.css.
             resources/js/app.tsx keeps this tag up to date after the settings are saved. --}}
        <style id="brand-palette">{!! $palette->css() !!}</style>
        <meta name="shop-timezone" content="{{ config('app.timezone') }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|bebas-neue:400" rel="stylesheet">

        @php($shopName = app(\App\Support\Settings::class)->string('shop.name') ?: config('app.name'))
        <meta name="shop-name" content="{{ $shopName }}">
        <title inertia>{{ $shopName }}</title>

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
