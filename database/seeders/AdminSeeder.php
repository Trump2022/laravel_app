<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name' => 'dev',
                'email' => 'dev@example.com',
                'password' => '12345678',
                'roles' => [
                    ['field' => 'code', 'value' => Role::DEVELOPER_CODE],
                ],
            ],
            [
                'name' => 'admin',
                'email' => 'admin@example.com',
                'password' => '12345678',
                'roles' => [
                    ['field' => 'code', 'value' => Role::SUPER_ADMIN_CODE],
                ],
            ],
            [
                'name' => 'manager',
                'email' => 'manage@example.com',
                'password' => '12345678',
                'roles' => [
                    ['field' => 'name', 'value' => '市场总监'],
                    ['field' => 'name', 'value' => '总经理'],
                ],
            ],
        ];

        foreach ($admins as $item) {
            $admin = Admin::firstOrNew([
                'email' => $item['email'],
            ]);

            $admin->name = $item['name'];
            $admin->password = $item['password'];
            $admin->status = 1;
            $admin->save();

            $roleIds = [];

            foreach ($item['roles'] as $roleItem) {
                $role = Role::where(
                    $roleItem['field'],
                    $roleItem['value']
                )->firstOrFail();

                $roleIds[] = $role->id;
            }

            $admin->roles()->sync($roleIds);
        }
    }
}
