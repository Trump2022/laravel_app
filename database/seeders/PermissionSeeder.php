<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permission = Permission::firstOrNew([
            'permission_code' => 'admin.dashboard.index:get',
        ]);

        $permission->name = '查看后台仪表盘';
        $permission->pid = 0;
        $permission->id_path = '0,';
        $permission->type = 3;
        $permission->status = 1;
        $permission->sort = 0;
        $permission->save();
    }
}
