<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_cannot_see_developer_only_permissions(): void
    {
        $admin = Admin::create([
            'name' => '运营人员',
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '运营';
        $role->save();

        $admin->roles()->attach($role->id);

        $listPermission = $this->createPermission(
            '查看权限列表',
            'admin.permissions.index:get',
        );
        $role->permissions()->attach($listPermission->id);

        $this->createPermission(
            '商品查看',
            'admin.products.index:get',
        );

        $this->createPermission(
            '权限删除',
            'admin.permissions.destroy:delete',
            developerOnly: true,
        );

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/permissions')
            ->assertOk()
            ->assertJsonFragment([
                'permission_code' => 'admin.products.index:get',
            ])
            ->assertJsonMissing([
                'permission_code' => 'admin.permissions.destroy:delete',
            ]);
    }

    public function test_super_admin_cannot_see_developer_only_permissions(): void
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

        $this->createPermission(
            '查看权限列表',
            'admin.permissions.index:get',
        );

        $this->createPermission(
            '权限删除',
            'admin.permissions.destroy:delete',
            developerOnly: true,
        );

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/permissions')
            ->assertOk()
            ->assertJsonMissing([
                'permission_code' => 'admin.permissions.destroy:delete',
            ]);
    }

    public function test_developer_can_see_developer_only_permissions(): void
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

        $this->createPermission(
            '权限删除',
            'admin.permissions.destroy:delete',
            developerOnly: true,
        );

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/permissions')
            ->assertOk()
            ->assertJsonFragment([
                'permission_code' => 'admin.permissions.destroy:delete',
            ]);
    }

    private function createPermission(
        string $name,
        string $permissionCode,
        bool $developerOnly = false,
    ): Permission {
        $permission = new Permission();
        $permission->name = $name;
        $permission->permission_code = $permissionCode;
        $permission->type = 3;
        $permission->status = 1;
        $permission->developer_only = $developerOnly;
        $permission->save();

        return $permission;
    }

    public function test_regular_admin_without_permission_cannot_view_permission_list(): void
    {
        $admin = Admin::create([
            'name' => '运营人员',
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/permissions')
            ->assertForbidden();
    }

    public function test_role_permission_options_exclude_disabled_permissions(): void
    {
        $admin = Admin::create([
            'name' => '超管',
            'email' => 'super-admin@example.test',
            'password' => 'password',
        ]);

        $superAdminRole = new Role();
        $superAdminRole->name = '超管';
        $superAdminRole->code = Role::SUPER_ADMIN_CODE;
        $superAdminRole->save();

        $admin->roles()->attach($superAdminRole->id);

        $targetRole = new Role();
        $targetRole->name = '运营';
        $targetRole->save();

        $this->createPermission(
            '查看角色权限选项',
            'admin.roles.permissions.index:get',
        );

        $this->createPermission(
            '商品查看',
            'admin.products.index:get',
        );

        $disabledPermission = $this->createPermission(
            '商品删除',
            'admin.products.destroy:delete',
        );
        $disabledPermission->status = 0;
        $disabledPermission->save();

        $this->actingAs($admin, 'admin')
            ->getJson("/api/admin/roles/{$targetRole->id}/permissions")
            ->assertOk()
            ->assertJsonFragment([
                'permission_code' => 'admin.products.index:get',
            ])
            ->assertJsonMissing([
                'permission_code' => 'admin.products.destroy:delete',
            ]);
    }
}
