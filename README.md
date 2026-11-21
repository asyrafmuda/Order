# Boss Burger — Sistem Order Online

Sistem order online untuk kedai makan (burger, restoran, kafe — nama, logo dan warna diubah di Admin → Settings), satu outlet atau banyak cawangan: menu & cart, checkout, tiket digital dengan tracking live, Kitchen Display (KDS), kaunter pickup, delivery dengan rider, dan admin panel. Order yang disahkan dihantar ke Loyverse POS sebagai resit.

- **Backend:** Laravel 13, PHP 8.3+, MySQL 8
- **Frontend:** Inertia 3 + React 19 + TypeScript + Tailwind 4
- **Real-time:** Laravel Reverb (websocket); kalau Reverb tak dihidupkan, skrin live guna polling
- **Ujian:** Pest

## Setup

Perlu PHP 8.3+, Composer, Node 22+ dan MySQL 8.

```bash
cp .env.example .env
# isi DB_DATABASE / DB_USERNAME / DB_PASSWORD dalam .env, kemudian:
composer setup          # install, key, kunci Reverb, migrate + seed, storage:link, npm install, build
composer dev            # server, queue worker, log, Vite dan Reverb serentak
```

Buka http://localhost:8000.

> **Waktu operasi.** Default ialah 12:00 PM – 11:00 PM (last order). Di luar waktu itu storefront papar "closed" dan butang order dimatikan. Tukar di Admin → Settings → Hours — contohnya sebelum demo pada waktu pagi. POS kaunter tak terikat dengan waktu ini.

`composer setup` seed menu Boss Burger, zon delivery dan satu akaun untuk setiap role. Untuk data jualan contoh (dashboard & laporan): `php artisan db:seed --class=DemoOrderSeeder`.

### Kemas kini selepas `git pull`

```bash
composer install
php artisan migrate      # tambah jadual/lajur baru; data sedia ada kekal
npm install && npm run build
```

Migration outlet memindahkan order, meja, zon delivery dan tetapan sedia ada ke *Main Outlet* secara automatik. Akaun demo baru (`manager@…`): `php artisan db:seed --class=UserSeeder`.

### Akaun demo

Staf login dengan email dan password `password`. Pelanggan login dengan nombor telefon dan PIN. **Tukar semuanya sebelum go live.**

| Role | Login | Skrin |
|---|---|---|
| Super Admin | admin@bossburger.test | `/admin` (semua, termasuk Outlets dan Settings) |
| Outlet Manager | manager@bossburger.test | `/admin` (outlet sendiri sahaja) |
| Cashier | cashier@bossburger.test | `/pos` (ambil order), `/counter` (serah order) |
| Kitchen | kitchen@bossburger.test | `/kitchen` |
| Rider | rider@bossburger.test | `/rider` |
| Waiter | waiter@bossburger.test | `/pos` (ambil order di meja), `/counter` |
| Customer | `012-345 6789`, PIN `135790` | storefront |

## Cara order

Setiap satu boleh dihidup/matikan di Admin → Settings → Ordering. Kedai tanpa delivery hanya perlu matikan suis Delivery.

| Cara | Siapa | Perlu login? |
|---|---|---|
| **Dine-in (QR meja)** | Pelanggan scan kod di meja, atau waiter ambil order di `/pos` dan pilih meja | Tidak. Nama dan telefon pun pilihan |
| **Di kaunter (QR kedai)** | Pelanggan scan poster di kaunter/kiosk, atau staf tekan di `/pos` | Tidak. Nama dan telefon pun pilihan |
| **Self pickup** | Pelanggan order dari rumah, datang ambil | Tidak (guest: nama + telefon) |
| **Delivery** | Pelanggan order dari rumah, rider hantar | Tidak (guest: nama + telefon + alamat) |

### Kiosk atau kaunter tanpa meja

Kedai yang hanya ada kaunter (tiada meja, tiada delivery): di Admin → Settings (atau Outlets) matikan *Delivery* dan *Dine-in by table QR*. Ada dua cara ambil order, dan dua-dua masuk ke skrin dapur yang sama dengan nombor giliran yang sama:

1. **Pelanggan cakap, staf tekan** — `/pos` di telefon atau tablet. Contoh "burger ayam ×3, burger daging ×2 tak nak sos": tekan burger ayam, `+` `+`, *Add*; tekan ikon pilihan pada burger daging, *No Sauce*, `+`, *Add*; *Charge* → cash. Nombor giliran keluar terus dan tiket masuk ke dapur — tak perlu tulis atas kertas.
2. **Pelanggan scan sendiri** — Admin → **Tables & QR** → *Shop QR code* → **Print poster**, tampal di kaunter. Pelanggan scan, pilih, hantar: tiada app, tiada nama, tiada login. Bayar di kaunter (atau online) dan tunggu nombor dipanggil; telefon mereka tunjuk bila order siap.

Setiap outlet ada kod kedainya sendiri; scan kod itu terus masuk ke menu dan harga outlet tersebut. Order melalui kod kedai dikira "pelanggan ada di kaunter" selama 3 jam selepas scan — sebab itu nama/telefon tak wajib dan bayar di kaunter dibenarkan tanpa akaun (ikut tetapan *Orders placed in the shop can pay at the counter*). Kalau kod tersebar, tekan *New code* dan cetak semula; kod lama terus mati. Sama seperti QR meja, **buka admin melalui alamat sebenar** (bukan `localhost`) sebelum mencetak.

### Dine-in dengan QR meja

1. Admin → **Tables & QR**: tambah meja, tekan **Print QR codes**, potong dan letak satu di setiap meja.
2. Pelanggan scan → menu terbuka dalam mod "Dine-in · Table 5" → pilih → hantar. Tiada app, tiada pendaftaran.
3. Dapur nampak nombor meja pada tiket; makanan dihantar ke meja.
4. Bayaran: di kaunter sebelum balik (default), atau online. Tetapan *Table orders can pay at the counter* mengawalnya.

Kod QR mengandungi alamat laman semasa admin membukanya. **Buka admin melalui alamat yang boleh dicapai telefon pelanggan** (domain sebenar, atau IP LAN semasa ujian — bukan `localhost`) sebelum mencetak. Kalau satu kod disalah guna, tekan *New code* pada meja itu dan cetak semula; kod lama terus tak berfungsi.

### Akaun pelanggan: telefon + PIN

Akaun adalah pilihan — untuk sejarah order, alamat tersimpan dan bayar di kaunter bagi pickup. Pendaftaran hanya nama, nombor telefon dan PIN 6 digit; email pilihan (untuk reset PIN sendiri). Pelanggan tanpa email yang lupa PIN boleh minta staf set PIN baru di Admin → Users. Login dihadkan: 5 cubaan setiap peranti dan 10 setiap akaun dalam 15 minit. Staf kekal guna email + password penuh.

OTP melalui SMS/WhatsApp belum ada; ia boleh ditambah di atas `App\Contracts\MessageChannel` bila penyedia mesej disambung.

## Jenama dan tema

Admin → Settings → **Branding**. Tiada kod yang perlu diubah untuk kedai lain.

- **Theme:** *Dark* (latar hitam, default) atau *Light* (latar putih). Terpakai pada semua skrin — pelanggan, POS, dapur, admin.
- **Ready-made looks:** lapan gabungan siap (Grill Night, Midnight Blue, Forest, Royal Purple, Clean Red, Fresh Green, Ocean, Coffee). Satu tekan mengisi tema dan dua warna; boleh diubah lagi selepas itu.
- **Main colour / Accent colour:** warna sendiri. Warna utama untuk butang dan sorotan; aksen untuk harga dan lencana.
- Nama, tagline, logo (atau emoji) dan teks footer.

Perubahan terpakai sebaik sahaja *Save* ditekan, tanpa refresh; pelanggan mendapatnya pada halaman seterusnya.

Yang dijaga oleh sistem: warna utama yang terlalu pudar untuk teks putih pada butang ditolak; pada tema gelap, aksen yang terlalu gelap ditolak; pada tema cerah, aksen digelapkan sendiri untuk teks supaya harga kekal terbaca. Ralat, butang padam, *sold out* dan tiket lewat **sentiasa merah** walau apa pun warna jenama.

Dalam kod: `App\Support\BrandPalette` menukar tetapan kepada CSS custom properties (`--color-ink-*`, `--color-brand-*`, `--color-flame-*`, `--color-strong`, …) yang menimpa default dalam `resources/css/app.css`. Guna token itu (`bg-ink-850`, `text-strong`, `text-danger-400`) dan jangan tulis warna tetap seperti `text-white` kecuali di atas latar berwarna pekat.

## Banyak outlet (cawangan)

Satu bisnes, banyak outlet. Dimatikan secara default — kedai satu cawangan tak nampak apa-apa perbezaan.

**Hidupkan:** Admin → Settings → **Outlets** → *This business has more than one outlet* → Save. Butiran kedai sedia ada jadi outlet utama (*Main Outlet*); kemudian tambah cawangan di Admin → **Outlets**.

| Dikongsi semua outlet | Berasingan setiap outlet |
|---|---|
| Menu, kategori, add-on, combo dan harga standard | Order dan nombor giliran (setiap outlet ada A001 sendiri; prefix boleh berbeza) |
| Akaun pelanggan dan sejarah order | Meja & kod QR, zon delivery, rider |
| Jenama, cukai, kaedah bayaran, integrasi | Alamat, telefon, WhatsApp, waktu operasi, kaunter |
| Stesen dapur | Suis pickup / delivery / dine-in, anggaran masa siap |
| | Senarai *sold out* (habis di Shah Alam tak sorok item di outlet lain) |
| | Harga sendiri, kalau outlet itu caj lain daripada harga standard |
| | Staf, dashboard, laporan, KDS, kaunter, POS |

**Siapa nampak apa**

| Role | Outlet | Boleh buat |
|---|---|---|
| **Super Admin** | Semua — tukar melalui pemilih outlet di header | Semua: menu, pengguna, outlets, settings, integrasi |
| **Outlet Manager** | Outlet sendiri | Dashboard, order, meja, zon delivery, rider, laporan dan butiran outlet sendiri (alamat, waktu, cara order). Tak boleh ubah menu, pengguna, settings atau outlet lain |
| Cashier, Waiter, Kitchen, Rider | Outlet sendiri | Skrin kerja masing-masing, hanya order outlet itu |

Staf ditugaskan ke outlet di Admin → Users → *Works at*. Staf yang belum ditugaskan dikira bekerja di outlet utama. Rekod outlet lain yang dibuka terus melalui URL dijawab 404.

**Pelanggan** pilih outlet di bahagian atas menu. Scan QR meja terus masuk ke outlet meja itu. Tiket, invois dan mesej WhatsApp sentiasa guna alamat outlet tempat order dibuat.

**Harga ikut outlet.** Admin → **Outlet Prices** (atau butang *Prices* pada kad outlet) — untuk outlet yang sedang dilihat, taip harga hanya pada item yang berbeza: produk, saiz, add-on atau naik taraf combo. Yang dibiarkan kosong ikut harga standard di Products, termasuk bila harga standard berubah kemudian. Menu pelanggan, cart, checkout dan POS semua guna harga outlet tempat order dibuat; order lama tak berubah. Hanya Super Admin boleh tetapkan harga.

**Laporan gabungan.** Admin → Reports → *All outlets* (Super Admin sahaja) menjumlahkan semua outlet, dengan jadual *Sales by outlet* untuk banding cawangan. Export CSV dalam mod ini ada lajur *Outlet*. Manager kekal nampak outlet sendiri sahaja.

**Mematikan semula** hanya dibenarkan bila outlet lain tiada order yang belum selesai. Data cawangan disimpan (tak dipadam) dan muncul semula bila dihidupkan balik. Outlet yang pernah terima order tak boleh dipadam — nyahaktifkan sahaja; outlet utama tak boleh dipadam atau dinyahaktifkan.

Dalam kod: `App\Support\OutletContext` tentukan outlet untuk setiap request; model `Order`, `DiningTable`, `DeliveryZone` guna trait `BelongsToOutlet` dan ditapis dengan `->forOutlet()`; middleware `outlet` (`EnsureOutletAccess`) jaga rekod yang dialamatkan terus; tetapan per-outlet disenaraikan dalam `Settings::OUTLET_KEYS`.

## Telefon dan tablet

Kebanyakan pelanggan guna telefon, dan krew guna tablet atau telefon, jadi setiap skrin direka untuk sentuhan:

- **Storefront di telefon:** header satu baris, bar tab di bawah (Menu, My Order, Kitchen, Account), kad menu padat, bar *View ticket* yang sentiasa nampak, dan butang bayar yang melekat di bawah semasa checkout.
- **POS di telefon:** menu skrin penuh dengan bar *View order* di bawah; tiket dan pilihan item dibuka sebagai helaian skrin penuh. Di tablet, menu dan tiket bersebelahan.
- **Kaunter di telefon:** order yang sedia diserah dipaparkan dahulu, dan tindakan (kutip cash, serah) berada di atas senarai item.
- Medan input 16px di skrin kecil supaya iOS Safari tak zoom bila ditekan, dan bar bawah menghormati *safe area* telefon.

## POS kaunter (`/pos`)

Untuk order walk-in yang diambil oleh staf. Direka untuk laju:

- Satu ketuk untuk tambah item biasa. Item yang ada pilihan wajib (saiz, sos) buka satu skrin pilihan — saiz, "No Veggies / No Sauce", "Extra Mayo", combo, nota dapur dan kuantiti semuanya di situ.
- Ketuk baris dalam tiket untuk ubah; `+` / `−` untuk kuantiti.
- Papan kekunci: `/` cari (nama atau kod, `Enter` pilih padanan pertama), `Enter` tambah / sahkan, `F2` bayar, `Esc` tutup.
- Bayaran: Cash (dengan kiraan baki), Card, QR / E-wallet, atau *Pay later* (kutip masa serah di `/counter`).
- Order terus masuk dapur dengan nombor giliran yang sama seperti order online. Takeaway atau Dine-in; nama / meja dan telefon adalah pilihan.
- Nota pantas ("Pedas", "Potong dua"…) boleh diubah di Admin → Settings → Counter POS. Pilihan seperti "No Sauce" ialah modifier biasa — urus di Admin → Options & Add-ons.

Tiket disimpan dalam tab browser, jadi reload tak hilangkan order yang separuh siap. Harga dikira semula di server semasa hantar.

## Dapur

- **Bunyi amaran** bila order baru masuk (butang *Sound* di KDS). Browser hanya benarkan bunyi selepas skrin disentuh sekali.
- **Stock**: krew boleh tanda item *Sold out* terus dari KDS; ia hilang dari jualan online dan POS serta-merta.

## Aliran order

```
Pending Payment → Confirmed → Preparing → Ready → (Out for Delivery) → Completed
                      └────────────── Cancelled (refund kalau dah bayar) ──┘
```

- **Online (FPX / kad / e-wallet):** order ditahan sebagai *Pending Payment* sehingga gateway sahkan bayaran. Order yang tak dibayar dalam 30 minit dibatalkan oleh `orders:cancel-unpaid`.
- **Cash at counter (pickup sahaja):** terus *Confirmed*; cashier kutip bayaran masa serah. Order begini sampai ke dapur sebelum dibayar, jadi secara default ia hanya untuk pelanggan yang login dan dihadkan kepada RM 150. Kaedah bayaran dan had ini diubah di Admin → Settings → Payment methods.
- Bila *Confirmed*: resit dihantar ke POS dan pelanggan dapat mesej (kedua-duanya atas queue).
- Dapur tukar status item; item pertama dimasak → *Preparing*, item terakhir siap → *Ready*.
- Pickup: cashier scan QR / masukkan kod pickup dan serah. Delivery: cashier assign rider, rider tekan *Picked up* dan *Delivered*.
- Order *Ready* yang tak ditanda sebagai diambil ditutup oleh `orders:close-stale` selepas beberapa jam (Admin → Settings): yang dah dibayar jadi *Completed*, yang belum dibayar dibatalkan sebagai tak diambil.

Semua pertukaran status melalui `App\Actions\Orders\TransitionOrder`, yang kuatkuasakan peta dalam `OrderStatus::allowedTransitions()`.

## Struktur kod

| Lokasi | Isi |
|---|---|
| `app/Enums` | Status dan jenis (order, payment, kitchen, delivery, role) |
| `app/Actions` | Logik bisnes: `PlaceOrder`, `TransitionOrder`, `CancelOrder`, `ConfirmPayment`, `UpdateKitchenItem`, `AssignRider`, `PushOrderToPos`… |
| `app/Services/Cart` | Cart dalam session + `CartPricer` (harga sentiasa dikira di server) |
| `app/Contracts` + `app/Services/{Payments,Pos,Messaging}` | Interface dan driver integrasi |
| `app/Http/Controllers` | Controller nipis; validation dalam `app/Http/Requests` |
| `app/Http/Resources` | Bentuk data yang dihantar ke React |
| `app/Support` | `Settings`, `OutletContext`, `OutletStock`, `OutletPrices`, `BrandPalette`, `Money`, `ShopHours`, `KitchenLoad`, `OrderTimeline` |
| `routes/web.php`, `auth.php`, `staff.php`, `admin.php` | Route ikut kawasan |
| `resources/js/pages` | Halaman Inertia (React) |
| `resources/js/components`, `layouts` | UI kit, layout storefront / back office |

Nota penting:

- **Duit disimpan sebagai integer sen.** Guna `App\Support\Money` (PHP) dan `money()` (TS) untuk paparan.
- **Harga disalin ke order** (`order_items`), jadi perubahan menu tak ubah order lama.
- **Timezone aplikasi** ialah `Asia/Kuala_Lumpur` (`APP_TIMEZONE`). Nombor giliran (A001…) reset setiap hari, bagi setiap outlet.
- **Tetapan kedai** (waktu operasi, SST, ETA, prefix giliran) diubah di Admin → Settings; default dalam `config/shop.php`. Dalam mod banyak outlet, tetapan per-outlet diubah di Admin → Outlets (atau *Outlet Details* untuk manager).

## Integrasi

Setiap integrasi ada interface dan driver yang dipilih melalui `.env`.

### Payment gateway — Admin → Payment Gateway

Super admin pilih **satu** gateway di Admin → Payment Gateway dan masukkan kuncinya di situ (tiada `.env` perlu diubah). Kunci rahsia disimpan berenkripsi dengan `APP_KEY` dan tidak pernah dihantar ke browser. Bila disimpan, sistem terus uji sambungan dan tunjuk hasilnya.

| Gateway | Perlu | Kaedah di checkout |
|---|---|---|
| **Billplz** | API Secret Key + Collection ID | FPX, kad, e-wallet (halaman bil Billplz) |
| **Bayarcash** | Personal Access Token + Portal Key (+ API Secret Key, disyorkan) | FPX → FPX, e-wallet → DuitNow QR, kad → kad kredit |
| **Stripe** | Secret key `sk_live_…` / `sk_test_…` | Stripe Checkout (kad, FPX, GrabPay ikut yang dihidupkan di dashboard Stripe) |
| **Demo** (`fake`) | – | Halaman bank simulasi. Tidak boleh dipilih di production (kecuali `PAYMENT_FAKE_IN_PRODUCTION=true`) |
| **None** | – | Bayar di kaunter sahaja |

Billplz dan Bayarcash ada mod **sandbox** (suis di halaman yang sama).

Cara bayaran disahkan: gateway hantar pelanggan balik ke `/payments/{gateway}/return` dan panggil `/payments/{gateway}/notify` (server ke server; dikecualikan daripada CSRF). Data dalam panggilan itu hanya petunjuk — sistem sentiasa **tanya API gateway itu sendiri** (bil Billplz, transaksi Bayarcash, Checkout Session Stripe) dan semak rujukan, jumlah dan mata wang sebelum order disahkan. Jadi X-Signature Billplz atau webhook secret Stripe tidak diperlukan. Jika kedua-dua panggilan hilang, `orders:cancel-unpaid` tanya gateway sekali lagi sebelum membatalkan order.

- **Stripe webhook (pilihan, disyorkan):** Developers → Webhooks → tambah endpoint `https://domain-kau/payments/stripe/notify` untuk event `checkout.session.completed`. Event lain dijawab `200 Ignored`.
- **Refund:** order online yang dibatalkan selepas dibayar dipulangkan automatik untuk Stripe. Billplz dan Bayarcash tiada API refund — pulangkan wang secara manual dari dashboard gateway (atau pindahan bank).
- Bayaran lama sentiasa disemak dengan gateway tempat ia dibuat, walaupun super admin sudah tukar gateway.
- `PAYMENT_GATEWAY` dalam `.env` hanya nilai permulaan sebelum apa-apa dipilih di admin.

Tambah gateway lain: buat class dalam `app/Services/Payments` (lanjutkan `HostedGateway`, implement `App\Contracts\PaymentGateway`: `checkoutUrl`, `verify`, `refund`, `test`), daftar dalam `config/payment.php` → `drivers`, dan tambah medan kuncinya di `PaymentGatewayController` / halaman admin.

### Loyverse POS — `POS_PROVIDER`

Default `fake` (resit simulasi). Untuk Loyverse sebenar:

```env
POS_PROVIDER=loyverse
LOYVERSE_ACCESS_TOKEN=...       # Loyverse Back Office → Access Tokens
LOYVERSE_STORE_ID=...
LOYVERSE_PAYMENT_TYPE_ID=...    # payment type untuk order online
LOYVERSE_WEBHOOK_SECRET=...     # pilihan, untuk /webhooks/loyverse
```

Kemudian pautkan menu: `php artisan pos:sync-menu` (atau Admin → Integrations). Padanan dibuat ikut SKU = kod produk (`B4`; untuk variant `B1-1`, `B1-2` ikut susunan), kemudian ikut nama item. Produk yang tak berpaut tak boleh dihantar sebagai resit; kegagalan direkod dalam log sync dan boleh dicuba semula dari Admin → Orders.

> Driver Loyverse ditulis ikut dokumentasi API dan diuji dengan HTTP fake sahaja — belum diuji dengan akaun Loyverse sebenar. Semak format signature webhook dengan akaun anda sebelum bergantung padanya.

### Mesej pelanggan — `MESSAGING_CHANNEL`

Default `log` (mesej ditulis ke `storage/logs` dan `notification_logs`). `whatsapp` guna WhatsApp Cloud API (`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`). Mesej teks bebas hanya sampai dalam tetingkap 24 jam; di luar itu Meta perlukan template yang diluluskan.

## Real-time

Event `OrderUpdated` di-broadcast ke channel `orders.{uuid}` (tiket pelanggan), `shop.{outlet}` (papan status awam) dan private `staff.{outlet}` (KDS, kaunter, rider) — setiap outlet hanya dengar order sendiri. Skrin memuat semula data bila event sampai.

`composer setup` hidupkan Reverb melalui `php artisan shop:enable-realtime` (jana kunci dalam `.env` dan set `BROADCAST_CONNECTION=reverb`). Kalau `BROADCAST_CONNECTION=log`, frontend guna polling setiap 8 saat — sistem tetap berfungsi tanpa websocket.

## Arahan berguna

```bash
php artisan test                 # semua ujian (SQLite in-memory)
npm run types                    # semak TypeScript
vendor/bin/pint                  # format kod PHP
php artisan pos:sync-menu        # pautkan menu dengan katalog POS
php artisan orders:cancel-unpaid # batal order online yang tak dibayar
php artisan orders:close-stale   # tutup order Ready yang tak diambil
php artisan shop:enable-realtime # jana kunci Reverb dan hidupkan websocket
```

## Jual / pasang di shared hosting

Untuk client yang guna shared hosting (cPanel), tanpa SSH, Composer atau Node:

```bash
scripts/package.sh          # → dist/order-system-<versi>.zip + dist/PANDUAN-PEMASANGAN.html
```

ZIP itu siap-upload: pakej PHP (tanpa pakej dev), JavaScript/CSS yang sudah di-build, `.htaccess` di akar supaya boleh extract terus ke `public_html`, dan **tiada** `.env`. Berikan kedua-dua fail kepada client; `PANDUAN-PEMASANGAN.html` menerangkan setiap langkah dalam Bahasa Melayu.

Di server client:

1. Upload & extract ZIP → buka domain → **installer web** di `/install` (semak server → purchase code → database → kedai & akaun super admin → siap). `.env` dicipta sendiri dengan kunci baru; installer dikunci selepas siap (`storage/app/installed.json`).
2. Tambah **satu cron job** setiap minit (arahan ditunjuk di skrin terakhir). Ia menjalankan tugas berjadual *dan* queue — shared hosting tiada queue worker. Semua tugas berjalan dalam proses yang sama, jadi ia berfungsi walaupun `proc_open` dimatikan. Dashboard memberi amaran jika cron tidak berjalan.
3. **Kemas kini:** naikkan nombor dalam fail `VERSION`, bina ZIP baru, client upload & overwrite. Jika ada migration baru, laman menunjukkan skrin *Finish the update* sehingga super admin mengesahkan dengan kata laluan; jika tiada, versi direkod senyap-senyap. `.env` dan gambar yang dimuat naik tidak tersentuh.

Pemasangan oleh installer menetapkan: `APP_ENV=production`, sesi & cache dalam fail, queue dalam database (diproses oleh cron), `BROADCAST_CONNECTION=log` (tiada websocket — skrin guna polling), bayaran online **dimatikan** (gateway `fake` tidak dibenarkan di production; lihat `PAYMENT_FAKE_IN_PRODUCTION`), dan jika `symlink()` disekat, gambar disimpan terus dalam `public/storage` (`FILESYSTEM_PUBLIC_IN_WEBROOT`).

Laman sedia ada (dipasang dari command line, seperti MacBook dev) dikesan secara automatik dan **tidak** dibawa ke installer.

Ujian pemasangan penuh terhadap MySQL (pilihan):

```bash
INSTALLER_TEST_DB=nama_db_kosong INSTALLER_TEST_USER=user INSTALLER_TEST_PASSWORD=pass php artisan test tests/Feature/Install
```

## Sistem lesen (purchase code)

Setiap salinan yang dijual diaktifkan dengan **purchase code** yang terikat pada satu domain. Bahagian-bahagiannya:

- **Pelayan lesen** — salinan app ini *milik kau sendiri* dengan `LICENSE_SERVER_ENABLED=true`. Admin → **Licenses (sold)**: cipta purchase code, lihat domain mana setiap kod dipakai (dan versi), batalkan kod (refund/chargeback), pulihkan, atau lepaskan domain bila client pindah.
- **Semakan dalam produk** — installer minta purchase code; pelayan lesen pulangkan sijil yang ditandatangani dengan kunci peribadi kau (ECDSA P-256), dan produk mengesahkannya dengan kunci awam dalam `config/licensing.php`. Kod pelayan lesen (`app/LicenseServer`) **tidak** dimasukkan dalam ZIP client.

### Sediakan sekali sahaja

1. Pasang pelayan lesen di domain https kau sendiri (contoh `https://lesen.domainkau.com`) — repo yang sama, di Laravel Cloud atau hosting lain.
2. Di MacBook: `php artisan license:keygen --server=https://lesen.domainkau.com`. Ia menulis kunci awam dan alamat pelayan ke `config/licensing.php` (**commit fail ini**) dan mencetak dua baris untuk `.env` pelayan lesen:
   ```
   LICENSE_SERVER_ENABLED=true
   LICENSE_SIGNING_KEY=…
   ```
3. Di pelayan lesen: tambah dua baris itu ke `.env`, kemudian `php artisan migrate` (jadual `licenses`, `license_activations` dan `releases`).
4. **Simpan `LICENSE_SIGNING_KEY` di tempat selamat** (password manager). Jika hilang, tiada laman baru boleh diaktifkan dan laman sedia ada berhenti dalam 30 hari. Jangan sekali-kali commit.

`scripts/package.sh` enggan membina ZIP selagi kunci awam belum diset (`ALLOW_UNLICENSED=1` untuk ZIP tanpa lesen).

### Setiap jualan

Admin → Licenses (sold) → **New purchase code** (atau `php artisan license:create "Kedai Ali" --email=ali@… --max=1`) → hantar ZIP + purchase code + panduan kepada client.

### Kelakuan di laman client

| Keadaan | Apa berlaku |
|---|---|
| Alamat tempatan (`localhost`, `127.0.0.1`, `*.test`, `*.local`, IP rangkaian dalaman) | Tiada lesen diperlukan — untuk dev dan ujian |
| Belum diaktifkan, domain lain, kod dibatalkan, atau fail lesen rosak | Laman berhenti: pelanggan dan staf nampak "Temporarily unavailable"; super admin masih boleh log masuk dan dibawa ke Admin → **License** |
| Semakan harian | Sijil baharu diambil setiap 7 hari (cron, atau di latar belakang semasa laman dibuka) |
| Pelayan lesen tidak dapat dihubungi | Laman terus berjalan; amaran di Dashboard selepas 14 hari; berhenti selepas 30 hari |
| Client pindah domain | Admin → License → **Move to another domain** di laman lama, kemudian aktifkan di laman baru. Jika laman lama sudah tiada, lepaskan domain itu dari Admin → Licenses (sold) |

Had: kod PHP boleh dibaca dan diubah oleh orang yang betul-betul berniat. Sistem ini menghalang salinan biasa ke domain lain dan memberi kau kawalan (batal, lihat siapa pakai), bukan perlindungan mutlak — sama seperti skrip CodeCanyon.

## Landing page jualan (`/web`)

Page jualan dalam Bahasa Melayu di `https://laman-demo-kau/web`: ciri, screenshot sebenar, perbandingan dengan platform berkomisen, cara mula, harga, pautan demo (menu, POS, dapur, admin), akaun demo staf, soalan lazim. Sedia untuk dikongsi di WhatsApp/Facebook (ada imej preview `public/images/landing/og.jpg`).

**Hanya untuk laman demo kau.** Ia mati secara default, jadi kedai client tidak pernah menunjukkannya. Hidupkan dalam `.env` laman demo:

```
LANDING_PAGE=true
LANDING_PRODUCT=OrderLa
LANDING_PRICE="RM 2,500"          # kosongkan untuk "Hubungi kami"
LANDING_PRICE_NOTE="Bayar sekali · 1 domain · tiada yuran bulanan"
LANDING_WHATSAPP=60123456789      # butang WhatsApp muncul bila diisi
LANDING_EMAIL=                    # pilihan
LANDING_DEMO_URL=                 # kosong = laman ini sendiri
```

Kemudian `php artisan config:clear` (atau `php artisan optimize` jika config di-cache). Akaun demo staf hanya disenaraikan jika akaun itu wujud di laman tersebut dan kata laluannya masih `password`, jadi page tidak pernah menunjukkan login yang tidak berfungsi. Teks page ada dalam `resources/views/landing.blade.php`; screenshot dalam `public/images/landing/`.

## Kemas kini satu klik (update untuk client)

Client yang ada lesen aktif nampak versi baru di **Admin → System Update** (dan lencana pada menu) dan pasang dengan satu klik. Tiada FTP, tiada extract ZIP.

### Susun atur yang disyorkan

| Laman | Apa | Siapa log masuk |
|---|---|---|
| `lesen.domainkau.com` (atau laman utama kau) | Pelayan lesen: Licenses (sold) + **Releases** | Kau sahaja |
| `demo.domainkau.com` | Salinan client biasa (pasang dari ZIP) dengan purchase code demo kau sendiri | Bakal pembeli (akaun demo) |
| Domain setiap client | Salinan client | Client |

Jangan hidupkan pelayan lesen pada laman demo: kata laluan admin demo dikongsi umum, dan sesiapa yang log masuk akan nampak senarai pembeli dan purchase code mereka.

### Terbitkan versi baru

1. Naikkan `VERSION` (contoh `1.0.0` → `1.1.0`) dan commit.
2. `scripts/package.sh` → `dist/order-system-1.1.0.zip`. ZIP kini membawa senarai setiap fail dengan checksum (`bootstrap/release-manifest.json`).
3. Di pelayan lesen: Admin → **Releases** → *Upload update* (tulis "What's new", pilih **semua client** atau **client tertentu sahaja**, terbit atau simpan sebagai draf). ZIP ~35 MB: jika had upload PHP lebih kecil, upload ZIP ke server dan jalankan `php artisan release:publish dist/order-system-1.1.0.zip --notes="…"`.
4. Uji dulu: terbitkan untuk purchase code demo sahaja → update laman demo → kemudian tukar ke *Every customer*.

Pelayan lesen menolak ZIP yang akan merosakkan semua laman: tiada senarai fail, dibina dengan `ALLOW_UNLICENSED`, kunci awam berlainan, atau mengandungi kod pelayan lesen.

### Siapa dapat update

- **Tempoh update** setiap purchase code: Admin → Licenses (sold) → Edit → *Updates until*. Kosong = update seumur hidup. Versi yang diterbitkan selepas tarikh itu tidak ditawarkan; client nampak "update period has ended — contact the seller" (peluang jual pembaharuan).
- Release untuk **client tertentu** (contoh ciri khas untuk Company A) hanya ditawarkan kepada purchase code yang dipilih. Ingat: versi umum yang seterusnya mesti juga mengandungi perubahan Company A, jika tidak ia hilang bila mereka update.
- Kod yang dibatalkan tidak dapat update.

### Apa berlaku bila client tekan Update

1. Laman tanya pelayan lesen (purchase code + domain mesti aktif), muat turun ZIP melalui pautan bertandatangan (sah 3 jam), dan semak checksum yang **ditandatangani dengan kunci peribadi kau** — ZIP yang diubah di tengah jalan ditolak.
2. Bandingkan fail di laman dengan senarai fail versi semasa:
   - Fail yang **client ubah sendiri** dan yang update akan ganti/buang disenaraikan dulu; client pilih *Replace them and update* atau *Not now*.
   - Fail yang client ubah tetapi tidak disentuh oleh versi baru (contoh `.htaccess` sendiri) dibiarkan.
   - Fail yang versi baru tidak ada lagi dibuang (supaya migration lama tidak tertinggal).
3. Backup setiap fail yang akan diganti/dibuang → `storage/app/updates/backups/` (3 terbaru disimpan).
4. Pelanggan nampak "Updating…" (maksimum 15 minit; cron berhenti sementara). Fail ditulis satu-satu (fail sementara + rename). Jika apa-apa gagal di tengah jalan, semua fail dipulihkan dari backup dan laman kembali seperti asal.
5. Permintaan baharu (kod baru) jalankan migration, kosongkan cache, catat versi baru.

Tidak disentuh: `.env`, `storage/` (gambar, log, lesen), `public/storage`. Laman yang dipasang dengan git (ada folder `.git`) tidak boleh update satu klik — guna `git pull`.

Perlu di hosting client: PHP extension **zip**, PHP boleh menulis ke folder app (biasa di cPanel), dan sambungan HTTPS keluar ke pelayan lesen. Semakan automatik setiap malam (`updates:check`, melalui cron).

Versi pertama yang mempunyai ciri ini tidak boleh dikemas kini kepada dirinya sendiri: client yang memasang ZIP lama (tanpa System Update) perlu upload ZIP versi ini sekali secara manual (langkah 8 dalam panduan). Selepas itu semua update satu klik.

## Production

- `APP_ENV=production`, `APP_DEBUG=false`, `npm run build`, `php artisan optimize`.
- Pilih gateway sebenar di Admin → Payment Gateway. Driver `fake` meluluskan bayaran dengan satu klik — untuk demo dan ujian sahaja, dan dianggap "None" di production.
- Queue worker mesti jalan (`php artisan queue:work`) — sync POS, mesej pelanggan dan broadcast semuanya atas queue. Guna Supervisor.
- Scheduler: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
- Reverb: `php artisan reverb:start` di belakang reverse proxy dengan TLS; set `REVERB_HOST` / `REVERB_SCHEME=https`.
- `php artisan storage:link` untuk gambar produk.
- Tukar semua password akaun seed.
