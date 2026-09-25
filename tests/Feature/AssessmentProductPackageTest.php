<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Domain\Enums\AssessmentMode;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentProductPackageTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->candidate = User::create([
            'name'     => 'Candidate Student',
            'email'    => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');
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

    public function test_01_assessment_product_may_have_assessment_family_toeic(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::Toeic->value,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->assertEquals('toeic', $product->assessment_family);
        $this->assertEquals('toeic', $product->getEffectiveFamily());
        $this->assertTrue($product->isAssessmentPackage());
        $this->assertFalse($product->hasSpecificTest());
    }

    public function test_02_assessment_product_may_have_assessment_family_toefl(): void
    {
        $product = Product::create([
            'title'             => 'TOEFL iBT Prep Package',
            'slug'              => 'toefl-ibt-prep-package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::Toefl->value,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->assertEquals('toefl', $product->assessment_family);
        $this->assertEquals('toefl', $product->getEffectiveFamily());
    }

    public function test_03_assessment_product_may_have_assessment_family_ielts(): void
    {
        $product = Product::create([
            'title'             => 'IELTS Academic Master Package',
            'slug'              => 'ielts-academic-master-package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::Ielts->value,
            'price'             => 850000,
            'is_active'         => true,
        ]);

        $this->assertEquals('ielts', $product->assessment_family);
        $this->assertEquals('ielts', $product->getEffectiveFamily());
    }

    public function test_04_assessment_product_may_have_assessment_family_general(): void
    {
        $product = Product::create([
            'title'             => 'General English Diagnostic Package',
            'slug'              => 'general-english-diagnostic-package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::General->value,
            'price'             => 350000,
            'is_active'         => true,
        ]);

        $this->assertEquals('general', $product->assessment_family);
        $this->assertEquals('general', $product->getEffectiveFamily());
    }

    public function test_05_assessment_product_may_have_assessment_family_vocational(): void
    {
        $product = Product::create([
            'title'             => 'Vocational Skills Assessment Package',
            'slug'              => 'vocational-skills-assessment-package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::Vocational->value,
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $this->assertEquals('vocational', $product->assessment_family);
        $this->assertEquals('vocational', $product->getEffectiveFamily());
    }

    public function test_06_assessment_package_may_have_test_id_null(): void
    {
        $product = Product::create([
            'title'             => 'Generic TOEIC Voucher Package',
            'slug'              => 'generic-toeic-voucher-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $this->assertNull($product->test_id);
        $this->assertNull($product->test);
        $this->assertTrue($product->isAssessmentPackage());
    }

    public function test_07_specific_test_product_may_have_test_id_not_null(): void
    {
        $test = $this->createMockTest('Specific TOEIC Test Edition 2026');

        $product = Product::create([
            'title'             => 'Specific TOEIC Test Edition Product',
            'slug'              => 'specific-toeic-test-edition',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $test->id,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->assertEquals($test->id, $product->test_id);
        $this->assertTrue($product->hasSpecificTest());
        $this->assertFalse($product->isAssessmentPackage());
    }

    public function test_08_existing_product_with_test_id_continues_to_resolve_its_test_and_derived_family(): void
    {
        $test = $this->createMockTest('TOEFL Institutional Edition', 'toefl');

        $product = Product::create([
            'title'             => 'Legacy TOEFL Product without Family',
            'slug'              => 'legacy-toefl-product',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => $test->id,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->assertNotNull($product->test);
        $this->assertEquals($test->id, $product->test->id);
        $this->assertEquals('toefl', $product->getEffectiveFamily());
    }

    public function test_09_non_assessment_product_may_have_assessment_family_null(): void
    {
        $category = \App\Modules\Academic\Models\CourseCategory::create([
            'name' => 'General English',
            'slug' => 'general-english',
        ]);

        $course = Course::create([
            'category_id'  => $category->id,
            'title'        => 'Business English Course',
            'code'         => 'ENG-BUS-101',
            'slug'         => 'business-english-course',
            'is_published' => true,
        ]);

        $product = Product::create([
            'title'             => 'Business English Course Product',
            'slug'              => 'business-english-course-prod',
            'product_type'      => 'course',
            'assessment_family' => null,
            'course_id'         => $course->id,
            'price'             => 1200000,
            'is_active'         => true,
        ]);

        $this->assertNull($product->assessment_family);
        $this->assertFalse($product->isAssessment());
    }

    public function test_10_invalid_assessment_family_is_rejected(): void
    {
        $data = [
            'title'             => 'Invalid Family Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'invalid_unknown_family',
            'price'             => 500000,
            'is_active'         => true,
        ];

        $validator = Validator::make($data, Product::validationRules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('assessment_family', $validator->errors()->toArray());
    }

    public function test_11_assessment_package_without_assessment_family_is_rejected(): void
    {
        $data = [
            'title'             => 'Missing Family Package',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'price'             => 500000,
            'is_active'         => true,
        ];

        $validator = Validator::make($data, Product::validationRules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('assessment_family', $validator->errors()->toArray());
    }

    public function test_12_assessment_package_with_null_test_id_remains_valid(): void
    {
        $data = [
            'title'             => 'Valid Assessment Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ];

        $validator = Validator::make($data, Product::validationRules());
        $this->assertTrue($validator->passes());
    }

    public function test_13_existing_course_product_remains_valid(): void
    {
        $category = \App\Modules\Academic\Models\CourseCategory::create([
            'name' => 'TOEIC Prep',
            'slug' => 'toeic-prep',
        ]);

        $course = Course::create([
            'category_id'  => $category->id,
            'title'        => 'TOEIC Prep Course',
            'code'         => 'TOEIC-PREP-101',
            'slug'         => 'toeic-prep-course',
            'is_published' => true,
        ]);

        $product = Product::create([
            'title'        => 'TOEIC Master Course',
            'slug'         => 'toeic-master-course',
            'product_type' => 'course',
            'course_id'    => $course->id,
            'price'        => 900000,
            'is_active'    => true,
        ]);

        $this->assertNotNull($product->course);
        $this->assertEquals($course->id, $product->course->id);
    }

    public function test_14_existing_simulator_linked_product_remains_valid(): void
    {
        $test = $this->createMockTest('Simulator Practice Test', 'toeic', 'simulator');

        $product = Product::create([
            'title'             => 'Simulator Test Access',
            'slug'              => 'simulator-test-access',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $test->id,
            'price'             => 100000,
            'is_active'         => true,
        ]);

        $this->assertNotNull($product->test);
        $this->assertTrue($product->test->isSimulator());
    }

    public function test_15_candidate_store_can_render_a_package_product_with_test_id_null(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Assessment Voucher Package',
            'slug'              => 'toeic-assessment-voucher-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Assessment Voucher Package');
        $response->assertSee('Assessment Family:');
        $response->assertSee('TOEIC');
        $response->assertSee('Mock Test Package');
    }

    public function test_16_product_detail_can_render_package_metadata_without_fake_test_fields(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Standalone Package',
            'slug'              => 'toeic-standalone-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $product->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Standalone Package');
        $response->assertSee('Assessment Package Details');
        $response->assertSee('TOEIC');
        $response->assertSee('2 Exam Attempts');
        $response->assertDontSee('Minutes'); // No fake test duration
    }

    public function test_17_checkout_engine_accepts_valid_package_product_with_test_id_null(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Abstract Package',
            'slug'              => 'toeic-abstract-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $result = $checkoutEngine->checkout($this->candidate, $product);

        $this->assertNotNull($result['order']);
        $this->assertNotNull($result['invoice']);
        $this->assertEquals(750000, $result['order']->subtotal);
        $this->assertEquals(832500, $result['order']->grand_total);
        $this->assertEquals(832500, $result['invoice']->amount);

        $payment = $billingEngine->createPayment($result['invoice'], 'manual_transfer');
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_18_no_candidate_test_assignment_is_created(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Package Non-Autoassign',
            'slug'              => 'toeic-pkg-non-autoassign',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);

        $result = $checkoutEngine->checkout($this->candidate, $product);
        $payment = $billingEngine->createPayment($result['invoice'], 'manual_transfer');
        $billingEngine->confirmPayment($payment, 'TXN-TEST-12345');

        $assignmentsCount = CandidateTestAssignment::where('user_id', $this->candidate->id)->count();
        $this->assertEquals(0, $assignmentsCount);
    }

    public function test_19_assignment_engine_behavior_remains_unchanged_from_pre_pd1_contract(): void
    {
        $test = $this->createMockTest('UAT Assignment Contract Test');

        $assignmentEngine = app(AssignmentEngine::class);

        // Simulator check is preserved
        $simTest = $this->createMockTest('Simulator Test', 'toeic', 'simulator');
        $this->assertTrue($assignmentEngine->isPaymentEligible($simTest, $this->candidate));
    }

    public function test_20_assessment_request_controller_behavior_remains_unchanged(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index'));
        $response->assertStatus(200);
    }

    public function test_21_light_theme_product_package_contract_passes(): void
    {
        $product = Product::create([
            'title'             => 'Light Theme Package Test',
            'slug'              => 'light-theme-package-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $product->id));

        $response->assertStatus(200);
        $response->assertSee('bg-white');
        $response->assertSee('text-slate-900');
        $response->assertSee('border-slate-200');
    }

    public function test_22_dark_theme_product_package_contract_passes(): void
    {
        $product = Product::create([
            'title'             => 'Dark Theme Package Test',
            'slug'              => 'dark-theme-package-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $product->id));

        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900');
        $response->assertSee('dark:text-white');
        $response->assertSee('dark:border-slate-800');
    }

    public function test_23_global_theme_contract_remains_intact(): void
    {
        $product = Product::create([
            'title'             => 'Global Theme Contract Test',
            'slug'              => 'global-theme-contract-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $product->id));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }

    public function test_24_legacy_inference_resolves_all_standard_families_when_family_and_test_id_are_null(): void
    {
        $cases = [
            ['title' => 'TOEIC Mock Test Package', 'slug' => 'toeic-mock-test-package', 'expected' => 'toeic'],
            ['title' => 'TOEFL iBT Examination Voucher', 'slug' => 'toefl-ibt-examination-voucher', 'expected' => 'toefl'],
            ['title' => 'IELTS General Training Bundle', 'slug' => 'ielts-general-training-bundle', 'expected' => null], // 'ielts' and 'general' -> ambiguous -> null
            ['title' => 'IELTS Academic Master Package', 'slug' => 'ielts-academic-master-package', 'expected' => 'ielts'],
            ['title' => 'General English Diagnostic Assessment', 'slug' => 'general-english-diagnostic-assessment', 'expected' => 'general'],
            ['title' => 'Vocational Competency Assessment', 'slug' => 'vocational-competency-assessment', 'expected' => 'vocational'],
        ];

        foreach ($cases as $case) {
            $product = new Product([
                'title'             => $case['title'],
                'slug'              => $case['slug'],
                'product_type'      => 'assessment',
                'assessment_family' => null,
                'test_id'           => null,
            ]);

            $this->assertEquals($case['expected'], $product->getEffectiveFamily(), "Failed for {$case['title']}");
        }
    }

    public function test_25_legacy_inference_returns_null_for_ambiguous_multiple_family_tokens(): void
    {
        $product = new Product([
            'title'             => 'Combined TOEIC and TOEFL Preparation Package',
            'slug'              => 'combined-toeic-toefl-package',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => null,
        ]);

        $this->assertNull($product->getEffectiveFamily());
    }

    public function test_26_legacy_inference_prevents_substring_false_positives(): void
    {
        $product = new Product([
            'title'             => 'Potatoeic Language Challenge',
            'slug'              => 'potatoeic-language-challenge',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => null,
        ]);

        $this->assertNull($product->getEffectiveFamily());
    }

    public function test_27_legacy_inference_does_not_trigger_for_non_assessment_product_types(): void
    {
        $product = new Product([
            'title'             => 'TOEIC Complete Mastery Course',
            'slug'              => 'toeic-complete-mastery-course',
            'product_type'      => 'course',
            'assessment_family' => null,
            'test_id'           => null,
        ]);

        $this->assertNull($product->getEffectiveFamily());
    }
}
