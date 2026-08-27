<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidateAssessmentPackageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'admin']);

        $this->candidate = User::create([
            'name'     => 'Jane Candidate',
            'email'    => 'candidate@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');
    }

    public function test_01_persistent_style_assessment_package_data_can_exist_with_test_id_null(): void
    {
        $package = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => AssessmentFamily::Toeic->value,
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
            'course_id'         => null,
        ]);

        $this->assertDatabaseHas('products', [
            'slug'              => 'toeic-mock-test-package',
            'assessment_family' => 'toeic',
            'test_id'           => null,
            'is_active'         => 1,
        ]);
        $this->assertTrue($package->isAssessmentPackage());
        $this->assertNull($package->test_id);
    }

    public function test_02_candidate_store_includes_active_abstract_assessment_package(): void
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
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('IDR 750,000');
    }

    public function test_03_candidate_store_does_not_require_a_published_test_for_abstract_package(): void
    {
        $this->assertEquals(0, Test::count());

        Product::create([
            'slug'              => 'standalone-toeic-package',
            'title'             => 'Standalone TOEIC Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Standalone TOEIC Package');
    }

    public function test_04_inactive_package_is_excluded(): void
    {
        Product::create([
            'slug'              => 'inactive-package',
            'title'             => 'Inactive Package Title',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => false,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertDontSee('Inactive Package Title');
    }

    public function test_05_package_family_toeic_renders_correctly(): void
    {
        Product::create([
            'slug'              => 'toeic-family-package',
            'title'             => 'TOEIC Family Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('Assessment Family:', false);
        $response->assertSee('TOEIC');
    }

    public function test_06_package_price_renders_correctly(): void
    {
        Product::create([
            'slug'              => 'priced-package',
            'title'             => 'Priced Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('IDR 750,000');
    }

    public function test_07_product_detail_does_not_require_test_metadata_for_abstract_package(): void
    {
        $package = Product::create([
            'slug'              => 'detail-abstract-pkg',
            'title'             => 'Detail Abstract Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.store.show', $package->id));

        $response->assertStatus(200);
        $response->assertSee('Detail Abstract Package');
        $response->assertSee('Assessment Package Details');
        $response->assertSee('2 Exam Attempts');
        $response->assertDontSee('Total Questions');
        $response->assertDontSee('Minutes'); // No fake test duration
    }

    public function test_08_buy_checkout_action_is_available_for_abstract_package(): void
    {
        $package = Product::create([
            'slug'              => 'checkout-target-pkg',
            'title'             => 'Checkout Target Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        // Checkout form page loads cleanly
        $response = $this->actingAs($this->candidate)->get(route('candidate.checkout.show', $package->id));
        $response->assertStatus(200);
        $response->assertSee('Order Checkout');
        $response->assertSee('Checkout Target Package');
        $response->assertSee('IDR 750,000');
    }

    public function test_09_no_mock_test_is_created_by_package_provisioning(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        $this->assertEquals(0, Test::count());
    }

    public function test_10_no_candidate_test_assignment_is_created_by_package_provisioning(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_11_no_attempt_is_created_by_package_provisioning(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        $this->assertEquals(0, Attempt::count());
    }

    public function test_12_no_certificate_is_created_by_package_provisioning(): void
    {
        Artisan::call('iap:commerce:provision-assessment-packages');
        $this->assertEquals(0, Certificate::count());
    }

    public function test_13_payment_empty_state_does_not_claim_packages_depend_on_academic_repository_publication(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('No assessment packages currently available');
        $response->assertSee('Assessment packages offered by iC.edu will appear here when available.');
        $response->assertDontSee('published by the academic repository');
    }

    public function test_14_existing_candidate_portal_navigation_remains_unchanged(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));

        $response->assertStatus(200);
        $response->assertSee('Portal Dashboard');
        $response->assertSee('PAYMENT');
    }

    public function test_15_light_theme_contract_remains_intact(): void
    {
        $this->candidate->setThemePreference('light');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_16_dark_theme_contract_remains_intact(): void
    {
        $this->candidate->setThemePreference('dark');

        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_17_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.store'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
