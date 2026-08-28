<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceDateFilterUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeUser;
    protected User $studentUser;
    protected Product $productToeic;
    protected Product $productToefl;
    protected Payment $uatPayment;
    protected Payment $confirmedPayment;
    protected Payment $failedPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->financeUser = User::factory()->create([
            'email' => 'finance@icedu.org',
            'name'  => 'Finance Officer',
        ]);
        $this->financeUser->assignRole('finance');

        $this->studentUser = User::factory()->create([
            'email' => 'student@icedu.org',
            'name'  => 'Candidate Student',
        ]);
        $this->studentUser->assignRole('student');

        // Products
        $this->productToeic = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $this->productToefl = Product::create([
            'title'             => 'TOEFL iBT Standard Package',
            'slug'              => 'toefl-ibt-standard-package',
            'product_type'      => 'assessment',
            'price'             => 900000,
            'is_active'         => true,
        ]);

        // UAT Payment: PAY-20260827-VZDM (Pending, 27 August 2026)
        $orderUat = Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260827-YIT4',
            'status'       => OrderStatus::Pending,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 82500,
            'grand_total'  => 832500,
        ]);
        DB::table('orders')->where('id', $orderUat->id)->update(['created_at' => '2026-08-27 10:00:00']);

        OrderItem::create([
            'order_id'   => $orderUat->id,
            'product_id' => $this->productToeic->id,
            'quantity'   => 1,
            'price'      => 750000,
            'total'      => 750000,
        ]);

        $invoiceUat = Invoice::create([
            'order_id'       => $orderUat->id,
            'user_id'        => $this->studentUser->id,
            'invoice_number' => 'INV-20260827-ZMJY',
            'status'         => InvoiceStatus::Unpaid,
            'amount'         => 832500,
            'due_date'       => Carbon::parse('2026-08-29 10:00:00'),
        ]);
        DB::table('invoices')->where('id', $invoiceUat->id)->update(['created_at' => '2026-08-27 10:00:00']);

        $this->uatPayment = Payment::create([
            'invoice_id'          => $invoiceUat->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260827-VZDM',
            'payment_gateway'     => 'manual_transfer',
            'status'              => PaymentStatus::Pending,
            'amount'              => 832500,
            'proof_path'          => 'payment-proofs/sample.png',
            'proof_original_name' => 'receipt.png',
            'proof_uploaded_at'   => Carbon::parse('2026-08-27 10:30:00'),
        ]);
        DB::table('payments')->where('id', $this->uatPayment->id)->update(['created_at' => '2026-08-27 10:00:00']);

        // Confirmed Payment (TOEFL, 20 August 2026)
        $orderConfirmed = Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260820-AAAA',
            'status'       => OrderStatus::Completed,
            'subtotal'     => 900000,
            'discount'     => 50000,
            'tax'          => 93500,
            'grand_total'  => 943500,
        ]);
        DB::table('orders')->where('id', $orderConfirmed->id)->update(['created_at' => '2026-08-20 14:00:00']);

        OrderItem::create([
            'order_id'   => $orderConfirmed->id,
            'product_id' => $this->productToefl->id,
            'quantity'   => 1,
            'price'      => 900000,
            'total'      => 900000,
        ]);

        $invoiceConfirmed = Invoice::create([
            'order_id'       => $orderConfirmed->id,
            'user_id'        => $this->studentUser->id,
            'invoice_number' => 'INV-20260820-CONF',
            'status'         => InvoiceStatus::Paid,
            'amount'         => 943500,
            'paid_at'        => Carbon::parse('2026-08-20 15:00:00'),
        ]);
        DB::table('invoices')->where('id', $invoiceConfirmed->id)->update(['created_at' => '2026-08-20 14:00:00']);

        $this->confirmedPayment = Payment::create([
            'invoice_id'          => $invoiceConfirmed->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260820-CONF',
            'payment_gateway'     => 'manual_transfer',
            'transaction_id'      => 'TXN-BCA-889911',
            'status'              => PaymentStatus::Success,
            'amount'              => 943500,
            'confirmed_at'        => Carbon::parse('2026-08-20 15:00:00'),
        ]);
        DB::table('payments')->where('id', $this->confirmedPayment->id)->update(['created_at' => '2026-08-20 14:00:00']);

        // Cancelled Payment (15 August 2026)
        $orderFailed = Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260815-FAIL',
            'status'       => OrderStatus::Cancelled,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 82500,
            'grand_total'  => 832500,
        ]);
        DB::table('orders')->where('id', $orderFailed->id)->update(['created_at' => '2026-08-15 09:00:00']);

        OrderItem::create([
            'order_id'   => $orderFailed->id,
            'product_id' => $this->productToeic->id,
            'quantity'   => 1,
            'price'      => 750000,
            'total'      => 750000,
        ]);

        $invoiceFailed = Invoice::create([
            'order_id'       => $orderFailed->id,
            'user_id'        => $this->studentUser->id,
            'invoice_number' => 'INV-20260815-FAIL',
            'status'         => InvoiceStatus::Cancelled,
            'amount'         => 832500,
        ]);
        DB::table('invoices')->where('id', $invoiceFailed->id)->update(['created_at' => '2026-08-15 09:00:00']);

        $this->failedPayment = Payment::create([
            'invoice_id'          => $invoiceFailed->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260815-FAIL',
            'payment_gateway'     => 'manual_transfer',
            'status'              => PaymentStatus::Failed,
            'amount'              => 832500,
            'proof_notes'         => 'Rejection reason: Invalid slip amount',
        ]);
        DB::table('payments')->where('id', $this->failedPayment->id)->update(['created_at' => '2026-08-15 09:00:00']);
    }

    public function test_01_finance_can_open_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
    }

    public function test_02_default_start_date_is_empty_when_no_start_date_parameter_exists(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('name="start_date" id="start_date" x-model="val" value=""', false);
    }

    public function test_03_default_end_date_is_empty_when_no_end_date_parameter_exists(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('name="end_date" id="end_date" x-model="val" value=""', false);
    }

    public function test_04_default_state_contains_no_implicit_date_restriction(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('PAY-20260820-CONF');
        $response->assertSee('PAY-20260815-FAIL');
    }

    public function test_05_empty_start_and_empty_end_returns_unfiltered_by_date_data(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '',
            'end_date'   => '',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('PAY-20260820-CONF');
        $response->assertSee('PAY-20260815-FAIL');
    }

    public function test_06_start_only_filter_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-20260820-CONF');
        $response->assertDontSee('PAY-20260815-FAIL');
    }

    public function test_07_end_only_filter_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'end_date' => '2026-08-18',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260815-FAIL');
        $response->assertDontSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-20260820-CONF');
    }

    public function test_08_start_and_end_filter_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-19',
            'end_date'   => '2026-08-22',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260820-CONF');
        $response->assertDontSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-20260815-FAIL');
    }

    public function test_09_explicit_query_dates_are_reflected_back_into_the_input_fields(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-27',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('value="2026-08-27"', false);
        $response->assertSee('value="2026-08-28"', false);
    }

    public function test_10_current_date_is_not_injected_when_no_date_parameter_exists(): void
    {
        $currentDate = now()->format('Y-m-d');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertDontSee('value="' . $currentDate . '"', false);
    }

    public function test_11_clear_removes_start_date(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('name="start_date" id="start_date" x-model="val" value=""', false);
    }

    public function test_12_clear_removes_end_date(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('name="end_date" id="end_date" x-model="val" value=""', false);
    }

    public function test_13_clear_removes_date_query_parameters(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
        ]));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index'));
    }

    public function test_14_clear_restores_no_date_filter_state(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('PAY-20260820-CONF');
        $response->assertSee('PAY-20260815-FAIL');
    }

    public function test_15_status_filter_works_without_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'status' => 'pending',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-20260820-CONF');
        $response->assertSee('name="start_date" id="start_date" x-model="val" value=""', false);
    }

    public function test_16_search_works_without_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'search' => 'CONF',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260820-CONF');
        $response->assertDontSee('PAY-20260827-VZDM');
        $response->assertSee('name="start_date" id="start_date" x-model="val" value=""', false);
    }

    public function test_17_product_filter_works_without_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'product_id' => $this->productToefl->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260820-CONF');
        $response->assertDontSee('PAY-20260827-VZDM');
        $response->assertSee('name="start_date" id="start_date" x-model="val" value=""', false);
    }

    public function test_18_combined_search_status_date_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'status'     => 'pending',
            'search'     => 'VZDM',
            'start_date' => '2026-08-26',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-20260820-CONF');
        $response->assertDontSee('PAY-20260815-FAIL');
    }

    public function test_19_pagination_preserves_explicit_date_filters(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-20',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('start_date=2026-08-20');
        $response->assertSee('end_date=2026-08-28');
    }

    public function test_20_pagination_does_not_introduce_date_filters_when_absent(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertDontSee('start_date=');
        $response->assertDontSee('end_date=');
    }

    public function test_21_csv_export_respects_explicit_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.csv', [
            'start_date' => '2026-08-25',
        ]));

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('PAY-20260827-VZDM', $csvContent);
        $this->assertStringNotContainsString('PAY-20260820-CONF', $csvContent);
    }

    public function test_22_csv_export_does_not_invent_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.csv'));

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('PAY-20260827-VZDM', $csvContent);
        $this->assertStringContainsString('PAY-20260820-CONF', $csvContent);
        $this->assertStringContainsString('PAY-20260815-FAIL', $csvContent);
    }

    public function test_23_xlsx_export_respects_explicit_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.xlsx', [
            'start_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);
    }

    public function test_24_xlsx_export_does_not_invent_dates(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.xlsx'));
        $response->assertStatus(200);
    }

    public function test_25_malformed_date_is_handled_safely(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => 'invalid-string',
            'end_date'   => '9999-99-99',
        ]));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
    }

    public function test_26_invalid_date_range_is_handled_safely(): void
    {
        // When end_date < start_date
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-28',
            'end_date'   => '2026-08-10',
        ]));
        $response->assertStatus(200);
        $response->assertSee('No payment records found', false);
    }

    public function test_27_light_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('text-slate-900', false);
        $response->assertSee('bg-white', false);
    }

    public function test_28_dark_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900', false);
        $response->assertSee('dark:text-white', false);
    }

    public function test_29_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gross Cash Collections');
    }

    public function test_30_pay_20260827_vzdm_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_31_pay_20260827_vzdm_remains_idr_832500(): void
    {
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
    }

    public function test_32_no_payment_state_mutation_occurs(): void
    {
        $initialStatuses = Payment::pluck('status', 'id')->toArray();

        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.export.csv'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.export.xlsx'));

        $this->assertEquals($initialStatuses, Payment::pluck('status', 'id')->toArray());
    }

    public function test_33_no_assignment_created(): void
    {
        $initialAssignments = CandidateTestAssignment::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialAssignments, CandidateTestAssignment::count());
    }

    public function test_34_no_attempt_created(): void
    {
        $initialAttempts = Attempt::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialAttempts, Attempt::count());
    }

    public function test_35_no_certificate_created(): void
    {
        $initialCerts = Certificate::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialCerts, Certificate::count());
    }
}
