<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim($validated['search'] ?? '');
        $actor = $request->user('admin');
        $query = Admin::query()
            ->with('roles:id,name,code')
            ->select(['id', 'name', 'email', 'status', 'created_at', 'updated_at']);

        if (! $actor->isDeveloper()) {
            $query->whereDoesntHave('roles', function ($roleQuery): void {
                $roleQuery->where('code', Role::DEVELOPER_CODE);
            });

            if (! $actor->isSuperAdmin()) {
                $query->whereDoesntHave('roles', function ($roleQuery): void {
                    $roleQuery->where('code', Role::SUPER_ADMIN_CODE);
                });
            }
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('roles', function ($roleQuery) use ($search): void {
                        $roleQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $admins = $query
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        $actor = $request->user('admin');

        $admins->getCollection()->transform(
            fn (Admin $admin): array => $this->adminData($admin) + [
                'can_disable' => $this->canDisableAdmin($actor, $admin),
            ],
        );

        return response()->json($admins);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @return JsonResponse
     */
    public function roleOptions(Request $request, Admin $admin): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request, $admin);

        $actorIsDeveloper = $actor->isDeveloper(); // 操作人是开发者
        $actorIsSuperAdmin = $actor->isSuperAdmin(); // 操作人是超管
        $isOrdinaryAdmin = ! $actorIsDeveloper && ! $actorIsSuperAdmin; // 操作人既是不开发者 也不是 超管 , 是普通管理员

        // 目标角色是目标账号在关联表中实际拥有的角色。
        $admin->loadMissing('roles:id,name,code');
        $targetRoleIds = $admin->roles->pluck('id')->map(fn ($id): int => (int) $id)->all(); // 目标用户实际已关联的角色id

        // 只有普通管理员的可分配范围，才是其自己实际关联的角色。
        $actorRoleIds = [];

        if ($isOrdinaryAdmin) {
            $actor->loadMissing('roles:id,name,code');
            $actorRoleIds = $actor->roles->pluck('id')->map(fn ($id): int => (int) $id)->all(); // 实际关联的角色id
        }

        // 先算“操作者可以分配哪些角色”。
        $assignableRoleQuery = Role::query()->select(['id', 'name', 'code'])->orderBy('id');

        // 非开发者只能分配部分角色
        if (! $actorIsDeveloper) {
            if ($actorIsSuperAdmin) {
                // 超管不能把普通管理员提升为超管，也不能分配开发者角色。
                // 保留 code 为空的普通角色。
                $assignableRoleQuery->where(function ($query): void {
                    $query->whereNull('code')->orWhereNotIn('code', [
                        Role::DEVELOPER_CODE,
                        Role::SUPER_ADMIN_CODE,
                    ]);
                });
            } else {
                // 普通管理员只能分配自己实际关联的角色。
                $assignableRoleQuery->whereIn('id', $actorRoleIds);
            }
        }
        $assignableRoles = $assignableRoleQuery->get();

        $assignableRoleIds = $assignableRoles->pluck('id')->map(fn ($id): int => (int) $id)->all();

        // 显示范围 = 可分配角色 ∪ 目标已有角色。
        // 因此目标已有但操作者无权分配的角色仍显示出来，供界面展示其已选状态。
        $visibleRoleIds = array_values(array_unique([
            ...$assignableRoleIds,
            ...$targetRoleIds,
        ]));
        $roles = Role::query()->whereIn('id', $visibleRoleIds)->select(['id', 'name', 'code'])->orderBy('id')->get();

        // 可移除范围按身份单独计算，再限制在目标当前已有的角色内。
        if ($actorIsDeveloper) {
            $removableRoleIds = $targetRoleIds; // 开发者可以移除目标当前拥有的任意角色。
        } else {
            if ($actorIsSuperAdmin) {
                // 超管可移除目标已有的普通角色，但不能移除开发者或超管角色。
                $removableRoleIds = $admin->roles
                    ->reject(fn (Role $role): bool => $this->isExclusiveRole($role))
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            } else {
                // 普通管理员只能移除双方都实际关联的角色；
                // 若目标有角色管理权限，则不允许普通管理员移除其角色。
                if ($this->adminCanManageRoles($admin)) {
                    $removableRoleIds = [];
                } else {
                    $removableRoleIds = array_values(array_intersect($actorRoleIds, $targetRoleIds));
                }
            }
        }

        // 前端用此标记锁定未选角色，避免特殊角色与其他角色并存。
        $hasExclusiveRole = $admin->roles->contains(
            fn (Role $role): bool => $this->isExclusiveRole($role),
        );

        return response()->json([
            'data' => [
                'roles' => $roles,
                'assignable_role_ids' => $assignableRoleIds,
                'removable_role_ids' => $removableRoleIds,
                'has_exclusive_role' => $hasExclusiveRole,
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @param Role $role
     * @return JsonResponse
     */
    public function assignRole(Request $request, Admin $admin, Role $role): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request, $admin);

        if (! $actor->isDeveloper()) {
            if ($role->code === Role::DEVELOPER_CODE
                || $role->code === Role::SUPER_ADMIN_CODE) {
                abort(403, '只有开发者可以分配超管或开发者角色。');
            }

            if (! $actor->isSuperAdmin() && ! $this->adminHasRole($actor, $role)) {
                abort(403, '只能分配自己拥有的角色。');
            }
        }

        if ($this->isExclusiveRole($role)) {
            // 分配超管或开发者就是提升等级：清除原有角色，只保留新角色。
            $admin->roles()->sync([$role->id]);
        } else {
            $targetHasExclusiveRole = $admin->roles()
                ->whereIn('roles.code', [
                    Role::DEVELOPER_CODE,
                    Role::SUPER_ADMIN_CODE,
                ])
                ->exists();

            if ($targetHasExclusiveRole) {
                throw ValidationException::withMessages([
                    'role' => '请先移除目标管理员已有的超管或开发者角色，再分配普通角色。',
                ]);
            }

            // 添加该普通角色，同时保留目标已有的其它普通角色。
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }

        $admin->unsetRelation('roles');

        return response()->json([
            'data' => $this->adminData($admin),
        ]);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @param Role $role
     * @return JsonResponse
     */
    public function revokeRole(Request $request, Admin $admin, Role $role): JsonResponse
    {
        $actor = $this->authorizeRoleManagement($request, $admin);

        $actorIsDeveloper = $actor->isDeveloper();
        $actorIsSuperAdmin = $actor->isSuperAdmin();

        if (! $actorIsDeveloper) {
            if ($actorIsSuperAdmin) {
                if ($this->isExclusiveRole($role)) {
                    abort(403, '超管不能移除开发者或超管角色。');
                }
            } else {
                if (! $this->adminHasRole($actor, $role)) {
                    abort(403, '只能移除自己拥有的角色。');
                }

                if ($this->adminCanManageRoles($admin)) {
                    abort(403, '目标管理员拥有角色管理权限，不能移除其角色。');
                }
            }
        }

        $admin->roles()->detach($role->id); // 从目标用户移除该角色
        $admin->unsetRelation('roles'); // 告诉 Laravel：丢弃这个 $admin 对象上已经加载的 roles 关系缓存

        return response()->json([
            'data' => $this->adminData($admin),
        ]);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @return Admin
     */
    private function authorizeRoleManagement(Request $request, Admin $admin): Admin
    {
        $actor = $request->user('admin');

        if (! $actor instanceof Admin) {
            abort(401);
        }

        if ($actor->getKey() === $admin->getKey()) {
            abort(403, '不能修改自己的角色。');
        }

        if ($admin->isDeveloper()) {
            abort(403, '不能修改开发者账号的角色。');
        }

        // 操作人是开发者,可以任意修改非开发者的角色
        if ($actor->isDeveloper()) {
            return $actor;
        }

        // 操作人不是开发者, 不能修改超管的角色
        if ($admin->isSuperAdmin()) {
            abort(403, '只有开发者可以修改超管账号的角色。');
        }

        return $actor;
    }

    /**
     * @param Admin $admin
     * @param Role $role
     * @return bool
     */
    private function adminHasRole(Admin $admin, Role $role): bool
    {
        return $admin->roles()
            ->where('roles.id', $role->id)
            ->exists();
    }

    /**
     * @param Admin $admin
     * @return bool
     */
    private function adminCanManageRoles(Admin $admin): bool
    {
        return $admin->hasApiPermission('admin.admins.roles.assign', 'PUT')
            || $admin->hasApiPermission('admin.admins.roles.revoke', 'DELETE');
    }

    /**
     * @param Role $role
     * @return bool
     */
    private function isExclusiveRole(Role $role): bool
    {
        return in_array($role->code, [
            Role::DEVELOPER_CODE,
            Role::SUPER_ADMIN_CODE,
        ], true);
    }

    /**
     * @param Admin $admin
     * @return array
     */
    private function adminData(Admin $admin): array
    {
        $admin->loadMissing('roles:id,name,code');

        return $admin->only([
            'id',
            'name',
            'email',
            'status',
            'created_at',
            'updated_at',
        ]) + [
            'roles' => $admin->roles
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                ])
                ->values()
                ->all(),
            'can_reset_admin_passwords' => $admin->hasApiPermission(
                'admin.admins.password',
                'PATCH',
            ),
        ];
    }

    /**
     * @param Admin $admin
     * @return int
     */
    private function adminPrivilegeLevel(Admin $admin): int
    {
        if ($admin->isDeveloper()) {
            return 2;
        }

        return $admin->isSuperAdmin() ? 1 : 0;
    }

    /**
     * @param Admin $actor
     * @param Admin $target
     * @return bool
     */
    private function canDisableAdmin(Admin $actor, Admin $target): bool
    {
        if ($actor->getKey() === $target->getKey()) {
            return false;
        }

        $targetCanDisableAdmins = $target->hasApiPermission(
            'admin.admins.status',
            'PATCH',
        );

        return ! $targetCanDisableAdmins
            || $this->adminPrivilegeLevel($target) < $this->adminPrivilegeLevel($actor);
    }

    /**
     * @param Admin $actor
     * @param Admin $target
     * @return bool
     */
    private function canManageAdminProfile(Admin $actor, Admin $target): bool
    {
        if (! $actor->hasApiPermission('admin.admins.update', 'PATCH')) {
            return false;
        }

        return $actor->is($target)
            || ! $this->targetHasEqualOrHigherPasswordResetPrivilege($actor, $target);
    }

    /**
     * @param Admin $actor
     * @param Admin $target
     * @return bool
     */
    private function canResetAdminPassword(Admin $actor, Admin $target): bool
    {
        if (! $actor->hasApiPermission('admin.admins.password', 'PATCH')) {
            return false;
        }

        return ! $actor->is($target)
            && ! $this->targetHasEqualOrHigherPasswordResetPrivilege($actor, $target);
    }

    /**
     * @param Admin $actor
     * @param Admin $target
     * @return bool
     */
    private function targetHasEqualOrHigherPasswordResetPrivilege(Admin $actor, Admin $target): bool
    {
        if (! $target->hasApiPermission('admin.admins.password', 'PATCH')) {
            return false;
        }

        $actorLevel = $actor->isDeveloper() ? 2 : ($actor->isSuperAdmin() ? 1 : 0);
        $targetLevel = $target->isDeveloper() ? 2 : ($target->isSuperAdmin() ? 1 : 0);

        return $targetLevel >= $actorLevel;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
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
            'data' => $this->adminData($admin),
        ], 201);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @return JsonResponse
     */
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

        $actor = $request->user('admin');

        if (! $this->canManageAdminProfile($actor, $admin)) {
            return response()->json([
                'message' => '无权修改该管理员的信息。',
            ], 403);
        }

        $admin->update($validated);
        $admin->refresh();

        return response()->json([
            'data' => $this->adminData($admin),
        ]);
    }

    /**
     * @param Request $request
     * @param Admin $admin
     * @return JsonResponse
     */
    public function updatePassword(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $actor = $request->user('admin');

        if (! $this->canResetAdminPassword($actor, $admin)) {
            return response()->json([
                'message' => '无权重置该管理员的密码。',
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

    /**
     * @param Request $request
     * @param Admin $admin
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);
        $actor = $request->user('admin');
        $status = (bool) $validated['status'];
        if (! $status && $actor->getKey() === $admin->getKey()) {
            return response()->json([
                'message' => '不能停用自己的账号。',
            ], 403);
        }

        if (! $status && ! $this->canDisableAdmin($actor, $admin)) {
            return response()->json([
                'message' => '不能停用权限等级相同或更高的管理员。',
            ], 403);
        }

        $admin->status = $status;
        $admin->save();
        $admin->refresh();

        return response()->json([
            'data' => $this->adminData($admin) + [
                'can_disable' => $this->canDisableAdmin($actor, $admin),
            ],
        ]);
    }
}
