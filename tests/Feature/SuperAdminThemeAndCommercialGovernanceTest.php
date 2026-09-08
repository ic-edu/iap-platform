<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminThemeAndCommercialGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $operationalAdmin;
    protected User $otherSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::create([
            'name'     => 'Super Admin Operator',
            'email'    => 'superadmin_governance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->otherSuperAdmin = User::create([
            'name'     => 'Peer Super Admin',
            'email'    => 'peersa_governance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->otherSuperAdmin->assignRole('super-admin');

        $this->operationalAdmin = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'opadmin_governance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->operationalAdmin->assignRole('admin');
    }

    public function test_super_admin_pages_render_successfully_in_light_mode(): void
    {
        $this->superAdmin->setThemePreference('light');

        $routes = [
            route('admin.all-users.index'),
            route('admin.approvals.organizations'),
            route('admin.users.index'),
            route('admin.approvals.assessments'),
            route('admin.approvals.question-banks'),
            route('admin.approvals.staff-creations'),
            route('admin.approvals.user-deletions'),
            route('admin.approvals.index'),
            route('admin.recycle-bin.index'),
            route('admin.commerce.index'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($this->superAdmin)->get($url);
            $response->assertStatus(200);
            $response->assertSee('data-theme="light"', false);
            // Verify no hardcoded un-prefixed dark bg in table headers
            $response->assertDontSee('class="bg-slate-900 text-white"', false);
            $response->assertDontSee('class="bg-slate-950 text-white"', false);
        }
    }

    public function test_super_admin_viewing_commerce_sees_governance_view_and_no_mutation_controls(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.commerce.index'));

        $response->assertStatus(200);
        $response->assertSee('Commercial Governance &amp; Approvals', false);
        $response->assertSee('Super Admin Governance Authority &bull; Commercial Oversight', false);
        $response->assertSee('Commercial Price Change Approval Queue', false);
        $response->assertSee('Package Catalog Oversight (Read-Only)', false);
        $response->assertSee('TOEIC Mock Test Package', false);

        // Assert operational mutation buttons are strictly absent
        $response->assertDontSee('Create New Assessment Package', false);
        $response->assertDontSee('Generate Voucher Campaign', false);
        $response->assertDontSee('Propose Price Change', false);
        $response->assertDontSee('Add Standalone Voucher', false);
    }

    public function test_operational_admin_viewing_commerce_sees_operational_catalog_with_creation_and_proposal_controls(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $response = $this->actingAs($this->operationalAdmin)->get(route('admin.commerce.index'));

        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
        $response->assertSee('Generate Voucher Campaign', false);
        $response->assertSee('Create Package', false);
        $response->assertSee('Propose Price Change', false);
        $response->assertDontSee('Super Admin Governance Authority &bull; Commercial Oversight', false);
    }

    public function test_super_admin_can_approve_pending_price_change_from_governance_queue(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $priceChange = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->operationalAdmin->id,
            'current_price_snapshot' => 85000,
            'proposed_price'         => 95000,
            'reason'                 => 'Market rate adjustment',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.approvals.price-changes.approve', $priceChange->id));

        $response->assertRedirect(route('admin.approvals.index'));
        $this->assertEquals(95000, $product->fresh()->price);
        $this->assertEquals('approved', $priceChange->fresh()->status);
        $this->assertEquals($this->superAdmin->id, $priceChange->fresh()->reviewed_by);
    }

    public function test_super_admin_cannot_approve_own_price_change_proposal_approver_separation(): void
    {
        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $priceChange = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->superAdmin->id, // Created by this super admin
            'current_price_snapshot' => 85000,
            'proposed_price'         => 120000,
            'reason'                 => 'Self proposed change',
            'status'                 => 'pending',
        ]);

        // Attempting to self-approve must be blocked with 403
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.approvals.price-changes.approve', $priceChange->id));

        $response->assertStatus(403);
        $this->assertEquals(85000, $product->fresh()->price);
        $this->assertEquals('pending', $priceChange->fresh()->status);

        // However, a peer Super Admin can approve it
        $peerResponse = $this->actingAs($this->otherSuperAdmin)
            ->post(route('admin.approvals.price-changes.approve', $priceChange->id));

        $peerResponse->assertRedirect(route('admin.approvals.index'));
        $this->assertEquals(120000, $product->fresh()->price);
        $this->assertEquals('approved', $priceChange->fresh()->status);
    }

    public function test_super_admin_direct_mutation_routes_are_guarded(): void
    {
        // Direct package store route is restricted to role:admin (Operational Admin)
        $response = $this->actingAs($this->superAdmin)->post(route('admin.commerce.products.store'), [
            'title'             => 'Direct SA Created Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 50000,
        ]);

        $response->assertStatus(403);
    }
}
