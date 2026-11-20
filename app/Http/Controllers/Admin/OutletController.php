<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OutletRequest;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Support\OutletContext;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OutletController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly OutletContext $outlets,
    ) {}

    /**
     * Super admin: every outlet, with today's numbers.
     */
    public function index(): Response
    {
        $today = Order::query()
            ->where('business_date', now()->toDateString())
            ->selectRaw('outlet_id, count(*) as orders, sum(case when payment_status = ? and status != ? then total else 0 end) as sales', ['paid', OrderStatus::Cancelled->value])
            ->groupBy('outlet_id')
            ->toBase()
            ->get()
            ->keyBy('outlet_id');

        $outlets = Outlet::query()->ordered()->withCount('tables')->get();

        return Inertia::render('Admin/Outlets/Index', [
            'outlets' => $outlets->map(fn (Outlet $outlet) => [
                ...$this->present($outlet),
                'tables_count' => $outlet->tables_count,
                // Counted through inOutlet(): staff nobody assigned work at the main outlet.
                'staff_count' => User::query()->whereIn('role', UserRole::outletStaff())->inOutlet($outlet)->count(),
                'orders_today' => (int) ($today[$outlet->id]->orders ?? 0),
                'sales_today' => (int) ($today[$outlet->id]->sales ?? 0),
            ]),
            'multi' => $this->outlets->multi(),
        ]);
    }

    /**
     * The outlet being managed: a manager's own, or the one a super admin is viewing.
     */
    public function edit(): Response
    {
        return Inertia::render('Admin/Outlets/Edit', [
            // Not "outlet": that name is the shared prop every page gets.
            'details' => $this->present($this->outlet()),
        ]);
    }

    public function store(OutletRequest $request): RedirectResponse
    {
        $outlet = DB::transaction(function () use ($request) {
            $outlet = Outlet::query()->create([
                ...$request->safe()->only('name', 'is_active', 'sort_order'),
                'slug' => $this->uniqueSlug($request->validated('name')),
            ]);

            $this->settings->put($request->settings(), $outlet);

            return $outlet;
        });

        $this->outlets->flush();

        return back()->with('success', "{$outlet->name} added. Next: add its tables, delivery areas and staff.");
    }

    public function update(OutletRequest $request, Outlet $outlet): RedirectResponse
    {
        $outlet->update($request->safe()->only('name', 'is_active', 'sort_order'));
        $this->settings->put($request->settings(), $outlet);
        $this->outlets->flush();

        return back()->with('success', "{$outlet->name} updated.");
    }

    /**
     * A manager (or an admin from the outlet page) saves the details of the
     * outlet they are working in. Name, order and status stay as they are.
     */
    public function updateCurrent(OutletRequest $request): RedirectResponse
    {
        $outlet = $this->outlet();

        // Managers cannot rename or close their outlet; super admins do that under Outlets.
        if ($request->user()->isAdmin()) {
            $outlet->update(['name' => $request->validated('name')]);
        }

        $this->settings->put($request->settings(), $outlet);
        $this->outlets->flush();

        return back()->with('success', 'Outlet details saved.');
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        if ($outlet->is_default) {
            return back()->with('error', 'The main outlet cannot be removed.');
        }

        if ($outlet->orders()->exists()) {
            return back()->with('error', "{$outlet->name} has order history, so it cannot be removed. Deactivate it instead.");
        }

        DB::transaction(function () use ($outlet) {
            // Nothing was ever ordered here, so its tables and delivery areas can go with it.
            $outlet->tables()->delete();
            $outlet->deliveryZones()->delete();
            $outlet->delete();
        });

        $this->outlets->flush();

        return back()->with('success', "{$outlet->name} removed.");
    }

    /**
     * Super admin: choose which outlet the back office shows.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate(['outlet_id' => ['required', Rule::exists(Outlet::class, 'id')]]);

        $this->outlets->switchTo($outlet = Outlet::query()->findOrFail($validated['outlet_id']));

        return back()->with('success', "Now viewing {$outlet->name}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Outlet $outlet): array
    {
        return [
            'id' => $outlet->id,
            'name' => $outlet->name,
            'slug' => $outlet->slug,
            'is_default' => $outlet->is_default,
            'is_active' => $outlet->is_active,
            'sort_order' => $outlet->sort_order,
            'settings' => Arr::undot($this->settings->forOutlet($outlet)),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'outlet';
        $slug = $base;

        for ($suffix = 2; Outlet::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
