<?php

namespace App\Console\Commands;

use App\Actions\Pos\SyncPosMenu;
use App\Exceptions\PosException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pos:sync-menu')]
#[Description('Link local products to items in the POS catalogue')]
class SyncPosMenuCommand extends Command
{
    public function handle(SyncPosMenu $sync): int
    {
        try {
            $result = $sync->handle();
        } catch (PosException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Linked {$result['linked']} menu items to the POS.");

        if ($result['unmatched'] !== []) {
            $this->components->warn('Not found in the POS catalogue:');
            $this->components->bulletList($result['unmatched']);
        }

        return self::SUCCESS;
    }
}
