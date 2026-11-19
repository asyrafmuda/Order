<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $known = array_intersect_key($settings->all(), config('shop.defaults', []));

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => Arr::undot($known),
        ]);
    }

    public function update(SettingRequest $request, Settings $settings): RedirectResponse
    {
        $settings->put($request->settings());

        return back()->with('success', 'Settings saved.');
    }
}
