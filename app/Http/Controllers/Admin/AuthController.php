<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->status) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json($this->adminData($admin));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerate();

        return response()->json([
            'message' => '已退出登录。',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json($this->adminData($admin));
    }

    public function updateOwnPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确。'],
            ]);
        }

        $admin->password = $validated['password'];
        $admin->save();

        return response()->json([
            'message' => '密码修改成功。',
        ]);
    }

    private function adminData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role_codes' => $admin->roles()
                ->pluck('code')
                ->filter()
                ->values()
                ->all(),
            'can_view_admin_role_options' => $admin->hasApiPermission(
                'admin.admins.role-options',
                'GET',
            ),
            'can_manage_role_permissions' => $admin->hasApiPermission('admin.roles.permissions.index', 'GET')
                && $admin->hasApiPermission('admin.roles.permissions.assign', 'PUT')
                && $admin->hasApiPermission('admin.roles.permissions.revoke', 'DELETE'),
            'can_update_admins' => $admin->hasApiPermission(
                'admin.admins.update',
                'PATCH',
            ),
            'can_reset_admin_passwords' => $admin->hasApiPermission(
                'admin.admins.password',
                'PATCH',
            ),
        ];
    }
}
