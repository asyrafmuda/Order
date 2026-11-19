<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\GeneratesUniqueSlugs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KitchenStationRequest;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KitchenStationController extends Controller
{
    use GeneratesUniqueSlugs;

    public function index(): Response
    {
        $categories = Category::query()->selectRaw('kitchen_station_id, count(*) as total')
            ->whereNotNull('kitchen_station_id')->groupBy('kitchen_station_id')->toBase()->pluck('total', 'kitchen_station_id');
        $products = Product::query()->selectRaw('kitchen_station_id, count(*) as total')
            ->whereNotNull('kitchen_station_id')->groupBy('kitchen_station_id')->toBase()->pluck('total', 'kitchen_station_id');

        return Inertia::render('Admin/KitchenStations/Index', [
            'stations' => KitchenStation::query()->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (KitchenStation $station) => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'slug' => $station->slug,
                    'sort_order' => $station->sort_order,
                    'is_active' => $station->is_active,
                    'categories_count' => (int) ($categories[$station->id] ?? 0),
                    'products_count' => (int) ($products[$station->id] ?? 0),
                ]),
        ]);
    }

    public function store(KitchenStationRequest $request): RedirectResponse
    {
        $station = KitchenStation::query()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug(KitchenStation::class, $request->validated('name')),
        ]);

        return back()->with('success', "Station {$station->name} was created.");
    }

    public function update(KitchenStationRequest $request, KitchenStation $kitchenStation): RedirectResponse
    {
        $kitchenStation->update([
            ...$request->validated(),
            'slug' => $this->uniqueSlug(KitchenStation::class, $request->validated('name'), $kitchenStation->id),
        ]);

        return back()->with('success', "Station {$kitchenStation->name} was updated.");
    }

    public function destroy(KitchenStation $kitchenStation): RedirectResponse
    {
        $inUse = Category::query()->where('kitchen_station_id', $kitchenStation->id)->exists()
            || Product::query()->where('kitchen_station_id', $kitchenStation->id)->exists();

        if ($inUse) {
            return back()->with('error', "{$kitchenStation->name} still cooks menu items. Reassign those categories or products first, or deactivate the station.");
        }

        $kitchenStation->delete();

        return back()->with('success', "Station {$kitchenStation->name} was deleted.");
    }
}
