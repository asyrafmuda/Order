<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Licensing\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Super admin: this copy's license — status, activation with a purchase
 * code, a manual check, and releasing the domain before a move.
 */
class LicenseController extends Controller
{
    public function __construct(private readonly License $license) {}

    public function show(Request $request): Response
    {
        $host = $request->getHost();
        $status = $this->license->status($host);
        $certificate = $this->license->certificate();

        return Inertia::render('Admin/License/Show', [
            'license' => [
                'status' => $status->value,
                'label' => $status->label(),
                'help' => $status->help(),
                'usable' => $status->allowsUse(),
                'configured' => $this->license->configured(),
                'exempt' => $this->license->isExempt($host),
                'host' => License::normalize($host),
                'domain' => $certificate?->domain(),
                'customer' => $certificate?->get('customer'),
                'code' => $certificate ? License::mask((string) $certificate->get('code')) : null,
                'activated_at' => $certificate?->get('issued_at'),
                'checked_at' => $certificate?->checkedAt()?->toIso8601String(),
                'message' => $certificate?->get('message'),
            ],
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);

        try {
            $this->license->activate($validated['code'], $request->getHost());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()->route('admin.license.show')->with('success', 'License activated for '.License::normalize($request->getHost()).'.');
    }

    public function check(Request $request): RedirectResponse
    {
        try {
            $this->license->refresh($request->getHost());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'License confirmed by the license server.');
    }

    /**
     * Free this domain so the code can be used on a new one.
     */
    public function release(): RedirectResponse
    {
        try {
            $this->license->release();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'This domain was released. Activate the purchase code on the new site.');
    }
}
