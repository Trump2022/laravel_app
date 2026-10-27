<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Admin;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_admin_api(): void
    {
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_admin_guard_can_access_admin_api(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('id', $admin->id);
    }

    public function test_admin_can_log_in_with_valid_credentials(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/admin/login', [
                'email' => 'admin@example.test',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('id', $admin->id);

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('id', $admin->id);
    }

    public function test_admin_cannot_log_in_with_wrong_password(): void
    {
        Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/admin/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_log_out(): void
    {
        Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/admin/login', [
                'email' => 'admin@example.test',
                'password' => 'password',
            ])
            ->assertOk();

        $this->postJson('/api/admin/logout')->assertOk();

        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_disabled_admin_cannot_log_in(): void
    {
        $admin = Admin::create([
            'name' => '已停用管理员',
            'email' => 'disabled@example.test',
            'password' => 'password',
        ]);

        $admin->status = 0;
        $admin->save();

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/admin/login', [
                'email' => 'disabled@example.test',
                'password' => 'password',
            ])
            ->assertUnprocessable();

        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_disabled_admin_cannot_continue_using_an_existing_session(): void
    {
        $admin = Admin::create([
            'name' => '已停用管理员',
            'email' => 'disabled@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin');

        $admin->status = 0;
        $admin->save();

        $this->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_admin_can_change_own_password_with_current_password(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'old-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->patchJson('/api/admin/me/password', [
                'current_password' => 'old-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk()
            ->assertJsonMissingPath('password');

        $admin->refresh();

        $this->assertTrue(password_verify('new-secure-password', $admin->password));
    }

    public function test_admin_cannot_change_own_password_with_wrong_current_password(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'old-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->patchJson('/api/admin/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertUnprocessable();

        $admin->refresh();

        $this->assertTrue(password_verify('old-password', $admin->password));
    }

    public function test_guest_cannot_change_admin_password(): void
    {
        $this->patchJson('/api/admin/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnauthorized();
    }
}
