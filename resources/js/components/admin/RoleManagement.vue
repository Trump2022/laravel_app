<script setup>
import { onMounted, ref } from 'vue';
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

async function loadRoles() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/roles');
        roles.value = response.data;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

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

        roles.value.push(response.data);
        cancelCreate();
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
    <section class="mx-auto mt-6 max-w-6xl rounded-xl bg-white p-6 shadow">
        <p v-if="errorMessage" class="mb-4 text-sm text-red-600">
            {{ errorMessage }}
        </p>


        <div class="mb-4">
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                @click="
            showCreateForm = !showCreateForm;
            errorMessage = '';
        "
            >
                {{ showCreateForm ? '收起' : '新建角色' }}
            </button>
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
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ creating ? '创建中…' : '保存角色' }}
                </button>

                <button
                    type="button"
                    :disabled="creating"
                    class="rounded border border-slate-300 px-4 py-2 text-slate-700 disabled:opacity-50"
                    @click="cancelCreate"
                >
                    取消
                </button>
            </div>
        </form>

        <form
            v-if="selectedRole"
            class="mb-6 grid gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="updateRole"
        >
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
                    :readonly="['developer', 'super_admin'].includes(selectedRole.code)"
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
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ updating ? '保存中…' : '保存修改' }}
                </button>

                <button
                    type="button"
                    :disabled="updating"
                    class="rounded border border-slate-300 px-4 py-2 text-slate-700 disabled:opacity-50"
                    @click="cancelEditing"
                >
                    取消
                </button>
            </div>
        </form>


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
                    class="border-b last:border-0"
                >
                    <td class="px-3 py-3">{{ role.id }}</td>
                    <td class="px-3 py-3">{{ role.name }}</td>
                    <td class="px-3 py-3">{{ role.code || '—' }}</td>
                    <td class="px-3 py-3">{{ role.remark || '—' }}</td>
                    <td class="px-3 py-3">
                        <button
                            type="button"
                            class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50"
                            @click="startEditing(role)"
                        >
                            编辑
                        </button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
