<?php

namespace App\Support;

use App\Models\Outlet;
use Closure;

/**
 * Which outlet the current request is working in.
 *
 *  - Multi-outlet switched off: always the default outlet.
 *  - Outlet staff (manager, cashier, waiter, kitchen, rider): their own outlet.
 *  - Super admins and customers: the outlet they picked, kept in the session.
 *  - Queue jobs and commands: whatever runAs() was given, else the default.
 */
class OutletContext
{
    public const SESSION_KEY = 'outlet_id';

    private ?Outlet $forced = null;

    /** @var array<string, Outlet> */
    private array $resolved = [];

    private ?Outlet $default = null;

    public function multi(): bool
    {
        return (bool) app(Settings::class)->global('outlets.multi_enabled', false);
    }

    public function default(): Outlet
    {
        return $this->default ??= Outlet::default();
    }

    public function current(): Outlet
    {
        if ($this->forced) {
            return $this->forced;
        }

        if (! $this->multi()) {
            return $this->default();
        }

        $request = request();
        $user = $request->user();

        // Outlet staff always work in their own outlet. Customers and super
        // admins work in the one they picked, which the session remembers.
        $outletStaff = $user?->isOutletStaff() ?? false;
        $outletId = $outletStaff ? $user->outlet_id : ($request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null);
        $staff = $user?->isStaff() ?? false;

        return $this->resolved[($staff ? 'staff' : 'customer').'|'.($outletId ?? '-')] ??= $this->resolve($outletId, $staff);
    }

    /**
     * Remember the outlet a customer or super admin chose.
     */
    public function switchTo(Outlet $outlet): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, $outlet->id);
        }
    }

    /**
     * Run code as if the request were for the given outlet, e.g. to read its
     * settings while handling one of its orders on the queue.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Outlet $outlet, Closure $callback): mixed
    {
        $previous = $this->forced;
        $this->forced = $outlet;

        try {
            return $callback();
        } finally {
            $this->forced = $previous;
        }
    }

    /**
     * Forget cached lookups after outlets were added, changed or removed.
     */
    public function flush(): void
    {
        $this->resolved = [];
        $this->default = null;
    }

    private function resolve(?int $outletId, bool $staff): Outlet
    {
        if (! $outletId) {
            return $this->default();
        }

        // Staff keep working in their outlet even while it is closed to
        // customers; customers can only pick an active one.
        $outlet = Outlet::query()->when(! $staff, fn ($query) => $query->active())->find($outletId);

        return $outlet ?? $this->default();
    }
}
