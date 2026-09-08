<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerDraftInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $rm;
    protected User $superAdmin;
    protected User $teacher;
    protected User $otherTeacher;
    protected User $ra;
    protected User $finance;
    protected User $coordinator;
    protected User $candidate;

    protected AssessmentRequest $assessmentRequest;
    protected Test $governedTest;
    protected TestSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->rm = User::factory()->create(['name' => 'RM Governer', 'email' => 'rm@iap.test']);
        $this->rm->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'sa@iap.test']);
        $this->superAdmin->assignRole('super-admin');

        $this->teacher = User::factory()->create(['name' => 'Teacher Instructor', 'email' => 'teacher@iap.test']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['name' => 'Teacher Other', 'email' => 'other.teacher@iap.test']);
        $this->otherTeacher->assignRole('teacher');

        $this->ra = User::factory()->create(['name' => 'Operational Admin', 'email' => 'ra@iap.test']);
        $this->ra->assignRole('admin');

        $this->finance = User::factory()->create(['name' => 'Finance Admin', 'email' => 'finance@iap.test']);
        $this->finance->assignRole('finance');

        $this->coordinator = User::factory()->create(['name' => 'Org Coordinator', 'email' => 'coordinator@iap.test']);
        $this->coordinator->assignRole('organization-coordinator');

        $this->candidate = User::factory()->create(['name' => 'Candidate CA01', 'email' => 'candidate.ca01@iap.test']);
        $this->candidate->assignRole('student');

        // Governed Request & Draft Fixtures
        $this->assessmentRequest = AssessmentRequest::create([
            'title'              => 'TOEIC Mock Test',
            'test_type'          => 'toeic',
            'candidate_id'       => $this->candidate->id,
            'program_context'    => 'iC.edu UAT University — Class 9A',
            'required_sections'  => 'Section 1: General Core',
            'notes'              => 'Skill UAT',
            'requested_deadline' => '2026-10-01',
            'requested_by'       => $this->ra->id,
            'status'             => 'draft_created',
        ]);

        $this->governedTest = Test::create([
            'title'                 => 'TOEIC Mock Test Group UAT',
            'slug'                  => 'toeic-mock-test-group-uat-fixture',
            'test_type'             => TestType::Toeic,
            'assessment_mode'       => AssessmentMode::RealTest,
            'duration_minutes'      => 120,
            'pass_score'            => 700,
            'status'                => 'draft',
            'is_published'          => false,
            'created_by'            => $this->rm->id,
            'assigned_to'           => $this->teacher->id,
            'assessment_request_id' => $this->assessmentRequest->id,
        ]);

        $this->assessmentRequest->update(['test_id' => $this->governedTest->id]);

        $this->section = TestSection::create([
            'test_id'      => $this->governedTest->id,
            'title'        => 'Section 1: General Core',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);
    }

    /**
     * TEST 1: RM can access the dedicated read-only draft inspection surface with complete metadata.
     */
    public function test_rm_can_access_draft_inspection_surface_with_governance_data(): void
    {
        $response = $this->actingAs($this->rm)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertStatus(200);
        $response->assertSee('TOEIC Mock Test Group UAT');
        $response->assertSee('TOEIC');
        $response->assertSee('Mock / Real Test');
        $response->assertSee('real_test');
        $response->assertSee('Draft — In Authoring');
        $response->assertSee('Unpublished');
        $response->assertSee('Teacher Instructor');
        $response->assertSee('CA01');
        $response->assertSee('iC.edu UAT University — Class 9A');
        $response->assertSee('Skill UAT');
        $response->assertSee('120 mins');
        $response->assertSee('700 points');
        $response->assertSee('Section 1: General Core');
        $response->assertDontSee('TOEIC Simulation');
    }

    /**
     * TEST 2: Super Admin can access the RM inspection surface.
     */
    public function test_super_admin_can_access_draft_inspection_surface(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertStatus(200);
        $response->assertSee('TOEIC Mock Test Group UAT');
    }

    /**
     * TEST 3: Non-RM / Non-SA roles are strictly denied (403) from the RM inspection route.
     */
    public function test_unauthorized_roles_denied_from_rm_inspection_route(): void
    {
        $url = route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id);

        $this->actingAs($this->teacher)->get($url)->assertForbidden();
        $this->actingAs($this->ra)->get($url)->assertForbidden();
        $this->actingAs($this->finance)->get($url)->assertForbidden();
        $this->actingAs($this->coordinator)->get($url)->assertForbidden();
        $this->actingAs($this->candidate)->get($url)->assertForbidden();
    }

    /**
     * TEST 4: Unauthenticated request is redirected to login.
     */
    public function test_unauthenticated_request_redirected(): void
    {
        $response = $this->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertRedirect('/login');
    }

    /**
     * TEST 5: Provenance guard rejects mismatched AssessmentRequest / Test associations.
     */
    public function test_provenance_guard_rejects_mismatched_test_and_request(): void
    {
        $otherTest = Test::create([
            'title'                 => 'Other Independent Test',
            'slug'                  => 'other-test-' . uniqid(),
            'test_type'             => TestType::General,
            'assessment_mode'       => AssessmentMode::Simulator,
            'duration_minutes'      => 60,
            'pass_score'            => 50,
            'status'                => 'draft',
            'created_by'            => $this->teacher->id,
            'assessment_request_id' => '01m_different_request_ulid',
        ]);

        // Break provenance by assigning mismatched test_id pointing to otherTest
        $this->assessmentRequest->update(['test_id' => $otherTest->id]);

        $response = $this->actingAs($this->rm)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertForbidden();
    }

    /**
     * TEST 6: Zero mutation test — visiting the inspection page does not modify database records.
     */
    public function test_zero_mutation_on_get_inspection_route(): void
    {
        $reqFreshBefore = $this->assessmentRequest->fresh();
        $testFreshBefore = $this->governedTest->fresh();

        $this->actingAs($this->rm)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        )->assertStatus(200);

        $reqFreshAfter = $this->assessmentRequest->fresh();
        $testFreshAfter = $this->governedTest->fresh();

        $this->assertEquals($reqFreshBefore->status, $reqFreshAfter->status);
        $this->assertEquals($reqFreshBefore->test_id, $reqFreshAfter->test_id);
        $this->assertEquals($testFreshBefore->status, $testFreshAfter->status);
        $this->assertEquals($testFreshBefore->is_published, $testFreshAfter->is_published);
        $this->assertEquals($testFreshBefore->assessment_mode, $testFreshAfter->assessment_mode);
        $this->assertEquals($testFreshBefore->pass_score, $testFreshAfter->pass_score);
        $this->assertEquals($testFreshBefore->duration_minutes, $testFreshAfter->duration_minutes);
        $this->assertEquals($testFreshBefore->assigned_to, $testFreshAfter->assigned_to);
    }

    /**
     * TEST 7: No authoring HTML controls are rendered in RM inspection view.
     */
    public function test_no_authoring_controls_rendered_in_rm_inspection_html(): void
    {
        $response = $this->actingAs($this->rm)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertStatus(200);

        // Assert absence of Teacher authoring form buttons & controls
        $response->assertDontSee('Save Settings Draft');
        $response->assertDontSee('Add from Question Bank');
        $response->assertDontSee('Add New Question');
        $response->assertDontSee('Add Section');
        $response->assertDontSee('Create Audio Group');
        $response->assertDontSee('Create Passage Group');
        $response->assertDontSee('Submit Assessment for Review');
        $response->assertDontSee('action="' . route('teacher.tests.update', $this->governedTest->id) . '"', false);
        $response->assertDontSee('action="' . route('teacher.tests.add-section', $this->governedTest->id) . '"', false);
    }

    /**
     * TEST 8: Back button navigation routes to RM intake queue and not teacher index (no 403).
     */
    public function test_back_navigation_routes_to_rm_intake_queue(): void
    {
        $response = $this->actingAs($this->rm)->get(
            route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id)
        );

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.assessment-requests.index'));
        $response->assertDontSee(route('teacher.tests.index'));
    }

    /**
     * TEST 9: Intake queue "View Assessment" button links to the dedicated RM route.
     */
    public function test_intake_queue_action_links_to_dedicated_rm_route(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.assessment-requests.index'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id));
    }

    /**
     * TEST 10: Calling generic admin.tests.show as RM redirects to the canonical RM inspection route.
     */
    public function test_generic_admin_tests_show_redirects_rm_to_inspection_surface(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.tests.show', $this->governedTest->id));

        $response->assertRedirect(route('admin.repository-manager.assessment-requests.assessment-show', $this->assessmentRequest->id));
    }

    /**
     * TEST 11: Assigned Teacher retains full access to Teacher authoring workspace.
     */
    public function test_assigned_teacher_retains_authoring_workspace_access(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->governedTest->id));

        $response->assertStatus(200);
        $response->assertSee('Assessment Authoring Editor');
        $response->assertSee('Save Settings Draft');
        $response->assertSee('Add Section');
    }

    /**
     * TEST 12: Unassigned Teacher cannot access the governed draft authoring workspace.
     */
    public function test_unassigned_teacher_cannot_access_draft(): void
    {
        $response = $this->actingAs($this->otherTeacher)->get(route('teacher.tests.show', $this->governedTest->id));

        $response->assertForbidden();
    }

    /**
     * TEST 13: Server-side security blocks RM from mutating Teacher authoring endpoints.
     */
    public function test_server_side_security_blocks_rm_authoring_mutations(): void
    {
        // RM attempting teacher update
        $response = $this->actingAs($this->rm)->put(route('teacher.tests.update', $this->governedTest->id), [
            'title' => 'RM Hijacked Title',
        ]);
        $response->assertForbidden();

        // RM attempting teacher add-section
        $response = $this->actingAs($this->rm)->post(route('teacher.tests.add-section', $this->governedTest->id), [
            'title' => 'RM Injected Section',
        ]);
        $response->assertForbidden();

        // RM attempting teacher create-question
        $response = $this->actingAs($this->rm)->post(route('teacher.tests.create-question', $this->governedTest->id), [
            'section_id' => $this->section->id,
            'prompt'     => 'RM Injected Question',
        ]);
        $response->assertForbidden();
    }
}
