<?php

namespace Tests;

use App\Services\DatabaseSafetyService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     * Enforces test harness safety as soon as the test application is bootstrapped,
     * BEFORE any test lifecycle traits (e.g. RefreshDatabase) can run.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        DatabaseSafetyService::enforceTestHarnessSafety($app);
        return $app;
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        DatabaseSafetyService::enforceTestHarnessSafety($this->app);
    }

    /**
     * Perform safety validation immediately before RefreshDatabase executes.
     */
    protected function beforeRefreshingDatabase()
    {
        DatabaseSafetyService::enforceTestHarnessSafety($this->app);
    }
}
