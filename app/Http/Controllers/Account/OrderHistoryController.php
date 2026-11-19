<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Support\Paginate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderHistoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(10);

        return Inertia::render('Account/Orders', [
            'orders' => Paginate::through($orders, OrderResource::class),
        ]);
    }
}
