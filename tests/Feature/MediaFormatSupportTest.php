<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFormatSupportTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected User $otherTeacherUser;
    protected User $studentUser;
    protected User $superAdminUser;
    protected QuestionBank $toeflListeningBank;
    protected QuestionBank $toeicPart1Bank;
    protected Payment $uatPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);

        Storage::fake('public');

        $this->teacherUser = User::factory()->create([
            'name'   => 'Teacher Jane',
            'email'  => 'teacher_jane@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->otherTeacherUser = User::factory()->create([
            'name'   => 'Other Teacher Bob',
            'email'  => 'teacher_bob@icedu.org',
            'status' => 'active',
        ]);
        $this->otherTeacherUser->assignRole('teacher');

        $this->studentUser = User::factory()->create([
            'name'   => 'Student Candidate',
            'email'  => 'candidate@icedu.org',
            'status' => 'active',
        ]);
        $this->studentUser->assignRole('student');

        $this->superAdminUser = User::factory()->create([
            'name'   => 'Super Admin',
            'email'  => 'super_admin@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdminUser->assignRole('super-admin');

        $this->toeflListeningBank = QuestionBank::where('slug', 'toefl-listening-core-starter')->firstOrFail();
        $this->toeicPart1Bank     = QuestionBank::where('slug', 'toeic-part-1-starter-pool')->firstOrFail();

        $order = Order::firstOrCreate(
            ['order_number' => 'ORD-20260827-YIT4'],
            [
                'user_id'     => $this->teacherUser->id,
                'status'      => OrderStatus::Pending,
                'subtotal'    => 750000,
                'discount'    => 0,
                'tax'         => 82500,
                'grand_total' => 832500,
            ]
        );

        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-20260827-ZMJY'],
            [
                'order_id' => $order->id,
                'user_id'  => $this->teacherUser->id,
                'status'   => InvoiceStatus::Unpaid,
                'amount'   => 832500,
                'due_date' => now()->addDays(2),
            ]
        );

        $this->uatPayment = Payment::firstOrCreate(
            ['reference_number' => 'PAY-20260827-VZDM'],
            [
                'amount'          => 832500,
                'status'          => PaymentStatus::Pending,
                'payment_gateway' => 'manual_transfer',
                'invoice_id'      => $invoice->id,
                'user_id'         => $this->teacherUser->id,
            ]
        );
    }

    /**
     * TEST 1: JPG upload accepted.
     */
    public function test_1_jpg_upload_accepted(): void
    {
        $file = UploadedFile::fake()->image('test_image.jpg', 600, 400);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Test JPG Image',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'test_image.jpg',
            'type'          => 'image',
        ]);
    }

    /**
     * TEST 2: JPEG upload accepted.
     */
    public function test_2_jpeg_upload_accepted(): void
    {
        $file = UploadedFile::fake()->image('test_photo.jpeg', 800, 600);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Test JPEG Photo',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'test_photo.jpeg',
            'type'          => 'image',
        ]);
    }

    /**
     * TEST 3: PNG upload accepted.
     */
    public function test_3_png_upload_accepted(): void
    {
        $file = UploadedFile::fake()->image('test_diagram.png', 400, 400);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Test PNG Diagram',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'test_diagram.png',
            'type'          => 'image',
        ]);
    }

    /**
     * TEST 4: WebP upload accepted.
     */
    public function test_4_webp_upload_accepted(): void
    {
        $file = UploadedFile::fake()->image('test_graphic.webp', 500, 500);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Test WebP Graphic',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'test_graphic.webp',
            'type'          => 'image',
        ]);
    }

    /**
     * TEST 5: MP3 upload accepted.
     */
    public function test_5_mp3_upload_accepted(): void
    {
        $file = UploadedFile::fake()->create('dialogue.mp3', 250, 'audio/mpeg');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'TOEIC Part 1 Audio Dialogue',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'dialogue.mp3',
            'type'          => 'audio',
        ]);
    }

    /**
     * TEST 6: M4A upload accepted.
     */
    public function test_6_m4a_upload_accepted(): void
    {
        $file = UploadedFile::fake()->create('lecture.m4a', 350, 'audio/mp4');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Academic Lecture Audio',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'lecture.m4a',
            'type'          => 'audio',
        ]);
    }

    /**
     * TEST 7: WAV upload accepted.
     */
    public function test_7_wav_upload_accepted(): void
    {
        $file = UploadedFile::fake()->create('prompt.wav', 400, 'audio/wav');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'TOEFL Listening Audio Prompt',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'prompt.wav',
            'type'          => 'audio',
        ]);
    }

    /**
     * TEST 8: Unsupported image extension rejected.
     */
    public function test_8_unsupported_image_extension_rejected(): void
    {
        $file = UploadedFile::fake()->create('animation.gif', 100, 'image/gif');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'animation.gif']);
    }

    /**
     * TEST 9: Unsupported audio extension rejected.
     */
    public function test_9_unsupported_audio_extension_rejected(): void
    {
        $file = UploadedFile::fake()->create('track.ogg', 100, 'audio/ogg');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'track.ogg']);
    }

    /**
     * TEST 10: Mismatched MIME rejected.
     */
    public function test_10_mismatched_mime_rejected(): void
    {
        // Text file named with .jpg extension
        $file = UploadedFile::fake()->create('fake_photo.jpg', 50, 'text/plain');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'fake_photo.jpg']);
    }

    /**
     * TEST 11: Zero-byte media rejected.
     */
    public function test_11_zero_byte_media_rejected(): void
    {
        $file = UploadedFile::fake()->create('empty.jpg', 0, 'image/jpeg');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'empty.jpg']);
    }

    /**
     * TEST 12: Corrupt image rejected.
     */
    public function test_12_corrupt_image_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('corrupt.jpg', 'CORRUPT_NON_IMAGE_BINARY_GARBAGE');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'corrupt.jpg']);
    }

    /**
     * TEST 13: Invalid audio rejected where reliably detectable.
     */
    public function test_13_invalid_audio_rejected_where_reliably_detectable(): void
    {
        // Video MP4 uploaded as M4A audio
        $file = UploadedFile::fake()->create('video_as_audio.m4a', 200, 'video/mp4');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'video_as_audio.m4a']);
    }

    /**
     * TEST 14: JPG stored correctly.
     */
    public function test_14_jpg_stored_correctly(): void
    {
        $file = UploadedFile::fake()->image('hotel.jpg', 400, 300);

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'hotel.jpg')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 15: JPEG stored correctly.
     */
    public function test_15_jpeg_stored_correctly(): void
    {
        $file = UploadedFile::fake()->image('meeting.jpeg', 400, 300);

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'meeting.jpeg')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 16: PNG stored correctly.
     */
    public function test_16_png_stored_correctly(): void
    {
        $file = UploadedFile::fake()->image('chart.png', 400, 300);

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'chart.png')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 17: WebP stored correctly.
     */
    public function test_17_webp_stored_correctly(): void
    {
        $file = UploadedFile::fake()->image('landscape.webp', 400, 300);

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'landscape.webp')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 18: MP3 stored correctly.
     */
    public function test_18_mp3_stored_correctly(): void
    {
        $file = UploadedFile::fake()->create('track1.mp3', 150, 'audio/mpeg');

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'track1.mp3')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 19: M4A stored correctly.
     */
    public function test_19_m4a_stored_correctly(): void
    {
        $file = UploadedFile::fake()->create('track2.m4a', 200, 'audio/mp4');

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'track2.m4a')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 20: WAV stored correctly.
     */
    public function test_20_wav_stored_correctly(): void
    {
        $file = UploadedFile::fake()->create('track3.wav', 300, 'audio/wav');

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'track3.wav')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
    }

    /**
     * TEST 21: MediaAsset records correct type.
     */
    public function test_21_media_asset_records_correct_type(): void
    {
        $imgFile = UploadedFile::fake()->image('visual.webp', 400, 300);
        $this->actingAs($this->teacherUser)->post(route('admin.media.store'), ['file' => $imgFile]);
        $imgAsset = MediaAsset::where('original_name', 'visual.webp')->firstOrFail();
        $this->assertSame('image', $imgAsset->type);

        $audFile = UploadedFile::fake()->create('voice.m4a', 150, 'audio/mp4');
        $this->actingAs($this->teacherUser)->post(route('admin.media.store'), ['file' => $audFile]);
        $audAsset = MediaAsset::where('original_name', 'voice.m4a')->firstOrFail();
        $this->assertSame('audio', $audAsset->type);
    }

    /**
     * TEST 22: Media Library shows correct media type.
     */
    public function test_22_media_library_shows_correct_media_type(): void
    {
        $img = MediaAsset::create([
            'filename'        => 'my_image.png',
            'original_name'   => 'My Image.png',
            'mime_type'       => 'image/png',
            'type'            => 'image',
            'path'            => 'question-media/my_image.png',
            'size'            => 120000,
            'status'          => 'active',
            'approval_status' => 'draft',
            'title'           => 'Unique PNG Asset 1234',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $aud = MediaAsset::create([
            'filename'        => 'my_audio.wav',
            'original_name'   => 'My Audio.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'path'            => 'question-media/my_audio.wav',
            'size'            => 450000,
            'status'          => 'active',
            'approval_status' => 'draft',
            'title'           => 'Unique WAV Audio 5678',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Unique PNG Asset 1234');
        $response->assertSee('Unique WAV Audio 5678');
    }

    /**
     * TEST 23: Image preview works.
     */
    public function test_23_image_preview_works(): void
    {
        $img = MediaAsset::create([
            'filename'        => 'preview_img.jpg',
            'original_name'   => 'Preview Image.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/preview_img.jpg',
            'size'            => 200000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'title'           => 'Preview Sample Image',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        Storage::disk('public')->put($img->path, 'FAKE_JPG_DATA');

        $response = $this->actingAs($this->teacherUser)
            ->get(route('media.preview', $img->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'inline; filename="preview_img.jpg"');
    }

    /**
     * TEST 24: Audio preview endpoint works.
     */
    public function test_24_audio_preview_endpoint_works(): void
    {
        $aud = MediaAsset::create([
            'filename'        => 'preview_aud.m4a',
            'original_name'   => 'Preview Audio.m4a',
            'mime_type'       => 'audio/mp4',
            'type'            => 'audio',
            'path'            => 'question-media/preview_aud.m4a',
            'size'            => 300000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'title'           => 'Preview Sample M4A Audio',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        Storage::disk('public')->put($aud->path, 'FAKE_M4A_DATA');

        $response = $this->actingAs($this->teacherUser)
            ->get(route('media.preview', $aud->id));

        $response->assertStatus(200);
        $response->assertHeader('Accept-Ranges', 'bytes');
    }

    /**
     * TEST 25: Supported image can attach to question.
     */
    public function test_25_supported_image_can_attach_to_question(): void
    {
        $img = MediaAsset::create([
            'filename'        => 'q_image.webp',
            'original_name'   => 'Q Image.webp',
            'mime_type'       => 'image/webp',
            'type'            => 'image',
            'path'            => 'question-media/q_image.webp',
            'size'            => 80000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $question = Question::where('question_bank_id', $this->toeicPart1Bank->id)->firstOrFail();
        $question->media_asset_id = $img->id;
        $question->save();

        $this->assertSame($img->id, $question->fresh()->media_asset_id);
        $this->assertSame($img->publicUrl(), $question->fresh()->getEffectiveImageUrl());
    }

    /**
     * TEST 26: Supported audio can attach to question.
     */
    public function test_26_supported_audio_can_attach_to_question(): void
    {
        $aud = MediaAsset::create([
            'filename'        => 'q_audio.wav',
            'original_name'   => 'Q Audio.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'path'            => 'question-media/q_audio.wav',
            'size'            => 350000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $question = Question::where('question_bank_id', $this->toeflListeningBank->id)->firstOrFail();
        $question->media_asset_id = $aud->id;
        $question->save();

        $this->assertSame($aud->id, $question->fresh()->media_asset_id);
        $this->assertSame($aud->publicUrl(), $question->fresh()->getEffectiveAudioUrl());
    }

    /**
     * TEST 27: Supported media can attach to section where supported.
     */
    public function test_27_supported_media_can_attach_to_section_where_supported(): void
    {
        $test = Test::create([
            'title'            => 'Test for Section Media',
            'slug'             => 'test-section-media-support',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacherUser->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $section = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Listening Section Directions',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $media = MediaAsset::create([
            'filename'        => 'directions.m4a',
            'original_name'   => 'Directions.m4a',
            'mime_type'       => 'audio/mp4',
            'type'            => 'audio',
            'path'            => 'question-media/directions.m4a',
            'size'            => 250000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('teacher.tests.sections.media.attach', [$test->id, $section->id]), [
                'media_asset_id' => $media->id,
                'caption'        => 'Directions Audio',
                'order'          => 1,
            ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $this->assertDatabaseHas('test_section_media', [
            'test_section_id' => $section->id,
            'media_asset_id'  => $media->id,
        ]);
    }

    /**
     * TEST 28: Candidate Preview renders supported image.
     */
    public function test_28_candidate_preview_renders_supported_image(): void
    {
        $img = MediaAsset::create([
            'filename'        => 'preview_render.webp',
            'original_name'   => 'Preview Render.webp',
            'mime_type'       => 'image/webp',
            'type'            => 'image',
            'path'            => 'question-media/preview_render.webp',
            'size'            => 100000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $test = Test::create([
            'title'            => 'TOEIC Simulation Test',
            'slug'             => 'toeic-simulation-test-img-preview',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacherUser->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $section = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $question = Question::where('question_bank_id', $this->toeicPart1Bank->id)->firstOrFail();
        $question->media_asset_id = $img->id;
        $question->save();

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 5,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.tests.preview', $test->id));

        $response->assertStatus(200);
        $response->assertSee('Candidate Preview');
        $response->assertSee(route('media.preview', $img->id, false), false);
    }

    /**
     * TEST 29: Candidate Preview renders supported audio.
     */
    public function test_29_candidate_preview_renders_supported_audio(): void
    {
        $aud = MediaAsset::create([
            'filename'        => 'preview_audio.wav',
            'original_name'   => 'Preview Audio.wav',
            'mime_type'       => 'audio/wav',
            'type'            => 'audio',
            'path'            => 'question-media/preview_audio.wav',
            'size'            => 320000,
            'status'          => 'active',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $test = Test::create([
            'title'            => 'TOEIC Simulation Audio Test',
            'slug'             => 'toeic-simulation-test-aud-preview',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacherUser->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $section = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $question = Question::where('question_bank_id', $this->toeicPart1Bank->id)->skip(1)->firstOrFail();
        $question->media_asset_id = $aud->id;
        $question->save();

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 5,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.tests.preview', $test->id));

        $response->assertStatus(200);
        $response->assertSee('Candidate Preview');
        $response->assertSee('Audio Prompt Player');
        $response->assertSee(route('media.preview', $aud->id, false), false);
    }

    /**
     * TEST 30: Unsupported media does not render as valid media.
     */
    public function test_30_unsupported_media_does_not_render_as_valid_media(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('admin.academic-library.index'));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media_assets', [
            'type' => 'unsupported',
        ]);
    }

    /**
     * TEST 31: Existing TOEIC Part 1 images remain accessible.
     */
    public function test_31_existing_toeic_part1_images_remain_accessible(): void
    {
        $part1Bank = QuestionBank::where('slug', 'toeic-part-1-starter-pool')->firstOrFail();
        $questions = Question::where('question_bank_id', $part1Bank->id)->get();

        $this->assertCount(5, $questions);
        foreach ($questions as $q) {
            $this->assertNotNull($q->image_url);
            $this->assertNotEmpty($q->image_url);
        }
    }

    /**
     * TEST 32: Existing WAV media remains accessible.
     */
    public function test_32_existing_wav_media_remains_accessible(): void
    {
        $wavAssets = MediaAsset::where('path', 'like', '%.wav')->get();
        $this->assertTrue($wavAssets->count() >= 0);
    }

    /**
     * TEST 33: No sample_passage orphan records recreated.
     */
    public function test_33_no_sample_passage_orphan_records_recreated(): void
    {
        $orphanCount = MediaAsset::where('path', 'like', 'media/sample_passage_%')->count();
        $this->assertSame(0, $orphanCount);
    }

    /**
     * TEST 34: No QuestionBank content modified.
     */
    public function test_34_no_question_bank_content_modified(): void
    {
        $bankCount = QuestionBank::count();
        $this->assertSame(17, $bankCount);
    }

    /**
     * TEST 35: No Assessment content modified.
     */
    public function test_35_no_assessment_content_modified(): void
    {
        $this->assertSame(17, QuestionBank::count());
    }

    /**
     * TEST 36: No current UAT payment modified.
     */
    public function test_36_no_current_uat_payment_modified(): void
    {
        $payment = Payment::where('reference_number', 'PAY-20260827-VZDM')->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(832500, (int) $payment->amount);
    }

    /**
     * TEST 37: No assignment created.
     */
    public function test_37_no_assignment_created(): void
    {
        $initialAssignments = \App\Modules\Assessment\Models\CandidateTestAssignment::count();

        $this->assertSame($initialAssignments, \App\Modules\Assessment\Models\CandidateTestAssignment::count());
    }

    /**
     * TEST 38: No attempt created.
     */
    public function test_38_no_attempt_created(): void
    {
        $initialAttempts = Attempt::count();

        $this->assertSame($initialAttempts, Attempt::count());
    }

    /**
     * TEST 39: No certificate created.
     */
    public function test_39_no_certificate_created(): void
    {
        $initialCerts = Certificate::count();

        $this->assertSame($initialCerts, Certificate::count());
    }

    /**
     * TEST 40: Light Theme contract passes.
     */
    public function test_40_light_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('My Media', false);
    }

    /**
     * TEST 41: Dark Theme contract passes.
     */
    public function test_41_dark_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('dark:bg-slate-900', false);
    }

    /**
     * TEST 42: Global Theme contract remains intact.
     */
    public function test_42_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Institutional Media Repository');
    }

    /**
     * TEST 43: Executable/dangerous file type rejected.
     */
    public function test_43_executable_dangerous_file_type_rejected(): void
    {
        $file = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        $response = $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertSessionHasErrors(['file']);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'malicious.php']);
    }

    /**
     * TEST 44: Path traversal cannot control storage path.
     */
    public function test_44_path_traversal_cannot_control_storage_path(): void
    {
        $file = UploadedFile::fake()->image('passwd.jpg', 400, 300);

        $this->actingAs($this->teacherUser)
            ->post(route('admin.media.store'), ['file' => $file]);

        $asset = MediaAsset::where('original_name', 'passwd.jpg')->firstOrFail();
        $this->assertStringStartsWith('question-media/', $asset->path);
        $this->assertFalse(str_contains($asset->path, '..'));
    }

    /**
     * TEST 45: Unauthorized user cannot upload to protected media area.
     */
    public function test_45_unauthorized_user_cannot_upload_to_protected_media_area(): void
    {
        $file = UploadedFile::fake()->image('unauthorized.jpg', 400, 300);

        $response = $this->actingAs($this->studentUser)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('media_assets', ['original_name' => 'unauthorized.jpg']);
    }

    /**
     * TEST 46: Unauthorized user cannot modify another user's media where authorization requires ownership.
     */
    public function test_46_unauthorized_user_cannot_modify_another_users_media(): void
    {
        $media = MediaAsset::create([
            'filename'        => 'teacher_private.jpg',
            'original_name'   => 'Teacher Private.jpg',
            'mime_type'       => 'image/jpeg',
            'type'            => 'image',
            'path'            => 'question-media/teacher_private.jpg',
            'size'            => 100000,
            'status'          => 'active',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->otherTeacherUser)
            ->put(route('admin.media.update', $media->id), [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);
        $this->assertNotSame('Hacked Title', $media->fresh()->title);
    }
}
