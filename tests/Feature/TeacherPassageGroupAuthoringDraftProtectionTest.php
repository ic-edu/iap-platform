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
        'name'   => 'TOEIC Reading Teacher Author',
        'email'  => 'toeic_reading_author@icedu.org',
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

    $this->sectionPart6 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => 'reading',
        'instructions' => 'Directions for Text Completion',
        'order'        => 6,
    ]);

    $this->sectionPart7 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 7: Reading Comprehension',
        'section_type' => 'reading',
        'instructions' => 'Directions for Reading Comprehension',
        'order'        => 7,
    ]);
});

test('p7 draft 01: passage group modal renders draft restored banner and discard button', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('id="pg-draft-restored-banner"', false);
    $response->assertSee('onclick="discardPassageGroupDraft(true)"', false);
    $response->assertSee('Unsaved draft restored.', false);
    $response->assertSee('Discard Draft', false);
});

test('p7 draft 02: passage group modal header close and cancel buttons invoke close guard', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('attemptClosePassageGroupModal()', false);
    $response->assertSee('aria-label="Close passage group modal"', false);
});

test('p7 draft 03: passage group modal includes session storage key generation and restore logic', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('getPassageGroupDraftStorageKey', false);
    $response->assertSee('triggerPassageGroupDraftSave', false);
    $response->assertSee('getPassageGroupCurrentSnapshot', false);
    $response->assertSee('sessionStorage.getItem(getPassageGroupDraftStorageKey', false);
});

test('p7 draft 04: passage group modal includes dirty check and confirmation guard', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('isPassageGroupFormDirty', false);
    $response->assertSee('attemptClosePassageGroupModal', false);
    $response->assertSee('forceClosePassageGroupModal', false);
    $response->assertSee('window.iapConfirm', false);
});

test('p7 draft 05: passage group modal backdrop click does not dismiss modal', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('function closeCreatePassageGroupModal(e = null)', false);
    $response->assertSee('if (e && e.target === document.getElementById(\'create-passage-group-modal\'))', false);
});

test('p7 draft 06: passage group modal includes beforeunload guard', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('beforeunload', false);
    $response->assertSee('isPassageGroupFormDirty()', false);
});

test('p7 draft 07: escape key listener targets passage group guard when modal is open', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee("event.key === 'Escape'", false);
    $response->assertSee('attemptClosePassageGroupModal();', false);
});

test('p7 draft 08: successful submission clears passage group transient draft', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('sessionStorage.removeItem(getPassageGroupDraftStorageKey(secId, window._currentPassageGroupId))', false);
});

test('p7 draft 09: user scoped draft storage key includes authenticated user id', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();

    $expectedKeyPrefix = "iap:passage_group_draft_{$this->teacher->id}_{$this->test->id}_";
    $response->assertSee($expectedKeyPrefix, false);
});

test('p7 draft 10: cross user isolation teacher a draft key differs from teacher b', function () {
    $teacherB = User::factory()->create([
        'name'   => 'TOEIC Teacher B',
        'email'  => 'teacher_b@icedu.org',
        'status' => 'active',
    ]);
    $teacherB->assignRole('teacher');

    $testB = Test::create([
        'title'            => 'TOEIC Teacher B Practice Test',
        'slug'             => 'toeic-teacher-b-practice-test-' . \Illuminate\Support\Str::random(6),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $teacherB->id,
        'assigned_to'      => $teacherB->id,
    ]);

    $responseA = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $responseA->assertOk();
    $expectedKeyA = "iap:passage_group_draft_{$this->teacher->id}_{$this->test->id}_";
    $responseA->assertSee($expectedKeyA, false);

    $responseB = $this->actingAs($teacherB)->get(route('teacher.tests.show', $testB->id));
    $responseB->assertOk();
    $expectedKeyB = "iap:passage_group_draft_{$teacherB->id}_{$testB->id}_";
    $responseB->assertSee($expectedKeyB, false);
    $responseB->assertDontSee($expectedKeyA, false);
});

test('p7 draft 11: cross test isolation test 1 key differs from test 2', function () {
    $test2 = Test::create([
        'title'            => 'TOEIC Second Practice Test',
        'slug'             => 'toeic-second-practice-test-' . \Illuminate\Support\Str::random(6),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    $responseTest1 = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $responseTest1->assertOk();
    $responseTest1->assertSee("iap:passage_group_draft_{$this->teacher->id}_{$this->test->id}_", false);

    $responseTest2 = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $test2->id));
    $responseTest2->assertOk();
    $responseTest2->assertSee("iap:passage_group_draft_{$this->teacher->id}_{$test2->id}_", false);
    $responseTest2->assertDontSee("iap:passage_group_draft_{$this->teacher->id}_{$this->test->id}_", false);
});

test('p7 draft 12: part 6 and part 7 both share unsaved work protection modal', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('id="create-passage-group-modal"', false);
    $response->assertSee('openCreatePassageGroupModal', false);
    $response->assertSee('openEditPassageGroupModal', false);
    $response->assertSee('isPart6', false);
});
