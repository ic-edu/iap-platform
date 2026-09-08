<?php

namespace App\Console\Commands;

use App\Services\DatabaseSafetyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class DatabaseSafetyStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:db:safety-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display comprehensive database safety configuration and protection status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Database Safety Status');
        $this->info('================================================================');

        $env = (string) Config::get('app.env', 'unknown');
        $connection = (string) Config::get('database.default', 'sqlite');
        $resolvedPath = DatabaseSafetyService::resolveActiveDatabasePath($connection);
        $classification = DatabaseSafetyService::classifyDatabase($resolvedPath, $connection);
        $isBlocked = DatabaseSafetyService::isDestructiveBlocked($connection, $resolvedPath);
        $latestSnapshot = DatabaseSafetyService::getLatestSnapshot();

        $rows = [
            ['Environment (APP_ENV)', $env],
            ['Default DB Connection', $connection],
            ['Resolved Database Path', $resolvedPath],
            ['Safety Classification', $classification],
            [
                'Destructive Commands (fresh/wipe/reset)',
                $isBlocked ? 'BLOCKED (ZERO BYPASS) 🔒' : 'ALLOWED (Disposable/Memory only) ⚠️'
            ],
            [
                'Test Harness Isolation Rule',
                ($env === 'testing' && $resolvedPath === ':memory:')
                    ? 'COMPLIANT (testing + :memory:) ✅'
                    : 'ENFORCED (Non-memory/Non-testing will ABORT) 🛡️'
            ],
            [
                'Pre-Migration Snapshot Guard',
                $classification === DatabaseSafetyService::CLASSIFICATION_PROTECTED_UAT
                    ? 'ACTIVE (Automatic Snapshot on migrate) 📸'
                    : 'BYPASS (Non-Protected Target)'
            ],
            [
                'Latest Backup Snapshot',
                $latestSnapshot
                    ? "{$latestSnapshot['basename']} (" . number_format($latestSnapshot['size']) . " bytes, {$latestSnapshot['mtime']})"
                    : 'No snapshots found'
            ],
        ];

        $this->table(['Safety Property', 'Configured Status'], $rows);

        if ($isBlocked) {
            $this->info("Current active database is PROTECTED. Destructive commands (migrate:fresh, db:wipe) are permanently prohibited.");
        } else {
            $this->warn("Current database is classified as {$classification}. Destructive commands permitted strictly for isolated testing.");
        }

        return self::SUCCESS;
    }
}
