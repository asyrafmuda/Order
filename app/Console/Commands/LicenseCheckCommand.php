<?php

namespace App\Console\Commands;

use App\Support\Licensing\License;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:check')]
#[Description('Fetch a fresh license certificate from the license server')]
class LicenseCheckCommand extends Command
{
    public function handle(License $license): int
    {
        if (! $license->configured() || $license->certificate() === null) {
            $this->components->info('Nothing to check: licensing is off or this copy is not activated.');

            return self::SUCCESS;
        }

        if ($license->refreshQuietly()) {
            $this->components->info('License confirmed.');

            return self::SUCCESS;
        }

        $this->components->warn('The license could not be confirmed. See the log for details.');

        return self::FAILURE;
    }
}
