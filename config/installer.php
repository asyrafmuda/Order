<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web installer
    |--------------------------------------------------------------------------
    |
    | The app ships as a ZIP for shared hosting: upload, open the site, and the
    | installer at /install sets it up. Once done it writes the lock file below
    | and the installer is closed for good. Uploading a newer version later
    | makes /install/update run the database changes it brings.
    |
    */

    // Written when the installation finishes. Holds the installed version.
    'lock' => storage_path('app/installed.json'),

    // The version of the code on disk. Each release bumps it.
    'version_file' => base_path('VERSION'),

    // Treat an existing working setup (a database with this app's tables) as
    // installed, so sites set up from the command line never see the installer.
    'adopt_existing' => true,

    'php' => '8.3.0',

    // PHP extensions the app cannot run without.
    'extensions' => ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'ctype', 'json', 'dom', 'xml', 'curl', 'fileinfo', 'filter', 'session'],

    // Nice to have: the app works without them, with a small loss.
    'recommended_extensions' => [
        'gd' => 'Images in PDF invoices',
        'zip' => 'Faster uploads of future updates',
        'intl' => 'Better formatting of names and dates',
    ],

    // Folders the app writes to, relative to the app folder.
    'writable' => ['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'],

    // Oldest database servers the migrations are written for.
    'mysql' => '5.7.8',
    'mariadb' => '10.3.0',

    // Set by the installer on shared hosting: a cron job runs the scheduler,
    // which also works through queued jobs (there is no queue worker process).
    'cron_expected' => (bool) env('SCHEDULER_CRON_EXPECTED', false),
    'queue_from_scheduler' => (bool) env('QUEUE_WORK_FROM_SCHEDULER', false),

    // One-click updates (Admin → System Update): the folder whose files a
    // release replaces, where downloads and backups of replaced files are
    // kept, and how many backups to keep.
    'updates' => [
        'root' => base_path(),
        'work_dir' => storage_path('app/updates'),
        'keep_backups' => 3,
    ],

];
