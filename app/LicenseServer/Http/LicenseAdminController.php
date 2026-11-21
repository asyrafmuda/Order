<?php

namespace App\LicenseServer\Http;

use App\Http\Controllers\Controller;
use App\LicenseServer\Models\Activation;
use App\LicenseServer\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin on the license server: the purchase codes sold, where each is
 * used, and the controls to cancel a code or free a domain.
 */
class LicenseAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');

        $licenses = License::query()
            ->search($search)
            ->with('activations')
            ->latest()
            ->limit(200)
            ->get();

        return Inertia::render('Admin/Licenses/Index', [
            'licenses' => $licenses->map(fn (License $license) => [
                'id' => $license->id,
                'code' => $license->code,
                'customer_name' => $license->customer_name,
                'customer_email' => $license->customer_email,
                'max_activations' => $license->max_activations,
                'updates_until' => $license->updates_until?->toDateString(),
                'notes' => $license->notes,
                'revoked' => $license->isRevoked(),
                'revoked_reason' => $license->revoked_reason,
                'created_at' => $license->created_at?->toIso8601String(),
                'activations' => $license->activations->map(fn (Activation $activation) => [
                    'id' => $activation->id,
                    'domain' => $activation->domain,
                    'version' => $activation->version,
                    'active' => $activation->deactivated_at === null,
                    'activated_at' => $activation->activated_at->toIso8601String(),
                    'last_checked_at' => $activation->last_checked_at?->toIso8601String(),
                ])->values(),
            ]),
            'filters' => ['search' => $search],
            'stats' => [
                'licenses' => License::query()->count(),
                'active_sites' => Activation::query()->whereNull('deactivated_at')->count(),
                'revoked' => License::query()->whereNotNull('revoked_at')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $license = License::query()->create([
            ...$this->validated($request),
            'code' => License::newCode(),
            'product' => config('licensing.product'),
        ]);

        return back()->with('success', "Purchase code for {$license->customer_name}: {$license->code}");
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $license->update($this->validated($request));

        return back()->with('success', "{$license->code} updated.");
    }

    public function revoke(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $license->update(['revoked_at' => now(), 'revoked_reason' => $validated['reason'] ?? null]);

        return back()->with('success', "{$license->code} cancelled. Its sites stop at their next check (within a day).");
    }

    public function restore(License $license): RedirectResponse
    {
        $license->update(['revoked_at' => null, 'revoked_reason' => null]);

        return back()->with('success', "{$license->code} restored. Its sites work again after their next check, or when the owner presses “Check now”.");
    }

    /**
     * Free a domain, e.g. when a customer moved without releasing it.
     */
    public function release(License $license, Activation $activation): RedirectResponse
    {
        abort_unless($activation->license_id === $license->id, 404);

        $activation->update(['deactivated_at' => now()]);

        return back()->with('success', "{$activation->domain} released from {$license->code}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'max_activations' => ['required', 'integer', 'min:1', 'max:100'],
            'updates_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
