<?php

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Dumps and restores MySQL through PDO, without shelling out.
 *
 * The normal path runs mysqldump. Locked-down shared hosting routinely disables
 * proc_open at a level the account cannot change, which leaves an application
 * with no way to run any external binary at all. This engine is what makes
 * backups work there: everything is done over the existing database connection.
 *
 * It writes the same gzipped SQL that mysqldump produces closely enough for the
 * two to be interchangeable — a dump taken here restores with the mysql client,
 * and a mysqldump file restores through here. That matters because a server may
 * change hands between the two engines and old backups must stay usable.
 *
 * Scope is tables and their rows. Views, triggers, stored routines and events
 * are not reproduced; a dump refuses rather than pretending, so nobody discovers
 * the gap during a restore.
 */
class PdoBackupEngine
{
    /** Rows to hold in memory at once while building INSERT statements. */
    private const ROWS_PER_INSERT = 200;

    /** Roughly how large a single INSERT statement may grow, in bytes. */
    private const MAX_INSERT_BYTES = 512 * 1024;

    private ?PDO $connection = null;

    public function name(): string
    {
        return 'PHP (PDO)';
    }

    /**
     * A dedicated connection, so the dump's transaction and its unbuffered
     * cursor never interfere with the request's own queries.
     *
     * Unbuffered mode is what keeps memory flat: rows arrive one at a time
     * instead of the whole table landing in PHP before a byte is written.
     */
    private function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $db = config('database.connections.mysql');

        $dsn = ! empty($db['unix_socket'])
            ? "mysql:unix_socket={$db['unix_socket']};dbname={$db['database']}"
            : "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}";

        $dsn .= ';charset='.($db['charset'] ?? 'utf8mb4');

        try {
            $connection = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ]);
        } catch (\PDOException $e) {
            throw new RuntimeException('Database connection check failed: '.$e->getMessage(), previous: $e);
        }

        return $this->connection = $connection;
    }

    public function preflight(): void
    {
        $this->connection()->query('SELECT 1')->fetchAll();
    }

    /**
     * Write a gzipped SQL dump of every base table.
     */
    public function dump(string $destPath): void
    {
        $pdo = $this->connection();
        $this->assertNothingUnsupported($pdo);

        $gz = @gzopen($destPath, 'wb9');

        if ($gz === false) {
            throw new RuntimeException("Unable to open {$destPath} for writing.");
        }

        // REPEATABLE READ across the whole dump gives InnoDB tables a single
        // consistent snapshot, the same guarantee mysqldump --single-transaction
        // provides, without locking anyone out while it runs.
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();

        try {
            gzwrite($gz, $this->header());

            foreach ($this->tables($pdo) as $table) {
                gzwrite($gz, $this->tableSchema($pdo, $table));
                $this->writeTableRows($pdo, $gz, $table);
            }

            gzwrite($gz, $this->footer());
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            gzclose($gz);
            @unlink($destPath);

            throw new RuntimeException('Backup failed: '.$e->getMessage(), previous: $e);
        }

        gzclose($gz);
    }

    /**
     * Execute a gzipped SQL file statement by statement.
     */
    public function restore(string $sourcePath): void
    {
        $pdo = $this->connection();
        $lockWait = (int) config('backup.lock_wait_timeout', 30);
        $deadline = microtime(true) + (int) config('backup.process_timeout', 120);

        // Same reasoning as the mysql-client path: a restore needs an exclusive
        // metadata lock on every table, and MySQL's default lock_wait_timeout is
        // a full year, so one open transaction elsewhere would hang this forever.
        $pdo->exec("SET SESSION lock_wait_timeout = {$lockWait}");
        $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');
        $pdo->exec("SET SESSION SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        try {
            foreach ($this->statements($sourcePath) as $statement) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException(
                        'Restore timed out after '.config('backup.process_timeout').' seconds.'
                    );
                }

                $pdo->exec($statement);
            }
        } catch (\PDOException $e) {
            throw new RuntimeException($this->explainRestoreFailure($e), previous: $e);
        } finally {
            // Best effort: the connection is discarded either way, but leaving
            // checks off would be the wrong state to hand back if it is reused.
            try {
                $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = 1');
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Refuse rather than silently dropping anything this engine cannot carry.
     */
    private function assertNothingUnsupported(PDO $pdo): void
    {
        $schema = config('database.connections.mysql.database');

        $counts = [
            'views' => 'SELECT COUNT(*) FROM information_schema.views WHERE table_schema = ?',
            'triggers' => 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = ?',
            'stored routines' => 'SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = ?',
        ];

        $found = [];

        foreach ($counts as $label => $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute([$schema]);

            if ((int) $statement->fetchColumn() > 0) {
                $found[] = $label;
            }

            $statement->closeCursor();
        }

        if ($found !== []) {
            throw new RuntimeException(
                'This database contains '.implode(' and ', $found).', which the PHP backup engine '
                .'does not reproduce. Backups here would be incomplete, so none was written. '
                .'Enable proc_open so mysqldump can be used, or export this database from outside the application.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $pdo): array
    {
        $excluded = (array) config('backup.excluded_tables', []);
        $tables = [];

        // Base tables only — a view has no rows of its own to carry.
        $result = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        foreach ($result->fetchAll(PDO::FETCH_NUM) as $row) {
            if (! in_array($row[0], $excluded, true)) {
                $tables[] = $row[0];
            }
        }

        sort($tables);

        return $tables;
    }

    private function tableSchema(PDO $pdo, string $table): string
    {
        $quoted = $this->quoteIdentifier($table);
        $row = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);

        return "\n--\n-- Table structure for {$table}\n--\n\n"
            ."DROP TABLE IF EXISTS {$quoted};\n"
            .$row[1].";\n\n";
    }

    /**
     * @param  resource  $gz
     */
    private function writeTableRows(PDO $pdo, $gz, string $table): void
    {
        $quoted = $this->quoteIdentifier($table);
        $statement = $pdo->query("SELECT * FROM {$quoted}");

        $columns = null;
        $buffer = '';
        $rows = 0;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $columns ??= implode(', ', array_map(
                fn (string $column): string => $this->quoteIdentifier($column),
                array_keys($row)
            ));

            $values = '('.implode(', ', array_map(
                fn ($value): string => $this->literal($pdo, $value),
                $row
            )).')';

            $buffer .= $buffer === '' ? "INSERT INTO {$quoted} ({$columns}) VALUES\n{$values}" : ",\n{$values}";
            $rows++;

            if ($rows >= self::ROWS_PER_INSERT || strlen($buffer) >= self::MAX_INSERT_BYTES) {
                gzwrite($gz, $buffer.";\n");
                $buffer = '';
                $rows = 0;
            }
        }

        $statement->closeCursor();

        if ($buffer !== '') {
            gzwrite($gz, $buffer.";\n");
        }

        if ($columns !== null) {
            gzwrite($gz, "\n");
        }
    }

    /**
     * One value, rendered as SQL.
     *
     * Anything that is not valid UTF-8 becomes a hex literal: quoting raw bytes
     * would produce a string the server reads back differently, which is how
     * binary columns quietly corrupt in hand-rolled dumps.
     */
    private function literal(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            return '0x'.bin2hex($value);
        }

        return $pdo->quote($value);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function header(): string
    {
        return "-- Database backup\n"
            .'-- Generated by '.config('app.name')." using the PHP (PDO) engine\n"
            .'-- Date: '.now()->toDateTimeString()."\n\n"
            ."SET NAMES utf8mb4;\n"
            ."SET FOREIGN_KEY_CHECKS = 0;\n"
            ."SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    }

    private function footer(): string
    {
        return "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }

    /**
     * Split a gzipped SQL file into executable statements.
     *
     * Streamed and split as it reads, so a large dump never has to sit in memory
     * whole. Quoting has to be tracked properly because a semicolon inside a
     * string is data, not a statement boundary — the failure mode of a naive
     * explode(';') is a restore that half works.
     *
     * @return \Generator<string>
     */
    private function statements(string $sourcePath): \Generator
    {
        $gz = @gzopen($sourcePath, 'rb');

        if ($gz === false) {
            throw new RuntimeException("Unable to open {$sourcePath} for reading.");
        }

        $current = '';
        $quote = null;       // ' or " while inside a string, ` inside an identifier
        $escaped = false;
        $lineComment = false;
        $blockComment = false;

        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 512 * 1024);

                for ($i = 0, $length = strlen($chunk); $i < $length; $i++) {
                    $char = $chunk[$i];
                    $next = $chunk[$i + 1] ?? '';

                    if ($lineComment) {
                        if ($char === "\n") {
                            $lineComment = false;
                        }

                        continue;
                    }

                    if ($blockComment) {
                        $current .= $char;

                        if ($char === '*' && $next === '/') {
                            $current .= $next;
                            $i++;
                            $blockComment = false;
                        }

                        continue;
                    }

                    if ($quote !== null) {
                        $current .= $char;

                        if ($escaped) {
                            $escaped = false;
                        } elseif ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === $quote) {
                            $quote = null;
                        }

                        continue;
                    }

                    // Outside any string: comments and statement ends matter.
                    if ($char === '-' && $next === '-' && ($chunk[$i + 2] ?? ' ') === ' ') {
                        $lineComment = true;
                        $i++;

                        continue;
                    }

                    if ($char === '#') {
                        $lineComment = true;

                        continue;
                    }

                    if ($char === '/' && $next === '*') {
                        // Kept, not skipped: /*!40101 ... */ carries directives the
                        // server acts on, and dropping them changes the restore.
                        $current .= '/*';
                        $i++;
                        $blockComment = true;

                        continue;
                    }

                    if ($char === "'" || $char === '"' || $char === '`') {
                        $quote = $char;
                        $current .= $char;

                        continue;
                    }

                    if ($char === ';') {
                        if (trim($current) !== '') {
                            yield trim($current);
                        }

                        $current = '';

                        continue;
                    }

                    $current .= $char;
                }
            }
        } finally {
            gzclose($gz);
        }

        if (trim($current) !== '') {
            yield trim($current);
        }
    }

    private function explainRestoreFailure(\PDOException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Lock wait timeout') || str_contains($message, 'metadata lock')) {
            return 'Restore could not get exclusive access to the tables — another session is holding them open. '
                .'Ask other users to leave the app, then try again. ('.$message.')';
        }

        return 'Restore failed: '.$message;
    }
}
