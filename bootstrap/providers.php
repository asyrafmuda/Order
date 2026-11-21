<?php

use App\LicenseServer\LicenseServerServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // Only in the source repository: the client ZIP leaves app/LicenseServer out.
    ...(class_exists(LicenseServerServiceProvider::class) ? [LicenseServerServiceProvider::class] : []),
];
