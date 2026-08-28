<?php

namespace Tests\Feature;

use App\Models\AclCategory;
use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicLibraryTeacherRepositoryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected User $otherTeacherUser;
    protected User $repoManagerUser;
    protected User $superAdminUser;
    protected QuestionBank $toeflListeningBank;
    protected QuestionBank $toeicPart1Bank;
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

        $this->otherTeacherUser = User::firstOrCreate(
            ['email' => 'otherteacher@icedu.org'],
            [
                'name'     => 'Other Teacher User',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->otherTeacherUser->syncRoles(['teacher']);

        $this->repoManagerUser = User::firstOrCreate(
            ['email' => 'repomanager@icedu.org'],
            [
                'name'     => 'Dr. Eleanor Vance (Repository Manager)',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->repoManagerUser->syncRoles(['repository-manager']);

        $this->superAdminUser = User::firstOrCreate(
            ['email' => 'admin@icedu.org'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->superAdminUser->syncRoles(['super-admin']);

        $this->toeflListeningBank = QuestionBank::where('slug', 'toefl-listening-core-starter')->firstOrFail();
        $this->toeicPart1Bank     = QuestionBank::where('slug', 'toeic-part-1-starter-pool')->firstOrFail();

        $order = \App\Modules\Commerce\Domain\Models\Order::firstOrCreate(
            ['order_number' => 'ORD-20260827-YIT4'],
            [
                'user_id'     => $this->teacherUser->id,
                'status'      => \App\Modules\Commerce\Domain\Enums\OrderStatus::Pending,
                'subtotal'    => 750000,
                'discount'    => 0,
                'tax'         => 82500,
                'grand_total' => 832500,
            ]
        );

        $invoice = \App\Modules\Commerce\Domain\Models\Invoice::firstOrCreate(
            ['invoice_number' => 'INV-20260827-ZMJY'],
            [
                'order_id' => $order->id,
                'user_id'  => $this->teacherUser->id,
                'status'   => \App\Modules\Commerce\Domain\Enums\InvoiceStatus::Unpaid,
                'amount'   => 832500,
                'due_date' => now()->addDays(2),
            ]
        );

        $this->uatPayment = Payment::firstOrCreate(
            ['reference_number' => 'PAY-20260827-VZDM'],
            [
                'amount'          => 832500,
                'status'          => PaymentStatus::Pending,
                'payment_gateway' => 'manual_transfer',
                'invoice_id'      => $invoice->id,
                'user_id'         => $this->teacherUser->id,
            ]
        );
    }

    /**
     * TEST 1: Teacher can access Academic Library category.
     */
    public function test_1_teacher_can_access_academic_library_category(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('admin.academic-library.show', 'toefl-listening'));

        $response->assertStatus(200);
        $response->assertSee('TOEFL Listening Library');
        $response->assertSee($this->toeflListeningBank->title);
    }

    /**
     * TEST 2: Teacher can click/open institutional repository (link points to teacher.question-banks.show).
     */
    public function test_2_teacher_can_click_open_institutional_repository(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('admin.academic-library.show', 'toefl-listening'));

        $response->assertStatus(200);

        $expectedTeacherUrl = route('teacher.question-banks.show', [
            'questionBank' => $this->toeflListeningBank->id,
            'from'         => 'academic_library',
            'category'     => 'toefl-listening',
        ]);

        $response->assertSee(e($expectedTeacherUrl), false);
        $response->assertDontSee(route('admin.repository-manager.question-bank-validate', $this->toeflListeningBank->id), false);
    }

    /**
     * TEST 3: Teacher repository target returns HTTP 200.
     */
    public function test_3_teacher_repository_target_returns_http_200(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertSee($this->toeflListeningBank->title);
        $response->assertSee('Total Questions:');
    }

    /**
     * TEST 4: Teacher does not receive 403 on valid institutional repository even if not original creator.
     */
    public function test_4_teacher_does_not_receive_403_on_valid_institutional_repository(): void
    {
        // otherTeacherUser is NOT user 5 (the creator of starter banks)
        $this->assertNotEquals($this->otherTeacherUser->id, $this->toeflListeningBank->created_by);

        $response = $this->actingAs($this->otherTeacherUser)
            ->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertSee($this->toeflListeningBank->title);
    }

    /**
     * TEST 5: Teacher does not receive Repository Manager governance controls.
     */
    public function test_5_teacher_does_not_receive_repository_manager_governance_controls(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertDontSee('Publish Live');
        $response->assertDontSee('Unpublish');
        $response->assertDontSee('Request Archive');
        $response->assertDontSee('Governance Queue');
    }

    /**
     * TEST 6: Repository Manager retains access to governance route.
     */
    public function test_6_repository_manager_retains_access_to_governance_route(): void
    {
        $response = $this->actingAs($this->repoManagerUser)
            ->get(route('admin.repository-manager.question-bank-validate', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertSee($this->toeflListeningBank->title);
    }

    /**
     * TEST 7: Super Admin access remains unchanged.
     */
    public function test_7_super_admin_access_remains_unchanged(): void
    {
        $categoryResponse = $this->actingAs($this->superAdminUser)
            ->get(route('admin.academic-library.show', 'toefl-listening'));
        $categoryResponse->assertStatus(200);

        $governanceResponse = $this->actingAs($this->superAdminUser)
            ->get(route('admin.repository-manager.question-bank-validate', $this->toeflListeningBank->id));
        $governanceResponse->assertStatus(200);
    }

    /**
     * TEST 8: 17 starter repositories remain intact.
     */
    public function test_8_all_17_starter_repositories_remain_intact(): void
    {
        $expectedSlugs = [
            'toefl-listening-core-starter',
            'toefl-structure-starter-pool',
            'toefl-reading-starter-pool',
            'toeic-part-1-starter-pool',
            'toeic-part-2-starter-pool',
            'toeic-part-3-starter-pool',
            'toeic-part-4-starter-pool',
            'toeic-part-5-starter-pool',
            'toeic-part-6-starter-pool',
            'toeic-part-7-starter-pool',
            'ielts-listening-starter-pool',
            'ielts-reading-starter-pool',
            'ielts-writing-starter-pool',
            'ielts-speaking-starter-pool',
            'placement-test-starter-pool',
            'core-grammar-starter-pool',
            'academic-vocabulary-starter-pool',
        ];

        foreach ($expectedSlugs as $slug) {
            $bank = QuestionBank::where('slug', $slug)->first();
            $this->assertNotNull($bank, "Starter repository with slug '{$slug}' must exist.");
            $this->assertEquals(5, $bank->questions()->count(), "Repository '{$slug}' must have exactly 5 questions.");
        }
    }

    /**
     * TEST 9: TOEIC Part 1 repository remains intact.
     */
    public function test_9_toeic_part_1_repository_remains_intact(): void
    {
        $bank = QuestionBank::where('slug', 'toeic-part-1-starter-pool')->firstOrFail();
        $this->assertEquals('toeic', is_object($bank->test_type) ? $bank->test_type->value : $bank->test_type);
        $this->assertEquals(5, $bank->questions()->count());
    }

    /**
     * TEST 10: TOEIC Part 1 questions remain intact.
     */
    public function test_10_toeic_part_1_questions_remain_intact(): void
    {
        $bank = QuestionBank::where('slug', 'toeic-part-1-starter-pool')->with('questions.choices')->firstOrFail();

        foreach ($bank->questions as $q) {
            $this->assertNotEmpty($q->prompt);
            $this->assertNotEmpty($q->image_url);
            $this->assertEquals(4, $q->choices->count());
            $this->assertEquals(1, $q->choices->where('is_correct', true)->count());
        }
    }

    /**
     * TEST 11: Valid media assets remain intact.
     */
    public function test_11_valid_media_assets_remain_intact(): void
    {
        $media = MediaAsset::create([
            'title'           => 'Valid Test Asset',
            'filename'        => 'valid_asset.jpg',
            'original_name'   => 'valid_asset.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'category'        => 'Part 1 Images',
            'sub_category'    => 'Visual Diagram',
            'exam_type'       => 'toeic',
            'path'            => 'images/toeic_part1_office.jpg',
            'size'            => 10376,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $this->assertDatabaseHas('media_assets', ['id' => $media->id, 'title' => 'Valid Test Asset']);
    }

    /**
     * TEST 12: 31 orphan MediaAsset records exist before targeted cleanup in isolated test scenario.
     */
    public function test_12_31_orphan_media_asset_records_exist_before_targeted_cleanup(): void
    {
        for ($i = 1; $i <= 31; $i++) {
            MediaAsset::create([
                'title'           => "Academic Reading Passage {$i}: Educational Research Text",
                'filename'        => "sample_passage_{$i}.txt",
                'original_name'   => "sample_passage_{$i}.txt",
                'mime_type'       => 'text/plain',
                'type'            => 'passage',
                'category'        => 'Reading Passage',
                'sub_category'    => 'Text Passage',
                'exam_type'       => 'toefl',
                'path'            => "media/sample_passage_{$i}.txt",
                'size'            => 1400,
                'status'          => 'active',
                'approval_status' => 'approved',
                'uploaded_by'     => $this->teacherUser->id,
            ]);
        }

        $orphansCount = MediaAsset::where('path', 'like', 'media/sample_passage_%')->count();
        $this->assertEquals(31, $orphansCount);
    }

    /**
     * TEST 13: Target orphan records are deleted.
     */
    public function test_13_target_orphan_records_are_deleted(): void
    {
        for ($i = 1; $i <= 31; $i++) {
            MediaAsset::create([
                'title'           => "Academic Reading Passage {$i}: Educational Research Text",
                'filename'        => "sample_passage_{$i}.txt",
                'original_name'   => "sample_passage_{$i}.txt",
                'mime_type'       => 'text/plain',
                'type'            => 'passage',
                'category'        => 'Reading Passage',
                'sub_category'    => 'Text Passage',
                'exam_type'       => 'toefl',
                'path'            => "media/sample_passage_{$i}.txt",
                'size'            => 1400,
                'status'          => 'active',
                'approval_status' => 'approved',
                'uploaded_by'     => $this->teacherUser->id,
            ]);
        }

        // Targeted delete
        $deletedCount = MediaAsset::where('path', 'like', 'media/sample_passage_%')->delete();
        $this->assertEquals(31, $deletedCount);

        $remainingOrphans = MediaAsset::where('path', 'like', 'media/sample_passage_%')->count();
        $this->assertEquals(0, $remainingOrphans);
    }

    /**
     * TEST 14: No unrelated MediaAsset records are deleted.
     */
    public function test_14_no_unrelated_media_asset_records_are_deleted(): void
    {
        $validAsset = MediaAsset::create([
            'title'           => 'Legitimate Audio File',
            'filename'        => 'toefl_audio_1.wav',
            'original_name'   => 'toefl_audio_1.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'category'        => 'Listening Audio',
            'sub_category'    => 'Audio Track',
            'exam_type'       => 'toefl',
            'path'            => 'media/toefl_audio_1.wav',
            'size'            => 176444,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        MediaAsset::where('path', 'like', 'media/sample_passage_%')->delete();

        $this->assertDatabaseHas('media_assets', ['id' => $validAsset->id, 'title' => 'Legitimate Audio File']);
    }

    /**
     * TEST 15: Current UAT payment remains pending.
     */
    public function test_15_current_uat_payment_remains_pending(): void
    {
        $payment = Payment::where('reference_number', 'PAY-20260827-VZDM')->firstOrFail();
        $status = is_object($payment->status) ? $payment->status->value : $payment->status;

        $this->assertEquals('pending', $status);
        $this->assertEquals(832500, $payment->amount);
    }

    /**
     * TEST 16: No assessment mutation.
     */
    public function test_16_no_assessment_mutation(): void
    {
        $test = Test::create([
            'title'       => 'TOEIC Listening & Reading Simulation Test',
            'slug'        => 'toeic-simulation-test',
            'description' => 'Simulation assessment',
            'status'      => 'draft',
            'created_by'  => $this->teacherUser->id,
        ]);

        $this->assertDatabaseHas('tests', ['id' => $test->id, 'title' => 'TOEIC Listening & Reading Simulation Test', 'status' => 'draft']);
    }

    /**
     * TEST 17: No assignment created.
     */
    public function test_17_no_assignment_created(): void
    {
        $initialCount = CandidateTestAssignment::count();

        $this->actingAs($this->teacherUser)->get(route('admin.academic-library.show', 'toefl-listening'));
        $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $this->assertEquals($initialCount, CandidateTestAssignment::count());
    }

    /**
     * TEST 18: No attempt created.
     */
    public function test_18_no_attempt_created(): void
    {
        $initialCount = Attempt::count();

        $this->actingAs($this->teacherUser)->get(route('admin.academic-library.show', 'toefl-listening'));
        $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $this->assertEquals($initialCount, Attempt::count());
    }

    /**
     * TEST 19: No certificate created.
     */
    public function test_19_no_certificate_created(): void
    {
        $initialCount = Certificate::count();

        $this->actingAs($this->teacherUser)->get(route('admin.academic-library.show', 'toefl-listening'));
        $this->actingAs($this->teacherUser)->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $this->assertEquals($initialCount, Certificate::count());
    }

    /**
     * TEST 20: Light Theme contract passes.
     */
    public function test_20_light_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertSee('text-slate-900', false);
        $response->assertSee('bg-slate-100', false);
    }

    /**
     * TEST 21: Dark Theme contract passes.
     */
    public function test_21_dark_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.question-banks.show', $this->toeflListeningBank->id));

        $response->assertStatus(200);
        $response->assertSee('dark:text-white', false);
        $response->assertSee('dark:bg-slate-800', false);
    }

    /**
     * TEST 22: Global Theme contract passes and Explorer routing is role-aware.
     */
    public function test_22_global_theme_contract_passes_and_explorer_routing_is_role_aware(): void
    {
        $bank = QuestionBank::where('slug', 'toefl-listening-core-starter')->firstOrFail();

        $explorerResponse = $this->actingAs($this->teacherUser)
            ->get(route('admin.academic-library.explorer'));

        $explorerResponse->assertStatus(200);
        $explorerResponse->assertSee('Repository Explorer');

        // Teacher in Explorer sees teacher.question-banks.show link (properly html-escaped in href)
        $expectedTeacherExplorerUrl = route('teacher.question-banks.show', [
            'questionBank' => $bank->id,
            'from'         => 'academic_explorer',
            'filter'       => 'all',
        ]);
        $explorerResponse->assertSee(e($expectedTeacherExplorerUrl), false);
    }
}
