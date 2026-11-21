@extends('install.layout', ['step' => 1])

@section('title', 'Check the server')

@section('content')
    <section class="rounded-xl border border-ink-700 bg-ink-850">
        <div class="border-b border-ink-700 px-4 py-3">
            <h1 class="text-base font-bold text-strong">Welcome</h1>
            <p class="mt-1 text-sm text-ink-300">This sets up your online ordering system in about two minutes. You will need your purchase code and the name, user and password of an empty MySQL database (create one in cPanel → MySQL Databases).</p>
        </div>

        @foreach ($checks as $group => $items)
            <div class="border-b border-ink-700 px-4 py-3 last:border-b-0">
                <h2 class="mb-2 text-[11px] font-extrabold tracking-[0.15em] text-ink-400 uppercase">{{ $group }}</h2>
                <ul class="space-y-2">
                    @foreach ($items as $check)
                        <li class="flex items-start gap-3 text-sm">
                            <span @class([
                                'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-extrabold',
                                'bg-go-900 text-go-400' => $check['ok'],
                                'bg-danger-900 text-danger-400' => ! $check['ok'] && $check['required'],
                                'bg-flame-900 text-flame-400' => ! $check['ok'] && ! $check['required'],
                            ]) aria-hidden>{{ $check['ok'] ? '✓' : ($check['required'] ? '✕' : '!') }}</span>
                            <span class="min-w-0">
                                <span class="block font-semibold text-ink-100">{{ $check['label'] }}</span>
                                @unless ($check['ok'])
                                    <span class="block text-xs text-ink-400">{{ $check['detail'] }}</span>
                                @endunless
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>

    <div class="mt-5 flex items-center justify-between gap-3">
        @if ($passes)
            <p class="text-sm text-go-400">Everything needed is in place.</p>
            <a href="{{ route('install.license') }}" class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500">Next</a>
        @else
            <p class="text-sm text-danger-400">Fix the items marked ✕, then reload this page.</p>
            <a href="{{ route('install.welcome') }}" class="inline-flex h-11 items-center rounded-lg bg-ink-700 px-5 text-sm font-bold text-ink-100 hover:bg-ink-600">Check again</a>
        @endif
    </div>
@endsection
