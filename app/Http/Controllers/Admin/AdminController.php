<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = Admin::query()
            ->select(['id', 'name', 'email', 'status', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $admins,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $admin = Admin::create($validated);
        $admin->refresh();

        return response()->json([
            'data' => $admin->only([
                'id',
                'name',
                'email',
                'status',
                'created_at',
            ]),
        ], 201);
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin->getKey()),
            ],
        ]);

        $admin->update($validated);
        $admin->refresh();

        return response()->json([
            'data' => $admin->only([
                'id',
                'name',
                'email',
                'status',
                'created_at',
            ]),
        ]);
    }

    public function updatePassword(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $actor = $request->user('admin');
        if ($actor->getKey() === $admin->getKey()) {
            return response()->json([
                'message' => 'Use the change-own-password endpoint to update your password.',
            ], 403);
        }

        $targetCanResetAdminPasswords = $admin->hasApiPermission(
            'admin.admins.password',
            'PATCH',
        );

        $actorLevel = $actor->isDeveloper() ? 2 : ($actor->isSuperAdmin() ? 1 : 0);
        $targetLevel = $admin->isDeveloper() ? 2 : ($admin->isSuperAdmin() ? 1 : 0);
        if ($targetCanResetAdminPasswords && $targetLevel >= $actorLevel) {
            return response()->json([
                'message' => 'You cannot reset the password of an admin with equal or higher privilege.',
            ], 403);
        }

        $admin->password = $validated['password'];
        $admin->save();

        return response()->json([
            'data' => $admin->only([
                'id',
                'name',
                'email',
            ]),
        ]);
    }

    public function updateStatus(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean']
        ]);
        $actor = $request->user('admin');
        $status = (bool) $validated['status'];
        if (! $status && $actor->getKey() === $admin->getKey()) {
            return response()->json([
                'message' => 'You cannot disable your own account.'
            ], 403);
        }

        $targetCanDisableAdmins = $admin->hasApiPermission('admin.admins.status','PATCH');
        $actorLevel = $actor->isDeveloper() ? 2 : ($actor->isSuperAdmin() ? 1 : 0);
        $targetLevel = $admin->isDeveloper() ? 2 : ($admin->isSuperAdmin() ? 1 : 0);
        if (! $status && $targetCanDisableAdmins && $targetLevel >= $actorLevel) {
            return response()->json([
                'message' => 'You cannot disable an admin with equal or higher privilege.',
            ], 403);
        }

        $admin->status = $status;
        $admin->save();
        $admin->refresh();
        return response()->json([
            'data' => \Illuminate\Support\Arr::only($admin->toArray(), [
                'id',
                'name',
                'email',
                'status',
                'created_at',
                'updated_at',
            ]),
        ]);
    }
}
