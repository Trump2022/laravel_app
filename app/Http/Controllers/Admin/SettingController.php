<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    /**
     * 设置列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'integer', 'between:1,5'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Setting::query()
            ->whereBetween('type', [1, 5]);

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function ($query) use ($keyword) {
                $query->where('setting_key', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $data = $query->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => '获取成功',
            'data' => $data,
        ]);
    }

    /**
     * 新增设置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $data = $this->validateSetting($request);
        $setting = Setting::create($data);
        return response()->json([
            'message' => '创建成功',
            'data' => $setting,
        ], 201);
    }

    /**
     * 设置详情
     */
    public function show(Setting $setting)
    {
        return response()->json([
            'message' => '获取成功',
            'data' => $setting,
        ]);
    }

    /**
     * 修改设置
     *
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     */
    public function update(Request $request, Setting $setting)
    {
        $data = $this->validateSetting($request, $setting);
        $setting->update($data);
        return response()->json([
            'message' => '修改成功',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * 删除设置
     *
     * @param Setting $setting
     * @return JsonResponse
     */
    public function destroy(Setting $setting)
    {
        $setting->delete();
        return response()->json([
            'message' => '删除成功',
        ]);
    }

    /**
     * 验证设置数据
     *
     * @param Request $request
     * @param Setting|null $setting
     * @return array
     */
    private function validateSetting(Request $request, ?Setting $setting = null)
    {
        $type = (int) $request->input('type');

        $rules = [
            'setting_key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('settings', 'setting_key')
                    ->ignore($setting?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'integer', 'between:1,5'],
        ];

        if ($type === 5) {
            $rules['json_value'] = ['required', 'array'];
            $rules['json_value.fields'] = ['required', 'array', 'min:1'];
            $rules['json_value.fields.*'] = ['required', 'array:title,key'];
            $rules['json_value.fields.*.title'] = ['required', 'string', 'max:150'];
            $rules['json_value.fields.*.key'] = [
                'required',
                'string',
                'max:100',
                'distinct:strict',
            ];
            $rules['json_value.data'] = ['present', 'array'];
            $rules['json_value.data.*'] = ['required', 'array'];
            $rules['json_value.data.*.*'] = ['string'];
            $rules['setting_value'] = ['prohibited'];
        } else {
            $rules['setting_value'] = match ($type) {
                1 => ['required', 'string', 'max:4096'],
                2 => ['required', 'integer'],
                3 => ['required', 'boolean'],
                4 => ['required', 'numeric'],
                default => ['prohibited'],
            };

            $rules['json_value'] = ['prohibited'];
        }

        $data = $request->validate($rules);

        if ($type === 5) {
            $data['setting_value'] = null;
        } else {
            if ($type === 3) {
                $data['setting_value'] = (string) (int) $data['setting_value'];
            } else {
                $data['setting_value'] = (string) $data['setting_value'];
            }

            $data['json_value'] = null;
        }

        return $data;
    }
}
