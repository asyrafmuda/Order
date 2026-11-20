@extends('install.layout', ['step' => 3])

@section('title', 'Your shop')

@section('content')
    <form method="POST" action="{{ route('install.run') }}" class="space-y-5" id="install-form">
        @csrf

        @error('install')
            {{-- Shown in the summary above. --}}
        @enderror

        <section class="rounded-xl border border-ink-700 bg-ink-850">
            <div class="border-b border-ink-700 px-4 py-3">
                <h1 class="text-base font-bold text-strong">Your shop</h1>
                <p class="mt-1 text-sm text-ink-300">Customers see these. You can change all of it later in Admin → Settings.</p>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2">
                @include('install.partials.field', ['name' => 'shop_name', 'label' => 'Shop name', 'required' => true, 'placeholder' => 'Kedai Burger Ali'])
                @include('install.partials.field', ['name' => 'tagline', 'label' => 'Tagline (optional)', 'placeholder' => 'Burger bakar sejak 2015'])
                @include('install.partials.field', ['name' => 'address', 'label' => 'Address', 'required' => true, 'class' => 'sm:col-span-2', 'placeholder' => 'No. 1, Jalan Besar, 40000 Shah Alam'])
                @include('install.partials.field', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'placeholder' => '012-345 6789'])
                @include('install.partials.field', ['name' => 'whatsapp', 'label' => 'WhatsApp (optional)', 'inputmode' => 'numeric', 'placeholder' => '60123456789', 'hint' => 'Digits only, starting with the country code.'])
            </div>
            <label class="flex cursor-pointer items-start gap-3 border-t border-ink-700 px-4 py-3">
                <input type="hidden" name="sample_menu" value="0">
                <input type="checkbox" name="sample_menu" value="1" class="mt-0.5 size-4 accent-brand-600" @checked(old('sample_menu'))>
                <span>
                    <span class="block text-sm font-semibold text-ink-100">Add a sample menu</span>
                    <span class="block text-xs text-ink-400">About 30 example dishes with options, plus sample tables and delivery areas, to try things out. Edit or delete them later. Leave it off to start with an empty menu.</span>
                </span>
            </label>
        </section>

        <section class="rounded-xl border border-ink-700 bg-ink-850">
            <div class="border-b border-ink-700 px-4 py-3">
                <h2 class="text-base font-bold text-strong">Your admin account</h2>
                <p class="mt-1 text-sm text-ink-300">The super admin: full control of the menu, staff, outlets and settings.</p>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2">
                @include('install.partials.field', ['name' => 'admin_name', 'label' => 'Your name', 'required' => true, 'autocomplete' => 'name'])
                @include('install.partials.field', ['name' => 'admin_email', 'label' => 'Email (you sign in with it)', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
                @include('install.partials.field', ['name' => 'admin_password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'At least 8 characters.'])
                @include('install.partials.field', ['name' => 'admin_password_confirmation', 'label' => 'Password again', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
            </div>
        </section>

        <section class="rounded-xl border border-ink-700 bg-ink-850">
            <div class="border-b border-ink-700 px-4 py-3">
                <h2 class="text-base font-bold text-strong">Website</h2>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2">
                @include('install.partials.field', ['name' => 'url', 'label' => 'Website address', 'type' => 'url', 'value' => $url, 'required' => true, 'hint' => 'Detected from this page. Use https:// if your site has SSL.'])
                <div class="space-y-1.5">
                    <label for="f-timezone" class="block text-xs font-semibold text-ink-300">Time zone<span class="ml-0.5 text-danger-400">*</span></label>
                    <select id="f-timezone" name="timezone" class="h-11 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 text-sm text-ink-100 focus:border-flame-400 focus:outline-none">
                        @foreach ($timezones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', $timezone) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-ink-400">Opening hours and daily queue numbers follow it.</p>
                </div>
            </div>
        </section>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('install.database') }}" class="text-sm font-semibold text-ink-300 hover:text-strong">Back</a>
            <button id="install-button" class="inline-flex h-12 items-center rounded-lg bg-brand-600 px-6 text-sm font-bold text-white hover:bg-brand-500 disabled:opacity-60">Install</button>
        </div>
    </form>

    <script>
        // Setting up the database takes a little while: stop a second press.
        document.getElementById('install-form').addEventListener('submit', function () {
            var button = document.getElementById('install-button');
            button.disabled = true;
            button.textContent = 'Installing… this takes up to a minute';
        });
    </script>
@endsection
