<?php

namespace App\Providers;

use App\Integrations\QuestionGeneration\OpenAIQuestionGenerationProvider;
use App\Modules\QuestionEngine\Contracts\QuestionGenerationProvider;
use App\Modules\QuestionEngine\Providers\FakeGenerationProvider;
use App\Modules\QuestionEngine\Providers\NullGenerationProvider;
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
        $this->app->bind(
            QuestionGenerationProvider::class,
            function ($app) {
                $providerKey = strtolower((string) config('question_generation.provider', 'null'));

                return match ($providerKey) {
                    'openai' => new OpenAIQuestionGenerationProvider(
                        apiKey: config('question_generation.openai.api_key'),
                        model: config('question_generation.openai.model'),
                        baseUrl: config('question_generation.openai.base_url'),
                        timeout: (int) config('question_generation.openai.timeout', 30),
                        connectTimeout: (int) config('question_generation.openai.connect_timeout', 10),
                    ),
                    'fake' => new FakeGenerationProvider,
                    'null' => new NullGenerationProvider,
                    default => new NullGenerationProvider,
                };
            }
        );
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
