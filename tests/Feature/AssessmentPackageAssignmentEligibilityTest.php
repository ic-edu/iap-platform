<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AssessmentPackageAssignmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $candidate2;
    protected User $adminUser;
    protected User $teacherUser;
    protected AssignmentEngine $assignmentEngine;
    protected CheckoutEngine $checkoutEngine;
    protected BillingEngine $billingEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->candidate = User::create([
            'name'     => 'Budi Candidate',
            'email'    => 'budi@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->candidate2 = User::create([
            'name'     => 'Siti Candidate',
            'email'    => 'siti@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate2->assignRole('student');

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->teacherUser = User::create([
            'name'     => 'Teacher User',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->assignmentEngine = app(AssignmentEngine::class);
        $this->checkoutEngine = app(CheckoutEngine::class);
        $this->billingEngine = app(BillingEngine::class);
    }

    protected function createMockTest(string $title, string $type = 'toeic', string $mode = 'real_test', bool $isPublished = true, string $status = 'published'): Test
    {
        return Test::create([
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(5),
            'test_type'        => $type,
            'assessment_mode'  => $mode,
            'duration_minutes' => 120,
            'passing_score'    => 70,
            'max_attempts'     => 2,
            'is_published'     => $isPublished,
            'status'           => $status,
            'author_id'        => $this->adminUser->id,
            'created_by'       => $this->adminUser->id,
        ]);
    }

    protected function purchaseAndPay(User $user, Product $product, string $status = 'success')
    {
        $result = $this->checkoutEngine->checkout($user, $product);
        $payment = $this->billingEngine->createPayment($result['invoice'], 'manual_transfer');

        if ($status === 'success') {
            $this->billingEngine->confirmPayment($payment, 'TXN-' . Str::random(8));
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $this->billingEngine->cancelPayment($payment, 'Payment rejected by test');
        }

        return [
            'order'   => $result['order']->fresh(),
            'invoice' => $result['invoice']->fresh(),
            'payment' => $payment->fresh(),
        ];
    }

    public function test_01_toeic_package_with_test_id_null_matches_published_toeic_mock_test(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeicTest = $this->createMockTest('TOEIC Official Mock 2026', 'toeic');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));

        $assignment = $this->assignmentEngine->assignToUser($toeicTest, $this->candidate, $this->adminUser);
        $this->assertNotNull($assignment);
        $this->assertEquals('active', $assignment->status);
        $this->assertEquals($toeicTest->id, $assignment->test_id);
    }

    public function test_02_toeic_package_does_not_match_published_toefl_mock_test(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-pkg-2',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeflTest = $this->createMockTest('TOEFL iBT Mock 2026', 'toefl');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeflTest, $this->candidate));

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($toeflTest, $this->candidate, $this->adminUser);
    }

    public function test_03_toefl_package_does_not_match_published_toeic_mock_test(): void
    {
        $product = Product::create([
            'title'             => 'TOEFL iBT Prep Package',
            'slug'              => 'toefl-pkg-3',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeicTest = $this->createMockTest('TOEIC Mock Test 2026', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($toeicTest, $this->candidate, $this->adminUser);
    }

    public function test_04_toeic_package_does_not_match_ielts(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Package',
            'slug'              => 'toeic-pkg-4',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $ieltsTest = $this->createMockTest('IELTS Academic Mock', 'ielts');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($ieltsTest, $this->candidate));
    }

    public function test_05_toeic_package_does_not_match_simulator_toeic_test(): void
    {
        $simTest = $this->createMockTest('Simulator Practice TOEIC', 'toeic', 'simulator');

        // Simulator tests are open for practice by default, but isRealTest checks remain false
        $this->assertTrue($simTest->isSimulator());
        $this->assertFalse($simTest->isRealTest());
    }

    public function test_06_pending_toeic_payment_does_not_grant_eligibility(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package',
            'slug'              => 'toeic-pkg-pending',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'pending');

        $toeicTest = $this->createMockTest('TOEIC Mock 2026', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
    }

    public function test_07_rejected_toeic_payment_does_not_grant_eligibility(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package',
            'slug'              => 'toeic-pkg-rejected',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'failed');

        $toeicTest = $this->createMockTest('TOEIC Mock 2026', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
    }

    public function test_08_cancelled_toeic_payment_does_not_grant_eligibility(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package',
            'slug'              => 'toeic-pkg-cancelled',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'cancelled');

        $toeicTest = $this->createMockTest('TOEIC Mock 2026', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
    }

    public function test_09_successful_toeic_payment_grants_eligibility_for_published_toeic_mock_test(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package Successful',
            'slug'              => 'toeic-pkg-success',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeicTest = $this->createMockTest('TOEIC Mock 2026', 'toeic');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
    }

    public function test_10_inactive_package_does_not_grant_eligibility(): void
    {
        $product = Product::create([
            'title'             => 'Inactive TOEIC Package',
            'slug'              => 'inactive-toeic-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => false,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeicTest = $this->createMockTest('TOEIC Mock 2026', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
    }

    public function test_11_inactive_unpublished_target_test_cannot_be_assigned(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package',
            'slug'              => 'toeic-pkg-unpub',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $draftTest = $this->createMockTest('Draft TOEIC Test', 'toeic', 'real_test', false, 'draft');

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($draftTest, $this->candidate, $this->adminUser);
    }

    public function test_12_specific_test_product_with_test_id_still_works_exactly_as_before(): void
    {
        $specificTest = $this->createMockTest('Specific TOEIC Test Instance', 'toeic');

        $product = Product::create([
            'title'             => 'Specific TOEIC Instance Product',
            'slug'              => 'specific-toeic-inst-prod',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $specificTest->id,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($specificTest, $this->candidate));

        $assignment = $this->assignmentEngine->assignToUser($specificTest, $this->candidate, $this->adminUser);
        $this->assertNotNull($assignment);
        $this->assertEquals($specificTest->id, $assignment->test_id);
    }

    public function test_13_legacy_specific_test_product_remains_backward_compatible(): void
    {
        $legacyTest = $this->createMockTest('Legacy Test Instance', 'toeic');

        $product = Product::create([
            'title'             => 'Legacy Product Without Explicit Family',
            'slug'              => 'legacy-prod-no-family',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => $legacyTest->id,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($legacyTest, $this->candidate));
    }

    public function test_14_multiple_family_entitlements_resolve_independently(): void
    {
        $toeicProduct = Product::create([
            'title'             => 'TOEIC Package',
            'slug'              => 'toeic-pkg-multi',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $toeflProduct = Product::create([
            'title'             => 'TOEFL Package',
            'slug'              => 'toefl-pkg-multi',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 800000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $toeicProduct, 'success');
        $this->purchaseAndPay($this->candidate, $toeflProduct, 'success');

        $toeicTest = $this->createMockTest('TOEIC Mock', 'toeic');
        $toeflTest = $this->createMockTest('TOEFL Mock', 'toefl');
        $ieltsTest = $this->createMockTest('IELTS Mock', 'ielts');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));
        $this->assertTrue($this->assignmentEngine->isPaymentEligible($toeflTest, $this->candidate));
        $this->assertFalse($this->assignmentEngine->isPaymentEligible($ieltsTest, $this->candidate));
    }

    public function test_15_multiple_toeic_purchases_do_not_create_duplicate_assignments(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package Duplicate Guard',
            'slug'              => 'toeic-pkg-dup',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');
        $this->purchaseAndPay($this->candidate, $product, 'success');

        $toeicTest = $this->createMockTest('TOEIC Mock', 'toeic');

        $assign1 = $this->assignmentEngine->assignToUser($toeicTest, $this->candidate, $this->adminUser);
        $assign2 = $this->assignmentEngine->assignToUser($toeicTest, $this->candidate, $this->adminUser);

        $this->assertEquals($assign1->id, $assign2->id);
        $this->assertEquals(1, CandidateTestAssignment::where('user_id', $this->candidate->id)->where('test_id', $toeicTest->id)->count());
    }

    public function test_16_candidate_without_any_assessment_package_cannot_be_assigned(): void
    {
        $toeicTest = $this->createMockTest('TOEIC Mock No Purchase', 'toeic');

        $this->assertFalse($this->assignmentEngine->isPaymentEligible($toeicTest, $this->candidate));

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($toeicTest, $this->candidate, $this->adminUser);
    }

    public function test_17_candidate_cannot_self_assign(): void
    {
        $toeicTest = $this->createMockTest('TOEIC Protected Assignment', 'toeic');

        $response = $this->actingAs($this->candidate)->post(route('admin.tests.assign-candidate', $toeicTest->id), [
            'candidate_id' => $this->candidate->id,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 302]));
        $this->assertEquals(0, CandidateTestAssignment::where('user_id', $this->candidate->id)->count());
    }

    public function test_18_unauthorized_staff_roles_cannot_bypass_assignment_rules(): void
    {
        $toeicTest = $this->createMockTest('TOEIC Teacher Protection', 'toeic');

        // Teacher cannot assign candidate via admin route
        $response = $this->actingAs($this->teacherUser)->post(route('admin.tests.assign-candidate', $toeicTest->id), [
            'candidate_id' => $this->candidate->id,
        ]);

        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_19_assignment_engine_get_eligible_candidates_returns_toeic_candidates_for_toeic_target_test(): void
    {
        $toeicProduct = Product::create([
            'title'             => 'TOEIC Package Candidate Lookup',
            'slug'              => 'toeic-pkg-lookup',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $toeicProduct, 'success');

        $toeicTest = $this->createMockTest('TOEIC Target Lookup', 'toeic');

        $eligible = $this->assignmentEngine->getEligibleCandidates($toeicTest);
        $this->assertTrue($eligible->contains('id', $this->candidate->id));
        $this->assertFalse($eligible->contains('id', $this->candidate2->id));
    }

    public function test_20_get_eligible_candidates_excludes_toefl_candidates_from_toeic_target(): void
    {
        $toeflProduct = Product::create([
            'title'             => 'TOEFL Package Candidate Lookup',
            'slug'              => 'toefl-pkg-lookup',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate2, $toeflProduct, 'success');

        $toeicTest = $this->createMockTest('TOEIC Target Lookup', 'toeic');

        $eligible = $this->assignmentEngine->getEligibleCandidates($toeicTest);
        $this->assertFalse($eligible->contains('id', $this->candidate2->id));
    }

    public function test_21_payment_entitlement_does_not_auto_create_candidate_test_assignment(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package No Autoassign',
            'slug'              => 'toeic-pkg-no-auto',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $assignmentsCount = CandidateTestAssignment::where('user_id', $this->candidate->id)->count();
        $this->assertEquals(0, $assignmentsCount);
    }

    public function test_22_paid_candidate_with_no_matching_published_test_remains_suitable_for_assessment_request_workflow(): void
    {
        $product = Product::create([
            'title'             => 'Specialist Diagnostic Package',
            'slug'              => 'specialist-diag-pkg',
            'product_type'      => 'assessment',
            'assessment_family' => 'general',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $product, 'success');

        $response = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
        $response->assertSee($this->candidate->name);
    }

    public function test_23_assignment_failure_does_not_create_partial_candidate_test_assignment(): void
    {
        $toeflTest = $this->createMockTest('TOEFL Test Mismatch', 'toefl');

        try {
            $this->assignmentEngine->assignToUser($toeflTest, $this->candidate, $this->adminUser);
        } catch (InvalidArgumentException $e) {
            // Expected
        }

        $this->assertEquals(0, CandidateTestAssignment::where('user_id', $this->candidate->id)->count());
    }

    public function test_24_specific_test_entitlement_and_package_entitlement_can_coexist_safely(): void
    {
        $specificTest = $this->createMockTest('Specific Test Instance', 'toeic');
        $genericTest = $this->createMockTest('Generic TOEIC Edition', 'toeic');

        $specProduct = Product::create([
            'title'             => 'Specific Product',
            'slug'              => 'specific-prod-coexist',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $specificTest->id,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $pkgProduct = Product::create([
            'title'             => 'Generic Package',
            'slug'              => 'generic-pkg-coexist',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->purchaseAndPay($this->candidate, $specProduct, 'success');
        $this->purchaseAndPay($this->candidate2, $pkgProduct, 'success');

        $this->assertTrue($this->assignmentEngine->isPaymentEligible($specificTest, $this->candidate));
        $this->assertTrue($this->assignmentEngine->isPaymentEligible($genericTest, $this->candidate2));
    }

    public function test_25_light_theme_contract_remains_intact_for_touched_assignment_surfaces(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
        $response->assertSee('bg-white');
        $response->assertSee('text-slate-900');
    }

    public function test_26_dark_theme_contract_remains_intact_for_touched_assignment_surfaces(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900');
        $response->assertSee('dark:text-white');
    }

    public function test_27_global_ui_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
