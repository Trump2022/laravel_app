<script setup>
import { computed, onMounted, ref } from 'vue';
import { apiRequest } from '../../api';

const rows = ref([]);
const loading = ref(false);
const errorMessage = ref('');

const emit = defineEmits(['success']);

const permissionTree = ref([]);
const showCreateForm = ref(false);
const creating = ref(false);

const newPermission = ref({
    name: '',
    type: 1,
    pid: 0,
    icon: '',
    path: '',
    component: '',
    permission_code: '',
    status: 0,
    sort: 0,
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

const typeNames = {
    1: '目录',
    2: '页面菜单',
    3: 'API 权限',
};

function flattenTree(nodes, depth = 0, result = []) {
    for (const node of nodes) {
        result.push({
            ...node,
            depth,
        });

        flattenTree(node.children ?? [], depth + 1, result);
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
        sort: 0,
        remark: '',
        authorization_required: true,
        developer_only: false,
    };
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

onMounted(loadPermissionTree);
</script>

<template>
    <section class="mx-auto mt-6 max-w-6xl rounded-xl bg-white p-6 shadow">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold">菜单管理</h2>
            <el-button type="primary" @click=" showCreateForm = !showCreateForm; errorMessage = '';  " >
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
            @submit.prevent="createPermission"
        >
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
                    default-expand-all
                    class="w-full"
                />
            </el-form-item>

            <el-form-item label="图标">
                <el-input v-model="newPermission.icon" maxlength="255" />
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

            <el-form-item label="排序">
                <el-input-number v-model="newPermission.sort" :min="0" />
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
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">名称</th>
                    <th class="px-3 py-3">类型</th>
                    <th class="px-3 py-3">父级 ID</th>
                    <th class="px-3 py-3">标识 / 页面路径</th>
                    <th class="px-3 py-3">状态</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="permission in rows"
                    :key="permission.id"
                    class="border-b last:border-0"
                >
                    <td class="px-3 py-3">{{ permission.id }}</td>

                    <td
                        class="px-3 py-3 font-medium"
                        :style="{ paddingLeft: `${12 + permission.depth * 24}px` }"
                    >
                        {{ permission.name }}
                    </td>

                    <td class="px-3 py-3">
                        {{ typeNames[permission.type] ?? '未知类型' }}
                    </td>

                    <td class="px-3 py-3">{{ permission.pid }}</td>

                    <td class="px-3 py-3">
                        {{ permission.permission_code || permission.path || '—' }}
                    </td>

                    <td class="px-3 py-3">
                        {{ permission.status === 1 ? '启用' : '停用' }}
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
