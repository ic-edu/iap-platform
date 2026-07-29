<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_profile_page_redirects_to_in_dashboard_profile_drawer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertRedirect(route('admin.dashboard', ['open_profile' => 1]));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $admin->refresh();

        $this->assertSame('Test User', $admin->name);
        $this->assertSame('test@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $admin->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull($admin->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/admin/dashboard')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/admin/dashboard');

        $this->assertNotNull($user->fresh());
    }
}
