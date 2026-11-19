<?php

namespace App\Jobs;

use App\Actions\Pos\SyncPosMenu;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPosMenuJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function handle(SyncPosMenu $sync): void
    {
        $sync->handle();
    }
}
