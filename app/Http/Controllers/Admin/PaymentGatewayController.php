<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\GatewaySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin: which payment gateway takes online payments, and its
 * credentials. Secrets are never sent back to the browser.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly GatewaySettings $credentials,
    ) {}

    public function edit(): Response
    {
        $c = $this->credentials;

        return Inertia::render('Admin/PaymentGateway/Edit', [
            'active' => $this->gateways->activeName(),
            'ready' => $this->gateways->ready(),
            'production' => app()->isProduction() && ! config('payment.fake_in_production'),
            'saved' => [
                'billplz' => [
                    'api_key' => $c->has('payments.billplz.api_key'),
                    'collection_id' => (string) $c->get('payments.billplz.collection_id'),
                    'sandbox' => $c->bool('payments.billplz.sandbox'),
                ],
                'bayarcash' => [
                    'token' => $c->has('payments.bayarcash.token'),
                    'secret_key' => $c->has('payments.bayarcash.secret_key'),
                    'portal_key' => (string) $c->get('payments.bayarcash.portal_key'),
                    'sandbox' => $c->bool('payments.bayarcash.sandbox'),
                ],
                'stripe' => [
                    'secret_key' => $c->has('payments.stripe.secret_key'),
                ],
            ],
            'urls' => [
                'stripe_webhook' => route('payments.notify', ['gateway' => 'stripe']),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $gateway = (string) $request->input('gateway');
        $saved = fn (string $key) => $this->credentials->has($key);
        $productionDemo = app()->isProduction() && ! config('payment.fake_in_production');

        $request->validate(['gateway' => ['required', Rule::in(['none', 'billplz', 'bayarcash', 'stripe', 'fake'])]]);

        if ($gateway === 'fake' && $productionDemo) {
            throw ValidationException::withMessages(['gateway' => 'The demo gateway approves every payment without taking money, so it cannot be used on a live site.']);
        }

        // Only the chosen gateway's details are checked and saved; the others
        // keep what they had, so switching back later needs no retyping.
        $rules = match ($gateway) {
            'billplz' => [
                'billplz.api_key' => [Rule::requiredIf(! $saved('payments.billplz.api_key')), 'nullable', 'string', 'max:255'],
                'billplz.collection_id' => ['required', 'string', 'max:100'],
                'billplz.sandbox' => ['boolean'],
            ],
            'bayarcash' => [
                'bayarcash.token' => [Rule::requiredIf(! $saved('payments.bayarcash.token')), 'nullable', 'string', 'max:4000'],
                'bayarcash.secret_key' => ['nullable', 'string', 'max:255'],
                'bayarcash.portal_key' => ['required', 'string', 'max:255'],
                'bayarcash.sandbox' => ['boolean'],
            ],
            'stripe' => [
                'stripe.secret_key' => [Rule::requiredIf(! $saved('payments.stripe.secret_key')), 'nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(live|test)_/'],
            ],
            default => [],
        };

        $validated = $request->validate($rules, [
            'stripe.secret_key.regex' => 'This is not a Stripe secret key. It starts with sk_live_ (or sk_test_ for testing). Use the secret key, not the publishable pk_ key.',
        ], [
            'billplz.api_key' => 'API secret key',
            'billplz.collection_id' => 'collection ID',
            'bayarcash.token' => 'API token',
            'bayarcash.portal_key' => 'portal key',
            'stripe.secret_key' => 'secret key',
        ]);

        $values = match ($gateway) {
            'billplz' => [
                'payments.billplz.api_key' => $validated['billplz']['api_key'] ?? null,
                'payments.billplz.collection_id' => trim($validated['billplz']['collection_id']),
                'payments.billplz.sandbox' => (bool) ($validated['billplz']['sandbox'] ?? false),
            ],
            'bayarcash' => [
                'payments.bayarcash.token' => $validated['bayarcash']['token'] ?? null,
                'payments.bayarcash.secret_key' => $validated['bayarcash']['secret_key'] ?? null,
                'payments.bayarcash.portal_key' => trim($validated['bayarcash']['portal_key']),
                'payments.bayarcash.sandbox' => (bool) ($validated['bayarcash']['sandbox'] ?? false),
            ],
            'stripe' => ['payments.stripe.secret_key' => $validated['stripe']['secret_key'] ?? null],
            default => [],
        };

        $this->credentials->put(['payments.gateway' => $gateway, ...$values]);

        if (in_array($gateway, ['none', 'fake'], true)) {
            return back()->with('success', $gateway === 'none' ? 'Online payment is off. Customers pay at the counter.' : 'The demo gateway is on.');
        }

        // Check the new details straight away, so a typo shows now rather than at a customer's checkout.
        try {
            $message = app(GatewayManager::class)->driver($gateway)->test();
        } catch (PaymentException $exception) {
            return back()->with('error', 'Saved, but the connection test failed: '.$exception->getMessage());
        }

        return back()->with('success', 'Saved. '.$message);
    }

    public function test(): RedirectResponse
    {
        $gateway = $this->gateways->active();

        if (! $gateway) {
            return back()->with('error', 'Choose a payment gateway first.');
        }

        try {
            return back()->with('success', $gateway->test());
        } catch (PaymentException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
