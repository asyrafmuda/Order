<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Installer\Installation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * After newer files are uploaded over an installed site, their database
 * changes run here. A super admin confirms with their password; until then
 * the site shows that it is being updated.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly Installation $installation) {}

    public function show(): View|RedirectResponse
    {
        if (! $this->installation->needsUpdate()) {
            return redirect()->to('/');
        }

        return view('install.update', [
            'from' => $this->installation->installedVersion() === '0.0.0' ? null : $this->installation->installedVersion(),
            'to' => $this->installation->version(),
            'pending' => count($this->installation->pendingMigrations()),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        if (! $this->installation->needsUpdate()) {
            return redirect()->to('/');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Only the columns every version has, so this works before the update.
        $admin = User::query()
            ->where('email', strtolower($credentials['email']))
            ->where('role', UserRole::Admin->value)
            ->first(['id', 'password']);

        if (! $admin || ! Hash::check($credentials['password'], (string) $admin->password)) {
            throw ValidationException::withMessages(['email' => 'Those details do not match a super admin account.']);
        }

        @set_time_limit(300);

        try {
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException(trim(Artisan::output()));
            }

            // Compiled views and cached settings from the old version.
            Artisan::call('optimize:clear');
            $this->installation->markInstalled();
        } catch (Throwable $exception) {
            Log::error('Update failed', ['exception' => $exception]);

            throw ValidationException::withMessages(['update' => 'The update stopped: '.$exception->getMessage()]);
        }

        return redirect()->to('/login')->with('success', 'Updated to version '.$this->installation->version().'. Sign in to carry on.');
    }
}
