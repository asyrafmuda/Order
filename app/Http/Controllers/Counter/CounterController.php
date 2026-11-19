<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Controller;

class CounterController extends Controller
{
    public function __invoke(): void
    {
        abort(501);
    }
}
