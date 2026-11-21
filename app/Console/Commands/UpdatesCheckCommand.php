<?php

namespace App\Console\Commands;

use App\Support\Updates\UpdateServer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('updates:check')]
#[Description('Ask the license server whether a newer version is out (shown in Admin → System Update)')]
class UpdatesCheckCommand extends Command
{
    public function handle(UpdateServer $server): int
    {
        if (! $server->supported()) {
            $this->components->info('Nothing to check: this copy has no activated license.');

            return self::SUCCESS;
        }

        $server->checkQuietly();
        $update = $server->last()['update'];

        $this->components->info($update ? "Version {$update->version} is available." : 'This site is up to date.');

        return self::SUCCESS;
    }
}
