<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryZoneController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/DeliveryZones/Index', [
            'zones' => DeliveryZone::query()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'areas', 'fee', 'min_order', 'eta_minutes', 'is_active', 'sort_order']),
        ]);
    }

    public function store(DeliveryZoneRequest $request): RedirectResponse
    {
        $zone = DeliveryZone::query()->create($request->validated());

        return back()->with('success', "{$zone->name} was created.");
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $deliveryZone->update($request->validated());

        return back()->with('success', "{$deliveryZone->name} was updated.");
    }

    public function destroy(DeliveryZone $deliveryZone): RedirectResponse
    {
        // Past deliveries keep their own copy of the fee and address.
        $deliveryZone->delete();

        return back()->with('success', "{$deliveryZone->name} was deleted.");
    }
}
