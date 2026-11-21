@extends('install.layout', ['step' => 2])

@section('title', 'License')

@section('content')
    <form method="POST" action="{{ route('install.license.store') }}" class="rounded-xl border border-ink-700 bg-ink-850" id="license-form">
        @csrf
        <div class="border-b border-ink-700 px-4 py-3">
            <h1 class="text-base font-bold text-strong">Activate your license</h1>
            <p class="mt-1 text-sm text-ink-300">Enter the purchase code you received with the app. It will be tied to <strong class="text-ink-100">{{ $host }}</strong>; to move to another address later, release it in Admin → License first.</p>
        </div>
        <div class="p-4">
            @include('install.partials.field', ['name' => 'code', 'label' => 'Purchase code', 'required' => true, 'placeholder' => 'XXXX-XXXX-XXXX-XXXX', 'autocomplete' => 'off'])
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-ink-700 px-4 py-3">
            <a href="{{ route('install.welcome') }}" class="text-sm font-semibold text-ink-300 hover:text-strong">Back</a>
            <button id="license-button" class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500 disabled:opacity-60">Activate and continue</button>
        </div>
    </form>

    <script>
        document.getElementById('license-form').addEventListener('submit', function () {
            var button = document.getElementById('license-button');
            button.disabled = true;
            button.textContent = 'Checking…';
        });
    </script>
@endsection
