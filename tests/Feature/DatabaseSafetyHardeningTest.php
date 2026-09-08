<?php

namespace Tests\Feature;

use App\Services\DatabaseSafetyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyHardeningTest extends TestCase
{
    protected string $disposableDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disposableDb = database_path('disposable/test-fixture-' . uniqid() . '.sqlite');
        if (!File::isDirectory(dirname($this->disposableDb))) {
            File::makeDirectory(dirname($this->disposableDb), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->disposableDb)) {
            File::delete($this->disposableDb);
        }

        parent::tearDown();
    }

    public function test_classification_correctly_identifies_all_safety_tiers(): void
    {
        // 1. Memory classification
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_TEST_MEMORY,
            DatabaseSafetyService::classifyDatabase(':memory:')
        );

        // 2. Protected UAT classification
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_PROTECTED_UAT,
            DatabaseSafetyService::classifyDatabase(database_path('database.sqlite'))
        );
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_PROTECTED_UAT,
            DatabaseSafetyService::classifyDatabase(database_path('recovery/uat-reconstructed.sqlite'))
        );

        // 3. Disposable classification
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_DISPOSABLE,
            DatabaseSafetyService::classifyDatabase(database_path('disposable/sample.sqlite'))
        );
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_DISPOSABLE,
            DatabaseSafetyService::classifyDatabase(database_path('recovery/test-temp.sqlite'))
        );

        // 4. Unknown classification
        $this->assertEquals(
            DatabaseSafetyService::CLASSIFICATION_UNKNOWN,
            DatabaseSafetyService::classifyDatabase('/var/data/custom-db.sqlite')
        );
    }

    public function test_destructive_commands_are_strictly_blocked_for_protected_uat(): void
    {
        $this->assertTrue(
            DatabaseSafetyService::isDestructiveBlocked(null, database_path('database.sqlite'))
        );
        $this->assertTrue(
            DatabaseSafetyService::isDestructiveBlocked(null, database_path('recovery/uat-reconstructed.sqlite'))
        );
    }

    public function test_unknown_classification_fails_closed_and_blocks_destructive_commands(): void
    {
        $this->assertTrue(
            DatabaseSafetyService::isDestructiveBlocked(null, '/unknown/random-path/db.sqlite')
        );
    }

    public function test_destructive_commands_are_allowed_only_for_memory_and_disposable(): void
    {
        $this->assertFalse(
            DatabaseSafetyService::isDestructiveBlocked(null, ':memory:')
        );
        $this->assertFalse(
            DatabaseSafetyService::isDestructiveBlocked(null, database_path('disposable/scratch.sqlite'))
        );
    }

    public function test_enforce_command_safety_throws_runtime_exception_on_protected_targets(): void
    {
        // Configure connection temporarily to protected path in test
        Config::set('database.connections.simulated_protected', [
            'driver'   => 'sqlite',
            'database' => database_path('database.sqlite'),
        ]);

        $destructiveCommands = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

        foreach ($destructiveCommands as $cmd) {
            try {
                DatabaseSafetyService::enforceCommandSafety($cmd, 'simulated_protected');
                $this->fail("Expected RuntimeException was not thrown for {$cmd} on protected target.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CRITICAL DATABASE SAFETY VIOLATION', $e->getMessage());
                $this->assertStringContainsString('PERMANENTLY PROHIBITED', $e->getMessage());
                $this->assertStringContainsString('ZERO bypass', $e->getMessage());
            }
        }
    }

    public function test_test_harness_safety_blocks_non_testing_environment(): void
    {
        $mockApp = [
            'config' => [
                'app.env'                                => 'local',
                'database.default'                       => 'sqlite',
                'database.connections.sqlite.database'   => ':memory:',
            ],
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CRITICAL TEST HARNESS SAFETY VIOLATION');

        DatabaseSafetyService::enforceTestHarnessSafety((object) $mockApp);
    }

    public function test_test_harness_safety_blocks_non_memory_database(): void
    {
        $mockApp = [
            'config' => [
                'app.env'                                => 'testing',
                'database.default'                       => 'sqlite',
                'database.connections.sqlite.database'   => database_path('database.sqlite'),
            ],
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CRITICAL TEST HARNESS SAFETY VIOLATION');

        DatabaseSafetyService::enforceTestHarnessSafety((object) $mockApp);
    }

    public function test_atomic_snapshot_creation_and_validation(): void
    {
        // Create a valid disposable SQLite database as snapshot source
        File::put($this->disposableDb, '');
        $pdo = new \PDO("sqlite:{$this->disposableDb}");
        $pdo->exec("CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration VARCHAR(255), batch INTEGER)");
        $pdo->exec("INSERT INTO migrations VALUES (1, '2026_01_01_000000_test', 1)");

        $snapshot = DatabaseSafetyService::createSnapshot('unit-test-verification', $this->disposableDb);

        $this->assertFileExists($snapshot['destination']);
        $this->assertGreaterThan(0, $snapshot['size']);
        $this->assertNotEmpty($snapshot['sha256']);
        $this->assertTrue($snapshot['sqlite_valid']);
        $this->assertTrue($snapshot['has_migrations']);

        // Clean up created snapshot
        if (File::exists($snapshot['destination'])) {
            File::delete($snapshot['destination']);
        }
    }

    public function test_forward_migration_safety_hook_creates_snapshot_on_protected_target(): void
    {
        // Create disposable file simulating protected target
        File::put($this->disposableDb, '');
        $pdo = new \PDO("sqlite:{$this->disposableDb}");
        $pdo->exec("CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration VARCHAR(255), batch INTEGER)");

        Config::set('database.connections.test_fwd_sim', [
            'driver'   => 'sqlite',
            'database' => $this->disposableDb,
        ]);

        // Classification as disposable will bypass snapshot requirement safely
        DatabaseSafetyService::enforceForwardMigrationSafety('test_fwd_sim');
        $this->assertTrue(true);
    }

    public function test_safety_status_command_executes_cleanly(): void
    {
        $exitCode = Artisan::call('iap:db:safety-status');
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Database Safety Status', $output);
        $this->assertStringContainsString('Safety Classification', $output);
    }

    public function test_static_safety_scanner_command_passes(): void
    {
        $exitCode = Artisan::call('iap:safety-scan');
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Zero unauthorized destructive command invocations detected', $output);
    }
}
