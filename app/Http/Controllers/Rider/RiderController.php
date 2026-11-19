<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;

class RiderController extends Controller
{
    public function __invoke(): void
    {
        abort(501);
    }
}
