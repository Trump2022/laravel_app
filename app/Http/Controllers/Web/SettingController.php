<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * 获取网站设置
     */
    public function getWebSiteSettings(Request $request)
    {
        $keys = $request->input('keys');

        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }

        // 默认获取的设置项
        $defaultKeys = [
            'web_title',
        ];

        $keys = empty($keys) ? $defaultKeys : $keys;

        $result = Setting::query()
            ->whereIn('setting_key', $keys)
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->setting_key => $item->value,
            ]);

        return response()->json([
            'message' => '获取成功',
            'data' => $result,
        ]);
    }
}
