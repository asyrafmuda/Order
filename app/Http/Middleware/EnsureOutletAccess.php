<?php

namespace App\Http\Middleware;

use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Rider;
use App\Support\OutletContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff may only act on records of the outlet they are working in. Lists are
 * already filtered; this covers records addressed directly in the URL
 * (/counter/orders/{order}/complete and the like), answering 404 for another
 * outlet's record exactly as if it did not exist.
 */
class EnsureOutletAccess
{
    public function __construct(private readonly OutletContext $outlets) {}

    public function handle(Request $request, Closure $next): Response
    {
        $outlet = $this->outlets->current();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            $owner = $this->outletIdOf($parameter);

            if ($owner === null || $owner === $outlet->id) {
                continue;
            }

            // A super admin opening another outlet's record switches to that
            // outlet, so the rest of the screen is about the same branch.
            abort_unless($this->outlets->multi() && $request->user()?->isAdmin(), Response::HTTP_NOT_FOUND);

            $this->outlets->switchTo(Outlet::query()->findOrFail($owner));
            $outlet = $this->outlets->current();
        }

        return $next($request);
    }

    /**
     * The outlet a route-bound record belongs to, or null when it is not
     * tied to one (products, users, …).
     */
    private function outletIdOf(mixed $record): ?int
    {
        return match (true) {
            $record instanceof Order, $record instanceof DiningTable, $record instanceof DeliveryZone => (int) $record->outlet_id,
            $record instanceof OrderItem, $record instanceof Delivery => (int) $record->order()->value('outlet_id'),
            $record instanceof Rider => (int) ($record->user()->value('outlet_id') ?? Outlet::default()->id),
            default => null,
        };
    }
}
