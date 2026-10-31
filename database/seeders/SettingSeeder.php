<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::firstOrCreate(
            ['setting_key' => 'web_title'],
            [
                'name' => '网站标题',
                'type' => 1,
                'setting_value' => '欢迎你',
                'json_value' => null,
            ],
        );

        Setting::firstOrCreate(
            ['setting_key' => 'web_admin_title'],
            [
                'name' => '后台title',
                'type' => 1,
                'setting_value' => '后台',
                'json_value' => null,
            ],
        );
    }
}
