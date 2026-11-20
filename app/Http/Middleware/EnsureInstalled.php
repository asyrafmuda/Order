<?php

namespace App\Http\Middleware;

use App\Support\Installer\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before installation every page leads to the installer; after uploading a
 * newer version every page leads to the update screen until its database
 * changes have run. Runs first in the "web" group, before the session starts.
 */
class EnsureInstalled
{
    public function __construct(private readonly Installation $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->installation->isInstalled()) {
            // There is no database yet, so nothing may be stored in it.
            config(['session.driver' => 'file', 'cache.default' => 'file']);

            if ($request->is('install', 'install/*')) {
                return $next($request);
            }

            return $this->away($request, route('install.welcome'), 'This site is not installed yet.');
        }

        if ($this->installation->needsUpdate() && ! $request->is('install/update')) {
            return $this->away($request, route('install.update'), 'This site is being updated. Please try again in a few minutes.');
        }

        return $next($request);
    }

    private function away(Request $request, string $to, string $message): Response
    {
        if ($request->expectsJson() || $request->is('webhooks/*', 'payments/callback')) {
            // Gateways and the POS retry later.
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '120']);
        }

        return redirect()->to($to);
    }
}
