<?php

namespace App\Providers;

use App\Listeners\ActivateEnrollmentOnPayment;
use App\Listeners\LogCommerceActivity;
use App\Listeners\LogPlatformOperationsActivity;
use App\Listeners\SendCommercePaymentNotifications;
use App\Modules\Academic\Events\CourseCreated;
use App\Modules\Academic\Events\EnrollmentCancelled;
use App\Modules\Academic\Events\EnrollmentCreated;
use App\Modules\Academic\Listeners\LogCourseCreated;
use App\Modules\Assessment\Events\AssignmentRevoked;
use App\Modules\Assessment\Events\AttemptExpired;
use App\Modules\Assessment\Events\AttemptResumed;
use App\Modules\Assessment\Events\AttemptStarted;
use App\Modules\Assessment\Events\AttemptSubmitted;
use App\Modules\Assessment\Events\ReminderSent;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Modules\Assessment\Events\TestAssigned;
use App\Modules\Assessment\Events\TestCreated;
use App\Modules\Assessment\Events\TestPublished;
use App\Modules\Assessment\Listeners\LogAssessmentDeliveryActivity;
use App\Modules\Assessment\Listeners\LogTestActivity;
use App\Modules\Certificate\Events\CertificateIssued;
use App\Modules\Certificate\Events\CertificateReissued;
use App\Modules\Certificate\Events\CertificateRevoked;
use App\Modules\Certificate\Listeners\LogCertificateActivity;
use App\Modules\Commerce\Events\CheckoutCompleted;
use App\Modules\Commerce\Events\InvoiceGenerated;
use App\Modules\Commerce\Events\PaymentCancelled;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Commerce\Events\PaymentRefunded;
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

            // 2. Load Views with Multi-Convention Namespace Registration
            $viewsPath = $modulePath.'/Views';
            if (file_exists($viewsPath)) {
                $snakeName = Str::snake($moduleName);
                $kebabName = Str::kebab($moduleName);
                $lowerName = Str::lower($moduleName);

                $this->loadViewsFrom($viewsPath, $snakeName);

                if ($kebabName !== $snakeName) {
                    $this->loadViewsFrom($viewsPath, $kebabName);
                }

                if ($lowerName !== $snakeName && $lowerName !== $kebabName) {
                    $this->loadViewsFrom($viewsPath, $lowerName);
                }
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

        Event::listen(CertificateIssued::class, [LogCertificateActivity::class, 'handleCertificateIssued']);
        Event::listen(CertificateReissued::class, [LogCertificateActivity::class, 'handleCertificateReissued']);
        Event::listen(CertificateRevoked::class, [LogCertificateActivity::class, 'handleCertificateRevoked']);

        Event::listen(EnrollmentCreated::class, [LogPlatformOperationsActivity::class, 'handleEnrollmentCreated']);
        Event::listen(EnrollmentCancelled::class, [LogPlatformOperationsActivity::class, 'handleEnrollmentCancelled']);
        Event::listen(TestAssigned::class, [LogPlatformOperationsActivity::class, 'handleTestAssigned']);
        Event::listen(AssignmentRevoked::class, [LogPlatformOperationsActivity::class, 'handleAssignmentRevoked']);
        Event::listen(ReminderSent::class, [LogPlatformOperationsActivity::class, 'handleReminderSent']);

        // Commerce Listeners
        Event::listen(PaymentConfirmed::class, [ActivateEnrollmentOnPayment::class, 'handle']);
        Event::listen(PaymentConfirmed::class, [\App\Modules\Organization\Listeners\ProvisionOrganizationEntitlementsOnPayment::class, 'handle']);
        Event::listen(PaymentCreated::class, [SendCommercePaymentNotifications::class, 'handlePaymentCreated']);
        Event::listen(PaymentConfirmed::class, [SendCommercePaymentNotifications::class, 'handlePaymentConfirmed']);
        Event::listen(PaymentCancelled::class, [SendCommercePaymentNotifications::class, 'handlePaymentCancelled']);
        Event::listen(CheckoutCompleted::class, [LogCommerceActivity::class, 'handleCheckoutCompleted']);
        Event::listen(InvoiceGenerated::class, [LogCommerceActivity::class, 'handleInvoiceGenerated']);
        Event::listen(PaymentCreated::class, [LogCommerceActivity::class, 'handlePaymentCreated']);
        Event::listen(PaymentConfirmed::class, [LogCommerceActivity::class, 'handlePaymentConfirmed']);
        Event::listen(PaymentCancelled::class, [LogCommerceActivity::class, 'handlePaymentCancelled']);
        Event::listen(PaymentRefunded::class, [LogCommerceActivity::class, 'handlePaymentRefunded']);
    }
}
