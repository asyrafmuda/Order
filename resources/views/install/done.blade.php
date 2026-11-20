@extends('install.layout', ['step' => 4])

@section('title', 'Installed')

@section('content')
    <section class="rounded-xl border border-go-500/40 bg-go-900 p-4">
        <h1 class="text-lg font-bold text-strong">{{ $done['shop'] }} is online 🎉</h1>
        <p class="mt-1 text-sm text-ink-200">Sign in with <strong class="text-strong">{{ $done['email'] }}</strong> and the password you chose.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ $done['url'] }}/login" class="inline-flex h-11 items-center rounded-lg bg-brand-600 px-5 text-sm font-bold text-white hover:bg-brand-500">Open the admin panel</a>
            <a href="{{ $done['url'] }}/" target="_blank" rel="noreferrer" class="inline-flex h-11 items-center rounded-lg bg-ink-700 px-5 text-sm font-bold text-ink-100 hover:bg-ink-600">View the shop</a>
        </div>
    </section>

    <section class="mt-5 rounded-xl border border-flame-500/40 bg-ink-850">
        <div class="border-b border-ink-700 px-4 py-3">
            <h2 class="text-base font-bold text-strong">One more step: the cron job</h2>
            <p class="mt-1 text-sm text-ink-300">It runs every minute and sends WhatsApp updates and POS receipts, cancels unpaid orders and closes forgotten ones. In cPanel → <strong class="text-ink-100">Cron Jobs</strong>, choose “Once Per Minute” and paste this command:</p>
        </div>
        <div class="p-4">
            <div class="flex items-stretch gap-2">
                <code id="cron" class="min-w-0 flex-1 overflow-x-auto rounded-lg bg-ink-950 px-3 py-2.5 font-mono text-xs whitespace-nowrap text-flame-300">{{ $cron }}</code>
                <button type="button" id="copy-cron" class="shrink-0 rounded-lg bg-ink-700 px-3 text-xs font-bold text-ink-100 hover:bg-ink-600">Copy</button>
            </div>
            <p class="mt-2 text-xs text-ink-400">The command must use PHP {{ config('installer.php') }} or newer. If your host's plain <code class="text-ink-200">php</code> is older, use its PHP 8.3 path instead — on cPanel servers usually <code class="text-ink-200">/opt/cpanel/ea-php83/root/usr/bin/php</code>. Using crontab directly? Put <code class="text-ink-200">* * * * *</code> in front. The admin dashboard warns you if the job is not running.</p>
        </div>
    </section>

    <section class="mt-5 rounded-xl border border-ink-700 bg-ink-850 p-4" id="security" hidden>
        <h2 class="text-base font-bold text-strong">Security check</h2>
        <p id="security-ok" class="mt-1 text-sm text-go-400" hidden>✓ Private files (settings, logs) cannot be opened from the web.</p>
        <div id="security-bad" class="mt-2 rounded-lg border border-danger-600/50 bg-danger-900 p-3 text-sm text-danger-400" hidden>
            <p class="font-bold">Your settings file can be downloaded by anyone.</p>
            <p class="mt-1 text-ink-200">The web server is not using the .htaccess file that came with the app. Point the domain's document root to the <code>public</code> folder (cPanel → Domains), or ask your host to enable mod_rewrite and .htaccess files. Do this before taking orders.</p>
        </div>
    </section>

    <section class="mt-5 rounded-xl border border-ink-700 bg-ink-850 p-4">
        <h2 class="text-base font-bold text-strong">Next</h2>
        <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-ink-200">
            <li>Admin → <strong>Settings</strong>: opening hours, logo, colours and ways of ordering.</li>
            <li>Admin → <strong>Products</strong>: {{ $done['sample_menu'] ? 'replace the sample menu with your own.' : 'add your menu.' }}</li>
            <li>Admin → <strong>Users</strong>: accounts for your cashier, kitchen and riders.</li>
            <li>Admin → <strong>Tables &amp; QR</strong>: print the shop QR poster for your counter.</li>
            <li>Online payment (FPX, card, e-wallet) stays off until a payment gateway is connected. Customers pay at the counter.</li>
        </ol>
        @unless ($done['storage_link'])
            <p class="mt-3 text-xs text-ink-400">This server does not allow symbolic links, so uploaded photos are kept in <code>public/storage</code>. Nothing to do — just keep that folder when you update.</p>
        @endunless
    </section>

    <script>
        document.getElementById('copy-cron').addEventListener('click', function () {
            var text = document.getElementById('cron').textContent;
            var button = this;
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                button.textContent = 'Copied';
            }, function () {
                var range = document.createRange();
                range.selectNodeContents(document.getElementById('cron'));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
            });
        });

        // Ask this server for the private settings file, as a stranger would.
        Promise.all(['/.env', '/composer.json'].map(function (path) {
            return fetch(path, { cache: 'no-store', credentials: 'omit' })
                .then(function (response) { return response.ok ? response.text() : ''; })
                .then(function (body) { return /APP_KEY|"laravel\/framework"/.test(body); })
                .catch(function () { return false; });
        })).then(function (results) {
            var exposed = results.some(Boolean);
            document.getElementById('security').hidden = false;
            document.getElementById(exposed ? 'security-bad' : 'security-ok').hidden = false;
        });
    </script>
@endsection
