<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankLockMessageStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name' => 'Teacher User',
            'email' => 'teacher_lock_test@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');
    }

    private function createBankWithStatus(string $status): QuestionBank
    {
        return QuestionBank::create([
            'title' => 'Test Bank ' . $status,
            'slug' => 'test-bank-' . $status . '-' . uniqid(),
            'test_type' => 'general',
            'status' => $status,
            'is_published' => ($status === 'published'),
            'created_by' => $this->teacher->id,
        ]);
    }

    /**
     * 1. PENDING_APPROVAL -> awaiting governance approval message
     */
    public function test_pending_approval_shows_awaiting_governance_approval_message()
    {
        $bank = $this->createBankWithStatus('pending_approval');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('🔒 Repository locked while awaiting governance approval.');
        $response->assertDontSee('🔒 Repository published and locked from editing.');
        $response->assertDontSee('🔒 Repository approved and locked from editing.');
    }

    /**
     * 2. PENDING_RESTORE_APPROVAL -> awaiting restoration approval message
     */
    public function test_pending_restore_approval_shows_awaiting_restoration_approval_message()
    {
        $bank = $this->createBankWithStatus('pending_restore_approval');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('🔒 Repository locked while awaiting restoration approval.');
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
    }

    /**
     * 3. APPROVED -> approved and locked message
     */
    public function test_approved_shows_approved_and_locked_message()
    {
        $bank = $this->createBankWithStatus('approved');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('🔒 Repository approved and locked from editing.');
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
        $response->assertDontSee('🔒 Repository published and locked from editing.');
    }

    /**
     * 4. PUBLISHED -> published and locked message
     */
    public function test_published_shows_published_and_locked_message()
    {
        $bank = $this->createBankWithStatus('published');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('🔒 Repository published and locked from editing.');
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
        $response->assertDontSee('🔒 Repository approved and locked from editing.');
    }

    /**
     * 5. ARCHIVED -> archived and locked message
     */
    public function test_archived_shows_archived_and_locked_message()
    {
        $bank = $this->createBankWithStatus('archived');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertSee('🔒 Repository archived and locked from editing.');
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
    }

    /**
     * 6. NEEDS_REVISION -> does NOT show governance-lock message (shows authoring/editing controls)
     */
    public function test_needs_revision_does_not_show_governance_lock_message()
    {
        $bank = $this->createBankWithStatus('needs_revision');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
        $response->assertDontSee('🔒 Repository published and locked from editing.');
        $response->assertSee('+ Add Question');
        $response->assertSee('✏ Edit Bank Details');
    }

    /**
     * 7. DRAFT -> does NOT show repository-lock message (shows authoring/editing controls)
     */
    public function test_draft_does_not_show_repository_lock_message()
    {
        $bank = $this->createBankWithStatus('draft');

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.show', $bank->id));

        $response->assertStatus(200);
        $response->assertDontSee('🔒 Repository locked while awaiting governance approval.');
        $response->assertDontSee('🔒 Repository published and locked from editing.');
        $response->assertSee('+ Add Question');
        $response->assertSee('✏ Edit Bank Details');
    }

    /**
     * 8. Direct model unit test for getLockMessage()
     */
    public function test_model_get_lock_message_mapping()
    {
        $bank = new QuestionBank(['status' => 'pending_approval']);
        $this->assertEquals('🔒 Repository locked while awaiting governance approval.', $bank->getLockMessage());

        $bank->status = 'pending_restore_approval';
        $this->assertEquals('🔒 Repository locked while awaiting restoration approval.', $bank->getLockMessage());

        $bank->status = 'approved';
        $this->assertEquals('🔒 Repository approved and locked from editing.', $bank->getLockMessage());

        $bank->status = 'published';
        $this->assertEquals('🔒 Repository published and locked from editing.', $bank->getLockMessage());

        $bank->status = 'archived';
        $this->assertEquals('🔒 Repository archived and locked from editing.', $bank->getLockMessage());

        $bank->status = 'needs_revision';
        $this->assertNull($bank->getLockMessage());

        $bank->status = 'draft';
        $this->assertNull($bank->getLockMessage());
    }
}
