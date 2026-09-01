<?php

use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\RepositoryQualityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('TEST 01-07: Teacher edits are staged and published master question remains immutable', function () {
    Notification::fake();

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $bank = QuestionBank::create([
        'title'        => 'Published Master Test Pool',
        'slug'         => 'published-master-test-pool',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => 'Original baseline prompt text',
        'question_type'    => 'multiple_choice',
        'difficulty'       => 'medium',
        'points'           => 1,
        'explanation'      => 'Original academic rationale',
    ]);

    $choiceA = QuestionChoice::create([
        'question_id' => $question->id,
        'label'       => 'A',
        'content'     => 'Original Choice A',
        'is_correct'  => true,
    ]);

    $choiceB = QuestionChoice::create([
        'question_id' => $question->id,
        'label'       => 'B',
        'content'     => 'Original Choice B',
        'is_correct'  => false,
    ]);

    // Teacher creates revision request
    $revisionRequest = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Proposed updates to question 1',
    ]);

    $item = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $revisionRequest->id,
        'question_bank_id'               => $bank->id,
        'question_id'                    => $question->id,
        'feedback'                       => 'Need to update prompt wording',
        'finding_type'                   => 'teacher_revision_request',
        'status'                         => 'OPEN',
    ]);

    // Teacher updates the question in revision mode
    $response = $this->actingAs($teacher)
        ->post(route('teacher.repository-revisions.update-question', [$revisionRequest->id, $item->id]), [
            'question_id'        => $question->id,
            'prompt'             => 'PROPOSED MODIFIED PROMPT TEXT',
            'question_type'      => 'multiple_choice',
            'explanation'        => 'PROPOSED MODIFIED EXPLANATION',
            'difficulty'         => 'hard',
            'points'             => 2,
            'choices'            => [
                $choiceA->id => ['content' => 'Proposed Choice A', 'label' => 'A'],
                $choiceB->id => ['content' => 'Proposed Choice B (Now Correct)', 'label' => 'B'],
            ],
            'correct_choice_id'  => $choiceB->id,
        ]);

    $response->assertRedirect();

    // Master Question MUST remain 100% untouched
    $question->refresh();
    expect($question->prompt)->toBe('Original baseline prompt text');
    expect($question->explanation)->toBe('Original academic rationale');
    $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty;
    expect($diffVal)->toBe('medium');
    expect($question->points)->toBe(1);

    $choiceA->refresh();
    expect($choiceA->content)->toBe('Original Choice A');
    expect((bool) $choiceA->is_correct)->toBeTrue();

    // Staged data MUST contain Teacher proposed edits
    $item->refresh();
    expect($item->proposed_data)->not->toBeNull();
    expect($item->proposed_data['prompt'])->toBe('PROPOSED MODIFIED PROMPT TEXT');
    expect($item->proposed_data['explanation'])->toBe('PROPOSED MODIFIED EXPLANATION');
    expect($item->proposed_data['difficulty'])->toBe('hard');
    expect($item->proposed_data['points'])->toBe(2);
});

test('TEST 12-17: Dedicated RM Revision Queue displays active revisions and handles filters', function () {
    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title'        => 'Institutional Pool 101',
        'slug'         => 'institutional-pool-101',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $openRev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Open revision note',
    ]);

    $resubmittedRev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'RESUBMITTED',
        'notes'            => 'Resubmitted revision note',
    ]);

    $approvedRev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'COMPLETED',
        'notes'            => 'Approved revision note',
    ]);

    // Access Dedicated Revisions Queue
    $response = $this->actingAs($rm)->get(route('admin.repository-manager.revisions.index'));
    $response->assertOk();
    $response->assertSee('Teacher Repository Revision Queue');
    $response->assertSee('Open revision note');
    $response->assertSee('Resubmitted revision note');
    $response->assertDontSee('Approved revision note');

    // Access with Approved Filter
    $approvedResponse = $this->actingAs($rm)->get(route('admin.repository-manager.revisions.index', ['status' => 'approved']));
    $approvedResponse->assertOk();
    $approvedResponse->assertSee('Approved revision note');

    // RM Dashboard links
    $dashResponse = $this->actingAs($rm)->get(route('admin.repository-manager.dashboard'));
    $dashResponse->assertOk();
    $dashResponse->assertSee(route('admin.repository-manager.revisions.index'));
    $dashResponse->assertSee(route('admin.repository-manager.revisions.review', $openRev->id));
});

test('TEST 18-23: Dedicated RM Revision Review Workspace renders Before vs After comparison', function () {
    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title'        => 'TOEFL Listening Bank',
        'slug'         => 'toefl-listening-bank',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => 'Original prompt on live server',
        'question_type'    => 'multiple_choice',
        'difficulty'       => 'medium',
        'points'           => 1,
    ]);

    $rev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Teacher revision requirement text',
    ]);

    $item = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $rev->id,
        'question_bank_id'               => $bank->id,
        'question_id'                    => $question->id,
        'feedback'                       => 'Fix grammar in question prompt',
        'status'                         => 'OPEN',
        'proposed_data'                  => [
            'prompt'      => 'Proposed corrected prompt text',
            'explanation' => 'Proposed corrected explanation',
            'difficulty'  => 'hard',
            'points'      => 2,
        ],
    ]);

    $response = $this->actingAs($rm)->get(route('admin.repository-manager.revisions.review', $rev->id));
    $response->assertOk();
    $response->assertSee('Repository Revision Review');
    $response->assertSee('Teacher revision requirement text');
    $response->assertSee('Original prompt on live server');
    $response->assertSee('Proposed corrected prompt text');
    $response->assertSee('Approve');
    $response->assertSee('Apply Revision to Master');
    $response->assertSee('Request Changes from Teacher');
    $response->assertSee('Reject Revision Request');
});

test('TEST 24-26: IRQA Quality Scanner does NOT auto-close human teacher_revision_request items', function () {
    $teacher = User::factory()->create(['status' => 'active']);
    $bank = QuestionBank::create([
        'title'        => 'IRQA Quality Test Bank',
        'slug'         => 'irqa-quality-test-bank',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $rev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Manual human note',
    ]);

    $humanItem = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $rev->id,
        'question_bank_id'               => $bank->id,
        'feedback'                       => 'tes uat notifikasi',
        'finding_type'                   => 'teacher_revision_request',
        'status'                         => 'OPEN',
    ]);

    $qualityService = app(RepositoryQualityService::class);
    $qualityService->syncRepositoryFindings($bank);

    $humanItem->refresh();
    // Human item MUST remain OPEN!
    expect($humanItem->status)->toBe('OPEN');
});

test('TEST 27-34: RM Approve & Apply atomically updates master question and notifies Teacher', function () {
    Notification::fake();

    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title'        => 'Atomic Apply Test Bank',
        'slug'         => 'atomic-apply-test-bank',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => 'Original Prompt Before Approval',
        'difficulty'       => 'easy',
        'points'           => 1,
    ]);

    $choiceA = QuestionChoice::create([
        'question_id' => $question->id,
        'label'       => 'A',
        'content'     => 'Original Choice A',
        'is_correct'  => true,
    ]);

    $rev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Approve test',
    ]);

    $item = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $rev->id,
        'question_bank_id'               => $bank->id,
        'question_id'                    => $question->id,
        'feedback'                       => 'Update prompt',
        'status'                         => 'OPEN',
        'proposed_data'                  => [
            'prompt'     => 'APPROVED MASTER PROMPT',
            'difficulty' => 'hard',
            'points'     => 3,
            'choices'    => [
                ['label' => 'A', 'content' => 'New Choice A', 'is_correct' => false],
                ['label' => 'B', 'content' => 'New Choice B', 'is_correct' => true],
            ],
        ],
    ]);

    $response = $this->actingAs($rm)->post(route('admin.repository-manager.revisions.approve', $rev->id), [
        'notes' => 'Institutional approval applied',
    ]);

    $response->assertRedirect(route('admin.repository-manager.revisions.index'));

    // Master Question MUST now reflect the approved changes
    $question->refresh();
    expect($question->prompt)->toBe('APPROVED MASTER PROMPT');
    $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty;
    expect($diffVal)->toBe('hard');
    expect($question->points)->toBe(3);

    // Choices must be updated
    expect($question->choices()->count())->toBe(2);
    expect($question->choices()->where('is_correct', true)->first()->content)->toBe('New Choice B');

    // Statuses
    $rev->refresh();
    expect($rev->status)->toBe('COMPLETED');
    $item->refresh();
    expect($item->status)->toBe('VERIFIED');

    // Audit log
    expect(RepositoryActivityLog::where('action', 'rm_revision_approved')->exists())->toBeTrue();

    // Teacher Notification
    Notification::assertSentTo($teacher, EnterpriseSystemNotification::class, function ($notification) {
        return $notification->type === 'REPOSITORY_REVISION_APPROVED';
    });
});

test('TEST 36-41: RM Request Changes leaves master unchanged and notifies Teacher', function () {
    Notification::fake();

    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title'        => 'Request Changes Test Bank',
        'slug'         => 'request-changes-test-bank',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => 'Master Prompt Remains Same',
    ]);

    $rev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Teacher draft note',
    ]);

    $response = $this->actingAs($rm)->post(route('admin.repository-manager.revisions.request-changes', $rev->id), [
        'notes' => 'Please provide more explanation for choice C.',
    ]);

    $response->assertRedirect(route('admin.repository-manager.revisions.index'));

    // Master unchanged
    $question->refresh();
    expect($question->prompt)->toBe('Master Prompt Remains Same');

    // Rev status
    $rev->refresh();
    expect($rev->status)->toBe('IN_PROGRESS');
    expect($rev->notes)->toBe('Please provide more explanation for choice C.');

    // Teacher Notification
    Notification::assertSentTo($teacher, EnterpriseSystemNotification::class, function ($notification) {
        return $notification->type === 'REPOSITORY_REVISION_CHANGES_REQUESTED';
    });
});

test('TEST 42-45: RM Reject leaves master unchanged and closes revision', function () {
    Notification::fake();

    $rm = User::factory()->create(['status' => 'active']);
    $rm->assignRole('repository-manager');

    $teacher = User::factory()->create(['status' => 'active']);
    $teacher->assignRole('teacher');

    $bank = QuestionBank::create([
        'title'        => 'Reject Revision Test Bank',
        'slug'         => 'reject-revision-test-bank',
        'status'       => 'published',
        'is_published' => true,
        'created_by'   => $teacher->id,
    ]);

    $question = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => 'Master Prompt Survives Rejection',
    ]);

    $rev = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $teacher->id,
        'requested_by_id'  => $teacher->id,
        'status'           => 'OPEN',
        'notes'            => 'Reject me',
    ]);

    $response = $this->actingAs($rm)->post(route('admin.repository-manager.revisions.reject', $rev->id), [
        'notes' => 'Revision request does not adhere to curriculum guidelines.',
    ]);

    $response->assertRedirect(route('admin.repository-manager.revisions.index'));

    // Master unchanged
    $question->refresh();
    expect($question->prompt)->toBe('Master Prompt Survives Rejection');

    // Rev status
    $rev->refresh();
    expect($rev->status)->toBe('REJECTED');

    // Teacher Notification
    Notification::assertSentTo($teacher, EnterpriseSystemNotification::class, function ($notification) {
        return $notification->type === 'REPOSITORY_REVISION_REJECTED';
    });
});
