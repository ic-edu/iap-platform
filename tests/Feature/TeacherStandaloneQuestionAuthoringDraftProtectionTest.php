<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->teacher = User::factory()->create([
        'name'   => 'TOEIC Question Teacher Author',
        'email'  => 'toeic_question_author@icedu.org',
        'status' => 'active',
    ]);
    $this->teacher->assignRole('teacher');

    $this->test = Test::create([
        'title'            => 'TOEIC Mock Test Group - UAT Class 9A',
        'slug'             => 'toeic-mock-test-group-uat-class-9a-' . \Illuminate\Support\Str::random(6),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    $this->sectionPart1 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'instructions' => 'Look at the photograph and choose the best statement.',
        'order'        => 1,
    ]);

    $this->sectionPart2 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 2: Question-Response',
        'section_type' => 'listening',
        'instructions' => 'Listen to the question and three responses.',
        'order'        => 2,
    ]);

    $this->sectionPart5 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 5: Incomplete Sentences',
        'section_type' => 'reading',
        'instructions' => 'Choose the one word or phrase that best completes the sentence.',
        'order'        => 5,
    ]);
});

test('p1 draft 01: standalone question modal renders draft restored banner and discard button', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('id="q-draft-restored-banner"', false);
    $response->assertSee('onclick="discardAuthoredQuestionDraft(true)"', false);
    $response->assertSee('Unsaved draft restored.', false);
    $response->assertSee('Discard Draft', false);
});

test('p1 draft 02: standalone question modal backdrop ignores click to protect unsaved work', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('id="create-authored-question-modal"', $content);
    $this->assertStringContainsString('onclick="closeCreateAuthoredQuestionModal(event)"', $content);
    $this->assertStringContainsString('function closeCreateAuthoredQuestionModal(e)', $content);
    $this->assertStringContainsString('e.target === document.getElementById(\'create-authored-question-modal\')', $content);
    $this->assertStringContainsString('// Backdrop click: do not dismiss modal to protect unsaved work', $content);
});

test('p1 draft 03: standalone question modal X and cancel buttons call guarded close', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('onclick="attemptCloseAuthoredQuestionModal()"', $content);
    $this->assertStringContainsString('function attemptCloseAuthoredQuestionModal()', $content);
    $this->assertStringContainsString('isAuthoredQuestionFormDirty()', $content);
});

test('p1 draft 04: standalone question draft key format strictly includes user, test, section, and part number', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('function getAuthoredQuestionDraftStorageKey(sectionId, partNumber', $content);
    $this->assertStringContainsString('iap:question_draft_', $content);
    $this->assertStringContainsString($this->test->id, $content);
});

test('p2 draft 01: part 2 question-response retains 3-choice contract and audio draft storage', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('function openCreateAuthoredQuestionModal(sectionId, partNumber, sectionTitle, sectionType)', $content);
    $this->assertStringContainsString('onCreateModalToeicPartChange', $content);
    $this->assertStringContainsString('getAuthoredQuestionCurrentSnapshot()', $content);
    $this->assertStringContainsString('triggerAuthoredQuestionDraftSave()', $content);
});

test('p2 draft 02: part 2 question-response audio media picker callbacks trigger draft save', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('selectQuestionMediaItem(id, title, type, url)', $content);
    $this->assertStringContainsString('removeQuestionAttachedMedia(mode, type)', $content);
});

test('p5 draft 01: part 5 incomplete sentences tracks dirty fields across prompt, choices, correct_choice, and explanation', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('getAuthoredQuestionCurrentSnapshot', $content);
    $this->assertStringContainsString('isAuthoredQuestionFormDirty', $content);
    $this->assertStringContainsString('window._qBaselineSnapshot', $content);
});

test('toeic draft 01: escape key triggers guarded close for standalone question modal', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString("if (event.key === 'Escape')", $content);
    $this->assertStringContainsString('const authoredQModal = document.getElementById(\'create-authored-question-modal\');', $content);
    $this->assertStringContainsString('attemptCloseAuthoredQuestionModal();', $content);
});

test('toeic draft 02: beforeunload handler detects dirty standalone question modal', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString("window.addEventListener('beforeunload'", $content);
    $this->assertStringContainsString('window._authoredQSubmitting', $content);
    $this->assertStringContainsString('isAuthoredQuestionFormDirty()', $content);
});

test('toeic draft 03: form input and change events trigger transient auto-save', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('const authoredQForm = document.getElementById(\'create-authored-question-form\');', $content);
    $this->assertStringContainsString('authoredQForm.addEventListener(\'input\'', $content);
    $this->assertStringContainsString('authoredQForm.addEventListener(\'change\'', $content);
    $this->assertStringContainsString('authoredQForm.addEventListener(\'submit\'', $content);
    $this->assertStringContainsString('sessionStorage.removeItem(getAuthoredQuestionDraftStorageKey(secId, partNum))', $content);
});

test('toeic draft 04: discardAuthoredQuestionDraft clears session storage and resets state', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    $this->assertStringContainsString('function discardAuthoredQuestionDraft(reopenFresh = false)', $content);
    $this->assertStringContainsString('sessionStorage.removeItem(getAuthoredQuestionDraftStorageKey(secId, partNum))', $content);
    $this->assertStringContainsString('banner.classList.add(\'hidden\');', $content);
});

test('toeic draft 05: audio group and passage group unsaved protection remains intact and functional', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $content = $response->getContent();

    // Audio group protection
    $this->assertStringContainsString('id="ag-draft-restored-banner"', $content);
    $this->assertStringContainsString('attemptCloseAudioGroupModal()', $content);
    $this->assertStringContainsString('isAudioGroupFormDirty()', $content);

    // Passage group protection
    $this->assertStringContainsString('id="pg-draft-restored-banner"', $content);
    $this->assertStringContainsString('attemptClosePassageGroupModal()', $content);
    $this->assertStringContainsString('isPassageGroupFormDirty()', $content);
});
