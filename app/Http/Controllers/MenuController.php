<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\ComboOffer;
use App\Models\DeliveryZone;
use App\Support\OutletContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->whereHas('products', fn ($query) => $query->visible())
            ->with(['products' => fn ($query) => $query->visible()->ordered()->with(['variants', 'modifierGroups.modifiers'])])
            ->get();

        return Inertia::render('Menu/Index', [
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'combo' => ComboOffer::current()?->only(['id', 'name', 'description', 'price', 'choices']),
            'zones' => DeliveryZone::query()->active()->forOutlet(app(OutletContext::class)->current())->orderBy('sort_order')->get(['id', 'name', 'areas', 'fee', 'min_order', 'eta_minutes']),
        ]);
    }
}
