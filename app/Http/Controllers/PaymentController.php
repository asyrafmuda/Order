<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ReconcilePayment;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PaymentController extends Controller
{
    /**
     * The simulated bank page of the demo gateway.
     */
    public function showFake(Payment $payment): Response|RedirectResponse
    {
        abort_unless($payment->gateway === 'fake', 404);

        $payment->load('order');

        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->route('orders.show', $payment->order);
        }

        return Inertia::render('Payments/Fake', [
            'payment' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'order_number' => $payment->order->order_number,
                'order_uuid' => $payment->order->uuid,
            ],
        ]);
    }

    public function completeFake(Request $request, Payment $payment, FakeGateway $gateway): RedirectResponse
    {
        abort_unless($payment->gateway === 'fake', 404);

        $validated = $request->validate(['action' => ['required', 'in:approve,decline']]);

        return redirect()->away($gateway->callbackUrl($payment, $validated['action'] === 'approve'));
    }

    /**
     * The customer comes back from the gateway's page.
     */
    public function return(Request $request, string $gateway, ReconcilePayment $reconcile): RedirectResponse
    {
        $payment = $this->payment($request, $gateway);

        try {
            $result = $reconcile->handle($payment, $request);
        } catch (PaymentException $exception) {
            report($exception);
            abort(400, 'Invalid payment callback.');
        }

        $order = $payment->order()->firstOrFail();

        return match ($result->status) {
            'paid' => redirect()->route('orders.show', $order)->with('success', "Payment received. Order {$order->order_number} is confirmed!"),
            'failed' => redirect()->route('orders.show', $order)->with('error', 'The payment was not completed. You can try again below.'),
            // E.g. FPX waiting for the bank: the gateway's own call confirms it shortly.
            default => redirect()->route('orders.show', $order)->with('success', 'Thank you! We are waiting for the bank to confirm your payment; this page updates by itself.'),
        };
    }

    /**
     * The gateway reports a result server to server. Always answered with
     * 200 once handled, so the gateway stops retrying.
     */
    public function notify(Request $request, string $gateway, ReconcilePayment $reconcile): HttpResponse
    {
        $payment = $this->payment($request, $gateway, required: false);

        // E.g. a Stripe event about something else: nothing to do, and no reason to retry.
        if (! $payment) {
            return response('Ignored');
        }

        try {
            $reconcile->handle($payment, $request);
        } catch (PaymentException $exception) {
            report($exception);

            // Not a forgery we can tell apart from an outage: let the gateway try again.
            return response('Could not confirm the payment yet.', 503);
        }

        return response('OK');
    }

    /**
     * The payment a return or notify call is about: our reference is in the
     * URL we gave the gateway, or (Stripe webhooks, Bayarcash callbacks) in
     * the body.
     */
    private function payment(Request $request, string $gateway, bool $required = true): ?Payment
    {
        try {
            app(GatewayManager::class)->driver($gateway);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $reference = $request->query('payment')
            ?? $request->input('data.object.client_reference_id')
            ?? $request->input('order_number');

        $payment = is_string($reference) && $reference !== ''
            ? Payment::query()->where('reference', $reference)->where('gateway', $gateway)->first()
            : null;

        abort_if($required && ! $payment, 404);

        return $payment;
    }
}
