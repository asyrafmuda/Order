<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to the given roles: ->middleware('role:admin,cashier').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->is_active && $user->hasRole(...array_map(UserRole::from(...), $roles)),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
