<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RiderRequest;
use App\Models\Rider;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RiderController extends Controller
{
    public function index(): Response
    {
        $riders = Rider::query()
            ->with('user')
            ->withCount([
                'deliveries',
                'deliveries as active_deliveries_count' => fn ($query) => $query
                    ->whereIn('status', [DeliveryStatus::Assigned, DeliveryStatus::PickedUp]),
            ])
            ->get()
            ->sortBy(fn (Rider $rider) => $rider->user->name)
            ->values();

        return Inertia::render('Admin/Riders/Index', [
            'riders' => $riders->map(fn (Rider $rider) => [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'email' => $rider->user->email,
                'phone' => $rider->user->phone,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'is_available' => $rider->is_available,
                'is_active' => $rider->user->is_active,
                'deliveries_count' => $rider->deliveries_count,
                'active_deliveries_count' => $rider->active_deliveries_count,
            ]),
        ]);
    }

    public function store(RiderRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                ...$request->safe()->only('name', 'email', 'password', 'is_active'),
                'phone' => Phone::normalize($request->validated('phone')),
                'role' => UserRole::Rider,
            ]);

            $user->rider()->create($request->safe()->only('vehicle_type', 'plate_number', 'is_available'));

            return $user;
        });

        return back()->with('success', "Rider {$user->name} was added.");
    }

    public function update(RiderRequest $request, Rider $rider): RedirectResponse
    {
        DB::transaction(function () use ($request, $rider) {
            $rider->user->update([
                ...$request->safe()->only('name', 'email', 'is_active'),
                'phone' => Phone::normalize($request->validated('phone')),
                ...($request->filled('password') ? ['password' => $request->validated('password')] : []),
            ]);

            $rider->update($request->safe()->only('vehicle_type', 'plate_number', 'is_available'));
        });

        return back()->with('success', "Rider {$rider->user->name} was updated.");
    }

    /**
     * Riders with delivery history are deactivated so past deliveries keep
     * their rider; riders who never delivered are removed completely.
     */
    public function destroy(Rider $rider): RedirectResponse
    {
        $name = $rider->user->name;

        if ($rider->deliveries()->exists()) {
            DB::transaction(function () use ($rider) {
                $rider->user->update(['is_active' => false]);
                $rider->update(['is_available' => false]);
            });

            return back()->with('success', "{$name} has delivery history, so the account was deactivated instead of deleted.");
        }

        $rider->user->delete();

        return back()->with('success', "Rider {$name} was deleted.");
    }
}
