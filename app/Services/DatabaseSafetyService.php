<?php

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use LogicException;

class DatabaseSafetyService
{
    public const CLASSIFICATION_PROTECTED_UAT = 'PROTECTED_UAT';
    public const CLASSIFICATION_TEST_MEMORY    = 'TEST_MEMORY';
    public const CLASSIFICATION_DISPOSABLE     = 'DISPOSABLE';
    public const CLASSIFICATION_UNKNOWN        = 'UNKNOWN';

    /**
     * List of destructive command names that must never run against protected databases.
     *
     * @var array<int, string>
     */
    public const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    /**
     * Get all canonical protected UAT database paths.
     *
     * @return array<int, string>
     */
    public static function getProtectedUatPaths(): array
    {
        $primary = database_path('database.sqlite');
        $reconstructed = database_path('recovery/uat-reconstructed.sqlite');

        return array_unique(array_filter([
            $primary,
            realpath($primary) ?: $primary,
            $reconstructed,
            realpath($reconstructed) ?: $reconstructed,
        ]));
    }

    /**
     * Resolve the active database path for a given connection.
     */
    public static function resolveActiveDatabasePath(?string $connection = null): string
    {
        $conn = $connection ?: Config::get('database.default', 'sqlite');
        $driver = Config::get("database.connections.{$conn}.driver");

        if ($driver === 'sqlite') {
            $db = Config::get("database.connections.{$conn}.database", database_path('database.sqlite'));
            return (string) $db;
        }

        return (string) Config::get("database.connections.{$conn}.database", 'unknown');
    }

    /**
     * Classify a database path or connection into its safety tier.
     */
    public static function classifyDatabase(?string $dbPath = null, ?string $connection = null): string
    {
        $path = $dbPath ?? self::resolveActiveDatabasePath($connection);

        if ($path === ':memory:') {
            return self::CLASSIFICATION_TEST_MEMORY;
        }

        // Normalize path
        $normalizedPath = Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);

        $realPath = realpath($normalizedPath) ?: $normalizedPath;

        // Check if path matches any protected UAT target
        foreach (self::getProtectedUatPaths() as $protected) {
            if ($realPath === $protected || $normalizedPath === $protected) {
                return self::CLASSIFICATION_PROTECTED_UAT;
            }
        }

        // Check if path is within designated disposable test directories
        $disposableDir = database_path('disposable');
        $recoveryTestPrefix = database_path('recovery/test-');

        if (
            Str::startsWith($realPath, $disposableDir) ||
            Str::startsWith($normalizedPath, $disposableDir) ||
            Str::startsWith($realPath, $recoveryTestPrefix) ||
            Str::startsWith($normalizedPath, $recoveryTestPrefix) ||
            Str::contains($normalizedPath, 'test-recovery-') ||
            Str::contains($normalizedPath, 'disposable')
        ) {
            return self::CLASSIFICATION_DISPOSABLE;
        }

        return self::CLASSIFICATION_UNKNOWN;
    }

    /**
     * Check whether destructive commands are blocked for the active database.
     * PROTECTED_UAT and UNKNOWN fail closed (BLOCKED).
     */
    public static function isDestructiveBlocked(?string $connection = null, ?string $dbPath = null): bool
    {
        $classification = self::classifyDatabase($dbPath, $connection);

        if ($classification === self::CLASSIFICATION_TEST_MEMORY) {
            return false;
        }

        if ($classification === self::CLASSIFICATION_DISPOSABLE) {
            return false;
        }

        // PROTECTED_UAT and UNKNOWN are strictly BLOCKED with NO BYPASS
        return true;
    }

    /**
     * Check if a given command name is considered destructive.
     */
    public static function isDestructiveCommand(string $command): bool
    {
        $normalized = strtolower(trim($command));
        foreach (self::DESTRUCTIVE_COMMANDS as $destructive) {
            if ($normalized === $destructive || Str::startsWith($normalized, $destructive . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enforce command safety at invocation time.
     *
     * @throws RuntimeException
     */
    public static function enforceCommandSafety(string $commandName, ?string $connection = null): void
    {
        if (!self::isDestructiveCommand($commandName)) {
            return;
        }

        $path = self::resolveActiveDatabasePath($connection);
        $classification = self::classifyDatabase($path, $connection);

        if (self::isDestructiveBlocked($connection, $path)) {
            throw new RuntimeException(
                "CRITICAL DATABASE SAFETY VIOLATION: Command '{$commandName}' is PERMANENTLY PROHIBITED on database '{$path}' " .
                "(Classification: {$classification}). Destructive schema commands against Protected UAT databases have ZERO bypass."
            );
        }
    }

    /**
     * Enforce test harness isolation safety at the earliest point.
     *
     * @throws LogicException
     */
    public static function enforceTestHarnessSafety(mixed $app = null): void
    {
        if (is_array($app) || $app instanceof \ArrayAccess) {
            $config = $app['config'] ?? null;
            $env = (is_array($config) || $config instanceof \ArrayAccess) ? ($config['app.env'] ?? 'unknown') : ($config?->get('app.env') ?? 'unknown');
            $connection = (is_array($config) || $config instanceof \ArrayAccess) ? ($config['database.default'] ?? 'sqlite') : ($config?->get('database.default') ?? 'sqlite');
            $db = (is_array($config) || $config instanceof \ArrayAccess) ? ($config["database.connections.{$connection}.database"] ?? 'unknown') : ($config?->get("database.connections.{$connection}.database") ?? 'unknown');
        } elseif (is_object($app) && property_exists($app, 'config')) {
            $config = $app->config;
            $env = is_array($config) ? ($config['app.env'] ?? 'unknown') : ($config->{'app.env'} ?? 'unknown');
            $connection = is_array($config) ? ($config['database.default'] ?? 'sqlite') : ($config->{'database.default'} ?? 'sqlite');
            $db = is_array($config) ? ($config["database.connections.{$connection}.database"] ?? 'unknown') : ($config->{"database.connections.{$connection}.database"} ?? 'unknown');
        } elseif (function_exists('app') && app()->has('config')) {
            $env = config('app.env') ?? 'unknown';
            $connection = config('database.default') ?? 'sqlite';
            $db = config("database.connections.{$connection}.database") ?? 'unknown';
        } else {
            $env = getenv('APP_ENV') ?: 'unknown';
            $connection = getenv('DB_CONNECTION') ?: 'sqlite';
            $db = getenv('DB_DATABASE') ?: 'unknown';
        }

        if ($env !== 'testing' || $db !== ':memory:') {
            throw new LogicException(
                "CRITICAL TEST HARNESS SAFETY VIOLATION: Automated tests must run with APP_ENV=testing and DB_DATABASE=:memory:. " .
                "Current state -> APP_ENV: '{$env}', DB_CONNECTION: '{$connection}', DB_DATABASE: '{$db}'. " .
                "Pre-RefreshDatabase safety check aborted execution before any database mutation."
            );
        }
    }

    /**
     * Create an atomic, verified SQLite snapshot.
     *
     * @return array{source: string, destination: string, size: int, sha256: string, sqlite_valid: bool, has_migrations: bool, created_at: string}
     */
    public static function createSnapshot(string $label = 'manual', ?string $sourcePath = null): array
    {
        $source = $sourcePath ?: self::resolveActiveDatabasePath();
        $sourceResolved = realpath($source) ?: $source;

        if (!File::exists($sourceResolved)) {
            throw new RuntimeException("Snapshot failed: Source database file does not exist at '{$sourceResolved}'.");
        }

        $backupDir = database_path('backups');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $sanitizedLabel = Str::slug($label) ?: 'manual';
        $timestamp = now()->format('Ymd-His');
        $destPath = $backupDir . DIRECTORY_SEPARATOR . "snapshot-{$timestamp}-{$sanitizedLabel}.sqlite";

        // Perform atomic snapshot via SQLite VACUUM INTO if supported, or transaction-safe copy
        try {
            $pdo = new PDO("sqlite:{$sourceResolved}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("VACUUM INTO " . $pdo->quote($destPath));
        } catch (\Throwable $e) {
            // Fallback to direct copy
            File::copy($sourceResolved, $destPath);
        }

        if (!File::exists($destPath) || filesize($destPath) === 0) {
            throw new RuntimeException("Snapshot failed: Output file was not created or is empty at '{$destPath}'.");
        }

        // Verify SQLite integrity of destination snapshot
        $verifyPdo = new PDO("sqlite:{$destPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $integrityResult = $verifyPdo->query("PRAGMA integrity_check")->fetchColumn();
        $sqliteValid = ($integrityResult === 'ok');

        $hasMigrations = (bool) $verifyPdo->query(
            "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='migrations'"
        )->fetchColumn();

        if (!$sqliteValid) {
            throw new RuntimeException("Snapshot verification failed: SQLite integrity check returned '{$integrityResult}'.");
        }

        return [
            'source'         => $sourceResolved,
            'destination'    => $destPath,
            'size'           => filesize($destPath),
            'sha256'         => hash_file('sha256', $destPath),
            'sqlite_valid'   => $sqliteValid,
            'has_migrations' => $hasMigrations,
            'created_at'     => now()->toIso8601String(),
        ];
    }

    /**
     * Get details of the most recent database snapshot.
     */
    public static function getLatestSnapshot(): ?array
    {
        $backupDir = database_path('backups');
        if (!File::isDirectory($backupDir)) {
            return null;
        }

        $files = File::glob($backupDir . DIRECTORY_SEPARATOR . '*.sqlite');
        if (empty($files)) {
            return null;
        }

        // Sort files by mtime descending
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];

        try {
            $pdo = new PDO("sqlite:{$latest}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
            $sqliteValid = ($integrity === 'ok');
            $hasMigrations = (bool) $pdo->query(
                "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='migrations'"
            )->fetchColumn();
        } catch (\Throwable) {
            $sqliteValid = false;
            $hasMigrations = false;
        }

        return [
            'file'           => $latest,
            'basename'       => basename($latest),
            'size'           => filesize($latest),
            'sha256'         => hash_file('sha256', $latest),
            'mtime'          => date('Y-m-d H:i:s', filemtime($latest)),
            'sqlite_valid'   => $sqliteValid,
            'has_migrations' => $hasMigrations,
        ];
    }

    /**
     * Enforce automatic verified snapshot before forward migration on protected databases.
     */
    public static function enforceForwardMigrationSafety(?string $connection = null): void
    {
        $path = self::resolveActiveDatabasePath($connection);
        $classification = self::classifyDatabase($path, $connection);

        // In-memory testing does not require snapshot
        if ($classification === self::CLASSIFICATION_TEST_MEMORY) {
            return;
        }

        if ($classification === self::CLASSIFICATION_PROTECTED_UAT) {
            try {
                $snapshot = self::createSnapshot('pre-forward-migration', $path);
                if (!$snapshot['sqlite_valid']) {
                    throw new RuntimeException("Pre-migration snapshot failed integrity validation.");
                }
            } catch (\Throwable $e) {
                throw new RuntimeException("CRITICAL FORWARD MIGRATION ABORTED: Failed to create required pre-migration snapshot: " . $e->getMessage());
            }
        }
    }
}
