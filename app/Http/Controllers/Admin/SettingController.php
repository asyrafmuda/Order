<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $known = array_intersect_key($settings->all(), config('shop.defaults', []));

        unset($known['brand.logo_path']);

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => Arr::undot($known),
        ]);
    }

    public function update(SettingRequest $request, Settings $settings): RedirectResponse
    {
        $settings->put($request->settings());

        return back()->with('success', 'Settings saved.');
    }

    /**
     * Replace the emoji icon with an uploaded logo.
     */
    public function storeLogo(Request $request, Settings $settings): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64'],
        ]);

        $this->deleteLogo($settings);

        $settings->put(['brand.logo_path' => $request->file('logo')->store('branding', 'public')]);

        return back()->with('success', 'Logo updated.');
    }

    public function destroyLogo(Settings $settings): RedirectResponse
    {
        $this->deleteLogo($settings);

        $settings->put(['brand.logo_path' => null]);

        return back()->with('success', 'Logo removed. The icon is shown instead.');
    }

    private function deleteLogo(Settings $settings): void
    {
        if ($path = $settings->get('brand.logo_path')) {
            Storage::disk('public')->delete($path);
        }
    }
}
