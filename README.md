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

## Tiga cara order

Setiap satu boleh dihidup/matikan di Admin → Settings → Ordering. Kedai tanpa delivery hanya perlu matikan suis Delivery.

| Cara | Siapa | Perlu login? |
|---|---|---|
| **Dine-in (QR meja)** | Pelanggan scan kod di meja, atau waiter ambil order di `/pos` dan pilih meja | Tidak. Nama dan telefon pun pilihan |
| **Self pickup** | Pelanggan order dari rumah, datang ambil | Tidak (guest: nama + telefon) |
| **Delivery** | Pelanggan order dari rumah, rider hantar | Tidak (guest: nama + telefon + alamat) |

### Dine-in dengan QR meja

1. Admin → **Tables & QR**: tambah meja, tekan **Print QR codes**, potong dan letak satu di setiap meja.
2. Pelanggan scan → menu terbuka dalam mod "Dine-in · Table 5" → pilih → hantar. Tiada app, tiada pendaftaran.
3. Dapur nampak nombor meja pada tiket; makanan dihantar ke meja.
4. Bayaran: di kaunter sebelum balik (default), atau online. Tetapan *Table orders can pay at the counter* mengawalnya.

Kod QR mengandungi alamat laman semasa admin membukanya. **Buka admin melalui alamat yang boleh dicapai telefon pelanggan** (domain sebenar, atau IP LAN semasa ujian — bukan `localhost`) sebelum mencetak. Kalau satu kod disalah guna, tekan *New code* pada meja itu dan cetak semula; kod lama terus tak berfungsi.

### Akaun pelanggan: telefon + PIN

Akaun adalah pilihan — untuk sejarah order, alamat tersimpan dan bayar di kaunter bagi pickup. Pendaftaran hanya nama, nombor telefon dan PIN 6 digit; email pilihan (untuk reset PIN sendiri). Pelanggan tanpa email yang lupa PIN boleh minta staf set PIN baru di Admin → Users. Login dihadkan: 5 cubaan setiap peranti dan 10 setiap akaun dalam 15 minit. Staf kekal guna email + password penuh.

OTP melalui SMS/WhatsApp belum ada; ia boleh ditambah di atas `App\Contracts\MessageChannel` bila penyedia mesej disambung.

## Jenama kedai

Admin → Settings → **Branding**: nama, tagline, logo (atau emoji), warna utama dan warna aksen, teks footer. Semua skrin, invois PDF dan favicon ikut tetapan ini — tiada kod yang perlu diubah untuk kedai lain. Warna yang terlalu pudar untuk dibaca atas latar gelap ditolak semasa simpan.

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

### Payment gateway — `PAYMENT_GATEWAY`

Default `fake`: halaman bank simulasi (Approve / Decline) supaya aliran penuh boleh diuji tanpa akaun merchant.

Untuk gateway sebenar (ToyyibPay, Billplz, Stripe…):

1. Buat class dalam `app/Services/Payments` yang implement `App\Contracts\PaymentGateway`:
   - `checkoutUrl(Payment)` — cipta bil di gateway, pulangkan URL untuk redirect pelanggan.
   - `parseCallback(Request)` — sahkan signature dan pulangkan `PaymentCallback`.
   - `refund(Payment)`.
2. Daftar dalam `config/payment.php` → `drivers`, set `PAYMENT_GATEWAY`.
3. Set return URL dan callback URL gateway kepada `/payments/callback` (dikecualikan daripada CSRF).

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

## Production

- `APP_ENV=production`, `APP_DEBUG=false`, `npm run build`, `php artisan optimize`.
- Set `PAYMENT_GATEWAY` kepada gateway sebenar. Driver `fake` meluluskan bayaran dengan satu klik — untuk demo dan ujian sahaja.
- Queue worker mesti jalan (`php artisan queue:work`) — sync POS, mesej pelanggan dan broadcast semuanya atas queue. Guna Supervisor.
- Scheduler: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
- Reverb: `php artisan reverb:start` di belakang reverse proxy dengan TLS; set `REVERB_HOST` / `REVERB_SCHEME=https`.
- `php artisan storage:link` untuk gambar produk.
- Tukar semua password akaun seed.
