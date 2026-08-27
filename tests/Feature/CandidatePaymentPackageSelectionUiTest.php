<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidatePaymentPackageSelectionUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'admin']);

        $this->candidate = User::create([
            'name'     => 'Jane Candidate',
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

    protected function createMockTest(string $title, string $type = 'toeic'): Test
    {
        return Test::create([
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . Str::random(5),
            'test_type'        => $type,
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'passing_score'    => 70,
            'max_attempts'     => 2,
            'is_published'     => true,
            'status'           => 'published',
            'author_id'        => $this->adminUser->id,
            'created_by'       => $this->adminUser->id,
        ]);
    }

    public function test_01_candidate_payment_page_contains_instruction(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Browse institutional mock tests, assessment packages, and preparatory courses.');
        $response->assertSee('Choose the test type according to your needs.');
    }

    public function test_02_candidate_payment_page_contains_warning_message(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('If there is any change or an incorrect test selection, please immediately contact the administrator!');
    }

    public function test_03_warning_uses_semantic_danger_presentation(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('role="alert"', false);
        $response->assertSee('text-rose-700');
        $response->assertSee('border-rose-200');
    }

    public function test_04_assessment_package_cta_displays_choose(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Choose');
    }

    public function test_05_buy_now_is_no_longer_displayed_on_package_cards(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Buy Now');
    }

    public function test_06_choose_cta_still_links_to_existing_product_checkout_route(): void
    {
        $package = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee(route('candidate.checkout.show', $package->id));
    }

    public function test_07_abstract_toeic_package_displays_assessment_family(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Assessment Family:');
        $response->assertSee('TOEIC');
        $response->assertSee('Package Type:');
        $response->assertSee('Mock Test Package');
    }

    public function test_08_abstract_package_does_not_display_fake_duration(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Duration:');
        $response->assertDontSee('Mins');
    }

    public function test_09_abstract_package_does_not_display_fake_question_count(): void
    {
        $package = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $package->id));

        $response->assertStatus(200);
        $response->assertDontSee('Total Questions');
        $response->assertDontSee('Questions');
    }

    public function test_10_abstract_package_does_not_display_real_test_mock_session(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Real Test Mock Session');
    }

    public function test_11_specific_test_product_may_still_display_valid_test_details(): void
    {
        $test = $this->createMockTest('Live TOEIC Real Test', 'toeic');

        Product::create([
            'slug'              => 'specific-test-product',
            'title'             => 'Specific TOEIC Real Test Product',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => $test->id,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Mock Test Ready');
        $response->assertSee('120 Mins');
    }

    public function test_12_no_official_toeic_wording_exists(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Official TOEIC');
        $response->assertDontSee('official TOEIC');
    }

    public function test_13_no_official_certification_wording_exists(): void
    {
        Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Official Certification');
        $response->assertDontSee('official certification');
    }

    public function test_14_no_backend_payment_logic_changes(): void
    {
        $package = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout($this->candidate, $package);

        $this->assertEquals(OrderStatus::Pending, $result['order']->status);
        $this->assertEquals(750000, $result['order']->items->first()->price);
    }

    public function test_15_no_assignment_engine_changes(): void
    {
        $test = $this->createMockTest('TOEIC Evaluation Test', 'toeic');
        $assignmentEngine = app(AssignmentEngine::class);

        $this->assertFalse($assignmentEngine->isPaymentEligible($test, $this->candidate));
    }

    public function test_16_light_theme_ui_contract_passes(): void
    {
        $this->candidate->setThemePreference('light');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_17_dark_theme_ui_contract_passes(): void
    {
        $this->candidate->setThemePreference('dark');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_18_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
