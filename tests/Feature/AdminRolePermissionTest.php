<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;




class AdminRolePermissionTest extends TestCase
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


    public function test_admin_can_resolve_roles_and_their_permissions(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '运营';
        $role->save();

        $permission = new Permission();
        $permission->name = '商品查看';
        $permission->type = 3;
        $permission->permission_code = 'admin.products.index:get';
        $permission->save();

        $admin->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        $adminWithRelations = Admin::with('roles.permissions')->findOrFail($admin->id);

        $this->assertSame($role->id, $adminWithRelations->roles->sole()->id);
        $this->assertSame(
            $permission->id,
            $adminWithRelations->roles->sole()->permissions->sole()->id,
        );
    }
}
