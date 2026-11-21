<?php

namespace App\LicenseServer\Console;

use App\LicenseServer\ReleasePackage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('release:publish {zip : The ZIP made by scripts/package.sh} {--notes= : What changed, shown to customers} {--draft : Upload without offering it to customers yet}')]
#[Description('Upload an update that customers install from Admin → System Update')]
class PublishReleaseCommand extends Command
{
    public function handle(ReleasePackage $package): int
    {
        $zip = (string) $this->argument('zip');

        if (! is_file($zip)) {
            $this->components->error("No file at {$zip}.");

            return self::FAILURE;
        }

        try {
            $release = $package->store($zip, $this->option('notes') ?: null, ! $this->option('draft'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($release->isPublished()
            ? "Version {$release->version} is published. Customers' sites offer it within a day (or when the owner presses “Check for updates”)."
            : "Version {$release->version} is uploaded as a draft. Publish it in Admin → Releases.");

        return self::SUCCESS;
    }
}
