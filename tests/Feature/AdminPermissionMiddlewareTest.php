<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;

class AdminPermissionMiddlewareTest extends TestCase
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

    public function test_guest_cannot_access_permission_protected_api(): void
    {
        $this->getJson('/api/admin/dashboard/index')->assertUnauthorized();
    }

    public function test_admin_without_permission_gets_forbidden_response(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertForbidden();
    }

    public function test_admin_with_enabled_permission_can_access_api(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '仪表盘查看';
        $role->save();

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->save();

        $admin->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertOk()
            ->assertJsonPath('message', '后台仪表盘');
    }

    public function test_admin_cannot_access_api_with_disabled_permission(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '仪表盘查看';
        $role->save();

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 0;
        $permission->save();

        $admin->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertForbidden();
    }

    public function test_authenticated_admin_can_access_api_without_role_assignment_when_authorization_is_not_required(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->authorization_required = false;
        $permission->save();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertOk()
            ->assertJsonPath('message', '后台仪表盘');
    }

    public function test_super_admin_can_access_api_with_disabled_permission(): void
    {
        $admin = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '开发者（超管）';
        $role->code = Role::SUPER_ADMIN_CODE;
        $role->save();

        $admin->roles()->attach($role->id);

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 0;
        $permission->save();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertOk()
            ->assertJsonPath('message', '后台仪表盘');
    }

    public function test_developer_can_access_developer_only_api(): void
    {
        $admin = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '开发者';
        $role->code = Role::DEVELOPER_CODE;
        $role->save();

        $admin->roles()->attach($role->id);

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->developer_only = true;
        $permission->save();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertOk()
            ->assertJsonPath('message', '后台仪表盘');
    }

    public function test_super_admin_cannot_access_developer_only_api(): void
    {
        $admin = Admin::create([
            'name' => '超管',
            'email' => 'super-admin@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '超管';
        $role->code = Role::SUPER_ADMIN_CODE;
        $role->save();

        $admin->roles()->attach($role->id);

        $permission = new Permission();
        $permission->name = '仪表盘查看';
        $permission->permission_code = 'admin.dashboard.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->authorization_required = false;
        $permission->developer_only = true;
        $permission->save();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/dashboard/index')
            ->assertForbidden();
    }
}
