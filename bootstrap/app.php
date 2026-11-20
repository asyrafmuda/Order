<?php

use App\Exceptions\OrderException;
use App\Http\Middleware\EnsureOutletAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware(['web', 'auth', 'outlet'])->group(base_path('routes/staff.php'));
            Route::middleware(['web', 'auth', 'role:admin,manager', 'outlet'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
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

            if (! app()->environment('local', 'testing') && in_array($status, [500, 503, 404, 403], true)) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            if ($status === 419) {
                return back()->with('error', 'The page expired, please try again.');
            }

            return $response;
        });
    })->create();
