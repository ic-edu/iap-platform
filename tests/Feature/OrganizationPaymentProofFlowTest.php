<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationPaymentProofFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationA;
    protected Organization $organizationB;
    protected User $coordinatorA;
    protected User $coordinatorB;
    protected User $memberA;
    protected User $financeUser;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('finance', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('organization-coordinator', 'web');

        // Org A
        $this->organizationA = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-univ',
            'status' => OrganizationStatus::Active,
        ]);

        $this->coordinatorA = User::factory()->create([
            'name' => 'Indra Wahyudi',
            'email' => 'indra.coord@ic.edu',
            'status' => 'active',
        ]);
        $this->coordinatorA->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
        ]);

        $this->memberA = User::factory()->create([
            'name' => 'Candidate Member A',
            'email' => 'member.a@ic.edu',
            'status' => 'active',
        ]);
        $this->memberA->assignRole('student');

        OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->memberA->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        // Org B
        $this->organizationB = Organization::create([
            'name' => 'Other Organization B',
            'slug' => 'other-org-b',
            'status' => OrganizationStatus::Active,
        ]);

        $this->coordinatorB = User::factory()->create([
            'name' => 'Other Coordinator B',
            'email' => 'coord.b@other.edu',
            'status' => 'active',
        ]);
        $this->coordinatorB->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->organizationB->id,
            'user_id' => $this->coordinatorB->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
        ]);

        // Finance User
        $this->financeUser = User::factory()->create([
            'name' => 'Finance Officer',
            'email' => 'finance@ic.edu',
            'status' => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        // Product
        $this->product = Product::create([
            'title' => 'TOEIC Mock Test Package',
            'slug' => 'toeic-mock-test-package',
            'assessment_family' => 'TOEIC',
            'product_type' => 'assessment',
            'price' => 85000.0,
            'is_active' => true,
        ]);
    }

    public function test_01_pending_order_view_renders_upload_form_and_bank_details(): void
    {
        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-TEST1',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'discount' => 25500.0,
            'tax' => 25245.0,
            'grand_total' => 254745.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'price' => 85000.0,
            'total' => 255000.0,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'invoice_number' => 'INV-20260907-TEST1',
            'amount' => 254745.0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $this->coordinatorA->id,
            'reference_number' => 'PAY-20260907-TEST1',
            'payment_gateway' => 'manual_transfer',
            'amount' => 254745.0,
            'status' => PaymentStatus::Pending,
            'proof_path' => null,
        ]);

        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.purchases.show', [$this->organizationA->slug, $order]));

        $response->assertOk();
        $response->assertSee('PENDING PAYMENT');
        $response->assertSee('Bank Transfer Details');
        $response->assertSee('Bank Mandiri (PT iC.edu Indonesia)');
        $response->assertSee('131-00-1928374-6');
        $response->assertSee('Upload Payment Receipt');
        $response->assertSee('Submit Payment Proof');
    }

    public function test_02_coordinator_can_upload_proof_reusing_existing_payment(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-TEST2',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'discount' => 25500.0,
            'tax' => 25245.0,
            'grand_total' => 254745.0,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'invoice_number' => 'INV-20260907-TEST2',
            'amount' => 254745.0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $existingPayment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $this->coordinatorA->id,
            'reference_number' => 'PAY-20260907-TEST2',
            'payment_gateway' => 'manual_transfer',
            'amount' => 254745.0,
            'status' => PaymentStatus::Pending,
            'proof_path' => null,
        ]);

        $initialPaymentCount = Payment::count();

        $file = UploadedFile::fake()->create('bank_receipt.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file,
                'sender_bank' => 'BCA',
                'sender_name' => 'Indra Wahyudi',
                'payment_reference' => 'TRX-998877',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Assert payment record was reused and NOT duplicated
        $this->assertEquals($initialPaymentCount, Payment::count());

        $existingPayment->refresh();
        $this->assertNotNull($existingPayment->proof_path);
        $this->assertStringStartsWith('payment_proofs/', $existingPayment->proof_path);
        $this->assertEquals('bank_receipt.pdf', $existingPayment->proof_original_name);
        $this->assertNotNull($existingPayment->proof_uploaded_at);
        $this->assertEquals(PaymentStatus::Pending, $existingPayment->status);
        $this->assertEquals('TRX-998877', $existingPayment->transaction_id);

        Storage::disk('local')->assertExists($existingPayment->proof_path);
    }

    public function test_03_post_upload_view_renders_awaiting_verification_and_actions(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-TEST3',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'discount' => 25500.0,
            'tax' => 25245.0,
            'grand_total' => 254745.0,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'invoice_number' => 'INV-20260907-TEST3',
            'amount' => 254745.0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $this->coordinatorA->id,
            'reference_number' => 'PAY-20260907-TEST3',
            'payment_gateway' => 'manual_transfer',
            'amount' => 254745.0,
            'status' => PaymentStatus::Pending,
            'proof_path' => 'payment_proofs/test_receipt.jpg',
            'proof_original_name' => 'test_receipt.jpg',
            'proof_uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.purchases.show', [$this->organizationA->slug, $order]));

        $response->assertOk();
        $response->assertSee('Payment Proof Uploaded');
        $response->assertSee('Awaiting Finance Verification');
        $response->assertSee('test_receipt.jpg');
        $response->assertSee('View Uploaded Proof');
        $response->assertSee('Replace Proof');
    }

    public function test_04_file_validation_rules(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-TEST4',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        // Invalid file format (.exe / text)
        $invalidFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');
        $res = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $invalidFile,
            ]);
        $res->assertSessionHasErrors(['proof']);

        // Oversized file (>5MB)
        $oversizedFile = UploadedFile::fake()->create('big_proof.pdf', 6000, 'application/pdf');
        $res2 = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $oversizedFile,
            ]);
        $res2->assertSessionHasErrors(['proof']);
    }

    public function test_05_tenant_isolation_cross_organization_blocked(): void
    {
        Storage::fake('local');

        $orderA = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-ORGA',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        $file = UploadedFile::fake()->create('proof.png', 200, 'image/png');

        // Coordinator B trying to upload to Org A order
        $response = $this->actingAs($this->coordinatorB)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $orderA]), [
                'proof' => $file,
            ]);

        $response->assertForbidden();

        // Coordinator B trying to access Org A order via Org B url
        $response2 = $this->actingAs($this->coordinatorB)
            ->post(route('organization.purchases.proof', [$this->organizationB->slug, $orderA]), [
                'proof' => $file,
            ]);

        $response2->assertForbidden();
    }

    public function test_06_role_isolation_candidate_member_blocked(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-MEMBER',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->memberA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_07_replace_proof_deletes_old_file_and_preserves_single_payment(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-REPLACE',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        $file1 = UploadedFile::fake()->create('first_receipt.jpg', 200, 'image/jpeg');
        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file1,
            ]);

        $order->loadMissing('invoice.payments');
        $payment = $order->invoice->payments->first();
        $oldPath = $payment->proof_path;

        Storage::disk('local')->assertExists($oldPath);

        // Upload replacement
        $file2 = UploadedFile::fake()->create('second_receipt.pdf', 300, 'application/pdf');
        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file2,
            ]);

        $payment->refresh();
        $newPath = $payment->proof_path;

        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertEquals(1, $order->invoice->payments()->count());
    }

    public function test_08_replace_blocked_if_payment_already_approved(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-APPRV',
            'status' => OrderStatus::Completed,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'invoice_number' => 'INV-20260907-APPRV',
            'amount' => 255000.0,
            'status' => InvoiceStatus::Paid,
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $this->coordinatorA->id,
            'reference_number' => 'PAY-20260907-APPRV',
            'payment_gateway' => 'manual_transfer',
            'amount' => 255000.0,
            'status' => PaymentStatus::Paid,
            'proof_path' => 'payment_proofs/verified.pdf',
        ]);

        $file = UploadedFile::fake()->create('new_receipt.pdf', 200, 'application/pdf');
        $response = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file,
            ]);

        $response->assertSessionHasErrors(['error']);
    }

    public function test_09_voucher_entitlements_and_o3_preserved_on_proof_upload(): void
    {
        Storage::fake('local');

        $campaign = CouponCampaign::create([
            'name' => 'TOEIC O2 UAT Promo',
            'assessment_family' => 'TOEIC',
            'scope_mode' => 'selected_products',
            'discount_type' => 'percentage',
            'discount_value' => 10.0,
            'generation_mode' => 'shared_single',
            'code_prefix' => 'TOEIC',
            'code_length' => 6,
            'uses_per_code' => 10,
            'total_codes' => 1,
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'campaign_id' => $campaign->id,
            'code' => 'TOEIC-JX3JFS',
            'type' => 'percentage',
            'value' => 10.0,
            'usage_limit' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'coupon_id' => $coupon->id,
            'order_number' => 'ORD-20260907-3DWQ-MOCK',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'discount' => 25500.0,
            'tax' => 25245.0,
            'grand_total' => 254745.0,
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'discount_amount' => 25500.0,
            'status' => 'reserved',
            'reserved_at' => now(),
        ]);

        $this->assertEquals(0, $coupon->used_count);
        $this->assertEquals(1, $coupon->redemptions()->where('status', 'reserved')->count());
        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organizationA->id)->count());
        $this->assertEquals(0, CandidateTestAssignment::count());

        $file = UploadedFile::fake()->create('dummy_transfer.jpg', 150, 'image/jpeg');
        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file,
            ]);

        // Assert Voucher is still strictly Reserved and used_count is 0
        $coupon->refresh();
        $this->assertEquals(0, $coupon->used_count);
        $this->assertEquals(1, $coupon->redemptions()->where('status', 'reserved')->count());
        $this->assertEquals(0, $coupon->redemptions()->where('status', 'consumed')->count());

        // Assert 0 Entitlements created
        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organizationA->id)->count());

        // Assert 0 CandidateTestAssignments created (O3 boundary)
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_10_finance_and_coordinator_can_stream_proof(): void
    {
        Storage::fake('local');

        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-20260907-STREAM',
            'status' => OrderStatus::Pending,
            'subtotal' => 255000.0,
            'grand_total' => 255000.0,
        ]);

        $file = UploadedFile::fake()->create('proof_stream.pdf', 100, 'application/pdf');
        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'proof' => $file,
            ]);

        $order->loadMissing('invoice.payments');
        $payment = $order->invoice->payments->first();

        // 1. Coordinator views proof via organization route
        $coordResponse = $this->actingAs($this->coordinatorA)
            ->get(route('organization.purchases.proof.view', [$this->organizationA->slug, $order]));
        $coordResponse->assertOk();

        // 2. Finance views proof via finance route
        $financeResponse = $this->actingAs($this->financeUser)
            ->get(route('finance.payments.proof', $payment->id));
        $financeResponse->assertOk();
    }
}
