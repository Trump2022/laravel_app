<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;



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

    public function test_admin_can_check_enabled_api_permission_by_route_and_method(): void
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
        $permission->permission_code = 'admin.products.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->save();

        $admin->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);

        $this->assertTrue($admin->hasApiPermission('admin.products.index', 'GET'));
        $this->assertFalse($admin->hasApiPermission('admin.products.index', 'POST'));

        $permission->status = 0;
        $permission->save();

        $this->assertFalse($admin->hasApiPermission('admin.products.index', 'GET'));
    }

    public function test_admin_can_access_enabled_api_permission_without_role_assignment_when_authorization_is_not_required(): void
    {
        $admin = Admin::create([
            'name' => '测试管理员',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $permission = new Permission();
        $permission->name = '免授权接口';
        $permission->permission_code = 'admin.public.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->authorization_required = false;
        $permission->save();

        $this->assertTrue($admin->hasApiPermission('admin.public.index', 'GET'));
    }

    public function test_super_admin_can_access_disabled_api_permission_without_role_assignment(): void
    {
        $admin = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '开发者（超管）';
        $role->code = 'super_admin';
        $role->save();

        $admin->roles()->attach($role->id);

        $permission = new Permission();
        $permission->name = '已停用接口';
        $permission->permission_code = 'admin.disabled.index:get';
        $permission->type = 3;
        $permission->status = 0;
        $permission->save();

        $this->assertTrue($admin->hasApiPermission('admin.disabled.index', 'GET'));
    }

    public function test_developer_can_access_developer_only_api_permission(): void
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
        $permission->name = '开发者专属接口';
        $permission->permission_code = 'admin.developer.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->developer_only = true;
        $permission->save();

        $this->assertTrue($admin->hasApiPermission('admin.developer.index', 'GET'));
    }

    public function test_super_admin_cannot_access_developer_only_api_permission(): void
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
        $permission->name = '开发者专属接口';
        $permission->permission_code = 'admin.developer.index:get';
        $permission->type = 3;
        $permission->status = 1;
        $permission->authorization_required = false;
        $permission->developer_only = true;
        $permission->save();

        $role->permissions()->attach($permission->id);

        $this->assertFalse($admin->hasApiPermission('admin.developer.index', 'GET'));
    }

    //
    public function test_guest_cannot_view_admin_roles(): void
    {
        $this->getJson('/api/admin/roles')
            ->assertUnauthorized();
    }

    public function test_developer_can_view_roles(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->remark = '系统开发与维护角色';
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        $developerRole = new Role();
        $developerRole->name = '运营';
        $developerRole->code = null;
        $developerRole->remark = '运营角色';
        $developerRole->save();

        $this->actingAs($developer, 'admin')
            ->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => '运营',
                'code' => null,
                'remark' => '运营角色',
            ])
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'code', 'remark'],
                ],
            ]);
    }

    public function test_admin_without_role_list_permission_is_forbidden(): void
    {
        $admin = Admin::create([
            'name' => '普通管理员',
            'email' => 'operator@example.test',
            'password' => 'operator-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->getJson('/api/admin/roles')
            ->assertForbidden();
    }

    //
    public function test_developer_can_create_role(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/roles', [
                'name' => '内容编辑',
                'code' => 'content_editor',
                'remark' => '负责内容维护',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '内容编辑')
            ->assertJsonPath('data.code', 'content_editor')
            ->assertJsonPath('data.remark', '负责内容维护');

        $this->assertDatabaseHas('roles', [
            'name' => '内容编辑',
            'code' => 'content_editor',
            'remark' => '负责内容维护',
        ]);
    }

    public function test_developer_can_create_a_role(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/roles', [
                'name' => '内容编辑',
                'code' => 'content_editor',
                'remark' => '负责内容维护',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '内容编辑')
            ->assertJsonPath('data.code', 'content_editor')
            ->assertJsonPath('data.remark', '负责内容维护');

        $this->assertDatabaseHas('roles', [
            'name' => '内容编辑',
            'code' => 'content_editor',
            'remark' => '负责内容维护',
        ]);
    }

    public function test_guest_cannot_create_a_role(): void
    {
        $this->postJson('/api/admin/roles', [
            'name' => '内容编辑',
            'code' => 'content_editor',
        ])->assertUnauthorized();
    }

    public function test_admin_without_role_create_permission_cannot_create_a_role(): void
    {
        $admin = Admin::create([
            'name' => '普通管理员',
            'email' => 'operator@example.test',
            'password' => 'operator-password',
        ]);
        $admin->refresh();

        $this->actingAs($admin, 'admin')
            ->postJson('/api/admin/roles', [
                'name' => '内容编辑',
                'code' => 'content_editor',
            ])
            ->assertForbidden();
    }

    public function test_developer_cannot_create_role_with_reserved_code(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/roles', [
                'name' => '伪开发者角色',
                'code' => Role::DEVELOPER_CODE,
            ])
            ->assertUnprocessable();
    }

    //
    public function test_developer_can_update_a_role(): void
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        $role = new Role();
        $role->name = '运营';
        $role->code = 'operator';
        $role->remark = '旧备注';
        $role->save();

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/roles/{$role->id}", [
                'name' => '内容运营',
                'code' => 'content_operator',
                'remark' => '负责内容管理',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '内容运营')
            ->assertJsonPath('data.code', 'content_operator')
            ->assertJsonPath('data.remark', '负责内容管理');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => '内容运营',
            'code' => 'content_operator',
            'remark' => '负责内容管理',
        ]);
    }

    public function test_guest_cannot_update_a_role(): void
    {
        $role = new Role();
        $role->name = '运营';
        $role->save();

        $this->patchJson("/api/admin/roles/{$role->id}", [
            'name' => '内容运营',
        ])->assertUnauthorized();
    }

    //
    public function test_developer_can_create_nested_menu_with_generated_id_path(): void
    {
        $developer = $this->createDeveloperAdmin();

        $rootResponse = $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/permissions', [
                'name' => '后台管理',
                'type' => 1,
                'pid' => 0,
                'status' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.pid', 0)
            ->assertJsonPath('data.id_path', '0,');

        $rootId = $rootResponse->json('data.id');

        $this->postJson('/api/admin/permissions', [
            'name' => '角色管理',
            'type' => 2,
            'pid' => $rootId,
            'path' => '/admin/roles',
            'component' => 'admin/RoleManagement',
            'status' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.pid', $rootId)
            ->assertJsonPath('data.id_path', "0,{$rootId},");

        $this->assertDatabaseHas('permissions', [
            'name' => '角色管理',
            'pid' => $rootId,
            'id_path' => "0,{$rootId},",
        ]);
    }

    public function test_developer_can_create_api_permission(): void
    {
        $developer = $this->createDeveloperAdmin();

        $this->actingAs($developer, 'admin')
            ->postJson('/api/admin/permissions', [
                'name' => '角色列表',
                'type' => 3,
                'permission_code' => 'admin.roles.index:get',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', '角色列表')
            ->assertJsonPath('data.type', 3)
            ->assertJsonPath('data.permission_code', 'admin.roles.index:get');

        $this->assertDatabaseHas('permissions', [
            'name' => '角色列表',
            'type' => 3,
            'permission_code' => 'admin.roles.index:get',
            'status' => 0,
        ]);
    }

    private function createDeveloperAdmin(): Admin
    {
        $developer = Admin::create([
            'name' => '开发者',
            'email' => 'developer@example.test',
            'password' => 'developer-password',
        ]);
        $developer->refresh();

        $developerRole = new Role();
        $developerRole->name = '开发者';
        $developerRole->code = Role::DEVELOPER_CODE;
        $developerRole->save();

        $developer->roles()->attach($developerRole->id);

        return $developer;
    }

    //
    public function test_developer_can_get_permission_tree_with_one_permissions_query(): void
    {
        $developer = $this->createDeveloperAdmin();

        $root = new Permission();
        $root->name = '后台管理';
        $root->type = 1;
        $root->pid = 0;
        $root->id_path = '0,';
        $root->save();

        $page = new Permission();
        $page->name = '角色管理';
        $page->type = 2;
        $page->pid = $root->id;
        $page->id_path = "0,{$root->id},";
        $page->save();

        $api = new Permission();
        $api->name = '角色列表';
        $api->type = 3;
        $api->pid = $page->id;
        $api->id_path = "0,{$root->id},{$page->id},";
        $api->permission_code = 'admin.roles.index:get';
        $api->save();

        $orphan = new Permission();
        $orphan->name = '孤儿节点';
        $orphan->type = 3;
        $orphan->pid = 999999;
        $orphan->id_path = '0,999999,';
        $orphan->save();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($developer, 'admin')
            ->getJson('/api/admin/permissions/tree');

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $permissionQueries = array_filter(
            $queryLog,
            fn (array $query): bool => str_contains(
                strtolower($query['query']),
                'permissions',
            ),
        );

        $response->assertOk();

        $tree = $response->json('data');
        $this->assertCount(1, $tree);
        $this->assertSame($root->id, $tree[0]['id']);
        $this->assertSame($page->id, $tree[0]['children'][0]['id']);
        $this->assertSame($api->id, $tree[0]['children'][0]['children'][0]['id']);

        $treeJson = json_encode($tree);
        $this->assertIsString($treeJson);
        $this->assertStringNotContainsString('孤儿节点', $treeJson);
        $this->assertCount(1, $permissionQueries);
    }

    //
    public function test_developer_can_move_permission_and_update_descendant_paths(): void
    {
        $developer = $this->createDeveloperAdmin();

        $rootA = new Permission();
        $rootA->name = '目录A';
        $rootA->type = 1;
        $rootA->pid = 0;
        $rootA->id_path = '0,';
        $rootA->save();

        $rootB = new Permission();
        $rootB->name = '页面B';
        $rootB->type = 2;
        $rootB->pid = 0;
        $rootB->id_path = '0,';
        $rootB->path = '/page-b';
        $rootB->component = 'PageB';
        $rootB->save();

        $child = new Permission();
        $child->name = '子节点';
        $child->type = 1;
        $child->pid = $rootA->id;
        $child->id_path = "0,{$rootA->id},";
        $child->save();

        $grandchild = new Permission();
        $grandchild->name = '孙节点';
        $grandchild->type = 3;
        $grandchild->pid = $child->id;
        $grandchild->id_path = "0,{$rootA->id},{$child->id},";
        $grandchild->permission_code = 'admin.example.index:get';
        $grandchild->save();

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/permissions/{$child->id}", [
                'pid' => $rootB->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.pid', $rootB->id)
            ->assertJsonPath('data.id_path', "0,{$rootB->id},");

        $this->assertDatabaseHas('permissions', [
            'id' => $child->id,
            'pid' => $rootB->id,
            'id_path' => "0,{$rootB->id},",
        ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $grandchild->id,
            'pid' => $child->id,
            'id_path' => "0,{$rootB->id},{$child->id},",
        ]);
    }

    public function test_permission_cannot_be_moved_under_its_descendant(): void
    {
        $developer = $this->createDeveloperAdmin();

        $parent = new Permission();
        $parent->name = '父节点';
        $parent->type = 1;
        $parent->pid = 0;
        $parent->id_path = '0,';
        $parent->save();

        $child = new Permission();
        $child->name = '子节点';
        $child->type = 1;
        $child->pid = $parent->id;
        $child->id_path = "0,{$parent->id},";
        $child->save();

        $this->actingAs($developer, 'admin')
            ->patchJson("/api/admin/permissions/{$parent->id}", [
                'pid' => $child->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('permissions', [
            'id' => $parent->id,
            'pid' => 0,
            'id_path' => '0,',
        ]);
    }
}
