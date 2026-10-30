<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => Role::DEVELOPER_CODE,
                'name' => '开发者',
                'remark' => '系统开发与维护角色',
            ],
            [
                'code' => Role::SUPER_ADMIN_CODE,
                'name' => '超管',
                'remark' => '拥有后台业务超管权限',
            ],
            [
                'code' => null,
                'name' => '总经理',
                'remark' => '普通管理员',
            ],
            [
                'code' => null,
                'name' => '财务',
                'remark' => '普通管理员',
            ],
            [
                'code' => null,
                'name' => '人事',
                'remark' => '普通管理员',
            ],
        ];

        foreach ($roles as $item) {
            if ($item['code'] !== null) {
                $role = Role::updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'remark' => $item['remark'],
                    ]
                );
            } else {
                $role = Role::updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'code' => null,
                        'remark' => $item['remark'],
                    ]
                );
            }

            if ($role->code === Role::DEVELOPER_CODE) {
                $permissionIds = Permission::pluck('id');
            } elseif ($role->code === Role::SUPER_ADMIN_CODE) {
                $permissionIds = Permission::where('developer_only', 0)->pluck('id');
            } else {
                $permissionIds = Permission::where(
                    'permission_code',
                    'admin.dashboard.index:get'
                )->orWhere('path', '/admin/dashboard')->pluck('id');
            }

            $role->permissions()->syncWithoutDetaching($permissionIds->all());
        }
    }
}
