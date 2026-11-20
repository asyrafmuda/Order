<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Support\OutletContext;

abstract class Controller
{
    /**
     * The outlet this request is working in. Everything a branch owns
     * (orders, tables, delivery areas, riders) is read through it.
     */
    protected function outlet(): Outlet
    {
        return app(OutletContext::class)->current();
    }
}
