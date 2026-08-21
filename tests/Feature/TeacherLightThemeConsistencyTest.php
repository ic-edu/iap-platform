<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Assessment;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherLightThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('teacher');
    }

    public function test_teacher_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('tw-hero');
        $response->assertSee('tw-kpi');
        $response->assertSee('tw-workspace');
    }

    public function test_institutional_academic_library_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.academic-library.index'));
        $response->assertStatus(200);
        $response->assertSee('al-hero');
        $response->assertSee('al-health-panel');
        $response->assertSee('Institutional Academic Library');
    }

    public function test_question_bank_workspace_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('acl-hero');
        $response->assertSee('acl-health-card');
        $response->assertSee('Academic Content Library');
    }

    public function test_assessment_test_builder_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertSee('tb-hero');
        $response->assertSee('tb-kpi');
        $response->assertSee('Test Builder Workspace');
    }

    public function test_institutional_media_repository_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('imr-hero');
        $response->assertSee('imr-kpi');
        $response->assertSee('Institutional Media Repository');
    }

    public function test_teacher_archived_repositories_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.archived-repositories.index'));
        $response->assertStatus(200);
        $response->assertSee('gov-hero-indigo');
        $response->assertSee('Archived Repositories');
    }

    public function test_teacher_revision_center_renders_for_teacher(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.revision-center'));
        $response->assertStatus(200);
        $response->assertSee('gov-hero-indigo');
        $response->assertSee('Teacher Revision Center');
    }
}
