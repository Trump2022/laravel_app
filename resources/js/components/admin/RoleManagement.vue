<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { apiRequest } from '../../api';

const roles = ref([]);
const loading = ref(false);
const errorMessage = ref('');

const emit = defineEmits(['success']);

const creating = ref(false);
const showCreateForm = ref(false);
const newRole = ref({
    name: '',
    code: '',
    remark: '',
});

const selectedRole = ref(null);
const editForm = ref({
    name: '',
    code: '',
    remark: '',
});
const updating = ref(false);

const selectedPermissionRole = ref(null);
const permissionRows = ref([]);
const permissionLoading = ref(false);
const permissionUpdateLocks = ref(new Map());

const searchText = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
let searchTimer = null;

const typeNames = {
    1: '目录',
    2: '页面菜单',
    3: 'API 权限',
};

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
});

const expandedPermissionIds = ref([]);

const visiblePermissionRows = computed(() => {
    const rowsById = new Map(
        permissionRows.value.map((row) => [row.id, row]),
    );

    return permissionRows.value.filter((row) => {
        let parentId = row.parentId;

        while (parentId !== null) {
            if (!expandedPermissionIds.value.includes(parentId)) {
                return false;
            }

            parentId = rowsById.get(parentId)?.parentId ?? null;
        }

        return true;
    });
});

function isMenuPermission(permission) {
    return [1, 2].includes(Number(permission.type));
}

function getPermissionAncestorIds(permission) {
    if (!isMenuPermission(permission)) {
        return [];
    }

    const ancestorIds = [];
    let parentId = permission.parentId;

    while (parentId !== null) {
        const parent = permissionRows.value.find(
            (row) => row.id === parentId,
        );

        if (!parent || !isMenuPermission(parent)) {
            break;
        }

        ancestorIds.push(parentId);
        parentId = parent.parentId;
    }

    return ancestorIds;
}

function getPermissionDescendantIds(permissionId) {
    const childrenByParentId = new Map();

    for (const row of permissionRows.value) {
        if (row.parentId === null) {
            continue;
        }

        const childIds = childrenByParentId.get(row.parentId) ?? [];
        childIds.push(row.id);
        childrenByParentId.set(row.parentId, childIds);
    }

    const descendantIds = [];
    const pendingIds = [permissionId];

    while (pendingIds.length > 0) {
        const parentId = pendingIds.pop();

        for (const childId of childrenByParentId.get(parentId) ?? []) {
            const child = permissionRows.value.find(
                (row) => row.id === childId,
            );

            if (!child || !isMenuPermission(child)) {
                continue;
            }

            descendantIds.push(childId);
            pendingIds.push(childId);
        }
    }

    return descendantIds;
}

function lockPermissions(permissionIds) {
    const locks = new Map(permissionUpdateLocks.value);

    for (const id of permissionIds) {
        locks.set(id, (locks.get(id) ?? 0) + 1);
    }

    permissionUpdateLocks.value = locks;
}

function unlockPermissions(permissionIds) {
    const locks = new Map(permissionUpdateLocks.value);

    for (const id of permissionIds) {
        const lockCount = locks.get(id) ?? 0;

        if (lockCount <= 1) {
            locks.delete(id);
        } else {
            locks.set(id, lockCount - 1);
        }
    }

    permissionUpdateLocks.value = locks;
}

function isPermissionUpdating(permissionId) {
    return permissionUpdateLocks.value.has(permissionId);
}


function canEditPermissions(role) {
    return props.admin.can_manage_role_permissions
        && !['developer', 'super_admin'].includes(role.code);
}

function flattenPermissionTree(nodes,    depth = 0,    result = [],    parentId = null,) {
    for (const node of nodes) {
        result.push({
            ...node,
            depth,
            parentId,
        });

        flattenPermissionTree(
            node.children ?? [],
            depth + 1,
            result,
            node.id,
        );
    }

    return result;
}

function hasPermissionChildren(permission) {
    return Boolean(permission.children?.length);
}

function isPermissionExpanded(permission) {
    return expandedPermissionIds.value.includes(permission.id);
}

function togglePermissionExpanded(permission) {
    if (isPermissionExpanded(permission)) {
        expandedPermissionIds.value = expandedPermissionIds.value.filter(
            (id) => id !== permission.id,
        );
        return;
    }

    expandedPermissionIds.value = [
        ...expandedPermissionIds.value,
        permission.id,
    ];
}

async function startEditingPermissions(role) {
    expandedPermissionIds.value = [];
    selectedPermissionRole.value = role;
    permissionRows.value = [];
    permissionLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/roles/${role.id}/permissions`,
        );

        permissionRows.value = flattenPermissionTree(response.data);
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        permissionLoading.value = false;
    }
}

function closeEditingPermissions() {
    selectedPermissionRole.value = null;
    permissionRows.value = [];
}

async function toggleRolePermission(permission, checked) {
    const roleId = selectedPermissionRole.value.id;
    const relatedPermissionIds = checked
        ? getPermissionAncestorIds(permission)
        : getPermissionDescendantIds(permission.id);
    const lockedPermissionIds = [
        permission.id,
        ...relatedPermissionIds,
    ];

    lockPermissions(lockedPermissionIds);
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/roles/${roleId}/permissions/${permission.id}`,
            {
                method: checked ? 'PUT' : 'DELETE',
            },
        );

        const changedIds = checked
            ? response.data.assigned_permission_ids
            : response.data.revoked_permission_ids;
        const changedIdSet = new Set(changedIds);

        for (const row of permissionRows.value) {
            if (changedIdSet.has(row.id)) {
                row.assigned = checked;
                row.locked = !row.authorization_required
                    || (row.developer_only && !row.assigned)
                    || (!row.assigned && Number(row.status) !== 1);
            }
        }
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        unlockPermissions(lockedPermissionIds);
    }
}

function startEditing(role) {
    selectedRole.value = role;
    editForm.value = {
        name: role.name,
        code: role.code ?? '',
        remark: role.remark ?? '',
    };
    errorMessage.value = '';
}

function cancelEditing() {
    selectedRole.value = null;
    editForm.value = {
        name: '',
        code: '',
        remark: '',
    };
    errorMessage.value = '';
}

async function updateRole() {
    updating.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/roles/${selectedRole.value.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({
                    name: editForm.value.name,
                    code: editForm.value.code || null,
                    remark: editForm.value.remark || null,
                }),
            },
        );

        roles.value = roles.value.map((role) =>
            role.id === response.data.id ? response.data : role,
        );
        selectedRole.value = null;
        emit('success', '角色资料已更新。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        updating.value = false;
    }
}

async function loadRoles(page = currentPage.value) {
    loading.value = true;
    errorMessage.value = '';
    currentPage.value = page;

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage.value),
            search: searchText.value.trim(),
        });

        const response = await apiRequest(`/api/admin/roles?${params}`);

        roles.value = response.data;
        currentPage.value = response.current_page;
        total.value = response.total;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

function handleSearchInput() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        loadRoles(1);
    }, 300);
}

function changePageSize(size) {
    perPage.value = size;
    loadRoles(1);
}

onUnmounted(() => {
    window.clearTimeout(searchTimer);
});

function cancelCreate() {
    showCreateForm.value = false;
    newRole.value = {
        name: '',
        code: '',
        remark: '',
    };
    errorMessage.value = '';
}

async function createRole() {
    creating.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/roles', {
            method: 'POST',
            body: JSON.stringify({
                name: newRole.value.name,
                code: newRole.value.code || null,
                remark: newRole.value.remark || null,
            }),
        });

        cancelCreate();
        await loadRoles();
        emit('success', '角色创建成功。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        creating.value = false;
    }
}

onMounted(loadRoles);
</script>

<template>
    <section class="mt-6 w-full rounded-xl bg-white p-6 shadow">
        <p v-if="errorMessage" class="mb-4 text-sm text-red-600">
            {{ errorMessage }}
        </p>


        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <button
                v-if="props.admin.can_create_roles"
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                @click=" showCreateForm = !showCreateForm; errorMessage = '';  " >{{ showCreateForm ? '收起' : '新建角色' }}
            </button>

            <el-input
                v-model="searchText"
                clearable
                class="w-full sm:max-w-sm"
                placeholder="搜索角色名称、标识或备注"
                @input="handleSearchInput"
            />
        </div>

        <form
            v-if="showCreateForm"
            class="mb-6 grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="createRole" >
            <label>
                <span class="text-sm font-medium">名称</span>
                <input
                    v-model="newRole.name"
                    type="text"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">标识（可选）</span>
                <input
                    v-model="newRole.code"
                    type="text"
                    maxlength="255"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">备注（可选）</span>
                <input
                    v-model="newRole.remark"
                    type="text"
                    maxlength="255"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <button
                    type="submit"
                    :disabled="creating"
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50">
                    {{ creating ? '创建中…' : '保存角色' }}
                </button>

                <button
                    type="button"
                    :disabled="creating"
                    class="rounded border border-slate-300 px-4 py-2 text-slate-700 disabled:opacity-50"
                    @click="cancelCreate" >
                    取消
                </button>
            </div>
        </form>

        <form
            v-if="selectedRole"
            class="mb-6 grid gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="updateRole">
            <h2 class="font-semibold sm:col-span-2 lg:col-span-3">
                编辑角色：{{ selectedRole.name }}
            </h2>

            <label>
                <span class="text-sm font-medium">名称</span>
                <input
                    v-model="editForm.name"
                    type="text"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">标识</span>
                <input
                    v-model="editForm.code"
                    type="text"
                    maxlength="255"
                    :readonly="['developer', 'super_admin'].includes(selectedRole.code) || !(props.admin.role_codes ?? []).includes('developer')"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 read-only:bg-slate-100"
                >
            </label>

            <label>
                <span class="text-sm font-medium">备注</span>
                <input
                    v-model="editForm.remark"
                    type="text"
                    maxlength="255"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <button
                    type="submit"
                    :disabled="updating"
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50" >
                    {{ updating ? '保存中…' : '保存修改' }}
                </button>

                <button
                    type="button"
                    @click="cancelEditing"
                    :disabled="updating"
                    class="rounded border border-slate-300 px-4 py-2 text-slate-700 disabled:opacity-50" >
                    取消
                </button>
            </div>
        </form>

        <section
            v-if="selectedPermissionRole"
            class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">
                    修改权限：{{ selectedPermissionRole.name }}
                </h2>

                <button
                    type="button"
                    class="rounded border border-slate-300 px-3 py-1"
                    @click="closeEditingPermissions" >
                    收起
                </button>
            </div>

            <p v-if="permissionLoading" class="text-slate-600">
                正在加载权限树…
            </p>

            <p v-else-if="permissionRows.length === 0" class="text-slate-600">
                没有可显示的目录、菜单或权限。
            </p>

            <div v-else class="max-h-[60vh] overflow-y-auto rounded border border-slate-200 bg-white">
                <label
                    v-for="permission in visiblePermissionRows"
                    :key="permission.id"
                    class="flex items-center gap-3 border-b border-slate-100 px-3 py-2 last:border-0"
                    >

                    <span
                        class="flex shrink-0 items-center"
                        :style="{ marginLeft: `${permission.depth * 24}px` }"
                        >
                        <button
                            v-if="hasPermissionChildren(permission)"
                            type="button"
                            class="w-6 text-center text-slate-600 hover:text-blue-600"
                            :aria-label="
                                isPermissionExpanded(permission) ? '折叠子项' : '展开子项'
                            "
                            @click.stop="togglePermissionExpanded(permission)"
                        >
                            {{ isPermissionExpanded(permission) ? '▾' : '▸' }}
                        </button>
                        <span v-else class="w-6"></span>
                    </span>

                    <input
                        type="checkbox"
                        :checked="permission.assigned"
                        :disabled="
                                permission.locked
                                || isPermissionUpdating(permission.id)
                            "
                                @change="
                            toggleRolePermission(
                                permission,
                                $event.target.checked,
                            )
                        "
                    >

                    <span>{{ permission.name }}</span>
                    <span class="text-xs text-slate-500">
                        {{ typeNames[permission.type] ?? '未知类型' }}
                    </span>
                    <span
                                v-if="permission.locked"
                                class="text-xs text-slate-400"
                            >
                        不可修改
                    </span>
                </label>
            </div>
        </section>


        <p v-if="loading" class="text-slate-600">
            正在加载角色列表…
        </p>

        <p v-else-if="roles.length === 0" class="text-slate-600">
            暂无角色。
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="border-b text-slate-500">
                <tr>
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">名称</th>
                    <th class="px-3 py-3">标识</th>
                    <th class="px-3 py-3">备注</th>
                    <th class="px-3 py-3">操作</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="role in roles"
                    :key="role.id"
                    class="border-b last:border-0" >
                    <td class="px-3 py-3">{{ role.id }}</td>
                    <td class="px-3 py-3">{{ role.name }}</td>
                    <td class="px-3 py-3">{{ role.code || '—' }}</td>
                    <td class="px-3 py-3">{{ role.remark || '—' }}</td>
                    <td class="px-3 py-3">
                        <button
                            v-if="props.admin.can_update_roles"
                            type="button"
                            class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50"
                            @click="startEditing(role)" >
                            编辑
                        </button>

                        <button
                            v-if="canEditPermissions(role)"
                            type="button"
                            class="ml-2 rounded border border-blue-300 px-3 py-1 text-blue-700 hover:bg-blue-50"
                            @click="startEditingPermissions(role)">
                            修改权限
                        </button>
                    </td>
                </tr>
                </tbody>
            </table>

            <el-pagination
                v-if="total > 0"
                class="mt-4 justify-end"
                :current-page="currentPage"
                :page-size="perPage"
                :page-sizes="[10, 20, 50]"
                :total="total"
                layout="total, sizes, prev, pager, next, jumper"
                @current-change="loadRoles"
                @size-change="changePageSize"
            />
        </div>
    </section>
</template>
