<?php

namespace App\LicenseServer\Http;

use App\Http\Controllers\Controller;
use App\LicenseServer\Models\Activation;
use App\LicenseServer\Models\License;
use App\LicenseServer\Signer;
use App\Support\Licensing\License as ClientLicense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What customers' sites call: activate a purchase code for a domain, get a
 * fresh certificate, release a domain. Answers that change a site's state
 * are signed; plain errors are not.
 */
class ApiController extends Controller
{
    public function __construct(private readonly Signer $signer) {}

    public function activate(Request $request): JsonResponse
    {
        [$code, $domain, $product] = $this->input($request);
        $license = License::query()->where('code', $code)->first();

        if (! $license || $license->product !== $product) {
            return $this->refuse('This purchase code is not valid. Check it and try again.');
        }

        if ($license->isRevoked()) {
            return $this->refuse('This purchase code has been cancelled. Please contact the seller.');
        }

        $activation = DB::transaction(function () use ($license, $domain, $request) {
            License::query()->whereKey($license->id)->lockForUpdate()->first();

            // Installing again on the same domain is not a new activation.
            $activation = $license->activeActivations()->where('domain', $domain)->first();

            if (! $activation) {
                $used = $license->activeActivations()->pluck('domain');

                if ($used->count() >= $license->max_activations) {
                    return null;
                }

                $activation = $license->activations()->create(['domain' => $domain, 'activated_at' => now()]);
            }

            $activation->update(['version' => $request->string('version')->limit(20)->toString(), 'ip' => $request->ip(), 'last_checked_at' => now()]);

            return $activation;
        });

        if (! $activation) {
            $used = $license->activeActivations()->pluck('domain')->implode(', ');

            return $this->refuse("This purchase code is already in use on {$used}. Release it there first (Admin → License → Move to another domain), or ask the seller to reset it.");
        }

        return response()->json($this->signer->active($license, $activation));
    }

    public function check(Request $request): JsonResponse
    {
        [$code, $domain, $product] = $this->input($request);
        $license = License::query()->where('code', $code)->where('product', $product)->first();
        $activation = $license?->activeActivations()->where('domain', $domain)->first();

        $refusal = match (true) {
            ! $license => 'This purchase code no longer exists. Please contact the seller.',
            $license->isRevoked() => 'This license was cancelled by the seller'.($license->revoked_reason ? ': '.rtrim($license->revoked_reason, '.').'.' : '.'),
            ! $activation => "This purchase code is no longer activated for {$domain}. Activate it again in Admin → License.",
            default => null,
        };

        if ($refusal !== null) {
            return response()->json($this->signer->revoked($product, $code, $domain, $refusal));
        }

        /** @var Activation $activation */
        $activation->update(['version' => $request->string('version')->limit(20)->toString(), 'ip' => $request->ip(), 'last_checked_at' => now()]);

        return response()->json($this->signer->active($license, $activation));
    }

    public function deactivate(Request $request): JsonResponse
    {
        [$code, $domain, $product] = $this->input($request);

        License::query()->where('code', $code)->where('product', $product)->first()
            ?->activeActivations()->where('domain', $domain)->update(['deactivated_at' => now()]);

        return response()->json(['message' => "Released {$domain}."]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function input(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'domain' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', 'max:60'],
            'version' => ['nullable', 'string', 'max:20'],
        ]);

        return [ClientLicense::normalizeCode($validated['code']), ClientLicense::normalize($validated['domain']), $validated['product']];
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
