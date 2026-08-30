<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillMediaContentHashes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:backfill-content-hashes {--force : Recompute hashes even if content_hash is already set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and backfill SHA-256 content hashes for existing MediaAssets without altering references.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $query = MediaAsset::query();

        if (!$force) {
            $query->whereNull('content_hash');
        }

        $total = $query->count();
        $this->info("Scanning {$total} media asset(s) for SHA-256 hash backfill...");

        $updated = 0;
        $missing = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $query->chunk(50, function ($assets) use (&$updated, &$missing, &$skipped, $progressBar) {
            foreach ($assets as $asset) {
                // Passages with no physical file
                if ($asset->type === 'passage' || empty($asset->path)) {
                    if (!empty($asset->content_text)) {
                        $hash = hash('sha256', $asset->content_text);
                        $asset->updateQuietly(['content_hash' => $hash]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    $progressBar->advance();
                    continue;
                }

                $disk = $asset->disk ?: 'public';
                $fullPath = null;

                if (Storage::disk($disk)->exists($asset->path)) {
                    $fullPath = Storage::disk($disk)->path($asset->path);
                } elseif (file_exists(storage_path('app/public/' . $asset->path))) {
                    $fullPath = storage_path('app/public/' . $asset->path);
                } elseif (file_exists(public_path($asset->path))) {
                    $fullPath = public_path($asset->path);
                }

                if ($fullPath && file_exists($fullPath) && is_readable($fullPath)) {
                    $hash = hash_file('sha256', $fullPath);
                    $asset->updateQuietly(['content_hash' => $hash]);
                    $updated++;
                } else {
                    $missing++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("SHA-256 Backfill Complete:");
        $this->line("  - Total Processed: {$total}");
        $this->line("  - Successfully Updated: {$updated}");
        $this->line("  - Missing / Unreadable Files: {$missing}");
        $this->line("  - Skipped (No text/path): {$skipped}");

        return Command::SUCCESS;
    }
}
