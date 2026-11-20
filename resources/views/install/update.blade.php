@extends('install.layout')

@section('title', 'Update')
@section('subtitle', 'A new version has been uploaded')

@section('content')
    <section class="rounded-xl border border-ink-700 bg-ink-850">
        <div class="border-b border-ink-700 px-4 py-3">
            <h1 class="text-base font-bold text-strong">Finish the update to version {{ $to }}</h1>
            <p class="mt-1 text-sm text-ink-300">
                The new files are in place{{ $from ? " (was version {$from})" : '' }}. {{ $pending }} database {{ $pending === 1 ? 'change' : 'changes' }} must run before the shop opens again. Customers see that the site is being updated until then.
            </p>
        </div>
        <form method="POST" action="{{ route('install.update.run') }}" class="space-y-4 p-4" id="update-form">
            @csrf
            <p class="text-sm text-ink-200">Super admin: confirm with your sign-in details.</p>
            @include('install.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'username'])
            @include('install.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'])
            <p class="text-xs text-ink-400">Tip: back up the database first (cPanel → phpMyAdmin → Export).</p>
            <button id="update-button" class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500 disabled:opacity-60">Update now</button>
        </form>
    </section>

    <script>
        document.getElementById('update-form').addEventListener('submit', function () {
            var button = document.getElementById('update-button');
            button.disabled = true;
            button.textContent = 'Updating…';
        });
    </script>
@endsection
