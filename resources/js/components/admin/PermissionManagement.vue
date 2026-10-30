<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiRequest } from '../../api';
// import { menuIconComponents, menuIconOptions } from './menuIcons';

import MenuIconPicker from './MenuIconPicker.vue';
import { menuIconComponents } from './menuIcons';

const rows = ref([]);
const expandedPermissionIds = ref([]);
const hasInitializedExpansion = ref(false);

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
});

const visibleRows = computed(() => {
    const rowsById = new Map(
        rows.value.map((row) => [row.id, row]),
    );

    return rows.value.filter((row) => {
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

function hasPermissionChildren(permission) {
    return rows.value.some((row) => row.pid === permission.id);
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

const loading = ref(false);
const errorMessage = ref('');

const emit = defineEmits(['success']);

const permissionTree = ref([]);
const editParentTree = ref([]);
const showCreateForm = ref(false);
const creating = ref(false);

const selectedPermission = ref(null);
const updating = ref(false);

const deletingId = ref(null);

const statusUpdatingId = ref(null);
const authorizationUpdatingId = ref(null);
const developerOnlyUpdatingId = ref(null);

const editPermission = ref({
    name: '',
    type: 1,
    pid: 0,
    icon: '',
    path: '',
    component: '',
    permission_code: '',
    status: 0,
    remark: '',
    authorization_required: true,
    developer_only: false,
});

const newPermission = ref({
    name: '',
    type: 1,
    pid: 0,
    icon: '',
    path: '',
    component: '',
    permission_code: '',
    status: 0,
    remark: '',
    authorization_required: true,
    developer_only: false,
});

const parentOptions = computed(() => [
    {
        id: 0,
        name: '顶级节点',
        children: permissionTree.value,
    },
]);

const editParentOptions = computed(() => [
    {
        id: 0,
        name: '顶级节点',
        children: editParentTree.value,
    },
]);

const typeNames = {
    1: '目录',
    2: '页面菜单',
    3: 'API 权限',
};

function flattenTree(    nodes,    depth = 0,    result = [],    parentId = null) {
    for (const node of nodes) {
        result.push({
            ...node,
            depth,
            parentId,
        });

        flattenTree(
            node.children ?? [],
            depth + 1,
            result,
            node.id,
        );
    }

    return result;
}

async function loadPermissionTree() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/permissions/tree');
        permissionTree.value = response.data;
        rows.value = flattenTree(permissionTree.value);

        const expandableIds = rows.value
            .filter((row) => hasPermissionChildren(row))
            .map((row) => row.id);

        if (!hasInitializedExpansion.value) {
            expandedPermissionIds.value = rows.value
                .filter((row) => row.depth === 0 && expandableIds.includes(row.id))
                .map((row) => row.id);
            hasInitializedExpansion.value = true;
        } else {
            expandedPermissionIds.value = expandedPermissionIds.value.filter(
                (id) => expandableIds.includes(id),
            );
        }
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

function resetNewPermission() {
    newPermission.value = {
        name: '',
        type: 1,
        pid: 0,
        icon: '',
        path: '',
        component: '',
        permission_code: '',
        status: 0,
        remark: '',
        authorization_required: true,
        developer_only: false,
    };
}

function startCreatingChild(permission) {
    resetNewPermission();
    newPermission.value.pid = permission.id;
    expandedPermissionIds.value = [
        ...new Set([...expandedPermissionIds.value, permission.id]),
    ];
    selectedPermission.value = null;
    showCreateForm.value = true;
    errorMessage.value = '';
}

async function createPermission() {
    creating.value = true;
    errorMessage.value = '';

    try {
        await apiRequest('/api/admin/permissions', {
            method: 'POST',
            body: JSON.stringify({
                ...newPermission.value,
                icon: newPermission.value.icon || null,
                path: newPermission.value.path || null,
                component: newPermission.value.component || null,
                permission_code: newPermission.value.permission_code || null,
                remark: newPermission.value.remark || null,
            }),
        });

        await loadPermissionTree();
        showCreateForm.value = false;
        resetNewPermission();
        emit('success', '菜单或权限创建成功。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        creating.value = false;
    }
}

async function startEditing(permission) {
    showCreateForm.value = false;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/permissions/tree?exclude_id=${permission.id}`,
        );

        editParentTree.value = response.data;
        editPermission.value = {
            name: permission.name,
            type: permission.type,
            pid: permission.pid,
            icon: permission.icon ?? '',
            path: permission.path ?? '',
            component: permission.component ?? '',
            permission_code: permission.permission_code ?? '',
            status: permission.status,
            remark: permission.remark ?? '',
            authorization_required: permission.authorization_required,
            developer_only: permission.developer_only,
        };
        selectedPermission.value = permission;
    } catch (error) {
        errorMessage.value = error.message;
    }
}

function cancelEditing() {
    selectedPermission.value = null;
    editParentTree.value = [];
    errorMessage.value = '';
}

async function updatePermission() {
    updating.value = true;
    errorMessage.value = '';

    try {
        await apiRequest(
            `/api/admin/permissions/${selectedPermission.value.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({
                    ...editPermission.value,
                    icon: editPermission.value.icon || null,
                    path: editPermission.value.path || null,
                    component: editPermission.value.component || null,
                    permission_code:
                        editPermission.value.permission_code || null,
                    remark: editPermission.value.remark || null,
                }),
            },
        );

        selectedPermission.value = null;
        await loadPermissionTree();
        emit('success', '菜单或权限更新成功。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        updating.value = false;
    }
}


async function togglePermissionStatus(permission) {
    const nextStatus = Number(permission.status) === 1 ? 0 : 1;

    statusUpdatingId.value = permission.id;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/permissions/${permission.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({ status: nextStatus }),
            },
        );

        rows.value = rows.value.map((row) =>
            row.id === permission.id
                ? { ...row, status: response.data.status }
                : row,
        );

        emit('success', nextStatus === 1 ? '菜单或权限已启用。' : '菜单或权限已停用。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        statusUpdatingId.value = null;
    }
}

async function togglePermissionRequired(permission) {
    const nextValue = !permission.authorization_required;

    authorizationUpdatingId.value = permission.id;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/permissions/${permission.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({
                    authorization_required: nextValue,
                }),
            },
        );

        rows.value = rows.value.map((row) =>
            row.id === permission.id
                ? {
                    ...row,
                    authorization_required:
                    response.data.authorization_required,
                }
                : row,
        );

        emit(
            'success',
            nextValue ? '已设为需要角色授权。' : '已设为无需角色授权。',
        );
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        authorizationUpdatingId.value = null;
    }
}

async function toggleDeveloperOnly(permission) {
    const nextValue = !permission.developer_only;

    developerOnlyUpdatingId.value = permission.id;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/permissions/${permission.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({ developer_only: nextValue }),
            },
        );

        rows.value = rows.value.map((row) =>
            row.id === permission.id
                ? { ...row, developer_only: response.data.developer_only }
                : row,
        );

        emit(
            'success',
            nextValue ? '已设为仅开发者可见。' : '已设为非开发者可见。',
        );
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        developerOnlyUpdatingId.value = null;
    }
}

async function deletePermission(permission) {
    if (permission.children?.length) {
        errorMessage.value = '该节点仍有子节点，不能删除。';
        return;
    }

    deletingId.value = permission.id;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/admin/permissions/${permission.id}`, {
            method: 'DELETE',
        });

        await loadPermissionTree();
        emit('success', '菜单或权限删除成功。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        deletingId.value = null;
    }
}

const movingId = ref(null);

function siblingsOf(permission) {
    return rows.value.filter((row) => row.pid === permission.pid);
}

function isFirstSibling(permission) {
    return siblingsOf(permission)[0]?.id === permission.id;
}

function isLastSibling(permission) {
    const siblings = siblingsOf(permission);

    return siblings[siblings.length - 1]?.id === permission.id;
}

async function movePermission(permission, direction) {
    movingId.value = permission.id;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/admin/permissions/${permission.id}/move`, {
            method: 'PATCH',
            body: JSON.stringify({ direction }),
        });

        await loadPermissionTree();
        emit('success', '菜单或权限顺序已更新。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        movingId.value = null;
    }
}

onMounted(loadPermissionTree);
</script>

<template>
    <section class="mt-6 w-full rounded-xl bg-white p-6 shadow">
        <div class="mb-4">
            <el-button
                v-if="props.admin.can_create_permissions"
                type="primary"
                @click="
                showCreateForm = !showCreateForm;
                errorMessage = ''; " >
                {{ showCreateForm ? '收起新增表单' : '新增菜单/权限' }}
            </el-button>
        </div>

        <p v-if="errorMessage" class="mb-4 text-sm text-red-600">
            {{ errorMessage }}
        </p>

        <el-form
            v-if="showCreateForm"
            label-position="top"
            class="mb-6 grid gap-x-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="createPermission" >
            <el-form-item label="名称" required>
                <el-input v-model="newPermission.name" maxlength="255" />
            </el-form-item>

            <el-form-item label="类型" required>
                <el-select v-model="newPermission.type">
                    <el-option label="目录" :value="1" />
                    <el-option label="页面菜单" :value="2" />
                    <el-option label="API 权限" :value="3" />
                </el-select>
            </el-form-item>

            <el-form-item label="父级">
                <el-tree-select
                    v-model="newPermission.pid"
                    :data="parentOptions"
                    node-key="id"
                    :props="{ value: 'id', label: 'name', children: 'children' }"
                    check-strictly
                    class="w-full"
                />
            </el-form-item>

            <el-form-item label="图标">
                <MenuIconPicker v-model="newPermission.icon" />
            </el-form-item>

            <el-form-item v-if="newPermission.type === 2" label="前端路由 path" required>
                <el-input v-model="newPermission.path" maxlength="255" />
            </el-form-item>

            <el-form-item v-if="newPermission.type === 2" label="前端组件 component" required>
                <el-input v-model="newPermission.component" maxlength="150" />
            </el-form-item>

            <el-form-item v-if="newPermission.type === 3" label="权限标识 permission_code" required>
                <el-input
                    v-model="newPermission.permission_code"
                    maxlength="191"
                    placeholder="例如 admin.roles.index:get"
                />
            </el-form-item>

            <el-form-item label="状态">
                <el-select v-model="newPermission.status">
                    <el-option label="停用" :value="0" />
                    <el-option label="启用" :value="1" />
                </el-select>
            </el-form-item>

            <el-form-item label="备注">
                <el-input v-model="newPermission.remark" maxlength="255" />
            </el-form-item>

            <el-form-item label="需要角色授权">
                <el-switch v-model="newPermission.authorization_required" />
            </el-form-item>

            <el-form-item label="仅开发者可见">
                <el-switch v-model="newPermission.developer_only" />
            </el-form-item>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <el-button
                    type="primary"
                    :loading="creating"
                    @click="createPermission"
                >
                    保存
                </el-button>
                <el-button
                    :disabled="creating"
                    @click="
                showCreateForm = false;
                resetNewPermission();
                errorMessage = '';
            "
                >
                    取消
                </el-button>
            </div>
        </el-form>

        <el-form
            v-if="selectedPermission"
            label-position="top"
            class="mb-6 grid gap-x-4 rounded-lg border border-blue-200 bg-blue-50 p-4 sm:grid-cols-2 lg:grid-cols-3" >
            <h3 class="font-semibold sm:col-span-2 lg:col-span-3">
                编辑：{{ selectedPermission.name }}
            </h3>

            <el-form-item label="名称" required>
                <el-input v-model="editPermission.name" maxlength="255" />
            </el-form-item>

            <el-form-item label="类型" required>
                <el-select v-model="editPermission.type">
                    <el-option label="目录" :value="1" />
                    <el-option label="页面菜单" :value="2" />
                    <el-option label="API 权限" :value="3" />
                </el-select>
            </el-form-item>

            <el-form-item label="父级">
                <el-tree-select
                    v-model="editPermission.pid"
                    :data="editParentOptions"
                    node-key="id"
                    :props="{ value: 'id', label: 'name', children: 'children' }"
                    check-strictly
                    class="w-full"
                />
            </el-form-item>

            <el-form-item label="图标">
                <MenuIconPicker v-model="editPermission.icon" />
            </el-form-item>

            <el-form-item label="状态">
                <el-select v-model="editPermission.status">
                    <el-option label="停用" :value="0" />
                    <el-option label="启用" :value="1" />
                </el-select>
            </el-form-item>

            <el-form-item label="备注">
                <el-input v-model="editPermission.remark" maxlength="255" />
            </el-form-item>

            <el-form-item label="需要角色授权">
                <el-switch v-model="editPermission.authorization_required" />
            </el-form-item>

            <el-form-item label="仅开发者可见">
                <el-switch v-model="editPermission.developer_only" />
            </el-form-item>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <el-button
                    type="primary"
                    :loading="updating"
                    @click="updatePermission"
                >
                    保存修改
                </el-button>

                <el-button
                    :disabled="updating"
                    @click="cancelEditing"
                >
                    取消
                </el-button>
            </div>
        </el-form>

        <p v-if="loading" class="text-slate-600">
            正在加载权限树…
        </p>

        <p v-else-if="rows.length === 0" class="text-slate-600">
            暂无权限或菜单记录。
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="border-b text-slate-500">
                <tr>
                    <th class="px-3 py-3">名称</th>
                    <th class="px-3 py-3">类型</th>
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">父级 ID</th>
                    <th class="px-3 py-3">ID 路径</th>
                    <th class="px-3 py-3">排序值</th>
                    <th class="px-3 py-3">标识 / 页面路径</th>
                    <th class="px-3 py-3">角色授权</th>
                    <th class="px-3 py-3">可见范围</th>
                    <th class="px-3 py-3">状态</th>
                    <th class="px-3 py-3">操作</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="permission in visibleRows"
                    :key="permission.id"
                    class="border-b last:border-0"
                    >


                    <td class="px-3 py-3 font-medium">
                        <div
                            class="flex items-center"
                            :style="{ marginLeft: `${permission.depth * 24}px` }"
                        >
                            <button
                                v-if="hasPermissionChildren(permission)"
                                type="button"
                                class="mr-1 flex h-7 w-7 cursor-pointer items-center justify-center rounded text-lg text-slate-600 hover:bg-slate-100 hover:text-blue-600"
                                :aria-label="
                                        isPermissionExpanded(permission) ? '折叠子项' : '展开子项'
                                    "
                                @click.stop="togglePermissionExpanded(permission)"
                                >
                                {{ isPermissionExpanded(permission) ? '▾' : '▸' }}
                            </button>
                            <span v-else class="mr-1 inline-block w-6"></span>

                            <el-icon
                                v-if="menuIconComponents[permission.icon]"
                                class="mr-2" >
                                <component :is="menuIconComponents[permission.icon]" />
                            </el-icon>
                            <span>{{ permission.name }}</span>
                        </div>
                    </td>

                    <td class="px-3 py-3">
                        {{ typeNames[permission.type] ?? '未知类型' }}
                    </td>

                    <td class="px-3 py-3">{{ permission.id }}</td>

                    <td class="px-3 py-3">{{ permission.pid }}</td>

                    <td class="px-3 py-3">{{ permission.id_path }}</td>
                    <td class="px-3 py-3">{{ permission.sort }}</td>


                    <td class="px-3 py-3">
                        {{ permission.permission_code || permission.path || '—' }}
                    </td>

                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="permission.authorization_required"
                                :aria-label="`${permission.name}：${permission.authorization_required ? '需授权' : '免授权'}`"
                                :disabled="authorizationUpdatingId === permission.id"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
                                :class="permission.authorization_required ? 'bg-emerald-500' : 'bg-slate-300'"
                                @click="togglePermissionRequired(permission)"
                            >
                            <span
                                class="inline-block size-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                :class="permission.authorization_required ? 'translate-x-5' : 'translate-x-0.5'"
                            ></span>
                            </button>

                            <span
                                class="text-sm font-medium"
                                :class="permission.authorization_required ? 'text-emerald-700' : 'text-slate-500'"
                                                >
                                {{
                                                        authorizationUpdatingId === permission.id
                                                            ? '处理中…'
                                                            : permission.authorization_required
                                                                ? '需授权'
                                                                : '免授权'
                                                    }}
                            </span>
                        </div>
                    </td>

                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="permission.developer_only"
                                :aria-label="`${permission.name}：${permission.developer_only ? '仅开发者可见' : '非开发者可见'}`"
                                :disabled="developerOnlyUpdatingId === permission.id"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
                                :class="permission.developer_only ? 'bg-emerald-500' : 'bg-slate-300'"
                                @click="toggleDeveloperOnly(permission)"
                                >
                                <span
                                    class="inline-block size-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                    :class="permission.developer_only ? 'translate-x-5' : 'translate-x-0.5'"
                                    >
                                </span>
                            </button>

                            <span
                                class="text-sm font-medium"
                                :class="permission.developer_only ? 'text-emerald-700' : 'text-slate-500'"
                                >
                                {{
                                    developerOnlyUpdatingId === permission.id
                                        ? '处理中…'
                                        : permission.developer_only
                                            ? '仅开发者'
                                            : '普通可见'
                                }}
                            </span>
                        </div>
                    </td>

                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="Number(permission.status) === 1"
                                :aria-label="`${permission.name}：${Number(permission.status) === 1 ? '启用' : '停用'}`"
                                :disabled="statusUpdatingId === permission.id"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
                                :class="Number(permission.status) === 1 ? 'bg-emerald-500' : 'bg-slate-300'"
                                @click="togglePermissionStatus(permission)" >
                                <span
                                    class="inline-block size-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                    :class="Number(permission.status) === 1 ? 'translate-x-5' : 'translate-x-0.5'" >
                                </span>
                            </button>
                            <span  class="text-sm font-medium"  :class="Number(permission.status) === 1 ? 'text-emerald-700' : 'text-slate-500'" >
                                {{ statusUpdatingId === permission.id ? '处理中…' : Number(permission.status) === 1 ? '启用' : '停用' }}
                            </span>
                        </div>
                    </td>

                    <td class="px-3 py-3">
                        <el-button
                            size="small"
                            @click="startCreatingChild(permission)" >
                            添加下级
                        </el-button>

                        <el-button
                            size="small"
                            :disabled="movingId !== null || isFirstSibling(permission)"
                            :loading="movingId === permission.id"
                            @click="movePermission(permission, 'up')" >
                            上移
                        </el-button>

                        <el-button
                            size="small"
                            :disabled="movingId !== null || isLastSibling(permission)"
                            @click="movePermission(permission, 'down')" >
                            下移
                        </el-button>

                        <el-button
                            size="small"
                            @click="startEditing(permission)" >
                            编辑
                        </el-button>

                        <el-popconfirm
                            title="确定删除这条记录吗？"
                            confirm-button-text="删除"
                            cancel-button-text="取消"
                            @confirm="deletePermission(permission)" >
                            <template #reference>
                                <el-button
                                    size="small"
                                    type="danger"
                                    :disabled="Boolean(permission.children?.length)"
                                    :loading="deletingId === permission.id"
                                >
                                    删除
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
