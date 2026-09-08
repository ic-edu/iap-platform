<?php

namespace App\Providers;

use App\Services\DatabaseSafetyService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Prohibit destructive database commands natively if database is protected or unknown (NO BYPASS)
        DB::prohibitDestructiveCommands(
            DatabaseSafetyService::isDestructiveBlocked()
        );

        // 2. Intercept command execution dynamically to protect databases and guarantee pre-migration snapshots
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $cmd = $event->command;
            if (!$cmd) {
                return;
            }

            // Destructive command interceptor
            if (DatabaseSafetyService::isDestructiveCommand($cmd)) {
                DatabaseSafetyService::enforceCommandSafety($cmd);
            }

            // Forward migration interceptor (auto-snapshot on protected UAT)
            if ($cmd === 'migrate') {
                DatabaseSafetyService::enforceForwardMigrationSafety();
            }
        });
    }
}
