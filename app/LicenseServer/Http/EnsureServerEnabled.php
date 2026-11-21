<?php

namespace App\LicenseServer\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The license server's routes exist in every source checkout but answer only
 * where LICENSE_SERVER_ENABLED is set.
 */
class EnsureServerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('licensing.server.enabled'), 404);

        return $next($request);
    }
}
