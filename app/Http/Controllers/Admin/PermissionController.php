<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $query = Permission::query();

        if (! $admin->isDeveloper()) {
            $query->where('developer_only', false);
        }

        $permissions = $query
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $permissions,
        ]);
    }

    public function tree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $admin = $request->user('admin');
        $query = Permission::query();

        if (! $admin->isDeveloper()) {
            $query->where('developer_only', false);
        }

        $permissions = $query
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $items = [];
        $childrenIds = [];

        foreach ($permissions as $permission) {
            $id = (int) $permission->id;
            $pid = (int) $permission->pid;

            $items[$id] = $permission->toArray();
            $childrenIds[$pid][] = $id;
        }

        $excludeId = isset($validated['exclude_id'])
            ? (int) $validated['exclude_id']
            : null;

        return response()->json([
            'data' => $this->buildPermissionTree(
                $childrenIds[0] ?? [],
                $childrenIds,
                $items,
                $excludeId,
            ),
        ]);
    }

    public function menuTree(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        $query = Permission::query()
            ->whereIn('type', [1, 2])
            ->where('status', 1);

        $actorIsDeveloper = $admin->isDeveloper();
        $actorIsSuperAdmin = $admin->isSuperAdmin();

        if (! $actorIsDeveloper) {
            $query->where('developer_only', false);
        }

        if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
            $assignedPermissionIds = $admin->roles()
                ->with('permissions:id')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $query->where(function ($permissionQuery) use ($assignedPermissionIds): void {
                $permissionQuery
                    ->where('authorization_required', false)
                    ->orWhereIn('id', $assignedPermissionIds);
            });
        }

        $permissions = $query
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $items = [];
        $childrenIds = [];

        foreach ($permissions as $permission) {
            $id = (int) $permission->id;
            $pid = (int) $permission->pid;

            $items[$id] = $permission->toArray();
            $childrenIds[$pid][] = $id;
        }

        return response()->json([
            'data' => $this->buildPermissionTree(
                $childrenIds[0] ?? [],
                $childrenIds,
                $items,
            ),
        ]);
    }

    /**
     * @param  list<int>  $nodeIds
     * @param  array<int, list<int>>  $childrenIds
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildPermissionTree(array $nodeIds, array $childrenIds, array $items, ?int $excludeId = null): array
    {
        $tree = [];

        foreach ($nodeIds as $id) {
            if ($id === $excludeId) {
                continue;
            }

            $node = $items[$id];
            $node['children'] = $this->buildPermissionTree(
                $childrenIds[$id] ?? [],
                $childrenIds,
                $items,
                $excludeId,
            );

            $tree[] = $node;
        }

        return $tree;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer', Rule::in([1, 2, 3])],
            'pid' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::when(
                    $request->integer('pid') > 0,
                    [Rule::exists('permissions', 'id')],
                ),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'path' => [
                Rule::requiredIf($request->integer('type') === 2),
                'nullable',
                'string',
                'max:255',
            ],
            'component' => [
                Rule::requiredIf($request->integer('type') === 2),
                'nullable',
                'string',
                'max:150',
            ],
            'permission_code' => [
                Rule::requiredIf($request->integer('type') === 3),
                'nullable',
                'string',
                'max:191',
                'unique:permissions,permission_code',
            ],
            'authorization_required' => ['sometimes', 'boolean'],
            'developer_only' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', Rule::in([0, 1])],
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $parentId = (int) ($validated['pid'] ?? 0);

        $permission = DB::transaction(function () use ($validated, $parentId): Permission {
            $parent = $parentId === 0
                ? null
                : Permission::query()->findOrFail($parentId);

            $maxSort = Permission::query()
                ->where('pid', $parentId)
                ->max('sort');

            $permission = new Permission;
            $permission->name = $validated['name'];
            $permission->type = (int) $validated['type'];
            $permission->pid = $parentId;
            $permission->id_path = $parent
                ? $parent->id_path.$parent->id.','
                : '0,';
            $permission->icon = $validated['icon'] ?? null;
            $permission->path = $validated['path'] ?? null;
            $permission->component = $validated['component'] ?? null;
            $permission->permission_code = $validated['permission_code'] ?? null;
            $permission->authorization_required = $validated['authorization_required'] ?? true;
            $permission->developer_only = $validated['developer_only'] ?? false;
            $permission->status = (int) ($validated['status'] ?? 0);
            $permission->sort = $maxSort === null ? 0 : (int) $maxSort + 1;
            $permission->remark = $validated['remark'] ?? null;
            $permission->save();

            $this->resetSiblingSorts($parentId);

            return $permission;
        });

        return response()->json([
            'data' => [
                'id' => $permission->id,
                'pid' => $permission->pid,
                'id_path' => $permission->id_path,
                'name' => $permission->name,
                'icon' => $permission->icon,
                'path' => $permission->path,
                'component' => $permission->component,
                'permission_code' => $permission->permission_code,
                'type' => $permission->type,
                'status' => $permission->status,
                'authorization_required' => $permission->authorization_required,
                'developer_only' => $permission->developer_only,
                'sort' => $permission->sort,
                'remark' => $permission->remark,
            ],
        ], 201);
    }

    /**
     * @throws Throwable
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'integer', Rule::in([1, 2, 3])],
            'pid' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::when(
                    $request->integer('pid') > 0,
                    [Rule::exists('permissions', 'id')],
                ),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'component' => ['sometimes', 'nullable', 'string', 'max:150'],
            'permission_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:191',
                Rule::unique('permissions', 'permission_code')->ignore($permission->id),
            ],
            'authorization_required' => ['sometimes', 'boolean'],
            'developer_only' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', Rule::in([0, 1])],
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $type = (int) ($validated['type'] ?? $permission->type);
        $path = array_key_exists('path', $validated)
            ? $validated['path']
            : $permission->path;
        $component = array_key_exists('component', $validated)
            ? $validated['component']
            : $permission->component;
        $permissionCode = array_key_exists('permission_code', $validated)
            ? $validated['permission_code']
            : $permission->permission_code;

        if ($type === 2 && (blank($path) || blank($component))) {
            throw ValidationException::withMessages([
                'path' => '页面菜单必须填写 path 和 component。',
            ]);
        }

        if ($type === 3 && blank($permissionCode)) {
            throw ValidationException::withMessages([
                'permission_code' => 'API 权限必须填写 permission_code。',
            ]);
        }

        $parentId = (int) ($validated['pid'] ?? $permission->pid);
        $oldParentId = (int) $permission->pid;
        $parent = $parentId === 0
            ? null
            : Permission::query()->findOrFail($parentId);

        if (
            $parent !== null
            && (
                (int) $parent->id === (int) $permission->id
                || str_contains($parent->id_path, ','.$permission->id.',')
            )
        ) {
            throw ValidationException::withMessages([
                'pid' => '不能将节点移动到自身或自己的后代下面。',
            ]);
        }

        $newIdPath = $parent
            ? $parent->id_path.$parent->id.','
            : '0,';

        $oldSubtreePrefix = $permission->id_path.$permission->id.',';

        DB::transaction(function () use (
            $permission,
            $validated,
            $type,
            $path,
            $component,
            $permissionCode,
            $parentId,
            $oldParentId,
            $newIdPath,
            $oldSubtreePrefix,
        ): void {
            if (array_key_exists('name', $validated)) {
                $permission->name = $validated['name'];
            }

            $permission->type = $type;
            $permission->pid = $parentId;
            $permission->id_path = $newIdPath;
            if ($parentId !== $oldParentId) {
                $maxSort = Permission::query()
                    ->where('pid', $parentId)
                    ->max('sort');

                $permission->sort = $maxSort === null
                    ? 0
                    : (int) $maxSort + 1;
            }
            $permission->path = $type === 2 ? $path : null;
            $permission->component = $type === 2 ? $component : null;
            $permission->permission_code = $type === 3 ? $permissionCode : null;

            foreach (['icon', 'status', 'remark', 'authorization_required', 'developer_only'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $permission->{$field} = $validated[$field];
                }
            }

            $permission->save();

            if ($oldSubtreePrefix !== $newIdPath) {
                $descendants = Permission::query()
                    ->where('id_path', 'like', $oldSubtreePrefix.'%')
                    ->get();

                foreach ($descendants as $descendant) {
                    $suffix = substr(
                        $descendant->id_path,
                        strlen($oldSubtreePrefix),
                    );
                    $descendant->id_path = $newIdPath.$permission->id.','.$suffix;
                    $descendant->save();
                }
            }

            if ($parentId !== $oldParentId) {
                $this->resetSiblingSorts($oldParentId);
                $this->resetSiblingSorts($parentId);
            }
        });

        return response()->json([
            'data' => [
                'id' => $permission->id,
                'pid' => $permission->pid,
                'id_path' => $permission->id_path,
                'name' => $permission->name,
                'icon' => $permission->icon,
                'path' => $permission->path,
                'component' => $permission->component,
                'permission_code' => $permission->permission_code,
                'type' => $permission->type,
                'status' => $permission->status,
                'authorization_required' => $permission->authorization_required,
                'developer_only' => $permission->developer_only,
                'sort' => $permission->sort,
                'remark' => $permission->remark,
            ],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Permission $permission): Response
    {
        $parentId = (int) $permission->pid;
        DB::transaction(function () use ($permission, $parentId): void {
            $permission = Permission::query()
                ->lockForUpdate()
                ->findOrFail($permission->getKey());

            $hasChildren = Permission::query()
                ->where('pid', $permission->getKey())
                ->exists();

            if ($hasChildren) {
                throw ValidationException::withMessages([
                    'permission' => '该节点仍有子节点，不能删除。',
                ]);
            }

            $permission->roles()->detach();
            $permission->delete();
            $this->resetSiblingSorts($parentId);
        });

        return response()->noContent();
    }

    /**
     * @throws Throwable
     */
    public function move(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        DB::transaction(function () use ($validated, $permission): void {
            $siblings = Permission::query()
                ->where('pid', $permission->pid)
                ->orderBy('sort')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentIndex = $siblings->search(
                fn (Permission $sibling): bool => $sibling->is($permission),
            );

            if ($currentIndex === false) {
                throw ValidationException::withMessages([
                    'permission' => '找不到该同级节点。',
                ]);
            }

            $targetIndex = $validated['direction'] === 'up'
                ? $currentIndex - 1
                : $currentIndex + 1;

            if (! $siblings->has($targetIndex)) {
                throw ValidationException::withMessages([
                    'direction' => '该节点已位于同级边界，不能继续移动。',
                ]);
            }

            $current = $siblings->get($currentIndex);
            $target = $siblings->get($targetIndex);

            [$current->sort, $target->sort] = [$target->sort, $current->sort];

            $current->save();
            $target->save();

            $this->resetSiblingSorts((int) $permission->pid);
        });

        return response()->json([
            'message' => '菜单或权限排序已更新。',
        ]);
    }

    private function resetSiblingSorts(int $parentId): void
    {
        $siblings = Permission::query()
            ->where('pid', $parentId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($siblings as $sort => $sibling) {
            if ((int) $sibling->sort !== $sort) {
                $sibling->sort = $sort;
                $sibling->save();
            }
        }
    }

    private function isProtectedRole(Role $role): bool
    {
        return in_array($role->code, [
            Role::DEVELOPER_CODE,
            Role::SUPER_ADMIN_CODE,
        ], true);
    }

    public function rolePermissions(Request $request, Role $role): JsonResponse
    {
        // 开发者和超管角色的权限固定，不通过普通角色权限编辑入口修改。
        if ($this->isProtectedRole($role)) {
            abort(403, '开发者和超管角色的权限不能通过角色管理修改。');
        }

        $admin = $request->user('admin');

        // 目标角色当前已关联的权限 ID；即使操作者无权分配，也要显示这些已有关联的权限。
        $assignedPermissionIds = $role->permissions()
            ->pluck('permissions.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $actorIsDeveloper = $admin->isDeveloper();
        $actorIsSuperAdmin = $admin->isSuperAdmin();
        $actorPermissionIds = [];

        // 普通管理员可操作的权限，来自其所有已关联角色所拥有权限的并集；
        // 开发者和超管按系统身份处理，不从自己的角色关联中推导操作范围。
        if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
            $actorPermissionIds = $admin->roles()
                ->with('permissions:id')
                ->get()
                ->flatMap(fn (Role $actorRole) => $actorRole->permissions->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $query = Permission::query();

        // 开发者专属权限不向开发者以外的操作者显示。
        if (! $actorIsDeveloper) {
            $query->where('developer_only', false);
        }

        // 普通管理员的显示范围是：
        // 无需授权的权限 + 自己可分配的权限 + 目标角色已有关联的权限。
        // 后两项分开查询，避免隐藏目标已有但操作者无权分配的权限。
        if (! $actorIsDeveloper && ! $actorIsSuperAdmin) {
            $query->where(function ($permissionQuery) use ($assignedPermissionIds, $actorPermissionIds): void {
                $permissionQuery
                    ->where('authorization_required', false)
                    ->orWhereIn('id', $actorPermissionIds)
                    ->orWhereIn('id', $assignedPermissionIds);
            });
        }

        $permissions = $query
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $items = [];
        $childrenIds = [];

        // 为每个权限计算勾选和锁定状态，并收集树形结构所需的父子 ID。
        foreach ($permissions as $permission) {
            $id = (int) $permission->id;

            // 无需授权的权限固定显示为已勾选；其他权限按目标角色的关联记录判断。
            $assigned = ! $permission->authorization_required
                || in_array($id, $assignedPermissionIds, true);

            $permission->setAttribute('assigned', $assigned);

            $canManagePermission = $actorIsDeveloper
                || (
                    ! $permission->developer_only
                    && (
                        $actorIsSuperAdmin
                        || in_array($id, $actorPermissionIds, true)
                    )
                );

            $permission->setAttribute(
                'locked',
                ! $permission->authorization_required// 无需授权的权限始终锁定；已停用权限仅在尚未分配时锁定，
                || ! $canManagePermission   //无权分配的也锁定
                || (! $assigned && (int) $permission->status !== 1),// 已分配的停用权限仍可取消勾选。
            );

            $items[$id] = $permission->toArray();
            $childrenIds[(int) $permission->pid][] = $id;
        }

        return response()->json([
            'data' => $this->buildPermissionTree(
                // 从根节点开始，将查询结果按父子关系组装为权限树。
                $childrenIds[0] ?? [],
                $childrenIds,
                $items,
            ),
        ]);
    }
}
