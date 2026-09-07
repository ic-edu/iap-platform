<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateInstitutionalSelfCommerceRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $institutionalCandidate;
    protected User $standaloneCandidate;
    protected Organization $organization;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Institutional Candidate (e.g. CA01)
        $this->institutionalCandidate = User::factory()->create(['email' => 'ca01@uat.org', 'name' => 'CA01']);
        $this->institutionalCandidate->assignRole('student');

        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'contact_email'     => 'uat@icedu.org',
            'status'            => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->institutionalCandidate->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        // 2. Standalone Candidate
        $this->standaloneCandidate = User::factory()->create(['email' => 'student@icedu.org', 'name' => 'Standalone Student']);
        $this->standaloneCandidate->assignRole('student');

        $this->product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);
    }

    public function test_vres_01_institutional_candidate_is_restricted_from_store_routes(): void
    {
        $response = $this->actingAs($this->institutionalCandidate)->get(route('candidate.store'));
        $response->assertRedirect(route('candidate.portal'));
        $response->assertSessionHas('error');

        $checkoutResponse = $this->actingAs($this->institutionalCandidate)->get(route('candidate.checkout.show', $this->product));
        $checkoutResponse->assertRedirect(route('candidate.portal'));
        $checkoutResponse->assertSessionHas('error');
    }

    public function test_vres_02_institutional_candidate_dashboard_hides_payment_card(): void
    {
        $response = $this->actingAs($this->institutionalCandidate)->get(route('candidate.portal'));
        $response->assertOk();

        // Institutional membership context banner should be visible
        $response->assertSee('Institutional Membership');
        $response->assertSee('iC.edu UAT University');

        // Self-commerce payment card should NOT be visible
        $response->assertDontSee('Assessment Entry');
        $response->assertDontSee('Browse Assessment Packages');
    }

    public function test_vres_03_standalone_candidate_has_full_access_to_self_commerce_store(): void
    {
        $response = $this->actingAs($this->standaloneCandidate)->get(route('candidate.store'));
        $response->assertOk();

        $dashboardResponse = $this->actingAs($this->standaloneCandidate)->get(route('candidate.portal'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('PAYMENT');
        $dashboardResponse->assertSee('Browse Assessment Packages');
    }
}
