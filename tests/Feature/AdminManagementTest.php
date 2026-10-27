<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Admin;
use App\Models\Role;
use App\Models\Permission;

class AdminManagementTest extends TestCase
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

    public function test_guest_cannot_view_admin_list(): void
    {
        $this->getJson('/api/admin/admins')
            ->assertUnauthorized();
    }

    public function test_developer_can_view_admin_list_without_exposing_passwords(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);
        $developer->refresh();

        Admin::create([
            'name' => '启用管理员',
            'email' => 'active@example.test',
            'password' => 'active-password',
        ]);

        $disabledAdmin = Admin::create([
            'name' => '停用管理员',
            'email' => 'disabled@example.test',
            'password' => 'disabled-password',
        ]);
        $disabledAdmin->status = 0;
        $disabledAdmin->save();

        $this->actingAs($developer, 'admin')
            ->getJson('/api/admin/admins')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment([
                'email' => 'active@example.test',
            ])
            ->assertJsonFragment([
                'email' => 'disabled@example.test',
                'status' => false,
            ])
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.1.password')
            ->assertJsonMissingPath('data.2.password');
    }

    public function test_regular_admin_without_list_permission_is_forbidden(): void
    {
        $admin = Admin::create([
            'name' => '普通管理员',
            'email' => 'operator@example.test',
            'password' => 'operator-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/admins')
            ->assertForbidden();
    }

    public function test_developer_can_create_admin_without_assigning_roles(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);
        $developer->refresh();

        $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/admins', [
                'name' => '新管理员',
                'email' => 'new-admin@example.test',
                'password' => 'new-admin-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '新管理员')
            ->assertJsonPath('data.email', 'new-admin@example.test')
            ->assertJsonPath('data.status', true)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('admins', [
            'email' => 'new-admin@example.test',
            'status' => 1,
        ]);

        $createdAdmin = Admin::where('email', 'new-admin@example.test')->firstOrFail();

        $this->assertNotSame('new-admin-password', $createdAdmin->password);
        $this->assertTrue(password_verify('new-admin-password', $createdAdmin->password));
        $this->assertCount(0, $createdAdmin->roles);
    }

    public function test_guest_cannot_create_admin(): void
    {
        $this->postJson('/api/admin/admins', [
            'name' => '新管理员',
            'email' => 'new-admin@example.test',
            'password' => 'new-admin-password',
        ])->assertUnauthorized();
    }

    public function test_admin_without_create_permission_cannot_create_admin(): void
    {
        $admin = Admin::create([
            'name' => '普通管理员',
            'email' => 'operator@example.test',
            'password' => 'operator-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->postJson('/api/admin/admins', [
                'name' => '新管理员',
                'email' => 'new-admin@example.test',
                'password' => 'new-admin-password',
            ])
            ->assertForbidden();
    }


    public function test_developer_can_update_admin_profile_without_changing_status_or_roles(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);
        $developer->refresh();

        $target = Admin::create([
            'name' => '原管理员',
            'email' => 'old-admin@example.test',
            'password' => 'old-password',
        ]);
        $target->refresh();

        $targetRole = new Role();
        $targetRole->name = '运营';
        $targetRole->save();

        $target->roles()->attach($targetRole->id);

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/admins/{$target->id}", [
                'name' => '修改后的管理员',
                'email' => 'updated-admin@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '修改后的管理员')
            ->assertJsonPath('data.email', 'updated-admin@example.test')
            ->assertJsonPath('data.status', true)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'name' => '修改后的管理员',
            'email' => 'updated-admin@example.test',
            'status' => 1,
        ]);

        $this->assertDatabaseHas('admin_role', [
            'admin_id' => $target->id,
            'role_id' => $targetRole->id,
        ]);
    }

    public function test_guest_cannot_update_admin_profile(): void
    {
        $target = Admin::create([
            'name' => '原管理员',
            'email' => 'old-admin@example.test',
            'password' => 'old-password',
        ]);

        $this->patchJson("/api/admin/admins/{$target->id}", [
            'name' => '修改后的管理员',
            'email' => 'updated-admin@example.test',
        ])->assertUnauthorized();
    }

    //
    public function test_developer_can_disable_another_admin(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);
        $developer->refresh();

        $target = Admin::create([
            'name' => '目标管理员',
            'email' => 'target@example.test',
            'password' => 'target-password',
        ]);
        $target->refresh();

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/admins/{$target->id}/status", [
                'status' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', false);

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'status' => 0,
        ]);
    }

    public function test_admin_cannot_disable_own_account(): void
    {
        $admin = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $admin->roles()->attach($developerRole->id);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->patchJson("/api/admin/admins/{$admin->id}/status", [
                'status' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'status' => 1,
        ]);
    }

    public function test_admin_cannot_disable_another_admin_who_can_disable_accounts(): void
    {
        $disablePermission = new Permission();
        $disablePermission->name = '停用管理员';
        $disablePermission->permission_code = 'admin.admins.status:patch';
        $disablePermission->type = 3;
        $disablePermission->status = 1;
        $disablePermission->save();

        $actor = Admin::create([
            'name' => '操作员',
            'email' => 'actor@example.test',
            'password' => 'actor-password',
        ]);

        $actorRole = new Role();
        $actorRole->name = '操作员角色';
        $actorRole->save();

        $actor->roles()->attach($actorRole->id);
        $actorRole->permissions()->attach($disablePermission->id);
        $actor->refresh();

        $target = Admin::create([
            'name' => '同级管理员',
            'email' => 'target@example.test',
            'password' => 'target-password',
        ]);
        $target->refresh();

        $targetRole = new Role();
        $targetRole->name = '同级角色';
        $targetRole->save();

        $target->roles()->attach($targetRole->id);
        $targetRole->permissions()->attach($disablePermission->id);

        $this->actingAs($actor, 'admin')
            ->patchJson("/api/admin/admins/{$target->id}/status", [
                'status' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'status' => 1,
        ]);
    }

    //
    public function test_developer_can_reset_another_admin_password(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);
        $developer->refresh();

        $target = Admin::create([
            'name' => '目标管理员',
            'email' => 'target@example.test',
            'password' => 'old-password',
        ]);
        $target->refresh();

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/admins/{$target->id}/password", [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.password');

        $target->refresh();

        $this->assertTrue(password_verify('new-secure-password', $target->password));
    }

    public function test_guest_cannot_reset_admin_password(): void
    {
        $target = Admin::create([
            'name' => '目标管理员',
            'email' => 'target@example.test',
            'password' => 'old-password',
        ]);

        $this->patchJson("/api/admin/admins/{$target->id}/password", [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnauthorized();
    }

    public function test_admin_without_password_reset_permission_cannot_reset_password(): void
    {
        $actor = Admin::create([
            'name' => '普通管理员',
            'email' => 'operator@example.test',
            'password' => 'operator-password',
        ]);
        $actor->refresh();

        $target = Admin::create([
            'name' => '目标管理员',
            'email' => 'target@example.test',
            'password' => 'old-password',
        ]);

        $this->actingAs($actor, 'admin')
            ->patchJson("/api/admin/admins/{$target->id}/password", [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertForbidden();

        $target->refresh();

        $this->assertTrue(password_verify('old-password', $target->password));
    }
}
