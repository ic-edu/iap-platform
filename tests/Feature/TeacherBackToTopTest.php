<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherBackToTopTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Scroll Teacher',
            'email'  => 'scroll_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');
    }

    /**
     * TEST 1: Teacher Dashboard renders Back to Top button.
     */
    public function test_teacher_dashboard_renders_back_to_top_button()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('id="back-to-top-btn"', false);
        $response->assertSee('aria-label="Back to top"', false);
        $response->assertSee('scrollToTop()', false);
    }

    /**
     * TEST 2: Teacher Revision Center renders Back to Top button.
     */
    public function test_teacher_revision_center_renders_back_to_top_button()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));

        $response->assertStatus(200);
        $response->assertSee('id="back-to-top-btn"', false);
        $response->assertSee('aria-label="Back to top"', false);
        $response->assertSee('title="Back to top"', false);
    }

    /**
     * TEST 3: Teacher Revision Request Detail Page renders Back to Top button.
     */
    public function test_teacher_revision_show_renders_back_to_top_button()
    {
        $bank = QuestionBank::create([
            'title'      => 'Long Repository For Revisions',
            'slug'       => 'long-repo-revisions',
            'test_type'  => 'toeic',
            'status'     => 'needs_revision',
            'created_by' => $this->teacher->id,
        ]);

        $request = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->teacher->id,
            'status'           => 'OPEN',
            'notes'            => 'Revision requested for repository items.',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $request->id));

        $response->assertStatus(200);
        $response->assertSee('id="back-to-top-btn"', false);
        $response->assertSee('aria-label="Back to top"', false);
    }
}
