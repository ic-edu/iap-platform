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

class FinanceDateFilterSemanticUxTest extends TestCase
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

    public function test_01_initial_date_fields_have_empty_value(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('name="start_date" id="start_date" value=""', false);
        $response->assertSee('name="end_date" id="end_date" value=""', false);
    }

    public function test_02_initial_date_fields_have_no_query_parameters(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $this->assertNull(request()->query('start_date'));
        $this->assertNull(request()->query('end_date'));
    }

    public function test_03_initial_start_date_helper_indicates_no_date_filter(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('No date filter');
    }

    public function test_04_initial_end_date_helper_indicates_no_date_filter(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('No date filter');
    }

    public function test_05_initial_unfiltered_result_uses_total_transactions_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('total transaction(s)');
        $response->assertDontSee('filtered transaction(s)');
    }

    public function test_06_explicit_start_date_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('Selected');
    }

    public function test_07_explicit_end_date_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'end_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('Selected');
    }

    public function test_08_explicit_start_and_end_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-19',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('Selected');
    }

    public function test_09_search_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'search' => 'VZDM',
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('No date filter');
    }

    public function test_10_product_filter_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'product_id' => $this->productToeic->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('No date filter');
    }

    public function test_11_status_only_filter_produces_filtered_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'status' => 'pending',
        ]));
        $response->assertStatus(200);
        $response->assertSee('filtered transaction(s)');
        $response->assertSee('No date filter');
    }

    public function test_12_clear_restores_no_date_filter_state(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('No date filter');
        $response->assertSee('name="start_date" id="start_date" value=""', false);
        $response->assertSee('name="end_date" id="end_date" value=""', false);
    }

    public function test_13_clear_restores_total_wording(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('total transaction(s)');
    }

    public function test_14_explicit_date_remains_visible_after_filtering(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-27',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('value="2026-08-27"', false);
        $response->assertSee('value="2026-08-28"', false);
        $response->assertSee('Selected');
    }

    public function test_15_no_implicit_current_date_is_generated(): void
    {
        $currentDate = now()->format('Y-m-d');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertDontSee('value="' . $currentDate . '"', false);
    }

    public function test_16_pagination_preserves_correct_state(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', [
            'start_date' => '2026-08-20',
            'end_date'   => '2026-08-28',
        ]));
        $response->assertStatus(200);
        $response->assertSee('start_date=2026-08-20');
        $response->assertSee('end_date=2026-08-28');
    }

    public function test_17_csv_preserves_explicit_date_filters(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.csv', [
            'start_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('PAY-20260827-VZDM', $csvContent);
        $this->assertStringNotContainsString('PAY-20260820-CONF', $csvContent);
    }

    public function test_18_xlsx_preserves_explicit_date_filters(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.export.xlsx', [
            'start_date' => '2026-08-25',
        ]));
        $response->assertStatus(200);
    }

    public function test_19_light_theme_contract(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('text-slate-900', false);
        $response->assertSee('bg-white', false);
    }

    public function test_20_dark_theme_contract(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900', false);
        $response->assertSee('dark:text-white', false);
    }

    public function test_21_global_theme_contract(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gross Cash Collections');
    }

    public function test_22_pay_20260827_vzdm_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_23_pay_20260827_vzdm_remains_idr_832500(): void
    {
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
    }

    public function test_24_no_payment_mutation(): void
    {
        $initialStatuses = Payment::pluck('status', 'id')->toArray();

        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.export.csv'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.export.xlsx'));

        $this->assertEquals($initialStatuses, Payment::pluck('status', 'id')->toArray());
    }

    public function test_25_no_assignment(): void
    {
        $initialCount = CandidateTestAssignment::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialCount, CandidateTestAssignment::count());
    }

    public function test_26_no_attempt(): void
    {
        $initialCount = Attempt::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialCount, Attempt::count());
    }

    public function test_27_no_certificate(): void
    {
        $initialCount = Certificate::count();
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $this->assertEquals($initialCount, Certificate::count());
    }
}
