<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssessmentResubmitModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Modal Teacher',
            'email'  => 'modal_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');
    }

    /**
     * TEST 1: Assessment detail view renders custom IAP modal and no native confirm().
     */
    public function test_assessment_detail_view_renders_custom_modal_and_no_native_confirm()
    {
        $test = Test::create([
            'title'      => 'TOEIC Full Simulation Test 01',
            'slug'       => 'toeic-full-sim-01',
            'status'     => 'needs_revision',
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.show', $test->id));

        $response->assertStatus(200);

        // Assert native confirm() is completely removed
        $response->assertDontSee("confirm('Resubmit this Assessment for Repository Review?')", false);

        // Assert custom modal elements and dynamic title exist
        $response->assertSee('id="resubmit-confirmation-modal"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('Resubmit Assessment for Review?');
        $response->assertSee('TOEIC Full Simulation Test 01');
        $response->assertSee('Your assessment has passed the Validation Assistant checks');
        $response->assertSee('openResubmitModal()', false);
        $response->assertSee('confirmAndSubmitResubmit()', false);
    }

    /**
     * TEST 2: Teacher resubmitting assessment executes resubmission workflow without regression.
     */
    public function test_teacher_resubmitting_assessment_executes_workflow()
    {
        $test = Test::create([
            'title'      => 'TOEIC Full Simulation Test 02',
            'slug'       => 'toeic-full-sim-02',
            'status'     => 'needs_revision',
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.resubmit', $test->id));

        $response->assertStatus(302);

        $test->refresh();
        $this->assertContains($test->status, ['pending_approval', 'submitted']);
    }
}
