<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StaticSafetyScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:safety-scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan repository code for dangerous destructive database patterns outside approved safety fixtures';

    /**
     * Dangerous patterns to detect.
     *
     * @var array<int, string>
     */
    protected array $patterns = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'Schema::dropAllTables',
    ];

    /**
     * Approved files allowed to mention destructive patterns for testing or safety guards.
     *
     * @var array<int, string>
     */
    protected array $allowlist = [
        'app/Services/DatabaseSafetyService.php',
        'app/Providers/AppServiceProvider.php',
        'app/Console/Commands/StaticSafetyScanCommand.php',
        'app/Console/Commands/DatabaseSafetyStatusCommand.php',
        'tests/Feature/DatabaseSafetyHardeningTest.php',
        'tests/Feature/StaticSafetyScanTest.php',
        'docs/DATABASE_SAFETY_POLICY.md',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Static Safety Scanner');
        $this->info('================================================================');

        $scanDirectories = [
            app_path(),
            database_path('seeders'),
            base_path('routes'),
            base_path('config'),
        ];

        $violations = [];

        foreach ($scanDirectories as $dir) {
            if (!File::isDirectory($dir)) {
                continue;
            }

            $files = File::allFiles($dir);

            foreach ($files as $file) {
                $relativePath = Str::after($file->getPathname(), base_path() . DIRECTORY_SEPARATOR);

                if ($this->isAllowlisted($relativePath)) {
                    continue;
                }

                $content = $file->getContents();
                $lines = explode("\n", $content);

                foreach ($lines as $lineIndex => $line) {
                    // Ignore comment lines
                    $trimmed = trim($line);
                    if (Str::startsWith($trimmed, ['//', '/*', '*', '#'])) {
                        continue;
                    }

                    foreach ($this->patterns as $pattern) {
                        if (Str::contains($line, $pattern)) {
                            // Check for dangerous invocation patterns
                            if (
                                Str::contains($line, ['Artisan::call', '$this->call', 'shell_exec', 'exec', 'system', 'Schema::'])
                            ) {
                                $violations[] = [
                                    'File'    => $relativePath,
                                    'Line'    => $lineIndex + 1,
                                    'Pattern' => $pattern,
                                    'Snippet' => trim(substr($trimmed, 0, 80)),
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (empty($violations)) {
            $this->info("Scan PASSED: Zero unauthorized destructive command invocations detected in application codebase.");
            return self::SUCCESS;
        }

        $this->error("Scan FAILED: " . count($violations) . " dangerous destructive pattern(s) detected!");
        $this->table(['File', 'Line', 'Pattern', 'Snippet'], $violations);
        return self::FAILURE;
    }

    /**
     * Check if a relative path is allowlisted.
     */
    protected function isAllowlisted(string $relativePath): bool
    {
        foreach ($this->allowlist as $allowed) {
            if ($relativePath === $allowed || Str::endsWith($relativePath, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
