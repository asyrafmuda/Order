<?php

namespace App\Http\Middleware;

use App\Support\Installer\Installation;
use App\Support\Updates\Updater;
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

        // Files are being replaced by a one-click update: hold everyone back
        // except the request that finishes it.
        if (Updater::applying() && ! $request->is('install/update/finish/*')) {
            return $this->updating($request);
        }

        if ($this->installation->needsUpdate() && ! $request->is('install/update', 'install/update/*')) {
            return $this->away($request, route('install.update'), 'This site is being updated. Please try again in a few minutes.');
        }

        return $next($request);
    }

    /**
     * A page that needs none of the files being replaced.
     */
    private function updating(Request $request): Response
    {
        $message = 'This site is being updated. Please try again in a minute.';

        if ($request->expectsJson() || $request->is('webhooks/*', 'payments/*/notify')) {
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '60']);
        }

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta http-equiv="refresh" content="15"><title>Updating</title></head>'
            .'<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#09090b;color:#e4e4e7;font-family:system-ui,sans-serif;text-align:center">'
            .'<main style="padding:24px"><p style="font-size:40px;margin:0">⏳</p><h1 style="font-size:20px">Updating…</h1><p style="color:#a1a1aa">'.$message.' This page reloads by itself.</p></main></body></html>';

        return response($html, Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '60']);
    }

    private function away(Request $request, string $to, string $message): Response
    {
        if ($request->expectsJson() || $request->is('webhooks/*', 'payments/*/notify')) {
            // Gateways and the POS retry later.
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '120']);
        }

        return redirect()->to($to);
    }
}
