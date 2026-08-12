<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('MYSQL_PATH', 'mysql'),
    'directory' => env('BACKUP_STORAGE_PATH', 'backups'),
    'process_timeout' => env('BACKUP_PROCESS_TIMEOUT', 300),

    /*
     * Runtime tables deliberately left out of backups.
     *
     * These hold transient state, not business data. Restoring `sessions` in
     * particular swaps the session store out from under the request doing the
     * restore, so the page's CSRF token stops matching and the next action is
     * rejected with a 419 — which looks exactly like "restore is broken".
     */
    'excluded_tables' => [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ],
];
