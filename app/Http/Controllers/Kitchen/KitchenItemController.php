<?php

namespace App\Http\Controllers\Kitchen;

use App\Actions\Kitchen\UpdateKitchenItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateKitchenItemRequest;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;

class KitchenItemController extends Controller
{
    /**
     * A station started, finished or re-queued one item. The order itself
     * follows along inside the action.
     */
    public function update(UpdateKitchenItemRequest $request, OrderItem $item, UpdateKitchenItem $update): RedirectResponse
    {
        $update->handle($item, $request->status(), $request->user());

        return back();
    }
}
