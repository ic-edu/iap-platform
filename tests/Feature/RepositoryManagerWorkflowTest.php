<?php

use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryReviewRequest;
use App\Models\RepositoryVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
});

test('repository manager can access command center dashboard', function () {
    $repoManager = User::factory()->create();
    $repoManager->assignRole('repository-manager');

    $response = $this->actingAs($repoManager)->get('/admin/repository-manager/dashboard');
    $response->assertStatus(200)
        ->assertSee('Repository Manager Command Center', false);
});

test('regular admin cannot access media approval actions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $media = MediaAsset::create([
        'filename'        => 'test_sample.jpg',
        'original_name'   => 'test_sample.jpg',
        'mime_type'       => 'image/jpeg',
        'type'            => 'image',
        'path'            => 'images/test_sample.jpg',
        'size'            => 1024,
        'status'          => 'published',
        'approval_status' => 'approved',
        'uploaded_by'     => $admin->id,
    ]);

    $reviewRequest = RepositoryReviewRequest::create([
        'resource_type' => 'MediaAsset',
        'resource_id'   => $media->id,
        'submitted_by'  => $admin->id,
        'status'        => 'pending_review',
        'changes_data'  => ['new_title' => 'Updated Title Test'],
    ]);

    $response = $this->actingAs($admin)->post('/admin/repository-manager/media/' . $reviewRequest->id . '/approve');
    $response->assertStatus(403);
});

test('teacher can submit media revision request', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $media = MediaAsset::create([
        'filename'        => 'teacher_sample.jpg',
        'original_name'   => 'teacher_sample.jpg',
        'mime_type'       => 'image/jpeg',
        'type'            => 'image',
        'path'            => 'images/teacher_sample.jpg',
        'size'            => 1024,
        'status'          => 'published',
        'approval_status' => 'approved',
        'uploaded_by'     => $teacher->id,
    ]);

    $response = $this->actingAs($teacher)->patch('/admin/media/' . $media->id . '/metadata', [
        'title'       => 'Proposed New Academic Title',
        'description' => 'Updated pedagogical purpose description.',
    ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('repository_review_requests', [
        'resource_type' => 'MediaAsset',
        'resource_id'   => $media->id,
        'submitted_by'  => $teacher->id,
        'status'        => 'pending_review',
    ]);
});

test('repository manager can approve revision request and increment version', function () {
    $repoManager = User::factory()->create();
    $repoManager->assignRole('repository-manager');

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $media = MediaAsset::create([
        'filename'        => 'sample_audio.wav',
        'original_name'   => 'sample_audio.wav',
        'mime_type'       => 'audio/wav',
        'type'            => 'audio',
        'path'            => 'media/sample_audio.wav',
        'size'            => 1024,
        'status'          => 'published',
        'approval_status' => 'approved',
        'version'         => 'v1.0',
        'title'           => 'Initial Audio Title',
        'uploaded_by'     => $teacher->id,
    ]);

    $reviewRequest = RepositoryReviewRequest::create([
        'resource_type' => 'MediaAsset',
        'resource_id'   => $media->id,
        'submitted_by'  => $teacher->id,
        'status'        => 'pending_review',
        'changes_data'  => [
            'media_type' => 'audio',
            'new_title'  => 'Approved High Quality Audio',
        ],
    ]);

    $response = $this->actingAs($repoManager)->post('/admin/repository-manager/media/' . $reviewRequest->id . '/approve', [
        'notes' => 'Verified audio clarity and transcript completeness.',
    ]);

    $response->assertRedirect('/admin/repository-manager/media');

    $media->refresh();
    expect($media->title)->toBe('Approved High Quality Audio');
    expect($media->version)->toBe('v1.1');
    expect($media->approval_status)->toBe('approved');

    $this->assertDatabaseHas('repository_versions', [
        'resource_type'  => 'MediaAsset',
        'resource_id'    => $media->id,
        'version_number' => 'v1.1',
        'is_current'     => true,
    ]);

    $this->assertDatabaseHas('repository_activity_logs', [
        'resource_type' => 'MediaAsset',
        'resource_id'   => $media->id,
        'action'        => 'approved',
        'reviewer_id'   => $repoManager->id,
    ]);
});

test('repository manager login redirects to repository manager dashboard', function () {
    $repoManager = User::factory()->create([
        'email'    => 'repomanager_test@icedu.com',
        'password' => Hash::make('password'),
        'status'   => 'active',
    ]);
    $repoManager->assignRole('repository-manager');

    $response = $this->post('/login', [
        'email'    => 'repomanager_test@icedu.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin/repository-manager/dashboard');
});

test('teacher cannot see IRQA Quality Audit button in academic library', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $response = $this->actingAs($teacher)->get('/admin/academic-library');
    $response->assertStatus(200)
        ->assertDontSee('IRQA Quality Audit', false)
        ->assertSee('Authoring Workspace', false);
});
