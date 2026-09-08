<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaticSafetyScanTest extends TestCase
{
    public function test_codebase_contains_no_unauthorized_destructive_database_calls(): void
    {
        $exitCode = Artisan::call('iap:safety-scan');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Zero unauthorized destructive command invocations detected', $output);
    }
}
