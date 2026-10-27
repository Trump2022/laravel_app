<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::updateOrCreate(
            ['code' => Role::DEVELOPER_CODE],
            [
                'name' => '开发者',
                'remark' => '系统开发与维护角色',
            ],
        );

        Role::updateOrCreate(
            ['code' => Role::SUPER_ADMIN_CODE],
            [
                'name' => '超管',
                'remark' => '拥有后台业务超管权限',
            ],
        );
    }
}
