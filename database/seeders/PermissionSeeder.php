<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // 权限管理目录
            [
                'key' => 'permission',
                'name' => '权限管理',
                'type' => 1,
                'pid' => null,
                'sort' => 1,
            ],
            [
                'key' => 'admins',
                'name' => '管理员管理',
                'type' => 2,
                'pid' => 'permission',
                'path' => '/admin/admins',
                'component' => 'admin/AdminManagement',
                'sort' => 2,
            ],
            [
                'key' => 'roles',
                'name' => '角色管理',
                'type' => 2,
                'pid' => 'permission',
                'path' => '/admin/roles',
                'component' => 'admin/RoleManagement',
                'sort' => 1,
            ],
            [
                'key' => 'permissions',
                'name' => '菜单管理',
                'type' => 2,
                'pid' => 'permission',
                'path' => '/admin/permissions',
                'component' => 'admin/PermissionManagement',
                'developer_only' => 1,
                'sort' => 0,
            ],

            // 仪表盘
            [
                'key' => 'dashboard',
                'name' => '仪表盘',
                'type' => 2,
                'pid' => null,
                'path' => '/admin/dashboard',
                'component' => 'dashboard',
                'sort' => 0,
            ],

            // 通用设置目录
            [
                'key' => 'settings',
                'name' => '通用设置',
                'type' => 1,
                'pid' => null,
                'sort' => 2,
            ],

            // 菜单管理 API
            [
                'key' => 'permission_index',
                'name' => '权限列表',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.index:get',
                'developer_only' => 1,
                'sort' => 0,
            ],
            [
                'key' => 'permission_tree',
                'name' => '权限树',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.tree:get',
                'developer_only' => 1,
                'sort' => 1,
            ],
            [
                'key' => 'permission_store',
                'name' => '新建菜单或权限',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.store:post',
                'developer_only' => 1,
                'sort' => 2,
            ],
            [
                'key' => 'permission_update',
                'name' => '编辑菜单或权限',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.update:patch',
                'developer_only' => 1,
                'sort' => 3,
            ],
            [
                'key' => 'permission_destroy',
                'name' => '删除菜单或权限',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.destroy:delete',
                'developer_only' => 1,
                'sort' => 4,
            ],
            [
                'key' => 'permission_move',
                'name' => '移动菜单或权限',
                'type' => 3,
                'pid' => 'permissions',
                'permission_code' => 'admin.permissions.move:patch',
                'developer_only' => 1,
                'sort' => 5,
            ],

            // 角色管理 API
            [
                'key' => 'role_index',
                'name' => '角色列表',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.index:get',
                'sort' => 0,
            ],
            [
                'key' => 'role_store',
                'name' => '新建角色',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.store:post',
                'sort' => 1,
            ],
            [
                'key' => 'role_update',
                'name' => '编辑角色',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.update:patch',
                'sort' => 2,
            ],
            [
                'key' => 'role_permissions_index',
                'name' => '查看角色权限',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.permissions.index:get',
                'sort' => 3,
            ],
            [
                'key' => 'role_permissions_assign',
                'name' => '分配角色权限',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.permissions.assign:put',
                'sort' => 4,
            ],
            [
                'key' => 'role_permissions_revoke',
                'name' => '移除角色权限',
                'type' => 3,
                'pid' => 'roles',
                'permission_code' => 'admin.roles.permissions.revoke:delete',
                'sort' => 5,
            ],

            // 管理员管理 API
            [
                'key' => 'admin_index',
                'name' => '管理员列表',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.index:get',
                'sort' => 0,
            ],
            [
                'key' => 'admin_store',
                'name' => '新建管理员',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.store:post',
                'sort' => 1,
            ],
            [
                'key' => 'admin_update',
                'name' => '编辑管理员资料',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.update:patch',
                'sort' => 2,
            ],
            [
                'key' => 'admin_status',
                'name' => '启用或停用管理员',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.status:patch',
                'sort' => 3,
            ],
            [
                'key' => 'admin_password',
                'name' => '重置管理员密码',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.password:patch',
                'sort' => 4,
            ],
            [
                'key' => 'admin_roles_assign',
                'name' => '分配管理员角色',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.roles.assign:put',
                'sort' => 5,
            ],
            [
                'key' => 'admin_roles_revoke',
                'name' => '移除管理员角色',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.roles.revoke:delete',
                'sort' => 6,
            ],
            [
                'key' => 'admin_role_options',
                'name' => '获取管理员可选角色',
                'type' => 3,
                'pid' => 'admins',
                'permission_code' => 'admin.admins.role-options:get',
                'sort' => 7,
            ],

            // 仪表盘 API
            [
                'key' => 'dashboard_index',
                'name' => '查看后台仪表盘',
                'type' => 3,
                'pid' => 'dashboard',
                'permission_code' => 'admin.dashboard.index:get',
                'sort' => 0,
            ],
        ];

        $ids = [];

        foreach ($items as $item) {
            $parentId = $item['pid'] === null
                ? 0
                : $ids[$item['pid']];

            if (isset($item['permission_code'])) {
                $permission = Permission::firstOrNew([
                    'permission_code' => $item['permission_code'],
                ]);
            } else {
                $permission = Permission::firstOrNew([
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'pid' => $parentId,
                ]);
            }

            $permission->pid = $parentId;

            if ($parentId === 0) {
                $permission->id_path = '0,';
            } else {
                $parent = Permission::findOrFail($parentId);
                $permission->id_path = $parent->id_path . $parentId . ',';
            }

            $permission->name = $item['name'];
            $permission->icon = $item['icon'] ?? null;
            $permission->path = $item['path'] ?? null;
            $permission->component = $item['component'] ?? null;
            $permission->permission_code = $item['permission_code'] ?? null;
            $permission->type = $item['type'];
            $permission->status = 1;
            $permission->sort = $item['sort'];
            $permission->remark = $item['remark'] ?? null;
            $permission->authorization_required = 1;
            $permission->developer_only = $item['developer_only'] ?? 0;

            $permission->save();

            $ids[$item['key']] = $permission->id;
        }
    }
}
