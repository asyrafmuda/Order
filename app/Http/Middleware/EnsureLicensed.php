<?php

namespace App\Http\Middleware;

use App\Support\Licensing\License;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A copy without a valid license for this address stops: visitors see that
 * the site is unavailable, and the super admin is sent to Admin → License to
 * enter a purchase code. Signing in and that page keep working.
 */
class EnsureLicensed
{
    /** Paths that stay open so the owner can sign in and fix it. */
    private const OPEN = ['login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*', 'admin/license', 'admin/license/*', 'install', 'install/*'];

    public function __construct(private readonly License $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->license->status($request->getHost());

        if ($status->wantsRefresh()) {
            $this->refreshLater();
        }

        if ($status->allowsUse() || $request->is(...self::OPEN)) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.license.show');
        }

        if ($request->expectsJson() || $request->is('webhooks/*', 'payments/callback')) {
            return response()->json(['message' => 'This site is not available right now.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->view('license.locked', ['status' => $status], Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Fetch a fresh certificate after the response is sent, at most once an
     * hour, so sites without a working cron job stay current too.
     */
    private function refreshLater(): void
    {
        if (! Cache::add('license:refresh-attempt', true, now()->addHour())) {
            return;
        }

        // Terminating callbacks stay registered for the life of the process
        // (long-running servers, tests), so this one runs only once.
        $done = false;
        app()->terminating(function () use (&$done) {
            if (! $done) {
                $done = true;
                $this->license->refreshQuietly();
            }
        });
    }
}
