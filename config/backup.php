<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('MYSQL_PATH', 'mysql'),
    'directory' => env('BACKUP_STORAGE_PATH', 'backups'),
    'process_timeout' => env('BACKUP_PROCESS_TIMEOUT', 300),
];
