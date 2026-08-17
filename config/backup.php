<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('MYSQL_PATH', 'mysql'),
    'directory' => env('BACKUP_STORAGE_PATH', 'backups'),
    'process_timeout' => env('BACKUP_PROCESS_TIMEOUT', 120),

    /*
     * Seconds the CLI client waits for a TCP connection before giving up.
     *
     * Without this the client falls back to the kernel's TCP retry budget —
     * roughly two minutes of silence per attempt — so an unreachable or
     * firewalled database host stalls a restore for minutes with no output.
     * Applied to the mysql client via the option file, and enforced for
     * mysqldump (which has no equivalent flag) by the preflight probe.
     */
    'connect_timeout' => env('BACKUP_CONNECT_TIMEOUT', 10),

    /*
     * Seconds a restore waits for an exclusive table lock before giving up.
     *
     * Restoring drops and recreates every table, which needs a metadata lock.
     * MySQL's own lock_wait_timeout default is 31536000 (one year), so another
     * session holding an open transaction would stall the restore silently and
     * indefinitely. Bounded here so it reports the conflict instead.
     */
    'lock_wait_timeout' => env('BACKUP_LOCK_WAIT_TIMEOUT', 30),

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
