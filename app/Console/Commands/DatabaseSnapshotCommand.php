<?php

namespace App\Console\Commands;

use App\Services\DatabaseSafetyService;
use Illuminate\Console\Command;

class DatabaseSnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:db:snapshot
                            {--label=manual : Optional label for the snapshot (e.g. pre-migration, uat-baseline)}
                            {--database= : Specific database file path to snapshot (defaults to active database)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely create and verify an atomic SQLite snapshot in database/backups/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Atomic Database Snapshot');
        $this->info('================================================================');

        $label = (string) ($this->option('label') ?: 'manual');
        $dbPath = $this->option('database') ? (string) $this->option('database') : null;

        try {
            $snapshot = DatabaseSafetyService::createSnapshot($label, $dbPath);

            $this->info("Snapshot successfully created and verified!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Source Database', $snapshot['source']],
                    ['Snapshot File', $snapshot['destination']],
                    ['File Size', number_format($snapshot['size']) . ' bytes (' . round($snapshot['size'] / 1024, 2) . ' KB)'],
                    ['SHA-256 Checksum', $snapshot['sha256']],
                    ['SQLite PRAGMA Check', $snapshot['sqlite_valid'] ? 'VALID (ok) ✅' : 'INVALID ❌'],
                    ['Migrations Table', $snapshot['has_migrations'] ? 'PRESENT ✅' : 'NOT FOUND ⚠️'],
                    ['Created At', $snapshot['created_at']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Snapshot creation failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
