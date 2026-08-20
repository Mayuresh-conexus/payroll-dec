<?php

namespace Tests\Feature;

use App\Services\PdoBackupEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the no-shell backup engine against a real MySQL server.
 *
 * The suite runs on sqlite, so these are skipped unless a MySQL connection is
 * configured and reachable. They are worth having anyway: this engine exists
 * for hosts where mysqldump can never run, so a round trip through real MySQL
 * is the only thing that actually proves it.
 *
 * Everything happens in a scratch table this test creates and drops, so a
 * developer's working database is never dumped or restored over.
 */
class PdoBackupEngineTest extends TestCase
{
    private const TABLE = 'pdo_backup_engine_fixture';

    /**
     * A scratch schema of its own. The suite runs on sqlite, so the mysql
     * connection has no usable database name here — and pointing these at the
     * developer's real one would mean dumping and restoring over live work.
     */
    private const SCHEMA = 'payroll_pdo_backup_test';

    private ?string $dumpPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql');
        $dsn = ! empty($db['unix_socket'])
            ? "mysql:unix_socket={$db['unix_socket']}"
            : "mysql:host={$db['host']};port={$db['port']}";

        try {
            $server = new \PDO($dsn, $db['username'], $db['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $server->exec('CREATE DATABASE IF NOT EXISTS '.self::SCHEMA.' CHARACTER SET utf8mb4');
        } catch (\Throwable $e) {
            $this->markTestSkipped('No reachable MySQL server: '.$e->getMessage());
        }

        config()->set('database.connections.mysql.database', self::SCHEMA);
        DB::purge('mysql');

        $this->createFixtureTable();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('mysql')->statement('DROP DATABASE IF EXISTS '.self::SCHEMA);
        } catch (\Throwable) {
        }

        DB::purge('mysql');

        if ($this->dumpPath && file_exists($this->dumpPath)) {
            @unlink($this->dumpPath);
        }

        parent::tearDown();
    }

    private function createFixtureTable(): void
    {
        $connection = DB::connection('mysql');
        $connection->statement('DROP TABLE IF EXISTS '.self::TABLE);
        $connection->statement('CREATE TABLE '.self::TABLE.' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            note TEXT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payload BLOB NULL,
            created_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function insertFixtureRows(): void
    {
        DB::connection('mysql')->table(self::TABLE)->insert([
            // Deliberately awkward: quotes, a semicolon, a backslash and a newline
            // are all things a naive dumper or statement splitter gets wrong.
            ['name' => "O'Brien; DROP TABLE users;--", 'note' => "line one\nline two", 'amount' => 1234.56, 'payload' => null, 'created_at' => '2026-08-17 10:00:00'],
            ['name' => 'Back\\slash and "quotes"', 'note' => null, 'amount' => 0, 'payload' => null, 'created_at' => null],
            ['name' => 'Unicode — café · 日本語', 'note' => 'accents held', 'amount' => -99.99, 'payload' => null, 'created_at' => '2026-01-01 00:00:00'],
            ['name' => 'binary', 'note' => null, 'amount' => 7.5, 'payload' => "\x00\x01\x02\xff\xfe", 'created_at' => null],
        ]);
    }

    private function dumpToFile(): string
    {
        $this->dumpPath = tempnam(sys_get_temp_dir(), 'pdo_dump_').'.sql.gz';
        app(PdoBackupEngine::class)->dump($this->dumpPath);

        return $this->dumpPath;
    }

    private function fixtureRows(): array
    {
        return DB::connection('mysql')->table(self::TABLE)->orderBy('id')->get()->map(
            fn ($row): array => (array) $row
        )->all();
    }

    public function test_a_dump_and_restore_round_trip_returns_every_row_unchanged(): void
    {
        $this->insertFixtureRows();
        $before = $this->fixtureRows();

        $path = $this->dumpToFile();

        // Wreck the table thoroughly: rows changed, rows removed, rows added.
        DB::connection('mysql')->table(self::TABLE)->delete();
        DB::connection('mysql')->table(self::TABLE)->insert(['name' => 'should not survive', 'amount' => 0]);

        app(PdoBackupEngine::class)->restore($path);

        $this->assertEquals($before, $this->fixtureRows(), 'the restored table does not match what was dumped');
    }

    public function test_quotes_semicolons_and_newlines_survive_the_round_trip(): void
    {
        // The statement splitter must not treat a semicolon inside a string as a
        // statement boundary — that is the failure mode of explode(';').
        $this->insertFixtureRows();
        $path = $this->dumpToFile();

        DB::connection('mysql')->table(self::TABLE)->delete();
        app(PdoBackupEngine::class)->restore($path);

        $names = DB::connection('mysql')->table(self::TABLE)->orderBy('id')->pluck('name')->all();

        $this->assertContains("O'Brien; DROP TABLE users;--", $names);
        $this->assertContains('Back\\slash and "quotes"', $names);
        $this->assertContains('Unicode — café · 日本語', $names);
    }

    public function test_binary_data_survives_the_round_trip(): void
    {
        // Raw bytes are hex-encoded rather than quoted, because quoting them
        // produces a string the server reads back differently.
        $this->insertFixtureRows();
        $path = $this->dumpToFile();

        DB::connection('mysql')->table(self::TABLE)->delete();
        app(PdoBackupEngine::class)->restore($path);

        $payload = DB::connection('mysql')->table(self::TABLE)->where('name', 'binary')->value('payload');

        $this->assertSame("\x00\x01\x02\xff\xfe", $payload);
    }

    public function test_an_empty_table_round_trips_without_losing_its_schema(): void
    {
        $path = $this->dumpToFile();

        DB::connection('mysql')->statement('DROP TABLE '.self::TABLE);
        app(PdoBackupEngine::class)->restore($path);

        $this->assertTrue(
            DB::connection('mysql')->getSchemaBuilder()->hasTable(self::TABLE),
            'the table was not recreated'
        );
        $this->assertSame(0, DB::connection('mysql')->table(self::TABLE)->count());
    }

    public function test_the_dump_is_gzipped_sql_that_declares_its_engine(): void
    {
        $this->insertFixtureRows();
        $sql = gzdecode(file_get_contents($this->dumpToFile()));

        $this->assertStringContainsString('PHP (PDO) engine', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `'.self::TABLE.'`', $sql);
        $this->assertStringContainsString('CREATE TABLE `'.self::TABLE.'`', $sql);
        $this->assertStringContainsString('INSERT INTO `'.self::TABLE.'`', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0', $sql);
    }

    public function test_runtime_tables_are_left_out_of_the_dump(): void
    {
        // Same property the mysqldump path has: because they are absent from the
        // dump, a restore leaves them alone and the admin's session survives it.
        config()->set('backup.excluded_tables', ['sessions', 'cache', self::TABLE]);

        $sql = gzdecode(file_get_contents($this->dumpToFile()));

        $this->assertStringNotContainsString('`'.self::TABLE.'`', $sql);
        $this->assertStringNotContainsString('DROP TABLE IF EXISTS `sessions`', $sql);
    }

    public function test_it_refuses_to_dump_a_schema_it_cannot_carry(): void
    {
        // Views, triggers and routines are not reproduced, so a dump says so
        // rather than writing a file that looks complete and is not.
        $connection = DB::connection('mysql');
        $connection->statement('CREATE OR REPLACE VIEW pdo_backup_engine_view AS SELECT id FROM '.self::TABLE);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/views.*does not reproduce/s');

            app(PdoBackupEngine::class)->dump(tempnam(sys_get_temp_dir(), 'pdo_dump_').'.sql.gz');
        } finally {
            $connection->statement('DROP VIEW IF EXISTS pdo_backup_engine_view');
        }
    }
}
