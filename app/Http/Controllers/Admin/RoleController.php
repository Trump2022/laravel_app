<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RoleController extends Controller
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

        $actor = $request->user('admin');
        $query = Role::query()
            ->select(['id', 'name', 'code', 'remark'])
            ->orderBy('id');

        $actor->loadMissing('roles:id,code');

        $actorIsDeveloper = $actor->roles->contains('code', Role::DEVELOPER_CODE);
        $actorIsSuperAdmin = $actor->roles->contains('code', Role::SUPER_ADMIN_CODE);

        if (!$actorIsDeveloper) {
            if ($actorIsSuperAdmin) {
                $query->where(function ($query): void {
                    $query->whereNull('code')
                        ->orWhere('code', '!=', Role::DEVELOPER_CODE);
                });
            } else {
                $query->where(function ($query): void {
                    $query->whereNull('code')
                        ->orWhereNotIn('code', [
                            Role::DEVELOPER_CODE,
                            Role::SUPER_ADMIN_CODE,
                        ]);
                });
            }
        }

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->paginate($validated['per_page'] ?? 10)->withQueryString(),
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
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

        $role = new Role;
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

    /**
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
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

    /**
     * @param Request $request
     * @param Role $role
     * @param Permission $permission
     * @return JsonResponse
     * @throws Throwable
     */
    public function assignPermission(Request $request, Role $role, Permission $permission): JsonResponse
    {
        $this->ensureRoleCanManagePermissions($role);
        $this->ensurePermissionCanBeAssigned($permission);

        if ($permission->developer_only) {
            abort(403, '开发者专属权限不能分配给其他角色。');
        }

        if ((int) $permission->status !== 1) {
            throw ValidationException::withMessages([
                'permission' => '已停用的权限不能分配给角色。',
            ]);
        }

        [$canAssignPermission, $actorIsDeveloper, $actorIsSuperAdmin, $actorPermissionIds]
            = $this->getPermissionManagementContext($request, $permission);

        if (! $canAssignPermission) {
            abort(403, '无权分配此权限。');
        }

        $permissionIds = [];

        if (in_array((int) $permission->type, [1, 2], true)) {
            $ancestorIds = array_values(array_unique(array_filter(
                array_map('intval', explode(',', $permission->id_path)),
                fn (int $id): bool => $id > 0,
            )));

            $ancestors = Permission::query()
                ->whereIn('id', $ancestorIds)
                ->get()
                ->keyBy('id');

            if ($ancestors->count() !== count($ancestorIds)) {
                throw ValidationException::withMessages([
                    'permission' => '权限的上级节点不存在，无法分配。',
                ]);
            }

            // 操作者身份和可分配权限已在上方计算；这里只用它们筛选自动补齐的上级权限。
            foreach ($ancestorIds as $ancestorId) {
                $ancestor = $ancestors->get($ancestorId);

                if (
                    ! in_array((int) $ancestor->type, [1, 2], true)
                    || ! $ancestor->authorization_required
                    || (int) $ancestor->status !== 1
                    || $ancestor->developer_only
                    || (
                        ! $actorIsDeveloper
                        && ! $actorIsSuperAdmin
                        && ! in_array($ancestorId, $actorPermissionIds, true)
                    )
                ) {
                    continue;
                }

                $permissionIds[] = $ancestorId;
            }
        }

        $permissionIds[] = $permission->id;
        $permissionIds = array_values(array_unique($permissionIds));

        DB::transaction(function () use ($role, $permissionIds): void {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });

        return response()->json([
            'data' => [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'assigned' => true,
                'assigned_permission_ids' => $permissionIds,
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Role $role
     * @param Permission $permission
     * @return JsonResponse
     * @throws Throwable
     */
    public function revokePermission(Request $request, Role $role, Permission $permission): JsonResponse
    {
        $this->ensureRoleCanManagePermissions($role);
        $this->ensurePermissionCanBeAssigned($permission);

        [$canRevokePermission, $actorIsDeveloper, $actorIsSuperAdmin, $actorPermissionIds]
            = $this->getPermissionManagementContext($request, $permission);

        if (! $canRevokePermission) {
            abort(403, '无权撤销此权限。');
        }

        $permissionIds = [(int) $permission->id];

        if (in_array((int) $permission->type, [1, 2], true)) {
            $descendantQuery = Permission::query()
                ->where(
                    'id_path',
                    'like',
                    $permission->id_path.$permission->id.',%',
                )
                ->whereIn('type', [1, 2])
                ->where('authorization_required', true);

            if (! $actorIsDeveloper) {
                $descendantQuery->where('developer_only', false);
            }

            if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
                $descendantQuery->whereIn('id', $actorPermissionIds);
            }

            $descendantIds = $descendantQuery
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $permissionIds = array_values(array_unique([
                ...$permissionIds,
                ...$descendantIds,
            ]));
        }

        if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
            $actor = $request->user('admin');
            $actor->loadMissing('roles:id');

            //当前操作者是否拥有正在编辑的目标角色
            $actorOwnsTargetRole = $actor->roles->contains(
                fn (Role $actorRole): bool => $actorRole->is($role),
            );

            //当前操作者有其它角色
            if ($actorOwnsTargetRole) {
                //找出他的其它角色
                $otherRoleIds = $actor->roles
                    ->reject(fn (Role $actorRole): bool => $actorRole->is($role)) //把满足条件的元素 过滤掉, 剔除当前正在操作的角色
                    ->modelKeys();

                //如果其它角色没有该权限
                $willLosePermission = $role->permissions()
                    ->whereIn('permissions.id', $permissionIds)
                    ->whereDoesntHave('roles', function ($query) use ($otherRoleIds): void {
                        $query->whereIn('roles.id', $otherRoleIds);
                    })
                    ->exists();

                if ($willLosePermission) {
                    abort(403, '不能撤销自己唯一拥有的权限。');
                }
            }

            if (
                // 如果当前的操作是移除目标角色的 "移除角色权限" 这个权限, 就阻止
                $role->permissions()
                    ->whereIn('permissions.id', $permissionIds)
                    ->where('permissions.permission_code', 'admin.roles.permissions.revoke:delete')
                    ->where('permissions.type', 3)
                    ->exists()
            ) {
                abort(403, '不能移除目标角色的撤销角色权限。');
            }
        }

        DB::transaction(function () use ($role, $permissionIds): void {
            $role->permissions()->detach($permissionIds);
        });

        return response()->json([
            'data' => [
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'assigned' => false,
                'revoked_permission_ids' => $permissionIds,
            ],
        ]);
    }

    /**
     * @param Role $role
     * @return void
     */
    private function ensureRoleCanManagePermissions(Role $role): void
    {
        if (in_array($role->code, [
            Role::DEVELOPER_CODE,
            Role::SUPER_ADMIN_CODE,
        ], true)) {
            abort(403, '开发者和超管角色的权限不能通过角色管理修改。');
        }
    }

    /**
     * @param Permission $permission
     * @return void
     */
    private function ensurePermissionCanBeAssigned(Permission $permission): void
    {
        if (! $permission->authorization_required) {
            throw ValidationException::withMessages([
                'permission' => '该权限不需要角色授权，不能修改角色关联。',
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Permission $permission
     * @return array
     */
    private function getPermissionManagementContext(Request $request, Permission $permission): array
    {
        $admin = $request->user('admin');
        $admin->loadMissing('roles:id,code');

        $actorIsDeveloper = $admin->roles->contains('code', Role::DEVELOPER_CODE);
        $actorIsSuperAdmin = $admin->roles->contains('code', Role::SUPER_ADMIN_CODE);
        $actorPermissionIds = [];

        if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
            $admin->loadMissing('roles.permissions:id');

            $actorPermissionIds = $admin->roles
                ->flatMap(fn (Role $actorRole) => $actorRole->permissions->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $canManagePermission = $actorIsDeveloper
            || (
                ! $permission->developer_only
                && (
                    $actorIsSuperAdmin
                    || in_array((int) $permission->id, $actorPermissionIds, true)
                )
            );

        return [
            $canManagePermission,//是否可管理这条权限
            $actorIsDeveloper,
            $actorIsSuperAdmin,
            $actorPermissionIds,//可管理的角色id集合
        ];
    }
}
