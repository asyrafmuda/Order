<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Support\OutletContext;
use App\Support\OutletPrices;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __invoke(Request $request, OutletPrices $prices): Response
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->whereHas('products', fn ($query) => $query->visible())
            ->with(['products' => fn ($query) => $query->visible()->ordered()->with(['variants', 'modifierGroups.modifiers'])])
            ->get();

        // The menu shows what this outlet charges.
        $prices->apply($categories->flatMap->products);

        return Inertia::render('Menu/Index', [
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'combo' => $prices->currentCombo()?->only(['id', 'name', 'description', 'price', 'choices']),
            'zones' => DeliveryZone::query()->active()->forOutlet(app(OutletContext::class)->current())->orderBy('sort_order')->get(['id', 'name', 'areas', 'fee', 'min_order', 'eta_minutes']),
        ]);
    }
}
