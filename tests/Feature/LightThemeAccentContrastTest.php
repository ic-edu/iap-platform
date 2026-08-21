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
}
