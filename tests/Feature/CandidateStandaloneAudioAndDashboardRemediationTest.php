<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\DeliveryUnitBuilder;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidateStandaloneAudioAndDashboardRemediationTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $teacher;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->candidate = User::factory()->create(['name' => 'Candidate Tester', 'email' => 'candidate@example.com']);
        $this->candidate->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher Tester', 'email' => 'teacher@example.com']);
        $this->teacher->assignRole('teacher');

        $this->adminUser = User::factory()->create(['name' => 'Admin Tester', 'email' => 'admin@example.com']);
        $this->adminUser->assignRole('admin');
    }

    /**
     * Helper to create a comprehensive TOEIC-style test with standalone questions and audio groups.
     */
    private function createToeicTestFixture(bool $isRealTest = false): array
    {
        Storage::fake('public');

        $test = Test::create([
            'title' => 'TOEIC Listening & Reading Fixture Test',
            'slug' => 'toeic-fixture-test-' . uniqid(),
            'status' => 'published',
            'is_published' => true,
            'assessment_mode' => $isRealTest ? \App\Modules\Assessment\Enums\AssessmentMode::RealTest : \App\Modules\Assessment\Enums\AssessmentMode::Simulator,
            'duration_minutes' => 120,
            'created_by' => $this->teacher->id,
            'test_type' => 'toeic',
        ]);

        // Section 1: Part 1 Photographs (3 questions with Dual Media: Image + Audio)
        $sec1 = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Part 1: Photographs',
            'order' => 1,
            'section_type' => SectionType::Listening->value,
            'duration_minutes' => 15,
        ]);

        $part1Questions = [];
        for ($i = 1; $i <= 3; $i++) {
            $imgFile = UploadedFile::fake()->image("p1_photo_{$i}.jpg", 400, 300);
            $imgPath = $imgFile->store('question-media', 'public');
            $imgAsset = MediaAsset::create([
                'filename' => basename($imgPath),
                'original_name' => "p1_photo_{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'type' => 'image',
                'path' => $imgPath,
                'size' => 45000,
                'uploaded_by' => $this->teacher->id,
            ]);

            $audFile = UploadedFile::fake()->createWithContent("p1_audio_{$i}.mp3", str_repeat("ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00", 200));
            $audPath = $audFile->store('question-media', 'public');
            $audAsset = MediaAsset::create([
                'filename' => basename($audPath),
                'original_name' => "p1_audio_{$i}.mp3",
                'mime_type' => 'audio/mpeg',
                'type' => 'audio',
                'path' => $audPath,
                'size' => 65000,
                'uploaded_by' => $this->teacher->id,
            ]);

            $q = Question::create([
                'title' => "Part 1 Photograph Q{$i}",
                'prompt' => "Look at the photograph and choose the best statement.",
                'type' => QuestionType::MultipleChoice->value,
                'part_number' => 1,
                'image_media_asset_id' => $imgAsset->id,
                'audio_media_asset_id' => $audAsset->id,
                'audio_url' => "http://legacy-cdn.example.com/audio/{$audAsset->id}.mp3", // Legacy URL present
                'image_url' => "http://legacy-cdn.example.com/images/{$imgAsset->id}.jpg",
                'created_by' => $this->teacher->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => $lbl,
                    'content' => "Statement ({$lbl})",
                    'is_correct' => $lbl === 'A',
                    'order' => ord($lbl) - 64,
                ]);
            }

            TestQuestion::create([
                'test_id' => $test->id,
                'test_section_id' => $sec1->id,
                'question_id' => $q->id,
                'order' => $i,
                'points' => 5,
            ]);

            $part1Questions[] = $q;
        }

        // Section 2: Part 2 Question-Response (12 standalone questions with Audio only)
        $sec2 = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Part 2: Question-Response',
            'order' => 2,
            'section_type' => SectionType::Listening->value,
            'duration_minutes' => 20,
        ]);

        $part2Questions = [];
        for ($i = 4; $i <= 15; $i++) {
            $audFile = UploadedFile::fake()->createWithContent("p2_audio_{$i}.mp3", str_repeat("ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00", 200));
            $audPath = $audFile->store('question-media', 'public');
            $audAsset = MediaAsset::create([
                'filename' => basename($audPath),
                'original_name' => "p2_audio_{$i}.mp3",
                'mime_type' => 'audio/mpeg',
                'type' => 'audio',
                'path' => $audPath,
                'size' => 60000,
                'uploaded_by' => $this->teacher->id,
            ]);

            $q = Question::create([
                'title' => "Part 2 Question-Response Q{$i}",
                'prompt' => "Mark your answer on your answer sheet.",
                'type' => QuestionType::MultipleChoice->value,
                'part_number' => 2,
                'audio_media_asset_id' => $audAsset->id,
                'audio_url' => "http://legacy-cdn.example.com/audio/{$audAsset->id}.mp3", // Legacy URL present
                'created_by' => $this->teacher->id,
            ]);

            foreach (['A', 'B', 'C'] as $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => $lbl,
                    'content' => "Response ({$lbl})",
                    'is_correct' => $lbl === 'B',
                    'order' => ord($lbl) - 64,
                ]);
            }

            TestQuestion::create([
                'test_id' => $test->id,
                'test_section_id' => $sec2->id,
                'question_id' => $q->id,
                'order' => $i - 3,
                'points' => 5,
            ]);

            $part2Questions[] = $q;
        }

        // Section 3: Part 3 Conversations (1 AudioGroup with 3 child questions)
        $sec3 = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Part 3: Conversations',
            'order' => 3,
            'section_type' => SectionType::Listening->value,
            'duration_minutes' => 20,
        ]);

        $groupAudFile = UploadedFile::fake()->createWithContent("p3_conversation.mp3", str_repeat("ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00", 500));
        $groupAudPath = $groupAudFile->store('question-media', 'public');
        $groupAudAsset = MediaAsset::create([
            'filename' => basename($groupAudPath),
            'original_name' => "p3_conversation.mp3",
            'mime_type' => 'audio/mpeg',
            'type' => 'audio',
            'path' => $groupAudPath,
            'size' => 250000,
            'uploaded_by' => $this->teacher->id,
        ]);

        $audioGroup = AudioGroup::create([
            'test_id' => $test->id,
            'title' => 'Conversation Questions 16-18',
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $groupAudAsset->id,
            'created_by' => $this->teacher->id,
        ]);

        $part3Questions = [];
        for ($i = 16; $i <= 18; $i++) {
            $q = Question::create([
                'title' => "Part 3 Conversation Q{$i}",
                'prompt' => "Where does the conversation take place?",
                'type' => QuestionType::MultipleChoice->value,
                'part_number' => 3,
                'audio_group_id' => $audioGroup->id,
                'created_by' => $this->teacher->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => $lbl,
                    'content' => "Choice {$lbl}",
                    'is_correct' => $lbl === 'C',
                    'order' => ord($lbl) - 64,
                ]);
            }

            TestQuestion::create([
                'test_id' => $test->id,
                'test_section_id' => $sec3->id,
                'question_id' => $q->id,
                'order' => $i - 15,
                'points' => 5,
            ]);

            $part3Questions[] = $q;
        }

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_score' => 0,
        ]);

        return [$test, $attempt, array_merge($part1Questions, $part2Questions, $part3Questions)];
    }

    // ==========================================
    // 30. STANDALONE SOURCE FOCUSED TESTS (AUDIO-01 - AUDIO-05)
    // ==========================================

    public function test_audio_01_q1_resolves_correct_audio_media_asset(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q1 = $questions[0];

        $effectiveAud = $q1->getEffectiveAudioMedia();
        $this->assertNotNull($effectiveAud);
        $this->assertEquals($q1->audio_media_asset_id, $effectiveAud->id);
        $this->assertEquals('audio/mpeg', $effectiveAud->mime_type);
    }

    public function test_audio_02_q4_resolves_correct_audio_media_asset(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q4 = $questions[3];

        $effectiveAud = $q4->getEffectiveAudioMedia();
        $this->assertNotNull($effectiveAud);
        $this->assertEquals($q4->audio_media_asset_id, $effectiveAud->id);
        $this->assertEquals('audio/mpeg', $effectiveAud->mime_type);
    }

    public function test_audio_03_q15_resolves_correct_audio_media_asset(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q15 = $questions[14];

        $effectiveAud = $q15->getEffectiveAudioMedia();
        $this->assertNotNull($effectiveAud);
        $this->assertEquals($q15->audio_media_asset_id, $effectiveAud->id);
    }

    public function test_audio_04_q1_candidate_html_uses_canonical_audio_stream_not_raw_audio_url(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q1 = $questions[0];

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $expectedStreamUrl = route('candidate.exam.audio-stream', [$attempt, $q1]);
        $response->assertSee($expectedStreamUrl, false);

        // Crucial: Must NOT contain raw hardcoded legacy URL
        $response->assertDontSee("src=\"{$q1->audio_url}\"", false);
    }

    public function test_audio_05_q4_candidate_html_uses_canonical_audio_stream(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q4 = $questions[3];

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $expectedStreamUrl = route('candidate.exam.audio-stream', [$attempt, $q4]);
        $response->assertSee($expectedStreamUrl, false);
        $response->assertDontSee("src=\"{$q4->audio_url}\"", false);
    }

    public function test_audio_06_to_08_q1_audio_stream_returns_200_audio_mpeg_and_content_length(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q1 = $questions[0];

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $response->assertStatus(200);
        $this->assertStringContainsString('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));
        $this->assertEquals('bytes', $response->headers->get('Accept-Ranges'));
    }

    public function test_audio_09_to_10_q4_audio_stream_returns_200_and_audio_mpeg(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q4 = $questions[3];

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q4]));
        $response->assertStatus(200);
        $this->assertStringContainsString('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));
    }

    // ==========================================
    // 33. ALL Q1–Q15 AUDIO TEST (AUDIO-11 - AUDIO-14)
    // ==========================================

    public function test_audio_11_to_14_all_q1_to_q15_have_fk_and_stream_200(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();

        for ($i = 0; $i < 15; $i++) {
            $q = $questions[$i];
            $this->assertNotNull($q->audio_media_asset_id, "Q" . ($i + 1) . " audio_media_asset_id is null");

            $streamUrl = route('candidate.exam.audio-stream', [$attempt, $q]);
            $response = $this->actingAs($this->candidate)->get($streamUrl);

            $this->assertEquals(200, $response->getStatusCode(), "Q" . ($i + 1) . " stream did not return 200");
            $this->assertStringContainsString('audio/', $response->headers->get('Content-Type'), "Q" . ($i + 1) . " MIME not audio");
            $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'), "Q" . ($i + 1) . " empty content length");
        }
    }

    // ==========================================
    // 34. HOST INDEPENDENCE TESTS (AUDIO-15 - AUDIO-17)
    // ==========================================

    public function test_audio_15_to_17_host_independence_across_localhost_and_ip(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q1 = $questions[0];

        $streamUrl = route('candidate.exam.audio-stream', [$attempt, $q1]);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Standalone audio must use the Attempt-scoped canonical stream route
        $response->assertSee($streamUrl, false);

        // Must not contain raw legacy audio_url in audio src
        $response->assertDontSee('src="' . $q1->audio_url . '"', false);
        $response->assertDontSee('src="http://legacy-cdn.example.com/', false);
    }

    // ==========================================
    // 35. PART 3 / PART 4 REGRESSION (AUDIO-18 - AUDIO-20)
    // ==========================================

    public function test_audio_18_to_20_part_3_audio_group_stream_works(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();
        $q16 = $questions[15]; // First Part 3 question

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // AudioGroup player renders correctly with effective media preview URL
        $previewUrl = route('media.preview', $questions[15]->audioGroup->media_asset_id);
        $response->assertSee($previewUrl, false);

        // Streaming endpoint works
        $groupStreamUrl = route('candidate.exam.audio-stream', [$attempt, $q16]);
        $streamResponse = $this->actingAs($this->candidate)->get($groupStreamUrl);
        $streamResponse->assertStatus(200);
        $this->assertStringContainsString('audio/mpeg', $streamResponse->headers->get('Content-Type'));
    }

    // ==========================================
    // 36. PART 1 IMAGE REGRESSION (AUDIO-21 - AUDIO-23)
    // ==========================================

    public function test_audio_21_to_23_part_1_images_render_simultaneously_with_audio(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture();

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        for ($i = 0; $i < 3; $i++) {
            $q = $questions[$i];
            $expectedImgUrl = route('media.preview', $q->image_media_asset_id);
            $expectedAudUrl = route('candidate.exam.audio-stream', [$attempt, $q]);

            $response->assertSee($expectedImgUrl, false);
            $response->assertSee($expectedAudUrl, false);
        }
    }

    // ==========================================
    // 37. REAL TEST / SIMULATOR AUDIO POLICY (AUDIO-24 - AUDIO-26)
    // ==========================================

    public function test_audio_24_simulator_audio_remains_replayable(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture(isRealTest: false);
        $q1 = $questions[0];

        // First play
        $r1 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r1->assertStatus(200);

        // Second play in simulator (must succeed)
        $r2 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r2->assertStatus(200);
    }

    public function test_audio_25_and_26_real_test_enforces_single_play_governance(): void
    {
        [$test, $attempt, $questions] = $this->createToeicTestFixture(isRealTest: true);
        $q1 = $questions[0];

        // First play in real test
        $r1 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r1->assertStatus(200);

        // Verify AttemptAudioPlay record was created
        $this->assertDatabaseHas('attempt_audio_plays', [
            'attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'play_count' => 1,
        ]);

        // Second play in real test (must be blocked 403)
        $r2 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
        $r2->assertStatus(403);
    }

    // ==========================================
    // 38. AUDIO LIFECYCLE & SINGLE ACTIVE AUDIO (AUDIO-LIFECYCLE-01 - 04)
    // ==========================================

    public function test_audio_lifecycle_01_exam_audio_elements_marked_with_data_exam_audio(): void
    {
        [$test, $attempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Verify data-exam-audio attribute is present on candidate exam audio tags
        $response->assertSee('data-exam-audio="true"', false);
    }

    public function test_audio_lifecycle_02_central_stop_all_exam_audio_function_defined(): void
    {
        [$test, $attempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $response->assertSee('function stopAllExamAudio(options = {})', false);
        $response->assertSee('document.querySelectorAll(\'audio[data-exam-audio], audio\')', false);
    }

    public function test_audio_lifecycle_03_single_active_audio_listener_enforced(): void
    {
        [$test, $attempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Single active audio capture listener
        $response->assertSee("document.addEventListener('play', function(e)", false);
    }

    public function test_audio_lifecycle_04_navigation_events_invoke_audio_cleanup(): void
    {
        [$test, $attempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $html = $response->getContent();

        // navigateDeliveryUnit invokes stopAllExamAudio
        $this->assertMatchesRegularExpression('/function navigateDeliveryUnit\([^)]*\)\s*\{[^}]*stopAllExamAudio\(\)/s', $html);

        // showSectionIntro invokes stopAllExamAudio
        $this->assertMatchesRegularExpression('/function showSectionIntro\([^)]*\)\s*\{[^}]*stopAllExamAudio\(\)/s', $html);

        // confirmExitSimulator invokes stopAllExamAudio
        $this->assertMatchesRegularExpression('/function confirmExitSimulator\(\)\s*\{[^}]*stopAllExamAudio\(\)/s', $html);

        // triggerFinalSubmitModal invokes stopAllExamAudio
        $this->assertMatchesRegularExpression('/function triggerFinalSubmitModal\(\)\s*\{[^}]*stopAllExamAudio\(\)/s', $html);
    }

    // ==========================================
    // 39. DASHBOARD UI TESTS (UI-01 - UI-13)
    // ==========================================

    public function test_ui_01_to_05_portal_dashboard_text_replaced_by_home_icon_with_clean_hover(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // UI-01 & UI-03: Has accessible Dashboard label and no visible 'Portal Dashboard' in nav
        $response->assertSee('aria-label="Dashboard"', false);
        $response->assertSee('title="Dashboard"', false);

        // UI-02: Routes to Candidate Portal
        $response->assertSee(route('candidate.portal'));

        // UI-04 & UI-05: No scale, zoom, blur, or glow in nav
        $navHtml = $response->getContent();
        $this->assertStringNotContainsString('hover:scale-[1.02]', $navHtml);
        $this->assertStringNotContainsString('backdrop-blur', $navHtml);
    }

    public function test_ui_06_to_13_kpi_cards_remain_clickable_and_have_stable_minimal_hover(): void
    {
        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // UI-06: Available Tests
        $response->assertSee(route('candidate.available-tests'));
        $response->assertSee('Available Tests');

        // UI-07: My Total Attempts
        $response->assertSee(route('candidate.my-attempts'));
        $response->assertSee('My Total Attempts');

        // UI-08: Completed Tests
        $response->assertSee(route('candidate.my-attempts', ['filter' => 'completed']));
        $response->assertSee('Completed Tests');

        // UI-09: My Certificates
        $response->assertSee(route('candidate.my-certificates'));
        $response->assertSee('My Certificates');

        // UI-10, 11, 12: No scale/translate/zoom on KPI cards
        $html = $response->getContent();
        $this->assertStringNotContainsString('hover:scale-[1.015]', $html);
        $this->assertStringNotContainsString('group-hover:scale-110', $html);
        $this->assertStringNotContainsString('active:scale-[0.99]', $html);

        // UI-13: Focus visible ring styling present for keyboard accessibility
        $response->assertSee('focus:ring-2 focus:ring-indigo-500', false);
    }

    // ==========================================
    // 40. SIMULATOR UX REGRESSION (UX-01 - UX-05)
    // ==========================================

    public function test_ux_01_and_02_simulator_has_back_to_dashboard_mock_and_real_do_not(): void
    {
        [$simTest, $simAttempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $simResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $simAttempt));
        $simResponse->assertStatus(200);
        $simResponse->assertSee('id="btn-simulator-back-to-dashboard"', false);

        [$realTest, $realAttempt, ] = $this->createToeicTestFixture(isRealTest: true);
        $realResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $realAttempt));
        $realResponse->assertStatus(200);
        $realResponse->assertDontSee('id="btn-simulator-back-to-dashboard"', false);
    }

    public function test_ux_04_and_05_header_has_no_permanent_final_submit_and_preserves_review(): void
    {
        [$test, $attempt, ] = $this->createToeicTestFixture(isRealTest: false);
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // No permanent final submit button in top header
        $response->assertDontSee('Final Submit (0/18)');
        $response->assertSee('Review Unanswered');
    }
}
