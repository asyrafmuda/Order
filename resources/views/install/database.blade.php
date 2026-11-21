@extends('install.layout', ['step' => 3])

@section('title', 'Database')

@section('content')
    @if ($existing)
        <section class="mb-5 rounded-xl border border-flame-500/40 bg-flame-900 p-4">
            <h1 class="text-base font-bold text-strong">This database already has a shop in it</h1>
            <p class="mt-1 text-sm text-ink-200">Connected to {{ $existing }}. It holds orders, a menu and an admin account from an earlier installation. Reconnect to keep all of it — you then sign in with your existing admin account.</p>
            <form method="POST" action="{{ route('install.reconnect') }}" class="mt-4 space-y-3">
                @csrf
                @include('install.partials.field', ['name' => 'url', 'label' => 'Website address', 'type' => 'url', 'value' => rtrim(request()->getSchemeAndHttpHost(), '/'), 'required' => true])
                <button class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500">Reconnect and keep my data</button>
            </form>
            <p class="mt-3 text-xs text-ink-400">To start a new shop instead, create a new empty database and enter it below.</p>
        </section>
    @endif

    <form method="POST" action="{{ route('install.database.store') }}" class="rounded-xl border border-ink-700 bg-ink-850">
        @csrf
        <div class="border-b border-ink-700 px-4 py-3">
            <h1 class="text-base font-bold text-strong">Connect the database</h1>
            <p class="mt-1 text-sm text-ink-300">In cPanel → <strong class="text-ink-100">MySQL Databases</strong>: create a database and a user, then add the user to the database with <strong class="text-ink-100">All privileges</strong>. Their full names usually start with your cPanel username, like <code class="text-flame-400">myuser_order</code>.</p>
        </div>
        <div class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_7rem]">
            @include('install.partials.field', ['name' => 'host', 'label' => 'Database host', 'value' => $values['host'], 'required' => true, 'hint' => 'Almost always "localhost" on shared hosting.'])
            @include('install.partials.field', ['name' => 'port', 'label' => 'Port', 'value' => $values['port'], 'required' => true, 'inputmode' => 'numeric'])
            @include('install.partials.field', ['name' => 'database', 'label' => 'Database name', 'value' => $values['database'], 'required' => true, 'placeholder' => 'myuser_order', 'class' => 'sm:col-span-2'])
            @include('install.partials.field', ['name' => 'username', 'label' => 'Database user', 'value' => $values['username'], 'required' => true, 'placeholder' => 'myuser_order', 'autocomplete' => 'off', 'class' => 'sm:col-span-2'])
            @include('install.partials.field', ['name' => 'password', 'label' => 'Database password', 'type' => 'password', 'autocomplete' => 'new-password', 'class' => 'sm:col-span-2'])
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-ink-700 px-4 py-3">
            <a href="{{ route('install.welcome') }}" class="text-sm font-semibold text-ink-300 hover:text-strong">Back</a>
            <button class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500">Test and continue</button>
        </div>
    </form>
@endsection
