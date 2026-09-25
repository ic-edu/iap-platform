<?php

namespace Tests\Feature;

use App\Models\AclCategory;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LightThemeAccentContrastTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $rm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->rm = User::factory()->create();
        $this->rm->assignRole('repository-manager');
    }

    public function test_repository_explorer_renders_open_repository_and_active_filters(): void
    {
        $bank = QuestionBank::create([
            'title'      => 'Test TOEIC Bank',
            'slug'       => 'test-toeic-bank',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->rm->id,
        ]);

        $response = $this->actingAs($this->rm)->get(route('admin.academic-library.explorer'));
        $response->assertStatus(200);
        $response->assertSee('Open Repository →');
        $response->assertSee('exp-btn-open');
        $response->assertSee('exp-filter-btn');
    }

    public function test_repository_show_page_renders_open_repository(): void
    {
        $category = AclCategory::firstOrCreate(
            ['slug' => 'toefl-reading-mc'],
            ['name' => 'TOEFL Reading MC', 'description' => 'Test Category']
        );

        $response = $this->actingAs($this->rm)->get(route('admin.academic-library.show', $category->slug));
        $response->assertStatus(200);
        $response->assertSee('← Back');
    }

    public function test_global_header_dashboard_button_has_white_text_and_icon(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('bg-indigo-600');
    }

    public function test_candidate_pre_assessment_instructions_institutional_notice_has_theme_safe_contrast(): void
    {
        $candidate = User::factory()->create();
        $candidate->assignRole('student');

        $bank = QuestionBank::create([
            'title'      => 'Test TOEIC Bank for Instructions',
            'slug'       => 'test-toeic-bank-for-instructions',
            'test_type'  => 'toeic',
            'status'     => 'published',
            'created_by' => $this->rm->id,
        ]);

        $questions = [];
        for ($k = 1; $k <= 200; $k++) {
            $questions[] = [
                'id'               => (string) \Illuminate\Support\Str::ulid(),
                'question_bank_id' => $bank->id,
                'question_type'    => 'multiple_choice',
                'prompt'           => "Question {$k} prompt",
                'section'          => 'listening',
                'points'           => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        foreach (array_chunk($questions, 50) as $chunk) {
            \Illuminate\Support\Facades\DB::table('questions')->insert($chunk);
        }

        $test = \App\Modules\Assessment\Models\Test::create([
            'title'            => 'TOEIC Mock Test Group - UAT Class 9A',
            'slug'             => 'toeic-mock-test-group-uat-class-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => \App\Modules\Assessment\Enums\AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 650,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->rm->id,
            'instructions'     => 'General instructions for TOEIC candidate evaluation.',
        ]);

        $qIndex = 0;
        for ($i = 1; $i <= 7; $i++) {
            $section = \App\Modules\Assessment\Models\TestSection::create([
                'test_id'          => $test->id,
                'title'            => "Part {$i}",
                'section_type'     => $i <= 4 ? 'listening' : 'reading',
                'duration_minutes' => 120,
                'order'            => $i,
            ]);

            $questionsCount = ($i === 1) ? 6 : (($i === 7) ? 54 : 28); // sum = 6+28+28+28+28+28+54 = 200
            for ($q = 1; $q <= $questionsCount; $q++) {
                \App\Modules\Assessment\Models\TestQuestion::create([
                    'test_section_id' => $section->id,
                    'question_id'     => $questions[$qIndex]['id'],
                    'order'           => $q,
                ]);
                $qIndex++;
            }
        }

        \App\Modules\Assessment\Models\CandidateTestAssignment::create([
            'user_id'        => $candidate->id,
            'test_id'        => $test->id,
            'status'         => 'active',
            'max_attempts'   => 2,
            'attempts_count' => 0,
            'assigned_at'    => now(),
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.tests.instructions', $test));

        $response->assertStatus(200);

        // Verify zero attempts created on instructions page
        $this->assertEquals(0, \App\Modules\Assessment\Models\Attempt::where('user_id', $candidate->id)->count());

        // Verify theme-safe styling classes for Institutional Notice
        $response->assertSee('bg-indigo-50');
        $response->assertSee('border-indigo-200');
        $response->assertSee('text-indigo-900');
        $response->assertSee('text-indigo-950');
        $response->assertSee('dark:bg-indigo-950/40');
        $response->assertSee('dark:border-indigo-500/30');
        $response->assertSee('dark:text-indigo-200');
        $response->assertSee('dark:text-indigo-100');

        // Verify key metadata and action labels
        $response->assertSee('120 Minutes');
        $response->assertSee('650 Points');
        $response->assertSee('7 Section(s)');
        $response->assertSee('200 Questions');
        $response->assertSee('Institutional Notice');
        $response->assertSee('I Understand &amp; Begin Assessment', false);
    }
}
