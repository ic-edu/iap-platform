<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackToTopNavigationUXTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $repoManager;
    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance UX',
            'email'  => 'vance_ux@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager UX',
            'email'  => 'repomanager_ux@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'UX Navigation Bank',
            'slug'       => 'ux-navigation-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Repository Smart Review Engine renders floating Back to Top button with scroll listener.
     */
    public function test_1_workspace_renders_floating_back_to_top_button()
    {
        $test = AssessmentTest::create([
            'title'            => 'UX Navigation Test 01',
            'slug'             => 'ux-navigation-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'UX Question Stem']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);

        // Task 1 & 2 Assertions: Button ID, Icon, Tooltip
        $res->assertSee('id="btn-back-to-top"', false);
        $res->assertSee('title="Back to Top"', false);
        $res->assertSee('↑');

        // Task 3 & 4 Assertions: Scroll threshold script & toast spacing
        $res->assertSee('window.scrollY > 500', false);
        $res->assertSee('smart-review-toast');
    }

    /**
     * TEST 2: Governance Panel and Navigator column is rendered with position sticky.
     */
    public function test_2_workspace_renders_sticky_governance_sidebar()
    {
        $test = AssessmentTest::create([
            'title'            => 'UX Sticky Sidebar Test 02',
            'slug'             => 'ux-sticky-sidebar-test-02',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Reading', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'UX Question Stem 02']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);

        // Task 5 UX Bonus Assertion: Sticky Governance Sidebar
        $res->assertSee('sticky-governance-sidebar', false);
        $res->assertSee('position:sticky;top:1.5rem;', false);
    }
}
