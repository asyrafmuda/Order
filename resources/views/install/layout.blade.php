<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#09090b">
        <title>@yield('title') · Setup</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh">
        <main class="mx-auto w-full max-w-2xl px-4 py-8 sm:py-12">
            <header class="mb-6 flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-xl shadow-glow" aria-hidden>🍽️</span>
                <div>
                    <p class="font-display text-2xl leading-none text-strong uppercase">Order system setup</p>
                    <p class="text-xs text-ink-400">@yield('subtitle', 'A few steps and your shop is online')</p>
                </div>
            </header>

            @isset($step)
                <ol class="mb-6 grid grid-cols-5 gap-1.5 text-[10px] font-bold tracking-wide uppercase sm:gap-2 sm:text-[11px]" aria-label="Progress">
                    @foreach (['Server', 'License', 'Database', 'Shop', 'Done'] as $index => $label)
                        <li @class([
                            'rounded-lg border px-1 py-2 text-center',
                            'border-brand-500 bg-brand-900 text-strong' => $index + 1 === $step,
                            'border-go-500/40 bg-go-900 text-go-400' => $index + 1 < $step,
                            'border-ink-700 bg-ink-850 text-ink-400' => $index + 1 > $step,
                        ]) @if ($index + 1 === $step) aria-current="step" @endif>
                            <span class="block text-sm leading-none">{{ $index + 1 < $step ? '✓' : $index + 1 }}</span>
                            <span class="mt-1 block truncate">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
            @endisset

            @if (session('success'))
                <p class="mb-4 rounded-xl border border-go-500/40 bg-go-900 px-4 py-3 text-sm font-medium text-go-400" role="status">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-danger-600/50 bg-danger-900 px-4 py-3 text-sm text-danger-400" role="alert">
                    <p class="font-bold">Please check the following</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')

            <p class="mt-8 text-center text-xs text-ink-500">Version {{ app(\App\Support\Installer\Installation::class)->version() }}</p>
        </main>
    </body>
</html>
