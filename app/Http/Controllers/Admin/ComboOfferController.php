<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComboOfferRequest;
use App\Models\ComboOffer;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ComboOfferController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ComboOffers/Index', [
            'offers' => ComboOffer::query()->latest('id')->get()->map(fn (ComboOffer $offer) => [
                'id' => $offer->id,
                'name' => $offer->name,
                'description' => $offer->description,
                'price' => $offer->price,
                'choices' => $offer->choices ?? [],
                'is_active' => $offer->is_active,
            ]),
            'eligibleProducts' => Product::query()->where('is_combo_eligible', true)->count(),
        ]);
    }

    public function store(ComboOfferRequest $request): RedirectResponse
    {
        $offer = DB::transaction(function () use ($request) {
            $offer = ComboOffer::query()->create($request->validated());
            $this->keepSingleActive($offer);

            return $offer;
        });

        return back()->with('success', "{$offer->name} was created.");
    }

    public function update(ComboOfferRequest $request, ComboOffer $comboOffer): RedirectResponse
    {
        DB::transaction(function () use ($request, $comboOffer) {
            $comboOffer->update($request->validated());
            $this->keepSingleActive($comboOffer);
        });

        return back()->with('success', "{$comboOffer->name} was updated.");
    }

    public function destroy(ComboOffer $comboOffer): RedirectResponse
    {
        $comboOffer->delete();

        return back()->with('success', "{$comboOffer->name} was deleted.");
    }

    /**
     * The storefront offers one combo at a time, so activating one switches
     * the others off.
     */
    private function keepSingleActive(ComboOffer $offer): void
    {
        if ($offer->is_active) {
            ComboOffer::query()->whereKeyNot($offer->id)->update(['is_active' => false]);
        }
    }
}
