<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ConfirmPayment;
use App\Actions\Payments\FailPayment;
use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * The simulated bank page of the "fake" gateway.
     */
    public function showFake(Payment $payment): Response|RedirectResponse
    {
        abort_unless(config('payment.default') === 'fake', 404);

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
        abort_unless(config('payment.default') === 'fake', 404);

        $validated = $request->validate(['action' => ['required', 'in:approve,decline']]);

        return redirect()->away($gateway->callbackUrl($payment, $validated['action'] === 'approve'));
    }

    /**
     * Return / webhook endpoint for the configured gateway.
     */
    public function callback(Request $request, PaymentGateway $gateway, ConfirmPayment $confirm, FailPayment $fail): RedirectResponse
    {
        try {
            $callback = $gateway->parseCallback($request);
        } catch (PaymentException $exception) {
            report($exception);
            abort(400, 'Invalid payment callback.');
        }

        $payment = Payment::query()
            ->where('reference', $callback->reference)
            ->where('gateway', $gateway->name())
            ->firstOrFail();

        if ($callback->successful) {
            $order = $confirm->handle($payment, $callback->transactionId, $callback->payload);

            return redirect()->route('orders.show', $order)->with('success', "Payment received. Order {$order->order_number} is confirmed!");
        }

        $fail->handle($payment, $callback->payload);

        return redirect()->route('orders.show', $payment->order)->with('error', 'The payment was not completed. You can try again below.');
    }
}
