<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
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

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerate();

        return response()->json([
            'message' => '已退出登录。',
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json($this->adminData($admin));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOwnProfile(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

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
            'data' => $this->adminData($admin),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
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
        $admin->loadMissing('roles:id,code');

        $roleCodes = $admin->roles->pluck('code')->filter()->values()->all();
        $roleIds = $admin->roles
            ->modelKeys();

        $permissionCodes = [
            'admin.admins.role-options:get',
            'admin.roles.permissions.index:get',
            'admin.roles.permissions.assign:put',
            'admin.roles.permissions.revoke:delete',
            'admin.admins.update:patch',
            'admin.admins.password:patch',
            'admin.admins.index:get',
            'admin.roles.update:patch',
            'admin.roles.store:post',
            'admin.admins.store:post',
            'admin.permissions.store:post',
        ];

        $permissions = Permission::query()
            ->where('type', 3)
            ->whereIn('permission_code', $permissionCodes)
            ->with('roles:id')
            ->get()
            ->keyBy('permission_code');

        $isDeveloper = in_array(Role::DEVELOPER_CODE, $roleCodes, true);
        $isSuperAdmin = in_array(Role::SUPER_ADMIN_CODE, $roleCodes, true);

        $hasApiPermission = function (string $permissionCode) use (
            $isDeveloper,
            $isSuperAdmin,
            $roleIds,
            $permissions,
        ): bool {
            if ($isDeveloper) {
                return true;
            }

            $permission = $permissions->get($permissionCode);

            if ($permission === null || $permission->developer_only) {
                return false;
            }

            if ($isSuperAdmin) {
                return true;
            }

            if ((int) $permission->status !== 1) {
                return false;
            }

            if (! $permission->authorization_required) {
                return true;
            }

            return $permission->roles->contains(
                fn (Role $role): bool => in_array($role->getKey(), $roleIds, true),
            );
        };

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role_codes' => $roleCodes,
            'can_view_admin_role_options' => $hasApiPermission('admin.admins.role-options:get'),
            'can_manage_role_permissions' => $hasApiPermission('admin.roles.permissions.index:get') && $hasApiPermission('admin.roles.permissions.assign:put')  && $hasApiPermission('admin.roles.permissions.revoke:delete'),
            'can_update_admins' => $hasApiPermission('admin.admins.update:patch'),
            'can_view_admins' => $hasApiPermission('admin.admins.index:get'),
            'can_reset_admin_passwords' => $hasApiPermission('admin.admins.password:patch'),
            'can_update_roles' => $hasApiPermission('admin.roles.update:patch'),
            'can_create_roles' => $hasApiPermission('admin.roles.store:post'),
            'can_create_admins' => $hasApiPermission('admin.admins.store:post'),
            'can_create_permissions' => $hasApiPermission('admin.permissions.store:post'),
        ];
    }
}
