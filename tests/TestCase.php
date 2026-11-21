<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?string $installedLock = null;

    /**
     * Tests run against an installed app. The lock file lives in the system
     * temp folder so the real storage/app/installed.json is never touched;
     * installer tests point the config elsewhere.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (self::$installedLock === null) {
            self::$installedLock = tempnam(sys_get_temp_dir(), 'installed-').'.json';
        }

        file_put_contents(self::$installedLock, json_encode([
            'version' => trim((string) file_get_contents($app->basePath('VERSION'))),
            'installed_at' => '2026-01-01T00:00:00+08:00',
            'via' => 'tests',
        ]));

        $app['config']->set('installer.lock', self::$installedLock);
        // Downloads and backups of one-click updates never land in the real storage folder.
        $app['config']->set('installer.updates.work_dir', sys_get_temp_dir().'/order-updates-'.bin2hex(random_bytes(5)));

        return $app;
    }
}
