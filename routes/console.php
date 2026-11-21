<?php

use App\Support\Installer\SchedulerHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Run the scheduler with a single cron entry:
| * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
| Every task runs inside the scheduler's own process (Artisan::call) rather
| than as a separate one: shared hosts often switch off proc_open(), which
| starting a separate process needs.
*/

$artisan = fn (string $command, array $parameters = []) => fn () => Artisan::call($command, $parameters);

// Lets the admin dashboard tell whether the cron job is running.
Schedule::call(fn () => app(SchedulerHeartbeat::class)->beat())->everyMinute()->name('scheduler-heartbeat');

Schedule::call($artisan('orders:cancel-unpaid'))->everyFiveMinutes()->name('orders:cancel-unpaid')->withoutOverlapping();
Schedule::call($artisan('orders:close-stale'))->hourly()->name('orders:close-stale')->withoutOverlapping();
Schedule::call($artisan('queue:prune-failed', ['--hours' => 168]))->daily()->name('queue:prune-failed');
Schedule::call($artisan('license:check'))->dailyAt('03:17')->name('license:check');

// Shared hosting has no long-running queue worker: each minute the scheduler
// works through what is waiting (POS receipts, customer messages) and stops
// before the next run. Last, so it never holds up the tasks above.
Schedule::call($artisan('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3, '--backoff' => 30]))
    ->everyMinute()
    ->name('queue:work')
    ->withoutOverlapping(2)
    ->when(fn () => (bool) config('installer.queue_from_scheduler'));
