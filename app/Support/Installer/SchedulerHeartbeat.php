<?php

namespace App\Support\Installer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Records each run of the scheduler, so the admin can be told when the cron
 * job that drives it is missing — otherwise messages and POS receipts would
 * quietly never be sent.
 */
class SchedulerHeartbeat
{
    private const KEY = 'scheduler:last-run';

    /** Minutes without a run before the admin is warned. */
    private const GRACE = 5;

    public function beat(): void
    {
        Cache::forever(self::KEY, now()->getTimestamp());
    }

    public function lastRun(): ?CarbonImmutable
    {
        $timestamp = Cache::get(self::KEY);

        return $timestamp ? CarbonImmutable::createFromTimestamp((int) $timestamp, config('app.timezone')) : null;
    }

    /**
     * Whether to warn: only where a cron job is expected (installed with the
     * web installer), and not in the first minutes after installing.
     */
    public function isMissing(Installation $installation): bool
    {
        if (! config('installer.cron_expected')) {
            return false;
        }

        $last = $this->lastRun();

        if ($last) {
            return $last->lt(now()->subMinutes(self::GRACE));
        }

        $since = $installation->installedAt();

        return $since === null || $since->lt(now()->subMinutes(self::GRACE));
    }
}
