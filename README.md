# Boss Burger — Sistem Order Online

Sistem order online untuk kedai burger: menu & cart, checkout, tiket digital dengan tracking live, Kitchen Display (KDS), kaunter pickup, delivery dengan rider, dan admin panel. Order yang disahkan dihantar ke Loyverse POS sebagai resit.

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

`composer setup` seed menu Boss Burger, zon delivery dan satu akaun untuk setiap role. Untuk data jualan contoh (dashboard & laporan): `php artisan db:seed --class=DemoOrderSeeder`.

### Akaun demo

Semua password ialah `password`. **Tukar sebelum go live.**

| Role | Email | Skrin |
|---|---|---|
| Admin | admin@bossburger.test | `/admin` |
| Cashier | cashier@bossburger.test | `/counter` |
| Kitchen | kitchen@bossburger.test | `/kitchen` |
| Rider | rider@bossburger.test | `/rider` |
| Customer | customer@bossburger.test | storefront |

## Aliran order

```
Pending Payment → Confirmed → Preparing → Ready → (Out for Delivery) → Completed
                      └────────────── Cancelled (refund kalau dah bayar) ──┘
```

- **Online (FPX / kad / e-wallet):** order ditahan sebagai *Pending Payment* sehingga gateway sahkan bayaran. Order yang tak dibayar dalam 30 minit dibatalkan oleh `orders:cancel-unpaid`.
- **Cash at counter (pickup sahaja):** terus *Confirmed*; cashier kutip bayaran masa serah.
- Bila *Confirmed*: resit dihantar ke POS dan pelanggan dapat mesej (kedua-duanya atas queue).
- Dapur tukar status item; item pertama dimasak → *Preparing*, item terakhir siap → *Ready*.
- Pickup: cashier scan QR / masukkan kod pickup dan serah. Delivery: cashier assign rider, rider tekan *Picked up* dan *Delivered*.

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
| `app/Support` | `Settings`, `Money`, `ShopHours`, `KitchenLoad`, `OrderTimeline` |
| `routes/web.php`, `auth.php`, `staff.php`, `admin.php` | Route ikut kawasan |
| `resources/js/pages` | Halaman Inertia (React) |
| `resources/js/components`, `layouts` | UI kit, layout storefront / back office |

Nota penting:

- **Duit disimpan sebagai integer sen.** Guna `App\Support\Money` (PHP) dan `money()` (TS) untuk paparan.
- **Harga disalin ke order** (`order_items`), jadi perubahan menu tak ubah order lama.
- **Timezone aplikasi** ialah `Asia/Kuala_Lumpur` (`APP_TIMEZONE`). Nombor giliran (A001…) reset setiap hari.
- **Tetapan kedai** (waktu operasi, SST, ETA, prefix giliran) diubah di Admin → Settings; default dalam `config/shop.php`.

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

Event `OrderUpdated` di-broadcast ke channel `orders.{uuid}` (tiket pelanggan), `shop` (papan status awam) dan private `staff` (KDS, kaunter, rider). Skrin memuat semula data bila event sampai.

`composer setup` hidupkan Reverb melalui `php artisan shop:enable-realtime` (jana kunci dalam `.env` dan set `BROADCAST_CONNECTION=reverb`). Kalau `BROADCAST_CONNECTION=log`, frontend guna polling setiap 8 saat — sistem tetap berfungsi tanpa websocket.

## Arahan berguna

```bash
php artisan test                 # semua ujian (SQLite in-memory)
npm run types                    # semak TypeScript
vendor/bin/pint                  # format kod PHP
php artisan pos:sync-menu        # pautkan menu dengan katalog POS
php artisan orders:cancel-unpaid # batal order online yang tak dibayar
php artisan shop:enable-realtime # jana kunci Reverb dan hidupkan websocket
```

## Production

- `APP_ENV=production`, `APP_DEBUG=false`, `npm run build`, `php artisan optimize`.
- Queue worker mesti jalan (`php artisan queue:work`) — sync POS, mesej pelanggan dan broadcast semuanya atas queue. Guna Supervisor.
- Scheduler: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
- Reverb: `php artisan reverb:start` di belakang reverse proxy dengan TLS; set `REVERB_HOST` / `REVERB_SCHEME=https`.
- `php artisan storage:link` untuk gambar produk.
- Tukar semua password akaun seed.
