<?php

use App\Exceptions\OrderException;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureLicensed;
use App\Http\Middleware\EnsureOutletAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Installer\EnvironmentFile;
use App\Support\Installer\Installation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// A release ZIP has no .env: create one with an application key on the first
// web request, so the installer can start. Does nothing once .env exists.
EnvironmentFile::prepare(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // The installer has no database to read shared page data from.
            Route::middleware('web')
                ->withoutMiddleware(HandleInertiaRequests::class)
                ->prefix('install')
                ->name('install.')
                ->group(base_path('routes/install.php'));

            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware(['web', 'auth', 'outlet'])->group(base_path('routes/staff.php'));
            Route::middleware(['web', 'auth', 'role:admin,manager', 'outlet'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            EnsureInstalled::class,
        ], append: [
            // After the session starts, so it knows whether a super admin is signed in.
            EnsureLicensed::class,
            HandleInertiaRequests::class,
        ]);

        // Honour X-Forwarded-* only when the request arrives from a private
        // address (nginx on the same host, a load balancer, a preview proxy),
        // so generated URLs use the public scheme and host.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'outlet' => EnsureOutletAccess::class,
        ]);

        // Payment gateways and the POS post to these without a session.
        $middleware->preventRequestForgery(except: [
            'payments/callback',
            'webhooks/*',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson(),
        );

        // Business-rule failures go back to the page as a flash message.
        $exceptions->dontReport(OrderException::class);
        $exceptions->render(function (OrderException $exception, Request $request) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY)
                : back()->with('error', $exception->getMessage());
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // The branded error page reads shop settings from the database, which
            // the installer does not have yet (and which may be what failed).
            $branded = ! $request->is('install', 'install/*') && app(Installation::class)->isInstalled();

            if ($branded && ! app()->environment('local', 'testing') && in_array($status, [500, 503, 404, 403], true)) {
                try {
                    return Inertia::render('Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (Throwable) {
                    return $response;
                }
            }

            if ($status === 419) {
                return back()->with('error', 'The page expired, please try again.');
            }

            return $response;
        });
    })->create();
