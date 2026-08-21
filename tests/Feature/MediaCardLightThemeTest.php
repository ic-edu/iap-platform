<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaCardLightThemeTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected MediaAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('teacher');

        $this->asset = MediaAsset::create([
            'title'         => 'Test Audio Prompt',
            'original_name' => 'test_audio.mp3',
            'filename'      => 'test_audio.mp3',
            'path'          => 'media/test_audio.mp3',
            'disk'          => 'public',
            'mime_type'     => 'audio/mpeg',
            'size'          => 1024,
            'type'          => 'audio',
            'exam_type'     => 'toeic',
            'uploaded_by'   => $this->teacher->id,
            'status'        => 'published',
            'version'       => '1.0',
        ]);
    }

    public function test_institutional_media_repository_renders_action_footers(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('imr-foot');
        $response->assertSee('imr-btn-neutral');
        $response->assertSee('imr-btn-edit');
        $response->assertSee('imr-btn-tracker');
        $response->assertSee('Preview');
        $response->assertSee('Edit');
        $response->assertSee('Version History');
        $response->assertSee('Tracker');
    }

    public function test_media_show_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.media.show', $this->asset->id));
        $response->assertStatus(200);
        $response->assertSee('imd-page');
    }

    public function test_media_edit_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.media.edit', $this->asset->id));
        $response->assertStatus(200);
        $response->assertSee('tme-page');
    }
}
