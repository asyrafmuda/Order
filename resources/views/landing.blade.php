@php
    $title = "{$product} — Sistem order QR, POS & dapur untuk kedai makan";
    $description = 'Pelanggan scan QR dan order sendiri, cashier ambil order di POS, dapur nampak tiket di skrin. Bayaran FPX terus ke akaun anda. Bayar sekali, tiada komisen.';
    $primaryContact = $whatsapp ?? ($email ? 'mailto:'.$email : null);
    $features = [
        ['📱', 'QR order — meja & kaunter', 'Tampal QR di meja atau di kaunter. Pelanggan pilih menu, ubah suai (tak nak sos, extra cheese), bayar dan terus dapat nombor giliran.'],
        ['⚡', 'POS kaunter yang pantas', 'Order lisan "burger ayam 3, daging 2, satu tak nak sos"? Cashier tekan, tambah nota, hantar ke dapur, dan cetak resit di printer thermal 58/80 mm.'],
        ['👨‍🍳', 'Skrin dapur (KDS)', 'Tiket ikut stesen — grill, fryer, minuman. Masa menunggu jelas, bunyi bila order baru masuk, dan tanda habis stok terus dari dapur.'],
        ['💳', 'Bayaran online', 'FPX, kad dan e-wallet melalui Billplz, Bayarcash atau Stripe. Duit masuk terus ke akaun merchant anda. Tunai di kaunter pun boleh.'],
        ['🛵', 'Pickup, delivery & dine-in', 'Zon penghantaran dengan caj sendiri, skrin rider, dan tiket pelanggan yang dikemas kini sendiri sampai order siap.'],
        ['🏬', 'Banyak cawangan', 'Satu menu untuk semua outlet, harga berbeza ikut outlet, manager untuk setiap cawangan, dan laporan gabungan untuk tuan punya.'],
        ['🎨', 'Jenama anda sendiri', 'Logo, nama dan warna tema kedai anda. Pelanggan nampak kedai anda — bukan aplikasi platform orang lain.'],
        ['📊', 'Laporan & invois', 'Jualan harian, menu terlaris, waktu sibuk, invois PDF dan SST — semuanya dari satu dashboard.'],
        ['🔄', 'Update satu klik', 'Versi baru dipasang terus dari admin dengan satu klik, dengan backup automatik sebelum apa-apa diganti.'],
    ];
    $screens = [
        ['Pelanggan', 'Order dari telefon sendiri', 'Menu bergambar dengan pilihan saiz, add-on dan set combo. Selepas bayar, pelanggan nampak nombor giliran dan anggaran masa siap — dikemas kini sendiri.', ['menu', 'customise', 'track']],
        ['Kaunter', 'POS untuk order lisan', 'Cari dengan kod menu (B1, R2…), tekan untuk tambah, nota untuk setiap item. Takeaway atau dine-in, tunai atau online.', ['pos']],
        ['Dapur', 'Tiket jelas, tiada kertas hilang', 'Setiap stesen nampak apa yang perlu dimasak dahulu. Tekan Start, Done, dan Ready — kaunter dan pelanggan terus tahu.', ['kds']],
        ['Tuan punya', 'Tahu prestasi kedai setiap hari', 'Jualan hari ini, 14 hari terakhir, menu terlaris dan status setiap order. Untuk banyak cawangan, lihat satu-satu atau gabungan.', ['dashboard']],
    ];
    $faqs = [
        ['Perlu VPS ke?', 'Tidak. Shared hosting biasa (cPanel) dengan PHP 8.3 dan MySQL sudah cukup. Installer membimbing anda langkah demi langkah, dan panduan pemasangan Bahasa Melayu disertakan.'],
        ['Ada yuran bulanan atau komisen?', 'Tiada yuran sistem dan tiada komisen setiap order. Kos lain hanyalah hosting dan domain anda sendiri, serta caj transaksi payment gateway (ikut kadar gateway yang anda pilih).'],
        ['Boleh guna tanpa bayaran online?', 'Boleh. Hidupkan "tunai di kaunter" sahaja, dan sambung payment gateway bila-bila masa kemudian.'],
        ['Perlu beli tablet atau mesin khas?', 'Tidak. Mana-mana telefon, tablet atau komputer dengan browser boleh jadi POS, skrin dapur atau skrin kaunter.'],
        ['Boleh guna untuk banyak cawangan?', 'Boleh. Hidupkan mod banyak outlet: setiap cawangan ada manager, stok, dan harga sendiri, dan tuan punya nampak laporan gabungan.'],
        ['Macam mana dapat versi baru?', 'Bila versi baru keluar, ia muncul di Admin → System Update. Tekan sekali untuk pasang; fail lama dibackup secara automatik.'],
        ['Boleh ubah suai sistem?', 'Boleh. Kod sumber penuh disertakan, jadi programmer anda boleh menambah ciri sendiri.'],
    ];
    $shot = fn (string $name) => asset("images/landing/{$name}.webp");
@endphp
<!DOCTYPE html>
<html lang="ms" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#09090b">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ url('/web') }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url('/web') }}">
        <meta property="og:image" content="{{ asset('images/landing/og.jpg') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|bebas-neue:400" rel="stylesheet">
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh bg-ink-950 text-ink-200 antialiased">
        {{-- Navigation --}}
        <header class="sticky top-0 z-30 border-b border-ink-800/80 bg-ink-950/85 backdrop-blur">
            <nav class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6" aria-label="Utama">
                <a href="#top" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-lg shadow-glow" aria-hidden>🍽️</span>
                    <span class="font-display text-2xl leading-none tracking-wide text-strong">{{ $product }}</span>
                </a>
                <div class="ml-auto hidden items-center gap-6 text-sm font-semibold text-ink-300 md:flex">
                    <a href="#ciri" class="hover:text-strong">Ciri</a>
                    <a href="#skrin" class="hover:text-strong">Skrin</a>
                    <a href="#harga" class="hover:text-strong">Harga</a>
                    <a href="#soalan" class="hover:text-strong">Soalan</a>
                </div>
                <a href="{{ $demoUrl }}" class="ml-auto inline-flex h-10 items-center rounded-lg bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-500 md:ml-0">Cuba demo</a>
            </nav>
        </header>

        <main id="top">
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-x-0 -top-40 h-[36rem] bg-[radial-gradient(ellipse_at_top,rgba(238,43,50,0.28),transparent_60%)]" aria-hidden></div>
                <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pt-14 pb-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pt-20 lg:pb-24">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-flame-500/40 bg-flame-900/40 px-3 py-1 text-xs font-bold tracking-wide text-flame-300">
                            <span class="size-1.5 rounded-full bg-flame-400" aria-hidden></span> Untuk kedai makan, kafe &amp; food truck
                        </p>
                        <h1 class="mt-5 font-display text-5xl leading-[0.95] text-strong uppercase sm:text-6xl lg:text-7xl">
                            Pelanggan scan.<br>Order masuk dapur.<br><span class="text-brand-500">Siap.</span>
                        </h1>
                        <p class="mt-5 max-w-xl text-base leading-relaxed text-ink-300 sm:text-lg">
                            QR order di meja atau kaunter, POS kaunter yang pantas, skrin dapur dan bayaran FPX — dalam satu sistem milik kedai anda sendiri. <strong class="text-ink-100">Tiada komisen setiap order.</strong>
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ $demoUrl }}" class="inline-flex h-12 items-center gap-2 rounded-xl bg-brand-600 px-6 text-base font-bold text-white shadow-glow hover:bg-brand-500">
                                Cuba demo sekarang <span aria-hidden>→</span>
                            </a>
                            @if ($whatsapp)
                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center gap-2 rounded-xl border border-ink-600 bg-ink-900 px-6 text-base font-bold text-ink-100 hover:border-go-500 hover:text-strong">
                                    <span aria-hidden>💬</span> WhatsApp kami
                                </a>
                            @else
                                <a href="#harga" class="inline-flex h-12 items-center rounded-xl border border-ink-600 bg-ink-900 px-6 text-base font-bold text-ink-100 hover:border-ink-400 hover:text-strong">Lihat harga</a>
                            @endif
                        </div>
                        <ul class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink-400">
                            <li class="flex items-center gap-1.5"><span class="text-go-400" aria-hidden>✓</span> Bayar sekali</li>
                            <li class="flex items-center gap-1.5"><span class="text-go-400" aria-hidden>✓</span> Shared hosting pun boleh</li>
                            <li class="flex items-center gap-1.5"><span class="text-go-400" aria-hidden>✓</span> Guna telefon &amp; tablet sedia ada</li>
                        </ul>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                        <div class="overflow-hidden rounded-2xl border border-ink-700 bg-ink-900 shadow-2xl shadow-black/60">
                            <div class="flex items-center gap-1.5 border-b border-ink-700 px-3 py-2" aria-hidden>
                                <span class="size-2.5 rounded-full bg-ink-600"></span><span class="size-2.5 rounded-full bg-ink-600"></span><span class="size-2.5 rounded-full bg-ink-600"></span>
                            </div>
                            <img src="{{ $shot('kds') }}" width="1600" height="1000" alt="Skrin dapur dengan tiket order ikut stesen" class="block w-full">
                        </div>
                        <div class="absolute -bottom-10 -left-2 w-[34%] max-w-[180px] overflow-hidden rounded-[1.4rem] border-4 border-ink-800 bg-ink-900 shadow-2xl shadow-black/70 sm:-left-8">
                            <img src="{{ $shot('menu') }}" width="780" height="1328" alt="Menu pelanggan di telefon" class="block w-full">
                        </div>
                    </div>
                </div>
            </section>

            {{-- Quick facts --}}
            <section class="border-y border-ink-800 bg-ink-900/60">
                <dl class="mx-auto grid max-w-6xl grid-cols-2 gap-px px-4 sm:px-6 lg:grid-cols-4">
                    @foreach ([['0%', 'komisen setiap order'], ['1×', 'bayar sekali, milik anda'], ['3', 'payment gateway Malaysia & antarabangsa'], ['∞', 'outlet, menu dan staf']] as [$figure, $label])
                        <div class="px-2 py-6 text-center">
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="font-display text-4xl text-flame-400">{{ $figure }}</dd>
                            <dd class="mt-1 text-sm text-ink-400">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Features --}}
            <section id="ciri" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Ciri-ciri</p>
                    <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Semua yang kedai perlukan, dari order sampai laporan</h2>
                </div>
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as [$icon, $name, $text])
                        <article class="rounded-2xl border border-ink-700 bg-ink-900 p-5 transition-colors hover:border-ink-500">
                            <p class="flex size-11 items-center justify-center rounded-xl bg-ink-800 text-2xl" aria-hidden>{{ $icon }}</p>
                            <h3 class="mt-4 text-lg font-bold text-strong">{{ $name }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-ink-300">{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
                <p class="mt-6 text-sm text-ink-400">
                    Integrasi: <span class="text-ink-200">Billplz</span> · <span class="text-ink-200">Bayarcash</span> · <span class="text-ink-200">Stripe</span> · <span class="text-ink-200">Loyverse POS</span> · <span class="text-ink-200">WhatsApp Cloud API</span>
                </p>
            </section>

            {{-- Screens --}}
            <section id="skrin" class="scroll-mt-20 border-t border-ink-800 bg-ink-900/40">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div class="max-w-2xl">
                        <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Skrin sebenar</p>
                        <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Satu sistem, empat skrin</h2>
                        <p class="mt-3 text-ink-300">Setiap orang nampak apa yang mereka perlukan sahaja — dari telefon pelanggan sampai dashboard tuan punya.</p>
                    </div>

                    <div class="mt-14 space-y-20">
                        @foreach ($screens as $index => [$who, $heading, $text, $images])
                            <div class="grid items-center gap-8 lg:grid-cols-[1fr_1.4fr] lg:gap-14">
                                <div @class(['lg:order-2' => $index % 2 === 1])>
                                    <p class="inline-flex rounded-full bg-brand-900 px-3 py-1 text-xs font-bold tracking-wide text-brand-400 uppercase">{{ $who }}</p>
                                    <h3 class="mt-3 text-2xl font-bold text-strong sm:text-3xl">{{ $heading }}</h3>
                                    <p class="mt-3 leading-relaxed text-ink-300">{{ $text }}</p>
                                </div>
                                @if (count($images) > 1)
                                    <div class="grid grid-cols-3 gap-3 sm:gap-4">
                                        @foreach ($images as $image)
                                            <img src="{{ $shot($image) }}" width="780" height="1328" loading="lazy" alt="Skrin pelanggan: {{ $image }}" class="w-full rounded-2xl border border-ink-700 shadow-xl shadow-black/50">
                                        @endforeach
                                    </div>
                                @else
                                    <img src="{{ $shot($images[0]) }}" width="1600" height="1000" loading="lazy" alt="Skrin {{ strtolower($who) }}" class="w-full rounded-2xl border border-ink-700 shadow-xl shadow-black/50">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Why own it --}}
            <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.2fr] lg:items-center">
                    <div>
                        <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Kenapa sistem sendiri</p>
                        <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Pelanggan anda, untung anda</h2>
                        <p class="mt-3 text-ink-300">Platform penghantaran bagus untuk dicari orang baru. Tapi untuk pelanggan tetap yang datang ke kedai atau order semula, kenapa kongsi untung setiap order?</p>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-ink-700">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-ink-850 text-xs tracking-wide text-ink-400 uppercase">
                                <tr>
                                    <th class="px-4 py-3 font-semibold" scope="col"></th>
                                    <th class="px-4 py-3 font-semibold" scope="col">Platform berkomisen</th>
                                    <th class="px-4 py-3 font-semibold text-strong" scope="col">{{ $product }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-700 bg-ink-900">
                                @foreach ([['Komisen setiap order', 'Ya, peratusan setiap order', 'Tiada'], ['Data pelanggan', 'Milik platform', 'Milik anda'], ['Jenama yang dilihat', 'Aplikasi platform', 'Kedai anda'], ['Duit jualan', 'Tunggu payout', 'Terus ke akaun gateway anda'], ['Order di meja & kaunter', 'Tidak', 'Ya, dengan QR & POS']] as [$row, $them, $us])
                                    <tr>
                                        <th class="px-4 py-3 font-semibold text-ink-200" scope="row">{{ $row }}</th>
                                        <td class="px-4 py-3 text-ink-400">{{ $them }}</td>
                                        <td class="px-4 py-3 font-semibold text-go-400">{{ $us }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- How it works --}}
            <section class="border-t border-ink-800 bg-ink-900/40">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Cara mula</p>
                    <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Tiga langkah, tanpa programmer</h2>
                    <ol class="mt-10 grid gap-4 md:grid-cols-3">
                        @foreach ([['Dapatkan lesen', 'Anda terima fail sistem, purchase code dan panduan pemasangan Bahasa Melayu.'], ['Pasang di hosting anda', 'Upload ke cPanel, buka laman anda, dan ikut installer: database, nama kedai, akaun admin.'], ['Mula terima order', 'Masukkan menu, cetak QR untuk meja atau kaunter, dan buka skrin dapur di tablet.']] as $step => [$name, $text])
                            <li class="relative rounded-2xl border border-ink-700 bg-ink-900 p-6">
                                <span class="font-display text-5xl leading-none text-brand-500">0{{ $step + 1 }}</span>
                                <h3 class="mt-3 text-lg font-bold text-strong">{{ $name }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-ink-300">{{ $text }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Pricing --}}
            <section id="harga" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6">
                <div class="mx-auto max-w-xl text-center">
                    <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Harga</p>
                    <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Bayar sekali. Milik anda.</h2>
                </div>
                <div class="relative mx-auto mt-10 max-w-lg overflow-hidden rounded-3xl border border-brand-600/60 bg-ink-900 p-7 shadow-glow sm:p-9">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-[radial-gradient(ellipse_at_top,rgba(238,43,50,0.22),transparent_70%)]" aria-hidden></div>
                    <div class="relative">
                        <p class="text-sm font-bold text-ink-300">Lesen penuh {{ $product }}</p>
                        @if ($price !== '')
                            <p class="mt-2 font-display text-6xl leading-none text-strong">{{ $price }}</p>
                            <p class="mt-2 text-sm text-ink-400">{{ $priceNote }}</p>
                        @else
                            <p class="mt-2 font-display text-5xl leading-none text-strong">Hubungi kami</p>
                            <p class="mt-2 text-sm text-ink-400">untuk harga dan pakej cawangan</p>
                        @endif
                        <ul class="mt-6 space-y-2.5 text-sm text-ink-200">
                            @foreach (['Semua ciri: QR order, POS, skrin dapur, delivery, laporan', 'Banyak cawangan dalam satu sistem', 'Billplz, Bayarcash atau Stripe — duit terus ke akaun anda', 'Installer untuk shared hosting (cPanel)', 'Update satu klik dari admin', 'Kod sumber penuh — boleh ubah suai', 'Panduan pemasangan Bahasa Melayu'] as $item)
                                <li class="flex gap-2.5"><span class="mt-0.5 text-go-400" aria-hidden>✓</span>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-8 grid gap-2.5">
                            @if ($primaryContact)
                                <a href="{{ $primaryContact }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-600 px-6 text-base font-bold text-white hover:bg-brand-500">
                                    {{ $whatsapp ? 'Beli melalui WhatsApp' : 'Hubungi kami' }}
                                </a>
                            @endif
                            <a href="{{ $demoUrl }}" @class([
                                'inline-flex h-12 items-center justify-center rounded-xl px-6 text-base font-bold',
                                'border border-ink-600 text-ink-100 hover:border-ink-400' => $primaryContact,
                                'bg-brand-600 text-white hover:bg-brand-500' => ! $primaryContact,
                            ])>Cuba demo dahulu</a>
                        </div>
                        <p class="mt-5 text-xs leading-relaxed text-ink-500">Hosting, domain dan caj transaksi payment gateway dibayar oleh kedai sendiri, terus kepada penyedia masing-masing.</p>
                    </div>
                </div>
            </section>

            {{-- Demo --}}
            <section id="demo" class="border-t border-ink-800 bg-ink-900/40">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div class="grid gap-10 lg:grid-cols-2">
                        <div>
                            <p class="text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Demo</p>
                            <h2 class="mt-2 font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Cuba sendiri, sekarang</h2>
                            <p class="mt-3 text-ink-300">Demo ini kedai burger contoh. Buat order dari telefon sebagai pelanggan, kemudian log masuk sebagai staf dan lihat order itu sampai ke dapur.</p>
                            <p class="mt-3 text-sm text-ink-400">Bayaran dalam demo adalah simulasi — tiada duit sebenar.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([['🛒', 'Menu pelanggan', '/', 'Order seperti pelanggan'], ['⚡', 'POS kaunter', '/pos', 'Ambil order lisan'], ['👨‍🍳', 'Skrin dapur', '/kitchen', 'Masak ikut tiket'], ['📊', 'Admin', '/admin', 'Menu, laporan, tetapan']] as [$icon, $name, $path, $hint])
                                <a href="{{ rtrim($demoUrl, '/').$path }}" class="flex items-center gap-3 rounded-2xl border border-ink-700 bg-ink-900 p-4 hover:border-brand-500">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-ink-800 text-2xl" aria-hidden>{{ $icon }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-bold text-strong">{{ $name }}</span>
                                        <span class="block text-xs text-ink-400">{{ $hint }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @if ($demoAccounts !== [])
                        <div class="mt-8 overflow-x-auto rounded-2xl border border-ink-700 bg-ink-900">
                            <table class="w-full min-w-[28rem] text-left text-sm">
                                <caption class="border-b border-ink-700 px-4 py-3 text-left text-sm font-bold text-strong">Akaun demo untuk staf</caption>
                                <thead class="text-xs tracking-wide text-ink-400 uppercase">
                                    <tr><th class="px-4 py-2 font-semibold" scope="col">Peranan</th><th class="px-4 py-2 font-semibold" scope="col">Login</th><th class="px-4 py-2 font-semibold" scope="col">Kata laluan</th></tr>
                                </thead>
                                <tbody class="divide-y divide-ink-700">
                                    @foreach ($demoAccounts as $account)
                                        <tr>
                                            <td class="px-4 py-2.5 text-ink-200">{{ $account['label'] }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs text-ink-100">{{ $account['email'] }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs text-ink-100">{{ $account['password'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            {{-- FAQ --}}
            <section id="soalan" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-20 sm:px-6">
                <p class="text-center text-sm font-bold tracking-[0.2em] text-brand-400 uppercase">Soalan lazim</p>
                <h2 class="mt-2 text-center font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Yang selalu ditanya</h2>
                <div class="mt-10 space-y-3">
                    @foreach ($faqs as [$question, $answer])
                        <details class="group rounded-2xl border border-ink-700 bg-ink-900 open:border-ink-500">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-bold text-strong [&::-webkit-details-marker]:hidden">
                                {{ $question }}
                                <span class="shrink-0 text-xl text-ink-400 transition-transform group-open:rotate-45" aria-hidden>+</span>
                            </summary>
                            <p class="px-5 pb-5 text-sm leading-relaxed text-ink-300">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- Final call --}}
            <section class="px-4 pb-20 sm:px-6">
                <div class="relative mx-auto max-w-6xl overflow-hidden rounded-3xl border border-brand-600/50 bg-brand-900 px-6 py-12 text-center sm:px-12">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_bottom,rgba(255,176,32,0.18),transparent_65%)]" aria-hidden></div>
                    <div class="relative">
                        <h2 class="font-display text-4xl leading-none text-strong uppercase sm:text-5xl">Sedia untuk waktu puncak seterusnya?</h2>
                        <p class="mx-auto mt-3 max-w-xl text-ink-200">Cuba demo dalam 2 minit. Bila sedia, kami bantu anda pasang.</p>
                        <div class="mt-7 flex flex-wrap justify-center gap-3">
                            <a href="{{ $demoUrl }}" class="inline-flex h-12 items-center rounded-xl bg-white px-6 text-base font-bold text-ink-950 hover:bg-ink-100">Cuba demo</a>
                            @if ($primaryContact)
                                <a href="{{ $primaryContact }}" @if ($whatsapp) target="_blank" rel="noopener" @endif class="inline-flex h-12 items-center rounded-xl border border-white/40 px-6 text-base font-bold text-white hover:border-white">
                                    {{ $whatsapp ? 'WhatsApp kami' : 'Emel kami' }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-ink-800">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-ink-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p>© {{ now()->year }} {{ $product }}. Sistem order untuk kedai makan.</p>
                <p class="flex flex-wrap gap-4">
                    @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-ink-200">WhatsApp</a>@endif
                    @if ($email)<a href="mailto:{{ $email }}" class="hover:text-ink-200">{{ $email }}</a>@endif
                    <a href="{{ $demoUrl }}" class="hover:text-ink-200">Demo</a>
                </p>
            </div>
        </footer>
    </body>
</html>
