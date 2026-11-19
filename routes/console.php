<?php

use Illuminate\Support\Facades\Schedule;

/*
| Run the scheduler with a single cron entry:
| * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('orders:cancel-unpaid')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
