<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;

class KitchenController extends Controller
{
    public function __invoke(): void
    {
        abort(501);
    }
}
