<?php

namespace App\LicenseServer;

use App\LicenseServer\Console\CreateLicenseCommand;
use App\LicenseServer\Console\KeygenCommand;
use App\LicenseServer\Console\PublishReleaseCommand;
use App\LicenseServer\Http\ApiController;
use App\LicenseServer\Http\EnsureServerEnabled;
use App\LicenseServer\Http\LicenseAdminController;
use App\LicenseServer\Http\ReleaseAdminController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The license server: your own copy of the app, with LICENSE_SERVER_ENABLED
 * set, issues purchase codes and signs the certificates customers' sites
 * check. Everything under app/LicenseServer is left out of the client ZIP.
 */
class LicenseServerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([KeygenCommand::class, CreateLicenseCommand::class, PublishReleaseCommand::class]);
        }

        $this->loadMigrationsFrom(database_path('migrations/license-server'));

        $enabled = EnsureServerEnabled::class;

        Route::prefix('api/license')->middleware([$enabled, 'throttle:30,1'])->group(function () {
            Route::post('/activate', [ApiController::class, 'activate']);
            Route::post('/check', [ApiController::class, 'check']);
            Route::post('/deactivate', [ApiController::class, 'deactivate']);
            Route::post('/updates', [ApiController::class, 'updates']);
        });

        // The update ZIP, through the signed link the updates call hands out.
        Route::get('api/license/download/{release}/{activation}', [ApiController::class, 'download'])
            ->middleware([$enabled, 'signed', 'throttle:10,1', SubstituteBindings::class])
            ->name('license.download');

        Route::middleware(['web', $enabled, 'auth', 'role:admin'])->prefix('admin/licenses')->name('admin.licenses.')->group(function () {
            Route::get('/', [LicenseAdminController::class, 'index'])->name('index');
            Route::post('/', [LicenseAdminController::class, 'store'])->name('store');
            Route::put('/{license}', [LicenseAdminController::class, 'update'])->name('update');
            Route::post('/{license}/revoke', [LicenseAdminController::class, 'revoke'])->name('revoke');
            Route::post('/{license}/restore', [LicenseAdminController::class, 'restore'])->name('restore');
            Route::delete('/{license}/activations/{activation}', [LicenseAdminController::class, 'release'])->name('release');
        });

        Route::middleware(['web', $enabled, 'auth', 'role:admin'])->prefix('admin/releases')->name('admin.releases.')->group(function () {
            Route::get('/', [ReleaseAdminController::class, 'index'])->name('index');
            Route::post('/', [ReleaseAdminController::class, 'store'])->name('store');
            Route::put('/{release}', [ReleaseAdminController::class, 'update'])->name('update');
            Route::delete('/{release}', [ReleaseAdminController::class, 'destroy'])->name('destroy');
        });
    }
}
