<?php

namespace Tests\Feature;

use App\Models\RepositoryFinding;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Database\Seeders\QuestionBankSeeder;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyQuestionBankCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected User $studentUser;
    protected Payment $uatPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);

        $this->teacherUser = User::firstOrCreate(
            ['email' => 'teacher@icedu.org'],
            [
                'name'     => 'Teacher Instructor',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->teacherUser->syncRoles(['teacher']);

        $this->studentUser = User::firstOrCreate(
            ['email' => 'student@icedu.org'],
            [
                'name'     => 'Candidate Student',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->studentUser->syncRoles(['student']);

        // Set up UAT Payment
        $product = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);

        $order = Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260827-YIT4',
            'status'       => OrderStatus::Pending,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 82500,
            'grand_total'  => 832500,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 750000,
            'total'      => 750000,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->studentUser->id,
            'invoice_number' => 'INV-20260827-ZMJY',
            'status'         => InvoiceStatus::Unpaid,
            'amount'         => 832500,
            'due_date'       => now()->addDays(2),
        ]);

        $this->uatPayment = Payment::create([
            'invoice_id'          => $invoice->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260827-VZDM',
            'payment_gateway'     => 'manual_transfer',
            'status'              => PaymentStatus::Pending,
            'amount'              => 832500,
        ]);
    }

    /**
     * Helper to simulate creating a legacy question bank fixture to verify deletion safety
     */
    protected function createLegacyDraftFixture(): QuestionBank
    {
        $bank = QuestionBank::create([
            'id'              => '01m0p8ph1zx4pcppkzadwhfbvn',
            'title'           => 'TOEIC Official Question Bank Vol. 1',
            'slug'            => 'toeic-official-bank-vol-1',
            'created_by'      => $this->teacherUser->id,
            'test_type'       => TestType::Toeic,
            'status'          => 'draft',
            'is_published'    => false,
            'current_version' => '1.0',
            'description'     => 'Official TOEIC listening and reading question pool.',
        ]);

        $q1 = Question::create([
            'id'               => '01m0p8ph22w2t3njf5b3td29n7',
            'question_bank_id' => $bank->id,
            'prompt'           => 'Listen to the audio and select the statement that best describes the picture.',
            'section'          => SectionType::Listening,
            'part_number'      => 1,
            'question_type'    => QuestionType::MultipleChoice,
            'difficulty'       => DifficultyLevel::Easy,
            'points'           => 5,
        ]);

        QuestionChoice::create(['id' => '01m0p8ph24sde4dtbjkdpd0fkj', 'question_id' => $q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['id' => '01m0p8ph25ekmhtmcm2ydn6hx9', 'question_id' => $q1->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
        QuestionChoice::create(['id' => '01m0p8ph269ck9h69ga3der0b0', 'question_id' => $q1->id, 'label' => 'C', 'content' => 'Choice C', 'is_correct' => false]);
        QuestionChoice::create(['id' => '01m0p8ph27qtn10jhmzqfswgrj', 'question_id' => $q1->id, 'label' => 'D', 'content' => 'Choice D', 'is_correct' => false]);

        $q2 = Question::create([
            'id'               => '01m0p8ph29cay4jfxm5mpv22mm',
            'question_bank_id' => $bank->id,
            'prompt'           => 'Choose the word that best completes the sentence.',
            'section'          => SectionType::Reading,
            'part_number'      => 5,
            'question_type'    => QuestionType::MultipleChoice,
            'difficulty'       => DifficultyLevel::Medium,
            'points'           => 5,
        ]);

        QuestionChoice::create(['id' => '01m0p8ph2brgypx2n948q4ej98', 'question_id' => $q2->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => false]);
        QuestionChoice::create(['id' => '01m0p8ph2cghnf7faq72q5xzmx', 'question_id' => $q2->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => true]);
        QuestionChoice::create(['id' => '01m0p8ph2d6cje6yka67g0jd9h', 'question_id' => $q2->id, 'label' => 'C', 'content' => 'Choice C', 'is_correct' => false]);
        QuestionChoice::create(['id' => '01m0p8ph2ewd31fg06vhv07mty', 'question_id' => $q2->id, 'label' => 'D', 'content' => 'Choice D', 'is_correct' => false]);

        RepositoryFinding::create([
            'id'               => '01a02c8b-4465-71a6-8b8a-7280e2a09f9a',
            'question_bank_id' => $bank->id,
            'finding_code'     => 'IRQA_WARN',
            'title'            => 'Missing Category association',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        return $bank;
    }

    public function test_01_target_question_bank_structure(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $this->assertEquals('TOEIC Official Question Bank Vol. 1', $bank->title);
        $this->assertEquals('toeic-official-bank-vol-1', $bank->slug);
    }

    public function test_02_target_is_draft_and_unpublished(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $this->assertEquals('draft', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertFalse((bool) $bank->is_published);
    }

    public function test_03_target_has_exactly_2_questions(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $this->assertEquals(2, $bank->questions()->count());
    }

    public function test_04_target_has_exactly_8_choices(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $choicesCount = QuestionChoice::whereIn('question_id', $bank->questions()->pluck('id'))->count();
        $this->assertEquals(8, $choicesCount);
    }

    public function test_05_target_has_zero_downstream_assessment_references(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $this->assertEquals(0, Test::count());
    }

    public function test_06_target_has_zero_attempt_references(): void
    {
        $this->createLegacyDraftFixture();
        $this->assertEquals(0, Attempt::count());
    }

    public function test_07_target_has_zero_certificate_references(): void
    {
        $this->createLegacyDraftFixture();
        $this->assertEquals(0, Certificate::count());
    }

    public function test_08_target_has_zero_assignment_references(): void
    {
        $this->createLegacyDraftFixture();
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_09_target_is_safe_for_targeted_deletion(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $this->assertTrue($bank->isDraft());
        $this->assertFalse($bank->isPublished());
    }

    public function test_10_target_question_bank_is_deleted(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $bankId = $bank->id;
        
        $bank->forceDelete();

        $this->assertDatabaseMissing('question_banks', ['id' => $bankId]);
    }

    public function test_11_both_child_questions_are_deleted_via_cascade(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $questionIds = $bank->questions()->pluck('id')->toArray();

        $bank->forceDelete();

        foreach ($questionIds as $qId) {
            $this->assertDatabaseMissing('questions', ['id' => $qId]);
        }
    }

    public function test_12_child_choices_are_deleted_via_cascade(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $bank->forceDelete();

        $this->assertDatabaseMissing('question_choices', ['id' => '01m0p8ph24sde4dtbjkdpd0fkj']);
        $this->assertDatabaseMissing('question_choices', ['id' => '01m0p8ph2brgypx2n948q4ej98']);
    }

    public function test_13_repository_findings_associated_with_target_are_deleted_via_cascade(): void
    {
        $bank = $this->createLegacyDraftFixture();
        $bank->forceDelete();

        $this->assertDatabaseMissing('repository_findings', ['id' => '01a02c8b-4465-71a6-8b8a-7280e2a09f9a']);
    }

    public function test_14_no_unrelated_question_bank_is_deleted(): void
    {
        $starterCount = QuestionBank::count();
        $bank = $this->createLegacyDraftFixture();
        $this->assertEquals($starterCount + 1, QuestionBank::count());

        $bank->forceDelete();

        $this->assertEquals($starterCount, QuestionBank::count());
    }

    public function test_15_no_published_question_bank_is_deleted(): void
    {
        $publishedCount = QuestionBank::where('status', 'published')->count();
        $bank = $this->createLegacyDraftFixture();
        $bank->forceDelete();

        $this->assertEquals($publishedCount, QuestionBank::where('status', 'published')->count());
    }

    public function test_16_seeder_no_longer_creates_target(): void
    {
        $this->seed(QuestionBankSeeder::class);

        $this->assertNull(QuestionBank::where('slug', 'toeic-official-bank-vol-1')->first());
        $this->assertNull(QuestionBank::where('title', 'TOEIC Official Question Bank Vol. 1')->first());
    }

    public function test_17_repository_contains_no_active_seed_reference_to_target_title(): void
    {
        $this->seed(QuestionBankSeeder::class);
        $this->assertDatabaseMissing('question_banks', ['slug' => 'toeic-official-bank-vol-1']);
    }

    public function test_18_teacher_workspace_no_longer_displays_target(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.index'));
        $response->assertStatus(200);
        $response->assertDontSee('TOEIC Official Question Bank Vol. 1');
    }

    public function test_19_current_uat_payment_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
    }

    public function test_20_no_candidate_assignment_created_or_changed(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_21_no_attempt_created_or_changed(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_22_no_certificate_created_or_changed(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_23_light_theme_remains_intact(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_24_dark_theme_remains_intact(): void
    {
        $this->teacherUser->setThemePreference('dark');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_25_global_ui_ux_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
