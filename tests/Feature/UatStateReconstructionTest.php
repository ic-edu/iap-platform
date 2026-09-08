<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationGroupMember;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UatStateReconstructionTest extends TestCase
{
    protected string $testRecoveryDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRecoveryDb = database_path('recovery/test-recovery-' . uniqid() . '.sqlite');
        if (!File::isDirectory(dirname($this->testRecoveryDb))) {
            File::makeDirectory(dirname($this->testRecoveryDb), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testRecoveryDb)) {
            File::delete($this->testRecoveryDb);
        }

        parent::tearDown();
    }

    public function test_command_strictly_rejects_active_database_target(): void
    {
        $activeDb = database_path('database.sqlite');

        $exitCode = Artisan::call('iap:uat-reconstruct', [
            '--database' => $activeDb,
        ]);

        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('CRITICAL SAFETY VIOLATION', $output);
        $this->assertStringContainsString('Target recovery database cannot be the active application database', $output);
    }

    public function test_command_requires_database_option(): void
    {
        $exitCode = Artisan::call('iap:uat-reconstruct');

        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Error: The --database option is required', $output);
    }

    public function test_command_dry_run_mode_does_not_create_or_modify_database(): void
    {
        $nonExistentPath = database_path('recovery/dry-run-test-' . uniqid() . '.sqlite');

        $exitCode = Artisan::call('iap:uat-reconstruct', [
            '--database' => $nonExistentPath,
            '--dry-run'  => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertFalse(File::exists($nonExistentPath));

        $output = Artisan::output();
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('DRY RUN COMPLETE', $output);
    }

    public function test_command_reconstructs_and_verifies_full_canonical_uat_state(): void
    {
        // 1. Initial reconstruction run
        $exitCode = Artisan::call('iap:uat-reconstruct', [
            '--database' => $this->testRecoveryDb,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($this->testRecoveryDb));
        $output = Artisan::output();
        $this->assertStringContainsString('UAT RECONSTRUCTION COMPLETED SUCCESSFULLY', $output);
        $this->assertStringContainsString('Verification PASSED', $output);

        // 2. Standalone verification run
        $verifyExitCode = Artisan::call('iap:uat-reconstruct', [
            '--database' => $this->testRecoveryDb,
            '--verify'   => true,
        ]);

        $this->assertEquals(0, $verifyExitCode);
        $verifyOutput = Artisan::output();
        $this->assertStringContainsString('Verification PASSED: All canonical assertions matched 100%!', $verifyOutput);

        // 3. Idempotent second reconstruction run
        $idempotentExitCode = Artisan::call('iap:uat-reconstruct', [
            '--database' => $this->testRecoveryDb,
        ]);

        $this->assertEquals(0, $idempotentExitCode);
    }
}
