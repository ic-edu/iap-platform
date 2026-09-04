<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Universal Candidate Exam Audio Lifecycle Test Suite
 * 
 * Validates platform-wide invariant:
 * - At most ONE examination audio element may play at any time.
 * - Navigation across any context (questions, units, sections, palette, exit, review) stops active audio.
 * - Assessment-mode aware: Simulator reset vs Real Test server governance.
 * - Assessment-framework agnostic: Works identically for TOEIC, TOEFL, IELTS, General, and Vocational assessments.
 */
class UniversalCandidateExamAudioLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->candidate = User::factory()->create(['name' => 'Universal Candidate', 'email' => 'candidate_universal@example.com']);
        $this->candidate->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Universal Teacher', 'email' => 'teacher_universal@example.com']);
        $this->teacher->assignRole('teacher');
    }

    /**
     * Create a completely NON-TOEIC General / Vocational Assessment fixture.
     */
    private function createNonToeicAssessmentFixture(bool $isRealTest = false, string $examFramework = 'general'): array
    {
        Storage::fake('public');

        $test = Test::create([
            'title' => 'Professional Workplace English & Technical Assessment',
            'slug' => 'universal-assessment-' . uniqid(),
            'status' => 'published',
            'is_published' => true,
            'assessment_mode' => $isRealTest ? AssessmentMode::RealTest : AssessmentMode::Simulator,
            'duration_minutes' => 90,
            'created_by' => $this->teacher->id,
            'test_type' => $examFramework, // Explicitly non-toeic
        ]);

        // Section A: Short Audio Announcements (Standalone Questions)
        $secA = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Section A: Workplace Audio Broadcasts',
            'order' => 1,
            'section_type' => SectionType::Listening->value,
            'duration_minutes' => 25,
        ]);

        $standaloneQuestions = [];
        for ($i = 1; $i <= 3; $i++) {
            $audFile = UploadedFile::fake()->createWithContent("broadcast_{$i}.mp3", str_repeat("ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00", 300));
            $audPath = $audFile->store('question-media', 'public');
            $audAsset = MediaAsset::create([
                'filename' => basename($audPath),
                'original_name' => "broadcast_{$i}.mp3",
                'mime_type' => 'audio/mpeg',
                'type' => 'audio',
                'path' => $audPath,
                'size' => 75000,
                'uploaded_by' => $this->teacher->id,
            ]);

            $q = Question::create([
                'title' => "Broadcast Announcement Q{$i}",
                'prompt' => "Listen to the announcement and select the primary directive.",
                'type' => QuestionType::MultipleChoice->value,
                'audio_media_asset_id' => $audAsset->id,
                'created_by' => $this->teacher->id,
            ]);

            foreach (['Option 1', 'Option 2', 'Option 3', 'Option 4'] as $idx => $opt) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => chr(65 + $idx),
                    'content' => $opt,
                    'is_correct' => $idx === 0,
                    'order' => $idx + 1,
                ]);
            }

            TestQuestion::create([
                'test_id' => $test->id,
                'test_section_id' => $secA->id,
                'question_id' => $q->id,
                'order' => $i,
                'points' => 10,
            ]);

            $standaloneQuestions[] = $q;
        }

        // Section B: Technical Briefings (Audio Group with multiple questions)
        $secB = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Section B: Technical Briefings & Dialogue',
            'order' => 2,
            'section_type' => SectionType::Listening->value,
            'duration_minutes' => 30,
        ]);

        $groupAudFile = UploadedFile::fake()->createWithContent("briefing.mp3", str_repeat("ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00", 600));
        $groupAudPath = $groupAudFile->store('question-media', 'public');
        $groupAudAsset = MediaAsset::create([
            'filename' => basename($groupAudPath),
            'original_name' => "briefing.mp3",
            'mime_type' => 'audio/mpeg',
            'type' => 'audio',
            'path' => $groupAudPath,
            'size' => 300000,
            'uploaded_by' => $this->teacher->id,
        ]);

        $audioGroup = AudioGroup::create([
            'test_id' => $test->id,
            'title' => 'Technical Briefing: Architecture Overhaul',
            'group_type' => 'conversation',
            'media_asset_id' => $groupAudAsset->id,
            'created_by' => $this->teacher->id,
        ]);

        $groupQuestions = [];
        for ($i = 4; $i <= 5; $i++) {
            $q = Question::create([
                'title' => "Briefing Question Q{$i}",
                'prompt' => "What is the recommended mitigation according to the briefing?",
                'type' => QuestionType::MultipleChoice->value,
                'audio_group_id' => $audioGroup->id,
                'created_by' => $this->teacher->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => $lbl,
                    'content' => "Mitigation {$lbl}",
                    'is_correct' => $lbl === 'A',
                    'order' => ord($lbl) - 64,
                ]);
            }

            TestQuestion::create([
                'test_id' => $test->id,
                'test_section_id' => $secB->id,
                'question_id' => $q->id,
                'order' => $i - 3,
                'points' => 10,
            ]);

            $groupQuestions[] = $q;
        }

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_score' => 0,
        ]);

        return [$test, $attempt, array_merge($standaloneQuestions, $groupQuestions)];
    }

    /**
     * TEST UNIVERSAL-01: Standalone question audio renders canonical stream and data-exam-audio marker.
     */
    public function test_universal_01_standalone_audio_uses_generic_lifecycle_marker(): void
    {
        [$test, $attempt, $questions] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $q1 = $questions[0];

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Uses canonical stream route
        $streamUrl = route('candidate.exam.audio-stream', [$attempt, $q1]);
        $response->assertSee($streamUrl, false);

        // Marked with data-exam-audio attribute
        $response->assertSee('data-exam-audio="true"', false);

        // Verification of CandidateExamAudioManager definition
        $response->assertSee('const CandidateExamAudioManager =', false);
    }

    /**
     * TEST UNIVERSAL-02: AudioGroup renders canonical preview and data-exam-audio marker.
     */
    public function test_universal_02_audio_group_uses_generic_lifecycle_marker(): void
    {
        [$test, $attempt, $questions] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Check that AudioGroup renders with data-exam-audio
        $html = $response->getContent();
        $this->assertStringContainsString('data-exam-audio="true"', $html);
    }

    /**
     * TEST UNIVERSAL-03: Single Active Audio Policy is enforced via global capture listener.
     */
    public function test_universal_03_single_active_audio_policy_present(): void
    {
        [$test, $attempt, ] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Global capture play event listener stops other playing audios
        $html = $response->getContent();
        $this->assertStringContainsString("document.addEventListener('play', handlePlayEvent, true)", $html);
    }

    /**
     * TEST UNIVERSAL-04: Palette navigation stops active audio via beforeDeliveryTransition.
     */
    public function test_universal_04_palette_navigation_stops_active_audio(): void
    {
        [$test, $attempt, ] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/function navigateDeliveryUnit\([^)]*\)\s*\{[^}]*CandidateExamAudioManager\.beforeDeliveryTransition/s', $html);
    }

    /**
     * TEST UNIVERSAL-05: Section transition stops active audio via beforeDeliveryTransition.
     */
    public function test_universal_05_section_transition_stops_active_audio(): void
    {
        [$test, $attempt, ] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/function showSectionIntro\([^)]*\)\s*\{[^}]*CandidateExamAudioManager\.beforeDeliveryTransition/s', $html);
    }

    /**
     * TEST UNIVERSAL-06: Final Review modal stops active audio via beforeDeliveryTransition.
     */
    public function test_universal_06_final_review_stops_active_audio(): void
    {
        [$test, $attempt, ] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/function triggerFinalSubmitModal\(\)\s*\{[^}]*CandidateExamAudioManager\.beforeDeliveryTransition/s', $html);
    }

    /**
     * TEST UNIVERSAL-07: Simulator Back to Dashboard stops active audio via beforeDeliveryTransition.
     */
    public function test_universal_07_simulator_back_to_dashboard_stops_active_audio(): void
    {
        [$test, $attempt, ] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/function confirmExitSimulator\(\)\s*\{[^}]*CandidateExamAudioManager\.beforeDeliveryTransition/s', $html);
    }

    /**
     * TEST UNIVERSAL-08: Simulator mode allows audio replay after returning to question/group.
     */
    public function test_universal_08_simulator_allows_replay_after_navigation(): void
    {
        [$test, $attempt, $questions] = $this->createNonToeicAssessmentFixture(isRealTest: false);
        $q1 = $questions[0];

        // 1st play
        $r1 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r1->assertStatus(200);

        // 2nd play in Simulator mode (must still return 200 without blocking)
        $r2 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r2->assertStatus(200);
    }

    /**
     * TEST UNIVERSAL-09: Real Test navigation preserves server-side AttemptAudioPlay single-play governance.
     */
    public function test_universal_09_real_test_governance_strictly_preserved(): void
    {
        [$test, $attempt, $questions] = $this->createNonToeicAssessmentFixture(isRealTest: true);
        $q1 = $questions[0];

        // 1st play in Real Test
        $r1 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r1->assertStatus(200);

        // Server-side governance record exists
        $this->assertDatabaseHas('attempt_audio_plays', [
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'play_count' => 1,
        ]);

        // 2nd play attempt in Real Test (must be blocked 403 Forbidden)
        $r2 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r2->assertStatus(403);
    }

    /**
     * TEST UNIVERSAL-10: Non-TOEIC frameworks (TOEFL, IELTS, General Assessment) run seamlessly on shared Candidate Engine.
     */
    public function test_universal_10_non_toeic_toefl_and_general_frameworks_run_seamlessly(): void
    {
        [$test, $attempt, $questions] = $this->createNonToeicAssessmentFixture(isRealTest: false, examFramework: 'toefl');

        $this->assertEquals('toefl', $test->test_type->value);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Verifies section titles and question prompts render
        $response->assertSee('Section A: Workplace Audio Broadcasts');
        $response->assertSee('Section B: Technical Briefings & Dialogue');
        $response->assertSee('Technical Briefing: Architecture Overhaul');

        // All audio streams return HTTP 200 with audio/mpeg
        foreach ($questions as $q) {
            $streamResp = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q]));
            $streamResp->assertStatus(200);
            $this->assertStringContainsString('audio/mpeg', $streamResp->headers->get('Content-Type'));
        }
    }
}
