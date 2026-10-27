<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
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

        return response()->json([
            'data' => $this->buildPermissionTree(
                $childrenIds[0] ?? [],
                $childrenIds,
                $items,
            ),
        ]);
    }

    /**
     * @param list<int> $nodeIds
     * @param array<int, list<int>> $childrenIds
     * @param array<int, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function buildPermissionTree(array $nodeIds, array $childrenIds, array $items): array {
        $tree = [];
        foreach ($nodeIds as $id) {
            $node = $items[$id];
            $node['children'] = $this->buildPermissionTree(
                $childrenIds[$id] ?? [],
                $childrenIds,
                $items,
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
            'sort' => ['sometimes', 'integer', 'min:0'],
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $parentId = (int) ($validated['pid'] ?? 0);
        $parent = $parentId === 0
            ? null
            : Permission::query()->findOrFail($parentId);

        $permission = new Permission();
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
        $permission->sort = (int) ($validated['sort'] ?? 0);
        $permission->remark = $validated['remark'] ?? null;
        $permission->save();

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
            'sort' => ['sometimes', 'integer', 'min:0'],
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
            $newIdPath,
            $oldSubtreePrefix,
        ): void {
            if (array_key_exists('name', $validated)) {
                $permission->name = $validated['name'];
            }

            $permission->type = $type;
            $permission->pid = $parentId;
            $permission->id_path = $newIdPath;
            $permission->path = $type === 2 ? $path : null;
            $permission->component = $type === 2 ? $component : null;
            $permission->permission_code = $type === 3 ? $permissionCode : null;

            foreach (['icon', 'status', 'sort', 'remark', 'authorization_required', 'developer_only'] as $field) {
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
        DB::transaction(function () use ($permission): void {
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
        });

        return response()->noContent();
    }

    public function rolePermissions(Request $request, Role $role): JsonResponse
    {
        $admin = $request->user('admin');

        $assignedPermissionIds = $role->permissions()
            ->pluck('permissions.id')
            ->all();

        $query = Permission::query()
            ->where('status', 1);

        if (! $admin->isDeveloper()) {
            $query->where('developer_only', false);
        }

        $permissions = $query
            ->orderBy('id')
            ->get()
            ->map(function (Permission $permission) use ($assignedPermissionIds): Permission {
                $permission->setAttribute(
                    'assigned',
                    ! $permission->authorization_required
                    || in_array($permission->getKey(), $assignedPermissionIds, true),
                );
                $permission->setAttribute(
                    'locked',
                    ! $permission->authorization_required,
                );

                return $permission;
            });

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
