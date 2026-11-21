<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Temporarily unavailable</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh">
        <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col items-center justify-center px-4 py-12 text-center">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-ink-800 text-2xl" aria-hidden>🔒</span>
            <h1 class="mt-5 font-display text-4xl leading-none text-strong uppercase">Temporarily unavailable</h1>
            <p class="mt-3 text-sm text-ink-300">This site is not taking orders right now. Please try again later.</p>
            <div class="mt-8 w-full rounded-xl border border-ink-700 bg-ink-850 p-4 text-left">
                <p class="text-xs font-bold tracking-wide text-ink-400 uppercase">For the site owner</p>
                <p class="mt-1 text-sm text-ink-200">License: {{ $status->label() }}. {{ $status->help() }}</p>
                <a href="/login" class="mt-3 inline-flex h-10 items-center rounded-lg bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-500">Sign in to fix it</a>
            </div>
        </main>
    </body>
</html>
