<?php

namespace App\Providers;

use App\Modules\Academic\Events\CourseCreated;
use App\Modules\Academic\Listeners\LogCourseCreated;
use App\Modules\Assessment\Events\AttemptExpired;
use App\Modules\Assessment\Events\AttemptResumed;
use App\Modules\Assessment\Events\AttemptStarted;
use App\Modules\Assessment\Events\AttemptSubmitted;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Modules\Assessment\Events\TestCreated;
use App\Modules\Assessment\Events\TestPublished;
use App\Modules\Assessment\Listeners\LogAssessmentDeliveryActivity;
use App\Modules\Assessment\Listeners\LogTestActivity;
use App\Modules\QuestionBank\Events\QuestionCreated;
use App\Modules\QuestionBank\Events\QuestionDeleted;
use App\Modules\QuestionBank\Events\QuestionUpdated;
use App\Modules\QuestionBank\Listeners\LogQuestionActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModuleServiceProvider extends ServiceProvider
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
        $this->registerModuleEventListeners();

        $modulesPath = app_path('Modules');

        if (!file_exists($modulesPath)) {
            return;
        }

        $modules = array_filter(glob($modulesPath.'/*'), 'is_dir');

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            $moduleNamespace = 'App\\Modules\\'.$moduleName;

            // 1. Load Routes
            $webRoute = $modulePath.'/Routes/web.php';
            if (file_exists($webRoute)) {
                Route::middleware('web')
                    ->namespace($moduleNamespace.'\\Controllers')
                    ->group($webRoute);
            }

            $apiRoute = $modulePath.'/Routes/api.php';
            if (file_exists($apiRoute)) {
                Route::middleware('api')
                    ->prefix('api')
                    ->namespace($moduleNamespace.'\\Controllers')
                    ->group($apiRoute);
            }

            // 2. Load Views
            $viewsPath = $modulePath.'/Views';
            if (file_exists($viewsPath)) {
                $this->loadViewsFrom($viewsPath, Str::lower($moduleName));
            }

            // 3. Load Migrations
            $migrationsPath = $modulePath.'/Database/Migrations';
            if (file_exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }

    /**
     * Register modular domain event listeners.
     */
    protected function registerModuleEventListeners(): void
    {
        Event::listen(CourseCreated::class, [LogCourseCreated::class, 'handleCourseCreated']);

        Event::listen(QuestionCreated::class, [LogQuestionActivity::class, 'handleQuestionCreated']);
        Event::listen(QuestionUpdated::class, [LogQuestionActivity::class, 'handleQuestionUpdated']);
        Event::listen(QuestionDeleted::class, [LogQuestionActivity::class, 'handleQuestionDeleted']);

        Event::listen(TestCreated::class, [LogTestActivity::class, 'handleTestCreated']);
        Event::listen(TestPublished::class, [LogTestActivity::class, 'handleTestPublished']);

        Event::listen(AttemptStarted::class, [LogAssessmentDeliveryActivity::class, 'handleAttemptStarted']);
        Event::listen(AttemptResumed::class, [LogAssessmentDeliveryActivity::class, 'handleAttemptResumed']);
        Event::listen(AttemptSubmitted::class, [LogAssessmentDeliveryActivity::class, 'handleAttemptSubmitted']);
        Event::listen(AttemptExpired::class, [LogAssessmentDeliveryActivity::class, 'handleAttemptExpired']);
        Event::listen(RuleViolationDetected::class, [LogAssessmentDeliveryActivity::class, 'handleRuleViolationDetected']);
    }
}
