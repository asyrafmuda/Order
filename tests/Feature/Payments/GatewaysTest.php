<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\StartPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\BayarcashGateway;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\GatewaySettings;
use App\Support\Settings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

function useGateway(string $gateway, array $credentials = []): void
{
    app(GatewaySettings::class)->put(['payments.gateway' => $gateway, ...$credentials]);
}

function useBillplz(): void
{
    useGateway('billplz', ['payments.billplz.api_key' => 'bp-secret', 'payments.billplz.collection_id' => 'col123']);
}

function useBayarcash(bool $withSecret = true): void
{
    useGateway('bayarcash', [
        'payments.bayarcash.token' => 'bc-token',
        'payments.bayarcash.secret_key' => $withSecret ? 'bc-secret' : null,
        'payments.bayarcash.portal_key' => 'portal-1',
    ]);
}

function useStripe(): void
{
    useGateway('stripe', ['payments.stripe.secret_key' => 'sk_test_123']);
}

/** An unpaid online order with a payment opened at the active gateway. */
function startedPayment(PaymentMethod $method = PaymentMethod::Fpx): Payment
{
    $order = placeOrder(['method' => $method, 'paid' => false]);
    app(StartPayment::class)->handle($order);

    return $order->payments()->latest('id')->firstOrFail();
}

beforeEach(fn () => Http::preventStrayRequests());

describe('Billplz', function () {
    it('creates a bill and sends the customer to it', function () {
        useBillplz();
        Http::fake(['www.billplz.com/api/v3/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://www.billplz.com/bills/bill_abc'])]);

        $order = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);
        $url = app(StartPayment::class)->handle($order);
        $payment = $order->payments()->sole();

        expect($url)->toBe('https://www.billplz.com/bills/bill_abc')
            ->and($payment->gateway)->toBe('billplz')
            ->and($payment->gateway_reference)->toBe('bill_abc');

        Http::assertSent(function (HttpRequest $request) use ($payment, $order) {
            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('bp-secret:'))
                && $request['collection_id'] === 'col123'
                && (int) $request['amount'] === $order->total
                && $request['mobile'] === '60123456789'
                && str_contains($request['redirect_url'], '/payments/billplz/return?payment='.$payment->reference)
                && str_contains($request['callback_url'], '/payments/billplz/notify?payment='.$payment->reference)
                && $request['reference_1'] === $order->order_number;
        });
    });

    it('uses the sandbox when switched on', function () {
        useBillplz();
        app(GatewaySettings::class)->put(['payments.billplz.sandbox' => true]);
        Http::fake(['www.billplz-sandbox.com/api/v3/bills' => Http::response(['id' => 'bill_s', 'url' => 'https://www.billplz-sandbox.com/bills/bill_s'])]);

        expect(startedPayment()->gateway_reference)->toBe('bill_s');
    });

    it('confirms the order only after Billplz itself says the bill is paid', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://www.billplz.com/bills/bill_abc'])]);
        $payment = startedPayment();

        // What Billplz answers for the bill, changed as the test goes on.
        $paid = false;
        Http::fake(['*/bills/bill_abc' => function () use (&$paid, $payment) {
            return Http::response(['id' => 'bill_abc', 'paid' => $paid, 'state' => $paid ? 'paid' : 'due', 'amount' => $payment->amount, 'paid_amount' => $paid ? $payment->amount : 0]);
        }]);

        // The customer's browser claims it paid, but Billplz says otherwise.
        $this->get(route('payments.return', ['gateway' => 'billplz', 'payment' => $payment->reference, 'billplz' => ['id' => 'bill_abc', 'paid' => 'true']]))
            ->assertRedirect(route('orders.show', $payment->order));
        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);

        $paid = true;
        $this->post(route('payments.notify', ['gateway' => 'billplz', 'payment' => $payment->reference]), ['id' => 'bill_abc', 'paid' => 'true'])
            ->assertOk()->assertSee('OK');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->gateway_transaction_id)->toBe('bill_abc')
            ->and($payment->order->status)->toBe(OrderStatus::Confirmed);

        // Billplz retries callbacks: a second one changes nothing.
        $this->post(route('payments.notify', ['gateway' => 'billplz', 'payment' => $payment->reference]))->assertOk();
        expect($payment->order->histories()->where('status', OrderStatus::Confirmed)->count())->toBe(1);
    });

    it('does not accept a bill that paid less than the order', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://x.test'])]);
        $payment = startedPayment();
        Http::fake(['*/bills/bill_abc' => Http::response(['id' => 'bill_abc', 'paid' => true, 'state' => 'paid', 'paid_amount' => $payment->amount - 1])]);

        $this->get(route('payments.return', ['gateway' => 'billplz', 'payment' => $payment->reference]));

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    it('lets the customer try again after leaving the bank page without paying', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://x.test'])]);
        $payment = startedPayment();
        Http::fake(['*/bills/bill_abc' => Http::response(['id' => 'bill_abc', 'paid' => false, 'state' => 'due'])]);

        $this->get(route('payments.return', ['gateway' => 'billplz', 'payment' => $payment->reference, 'billplz' => ['id' => 'bill_abc', 'paid' => 'false']]))
            ->assertSessionHas('error');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
            ->and($payment->order->status)->toBe(OrderStatus::PendingPayment);
    });
});

describe('Bayarcash', function () {
    it('opens a payment intent on the right channel, signed with the secret key', function (PaymentMethod $method, int $channel) {
        useBayarcash();
        Http::fake(['api.console.bayar.cash/v3/payment-intents' => Http::response(['id' => 'pi_bc_1', 'url' => 'https://pay.bayar.cash/x'])]);

        $payment = startedPayment($method);

        expect($payment->gateway_reference)->toBe('pi_bc_1');

        Http::assertSent(function (HttpRequest $request) use ($payment, $channel) {
            $expected = BayarcashGateway::checksum([
                'payment_channel' => $channel,
                'order_number' => $payment->reference,
                'amount' => number_format($payment->amount / 100, 2, '.', ''),
                'payer_name' => 'Ahmad Faiz',
                'payer_email' => strtolower($payment->reference).'@guest.invalid',
            ], 'bc-secret');

            return $request->hasHeader('Authorization', 'Bearer bc-token')
                && $request['portal_key'] === 'portal-1'
                && (int) $request['payment_channel'] === $channel
                && $request['order_number'] === $payment->reference
                && $request['payer_telephone_number'] === '60123456789'
                && $request['checksum'] === $expected;
        });
    })->with([
        'FPX' => [PaymentMethod::Fpx, 1],
        'e-wallet (DuitNow QR)' => [PaymentMethod::Ewallet, 6],
        'card' => [PaymentMethod::Card, 12],
    ]);

    it('signs the checksum the way the Bayarcash SDK does', function () {
        $payload = ['payment_channel' => 1, 'order_number' => 'ORD1', 'amount' => '10.00', 'payer_name' => 'Ali', 'payer_email' => 'a@b.c'];

        // Keys sorted: amount, order_number, payer_email, payer_name, payment_channel.
        expect(BayarcashGateway::checksum($payload, 'k'))->toBe(hash_hmac('sha256', '10.00|ORD1|a@b.c|Ali|1', 'k'));
    });

    it('confirms on the callback when the transaction is successful', function () {
        useBayarcash();
        Http::fake(['*/payment-intents' => Http::response(['id' => 'pi_bc_1', 'url' => 'https://pay.bayar.cash/x'])]);
        $payment = startedPayment();
        $amount = number_format($payment->amount / 100, 2, '.', '');

        Http::fake(['*/transactions*' => Http::response(['data' => [
            ['id' => 'trx_other', 'order_number' => 'SOMEONE-ELSE', 'status' => 3, 'amount' => '999.00'],
            ['id' => 'trx_1', 'order_number' => $payment->reference, 'status' => 3, 'amount' => $amount, 'payer_bank_name' => 'Maybank2u'],
        ]])]);

        // The callback carries our reference in its body, as Bayarcash sends it.
        $this->post(route('payments.notify', ['gateway' => 'bayarcash']), ['order_number' => $payment->reference, 'status' => '3'])->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->gateway_transaction_id)->toBe('trx_1');
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'transactions?order_number='.$payment->reference));
    });

    it('ignores a successful transaction belonging to another order', function () {
        useBayarcash(withSecret: false);
        Http::fake(['*/payment-intents' => Http::response(['id' => 'pi_bc_1', 'url' => 'https://pay.bayar.cash/x'])]);
        $payment = startedPayment();
        Http::fake(['*/transactions*' => Http::response(['data' => [['id' => 'trx_x', 'order_number' => 'PAY-OTHER', 'status' => 3, 'amount' => '999.00']]])]);

        $this->get(route('payments.return', ['gateway' => 'bayarcash', 'payment' => $payment->reference, 'status' => 3]));

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
        Http::assertSent(fn (HttpRequest $request) => ! str_contains($request->url(), 'payment-intents') || ! isset($request['checksum']));
    });

    it('marks the payment failed when the customer cancelled', function () {
        useBayarcash();
        Http::fake(['*/payment-intents' => Http::response(['id' => 'pi_bc_1', 'url' => 'https://pay.bayar.cash/x'])]);
        $payment = startedPayment();
        Http::fake(['*/transactions*' => Http::response(['data' => [['id' => 'trx_1', 'order_number' => $payment->reference, 'status' => 4, 'amount' => '1.00']]])]);

        $this->get(route('payments.return', ['gateway' => 'bayarcash', 'payment' => $payment->reference]))->assertSessionHas('error');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
    });
});

describe('Stripe', function () {
    it('opens a Checkout Session in ringgit', function () {
        useStripe();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

        $payment = startedPayment(PaymentMethod::Card);

        expect($payment->gateway_reference)->toBe('cs_test_1');
        Http::assertSent(function (HttpRequest $request) use ($payment) {
            parse_str($request->body(), $body);

            return $request->hasHeader('Authorization', 'Bearer sk_test_123')
                && $body['mode'] === 'payment'
                && $body['client_reference_id'] === $payment->reference
                && $body['line_items'][0]['price_data']['currency'] === 'myr'
                && (int) $body['line_items'][0]['price_data']['unit_amount'] === $payment->amount
                && str_ends_with($body['success_url'], '&session_id={CHECKOUT_SESSION_ID}');
        });
    });

    it('confirms from the webhook after fetching the session, and refunds on cancel', function () {
        useStripe();
        Http::fake(['*/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/x'])]);
        $payment = startedPayment(PaymentMethod::Card);

        Http::fake([
            '*/checkout/sessions/cs_test_1' => Http::response([
                'id' => 'cs_test_1', 'payment_status' => 'paid', 'status' => 'complete', 'client_reference_id' => $payment->reference,
                'currency' => 'myr', 'amount_total' => $payment->amount, 'payment_intent' => 'pi_123',
            ]),
            '*/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded']),
        ]);

        // A webhook body is only a hint: the session is fetched from Stripe.
        $this->postJson(route('payments.notify', ['gateway' => 'stripe']), [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'client_reference_id' => $payment->reference]],
        ])->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->gateway_transaction_id)->toBe('pi_123');

        app(CancelOrder::class)->handle($payment->order->refresh(), 'Out of buns');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/refunds') && $request['payment_intent'] === 'pi_123');
    });

    it('rejects a session in another currency or for a smaller amount', function (array $session) {
        useStripe();
        Http::fake(['*/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/x'])]);
        $payment = startedPayment(PaymentMethod::Card);

        Http::fake(['*/checkout/sessions/cs_test_1' => Http::response([
            'id' => 'cs_test_1', 'payment_status' => 'paid', 'client_reference_id' => $payment->reference, 'payment_intent' => 'pi_1',
            'currency' => 'myr', 'amount_total' => $payment->amount, ...$session,
        ])]);

        $this->get(route('payments.return', ['gateway' => 'stripe', 'payment' => $payment->reference, 'session_id' => 'cs_test_1']));

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    })->with([
        'currency' => [['currency' => 'usd']],
        'amount' => [['amount_total' => 1]],
        'other payment' => [['client_reference_id' => 'PAY-SOMEONEELSE']],
    ]);

    it('answers webhooks about other things with 200 so Stripe stops retrying', function () {
        $this->postJson(route('payments.notify', ['gateway' => 'stripe']), ['type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]])
            ->assertOk()->assertSee('Ignored');
    });
});

describe('gateway choice', function () {
    it('cannot pay online when no gateway is chosen or its credentials are missing', function () {
        useGateway('none');
        expect(app(GatewayManager::class)->ready())->toBeFalse();

        useGateway('billplz', ['payments.billplz.collection_id' => 'col']);
        expect(app(GatewayManager::class)->ready())->toBeFalse();

        // Checkout no longer offers online payment at all.
        expect(fn () => placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]))->toThrow(OrderException::class, 'not set up');
    });

    it('does not offer the demo gateway on a live site', function () {
        useGateway('fake');
        expect(app(GatewayManager::class)->ready())->toBeTrue();

        app()->detectEnvironment(fn () => 'production');
        expect(app(GatewayManager::class)->ready())->toBeFalse()
            ->and(app(GatewayManager::class)->activeName())->toBe('none');

        config(['payment.fake_in_production' => true]);
        expect(app(GatewayManager::class)->ready())->toBeTrue();
    });

    it('keeps the order and explains when the gateway refuses to open a page', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['error' => ['type' => 'RecordInvalid', 'message' => ['Collection not found']]], 422)]);
        $order = placeOrder(['method' => PaymentMethod::Fpx, 'paid' => false]);

        expect(fn () => app(StartPayment::class)->handle($order))->toThrow(OrderException::class, 'Collection not found');
        expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment)
            ->and($order->payment_status)->toBe(PaymentStatus::Failed)
            ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed);
    });

    it('checks a payment made before the owner switched gateway with the gateway it was made at', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://x.test'])]);
        $payment = startedPayment();

        useStripe();
        Http::fake(['*/bills/bill_abc' => Http::response(['id' => 'bill_abc', 'paid' => true, 'paid_amount' => $payment->amount])]);
        $this->post(route('payments.notify', ['gateway' => 'billplz', 'payment' => $payment->reference]))->assertOk();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
    });

    it('a return to the wrong gateway or for an unknown payment is not found', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://x.test'])]);
        $payment = startedPayment();

        $this->get(route('payments.return', ['gateway' => 'stripe', 'payment' => $payment->reference]))->assertNotFound();
        $this->get(route('payments.return', ['gateway' => 'paypal', 'payment' => $payment->reference]))->assertNotFound();
        $this->get(route('payments.return', ['gateway' => 'billplz', 'payment' => 'PAY-NOPE']))->assertNotFound();
    });

    it('answers 503 when the gateway cannot be reached, so the callback is retried', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::response(['id' => 'bill_abc', 'url' => 'https://x.test'])]);
        $payment = startedPayment();
        Http::fake(['*/bills/bill_abc' => Http::response('down', 500)]);

        $this->post(route('payments.notify', ['gateway' => 'billplz', 'payment' => $payment->reference]))->assertStatus(503);
        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    it('asks the gateway before cancelling an order that looks unpaid', function () {
        useBillplz();
        Http::fake(['*/bills' => Http::sequence()->push(['id' => 'bill_paid', 'url' => 'https://x.test'])->push(['id' => 'bill_unpaid', 'url' => 'https://x.test'])]);
        $paid = startedPayment();
        $unpaid = startedPayment();

        Http::fake([
            '*/bills/bill_paid' => Http::response(['id' => 'bill_paid', 'paid' => true, 'paid_amount' => $paid->amount]),
            '*/bills/bill_unpaid' => Http::response(['id' => 'bill_unpaid', 'paid' => false, 'state' => 'due']),
        ]);
        $this->travel(31)->minutes();

        $this->artisan('orders:cancel-unpaid')->expectsOutputToContain('Cancelled 1 unpaid order')->assertSuccessful();

        expect($paid->order->refresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($unpaid->order->refresh()->status)->toBe(OrderStatus::Cancelled);
    });
});

describe('admin page', function () {
    it('is for super admins only', function () {
        $this->actingAs(userWithRole(UserRole::Manager))->get(route('admin.payment-gateway.edit'))->assertForbidden();
    });

    it('saves the credentials encrypted and never shows the secrets', function () {
        Http::fake(['*/collections/col123' => Http::response(['id' => 'col123', 'title' => 'Boss Burger'])]);
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.payment-gateway.update'), [
            'gateway' => 'billplz',
            'billplz' => ['api_key' => 'bp-very-secret', 'collection_id' => 'col123', 'sandbox' => false],
        ])->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, 'Boss Burger'));

        $stored = app(Settings::class)->global('payments.billplz.api_key');
        expect($stored)->not->toBe('bp-very-secret')
            ->and(app(GatewaySettings::class)->get('payments.billplz.api_key'))->toBe('bp-very-secret')
            ->and(app(GatewayManager::class)->activeName())->toBe('billplz');

        $page = $this->get(route('admin.payment-gateway.edit'))->assertOk();
        $page->assertInertia(fn ($inertia) => $inertia->component('Admin/PaymentGateway/Edit')
            ->where('active', 'billplz')->where('saved.billplz.api_key', true)->where('saved.billplz.collection_id', 'col123'));
        expect($page->getContent())->not->toContain('bp-very-secret')->not->toContain($stored);

        // Saving again without typing the key keeps it.
        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'billplz', 'billplz' => ['api_key' => '', 'collection_id' => 'col123']])->assertSessionHasNoErrors();
        expect(app(GatewaySettings::class)->get('payments.billplz.api_key'))->toBe('bp-very-secret');
    });

    it('requires each gateway\'s details and a real Stripe secret key', function () {
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'billplz'])->assertSessionHasErrors(['billplz.api_key', 'billplz.collection_id']);
        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'bayarcash'])->assertSessionHasErrors(['bayarcash.token', 'bayarcash.portal_key']);
        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'stripe', 'stripe' => ['secret_key' => 'pk_live_abc']])->assertSessionHasErrors('stripe.secret_key');
        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'paypal'])->assertSessionHasErrors('gateway');

        expect(app(GatewayManager::class)->activeName())->toBe('fake');
    });

    it('checks only the chosen gateway, and keeps the others\' details for later', function () {
        Http::fake(['*/collections/col123' => Http::response(['id' => 'col123', 'title' => 'Shop'])]);
        $this->actingAs(userWithRole(UserRole::Admin));

        // A wrong key left in the Stripe form does not block saving Billplz.
        $this->put(route('admin.payment-gateway.update'), [
            'gateway' => 'billplz',
            'billplz' => ['api_key' => 'bp-key', 'collection_id' => 'col123', 'sandbox' => true],
            'stripe' => ['secret_key' => 'pk_live_wrong'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        expect(app(GatewaySettings::class)->has('payments.stripe.secret_key'))->toBeFalse();

        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'none'])->assertSessionHasNoErrors();

        expect(app(GatewaySettings::class)->get('payments.billplz.api_key'))->toBe('bp-key')
            ->and(app(GatewaySettings::class)->get('payments.billplz.collection_id'))->toBe('col123')
            ->and(app(GatewaySettings::class)->bool('payments.billplz.sandbox'))->toBeTrue();
    });

    it('saves but warns when the connection test fails', function () {
        Http::fake(['api.stripe.com/v1/balance' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid API Key provided']], 401)
            ->push(['livemode' => true])]);
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'stripe', 'stripe' => ['secret_key' => 'sk_live_wrong']])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'did not accept the credentials'));

        expect(app(GatewayManager::class)->activeName())->toBe('stripe');

        $this->post(route('admin.payment-gateway.test'))->assertSessionHas('success', 'Connected to Stripe (live mode).');
    });

    it('refuses the demo gateway on a live site', function () {
        $this->actingAs(userWithRole(UserRole::Admin))->withoutMiddleware(PreventRequestForgery::class);
        app()->detectEnvironment(fn () => 'production');

        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'fake'])->assertSessionHasErrors('gateway');
    });

    it('turns online payment off', function () {
        $this->actingAs(userWithRole(UserRole::Admin));

        $this->put(route('admin.payment-gateway.update'), ['gateway' => 'none'])->assertSessionHas('success');

        expect(app(GatewayManager::class)->ready())->toBeFalse()
            ->and(Order::query()->count())->toBe(0);
    });
});
