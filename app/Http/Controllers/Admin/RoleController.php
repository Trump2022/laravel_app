<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->select(['id', 'name', 'code', 'remark'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                'unique:roles,code',
                Rule::notIn([
                    Role::DEVELOPER_CODE,
                    Role::SUPER_ADMIN_CODE,
                ]),
            ],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $role = new Role();
        $role->name = $validated['name'];
        $role->code = $validated['code'] ?? null;
        $role->remark = $validated['remark'] ?? null;
        $role->save();

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'remark' => $role->remark,
            ],
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $codeRules = [
            'sometimes',
            'nullable',
            'string',
            'max:255',
            Rule::unique('roles', 'code')->ignore($role->getKey()),
        ];

        if (in_array($role->code, [Role::DEVELOPER_CODE, Role::SUPER_ADMIN_CODE], true)) {
            $codeRules[] = Rule::in([$role->code]);
        } else {
            $codeRules[] = Rule::notIn([
                Role::DEVELOPER_CODE,
                Role::SUPER_ADMIN_CODE,
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->getKey()),
            ],
            'code' => $codeRules,
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // $role->fill($validated);
        if (array_key_exists('name', $validated)) {
            $role->name = $validated['name'];
        }

        if (array_key_exists('code', $validated)) {
            $role->code = $validated['code'];
        }

        if (array_key_exists('remark', $validated)) {
            $role->remark = $validated['remark'];
        }
        $role->save();

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'remark' => $role->remark,
            ],
        ]);
    }
}
