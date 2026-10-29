<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import {apiRequest} from '../../api';

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['success']);

const adminErrorMessage = ref('');
const admins = ref([]);
const adminsLoading = ref(false);
const creatingAdmin = ref(false);
const showCreateAdminForm = ref(false);
const newAdmin = ref({
    name: '',
    email: '',
    password: '',
});
const selectedAdmin = ref(null);
const activeAdminPanel = ref(null);
const editForm = ref({
    name: '',
    email: '',
});
const updatingAdmin = ref(false);
const statusUpdatingId = ref(null);
const resettingPassword = ref(false);
const resetPasswordForm = ref({
    password: '',
    password_confirmation: '',
});

const roleOptions = ref({
    roles: [],
    assignable_role_ids: [],
    removable_role_ids: [],
    has_exclusive_role: false,
});
const roleOptionsLoading = ref(false);
const roleUpdatingKey = ref(null);

const searchText = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
let searchTimer = null;

function handleSearchInput() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        loadAdmins(1);
    }, 300);
}

function changePageSize(size) {
    perPage.value = size;
    loadAdmins(1);
}

onUnmounted(() => {
    window.clearTimeout(searchTimer);
});

async function loadAdmins(page = currentPage.value) {
    adminsLoading.value = true;
    adminErrorMessage.value = '';
    currentPage.value = page;

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage.value),
            search: searchText.value.trim(),
        });
        const response = await apiRequest(`/api/admin/admins?${params}`);

        admins.value = response.data;
        currentPage.value = response.current_page;
        total.value = response.total;
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        adminsLoading.value = false;
    }
}


async function startEditingRoles(item) {
    adminErrorMessage.value = '';
    roleOptionsLoading.value = true;

    try {
        const response = await apiRequest(
            `/api/admin/admins/${item.id}/role-options`,
        );

        selectedAdmin.value = item;
        activeAdminPanel.value = 'roles';
        roleOptions.value = response.data;
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        roleOptionsLoading.value = false;
    }
}

function canEditRoles(item) {
    const actorRoleCodes = props.admin.role_codes ?? [];
    const targetRoleCodes = (item.roles ?? []).map((role) => role.code);

    if (
        item.id === props.admin.id
        || targetRoleCodes.includes('developer')
    ) {
        return false;
    }

    if (actorRoleCodes.includes('developer')) {
        return true;
    }

    if (targetRoleCodes.includes('super_admin')) {
        return false;
    }

    return Boolean(props.admin.can_view_admin_role_options);
}

function isRoleAssigned(admin, role) {
    return (admin.roles ?? []).some(
        (assignedRole) => assignedRole.id === role.id,
    );
}

function isRoleToggleDisabled(admin, role) {
    if (isRoleAssigned(admin, role)) {
        return !roleOptions.value.removable_role_ids.includes(role.id);
    }

    const actorIsDeveloper = (props.admin.role_codes ?? [])
        .includes('developer');
    const targetHasSuperAdmin = (admin.roles ?? [])
        .some((assignedRole) => assignedRole.code === 'super_admin');
    const isPromotionToDeveloper = actorIsDeveloper
        && targetHasSuperAdmin
        && role.code === 'developer';

    return !roleOptions.value.assignable_role_ids.includes(role.id)
        || (roleOptions.value.has_exclusive_role && !isPromotionToDeveloper);
}

async function toggleAdminRole(admin, role, checked) {
    roleUpdatingKey.value = `${admin.id}:${role.id}`;
    adminErrorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/admins/${admin.id}/roles/${role.id}`,
            { method: checked ? 'PUT' : 'DELETE' },
        );

        const updatedAdmin = response.data;

        admins.value = admins.value.map((item) =>
            item.id === updatedAdmin.id ? updatedAdmin : item,
        );

        if (!canEditRoles(updatedAdmin)) {
            selectedAdmin.value = null;
            activeAdminPanel.value = null;
        } else {
            selectedAdmin.value = updatedAdmin;
            roleOptionsLoading.value = true;

            try {
                const optionsResponse = await apiRequest(
                    `/api/admin/admins/${updatedAdmin.id}/role-options`,
                );

                roleOptions.value = optionsResponse.data;
            } finally {
                roleOptionsLoading.value = false;
            }
        }
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        roleUpdatingKey.value = null;
    }
}

async function createAdmin() {
    creatingAdmin.value = true;
    adminErrorMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/admins', {
            method: 'POST',
            body: JSON.stringify(newAdmin.value),
        });

        admins.value.push(response.data);
        newAdmin.value = { name: '', email: '', password: '' };
        showCreateAdminForm.value = false;
        emit('success', '管理员创建成功。');
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        creatingAdmin.value = false;
    }
}

function cancelCreateAdmin() {
    showCreateAdminForm.value = false;
    newAdmin.value = { name: '', email: '', password: '' };
    adminErrorMessage.value = '';
}

function startEditing(item) {
    selectedAdmin.value = item;
    activeAdminPanel.value = 'edit';
    editForm.value = { name: item.name, email: item.email };
    adminErrorMessage.value = '';
}

function editOwnProfile() {
    startEditing(props.admin);
}

defineExpose({ editOwnProfile });

function startResettingPassword(item) {
    selectedAdmin.value = item;
    activeAdminPanel.value = 'password';
    resetPasswordForm.value = {
        password: '',
        password_confirmation: '',
    };
    adminErrorMessage.value = '';
}

function cancelEditing() {
    selectedAdmin.value = null;
    activeAdminPanel.value = null;
}

function cancelResettingPassword() {
    resetPasswordForm.value = {
        password: '',
        password_confirmation: '',
    };
    selectedAdmin.value = null;
    activeAdminPanel.value = null;
    adminErrorMessage.value = '';
}

async function updateAdmin() {
    updatingAdmin.value = true;
    adminErrorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/admins/${selectedAdmin.value.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify(editForm.value),
            },
        );

        admins.value = admins.value.map((item) =>
            item.id === response.data.id ? response.data : item,
        );
        selectedAdmin.value = null;
        activeAdminPanel.value = null;
        emit('success', '管理员资料已更新。');
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        updatingAdmin.value = false;
    }
}

async function resetAdminPassword() {
    resettingPassword.value = true;
    adminErrorMessage.value = '';

    try {
        await apiRequest(
            `/api/admin/admins/${selectedAdmin.value.id}/password`,
            {
                method: 'PATCH',
                body: JSON.stringify(resetPasswordForm.value),
            },
        );

        resetPasswordForm.value = {
            password: '',
            password_confirmation: '',
        };
        selectedAdmin.value = null;
        activeAdminPanel.value = null;
        emit('success', '管理员密码已重置。');
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        resettingPassword.value = false;
    }
}

function roleCodes(admin) {
    return admin.role_codes
        ?? admin.roles?.map((role) => role.code)
        ?? [];
}

function privilegeLevel(admin) {
    const codes = roleCodes(admin);

    if (codes.includes('developer')) {
        return 2;
    }

    return codes.includes('super_admin') ? 1 : 0;
}

function isProtectedTarget(item) {
    return Boolean(item.can_reset_admin_passwords)
        && privilegeLevel(item) >= privilegeLevel(props.admin);
}

function canEditAdmin(item) {
    return Boolean(props.admin.can_update_admins)
        && (
            item.id === props.admin.id
            || !isProtectedTarget(item)
        );
}

function canResetAdminPassword(item) {
    return Boolean(props.admin.can_reset_admin_passwords)
        && item.id !== props.admin.id
        && !isProtectedTarget(item);
}

function toggleCreateAdminForm() {
    showCreateAdminForm.value = !showCreateAdminForm.value;
    adminErrorMessage.value = '';
}

async function toggleAdminStatus(item) {
    const nextStatus = !item.status;

    statusUpdatingId.value = item.id;
    adminErrorMessage.value = '';

    try {
        const response = await apiRequest(
            `/api/admin/admins/${item.id}/status`,
            {
                method: 'PATCH',
                body: JSON.stringify({ status: nextStatus }),
            },
        );

        admins.value = admins.value.map((adminItem) =>
            adminItem.id === response.data.id ? response.data : adminItem,
        );
        emit('success', nextStatus ? '管理员已启用。' : '管理员已停用。');
    } catch (error) {
        adminErrorMessage.value = error.message;
    } finally {
        statusUpdatingId.value = null;
    }
}

onMounted(loadAdmins);
</script>

<template>
    <section class="mt-6 w-full rounded-xl bg-white p-6 shadow">
        <p v-if="adminErrorMessage" class="mb-4 text-sm text-red-600">
            {{ adminErrorMessage }}
        </p>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                @click="toggleCreateAdminForm" >
                {{ showCreateAdminForm ? '收起' : '新管理员' }}
            </button>

            <el-input
                v-model="searchText"
                clearable
                class="w-full sm:max-w-sm"
                placeholder="搜索姓名、邮箱、角色或 ID"
                @input="handleSearchInput"
            />
        </div>

        <form
            v-if="showCreateAdminForm"
            class="mb-6 grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4" >
            <label>
                <span class="text-sm font-medium">姓名</span>
                <input
                    v-model="newAdmin.name"
                    type="text"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">邮箱</span>
                <input
                    v-model="newAdmin.email"
                    type="email"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">初始密码（至少 8 位）</span>
                <input
                    v-model="newAdmin.password"
                    type="password"
                    minlength="8"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <div class="flex items-end">
                <button
                    type="submit"
                    :disabled="creatingAdmin"
                    class="w-full rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50" >
                    {{ creatingAdmin ? '创建中…' : '新增管理员' }}
                </button>
                <button
                    type="button"
                    class="mt-2 w-full rounded border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-50"
                    @click="cancelCreateAdmin"
                >
                    取消
                </button>
            </div>
        </form>

        <form
            v-if="selectedAdmin && activeAdminPanel === 'edit'"
            class="mb-6 grid gap-4 rounded-lg border border-blue-200 bg-blue-50 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="updateAdmin" >
            <h2 class="sm:col-span-2 lg:col-span-3 font-semibold">
                编辑管理员：{{ selectedAdmin.email }}
            </h2>

            <label>
                <span class="text-sm font-medium">姓名</span>
                <input
                    v-model="editForm.name"
                    type="text"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">邮箱</span>
                <input
                    v-model="editForm.email"
                    type="email"
                    maxlength="255"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    :disabled="updatingAdmin"
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50" >
                    {{ updatingAdmin ? '保存中…' : '保存修改' }}
                </button>

                <button
                    @click="cancelEditing"
                    type="button"
                    class="rounded border border-slate-300 px-4 py-2" >
                    取消
                </button>
            </div>
        </form>

        <form
            v-if="selectedAdmin && activeAdminPanel === 'password'"
            class="mb-6 grid gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="resetAdminPassword">
            <h2 class="font-semibold sm:col-span-2 lg:col-span-3">
                重置密码：{{ selectedAdmin.email }}
            </h2>

            <label>
                <span class="text-sm font-medium">新密码（至少 8 位）</span>
                <input
                    v-model="resetPasswordForm.password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2" >
            </label>

            <label>
                <span class="text-sm font-medium">确认新密码</span>
                <input
                    v-model="resetPasswordForm.password_confirmation"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2" >
            </label>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    :disabled="resettingPassword"
                    class="rounded bg-amber-600 px-4 py-2 text-white disabled:opacity-50" >
                    {{ resettingPassword ? '重置中…' : '确认重置密码' }}
                </button>

                <button
                    type="button"
                    :disabled="resettingPassword"
                    class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 disabled:opacity-50"
                    @click="cancelResettingPassword" >
                    取消
                </button>
            </div>
        </form>

        <form
            v-if="selectedAdmin && activeAdminPanel === 'roles'"
            class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h2 class="mb-3 font-semibold">
                修改角色：{{ selectedAdmin.email }}
            </h2>

            <div class="flex flex-wrap gap-4">
                <label
                    v-for="role in roleOptions.roles"
                    :key="role.id"
                    class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        :checked="isRoleAssigned(selectedAdmin, role)"
                        :disabled="
                            roleOptionsLoading
                            || roleUpdatingKey === `${selectedAdmin.id}:${role.id}`
                            || isRoleToggleDisabled(selectedAdmin, role)
                        "
                                            @change="
                            toggleAdminRole(
                                selectedAdmin,
                                role,
                                $event.target.checked,
                            )
                        "
                    >
                    <span>{{ role.name }}</span>
                </label>
            </div>

            <button
                type="button"
                class="mt-4 rounded border border-slate-300 px-3 py-1"
                @click="cancelEditing">
                收起
            </button>
        </form>

        <p v-if="adminsLoading" class="text-slate-600">
            正在加载管理员列表…
        </p>

        <p v-else-if="admins.length === 0" class="text-slate-600">
            暂无管理员数据。
        </p>



        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="border-b text-slate-500">
                <tr>
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">姓名</th>
                    <th class="px-3 py-3">角色</th>
                    <th class="px-3 py-3">邮箱</th>
                    <th class="px-3 py-3">状态</th>
                    <th class="px-3 py-3">创建时间</th>
                    <th class="px-3 py-3">操作</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="item in admins"
                    :key="item.id"
                    class="border-b last:border-0" >
                    <td class="px-3 py-3">{{ item.id }}</td>
                    <td class="px-3 py-3">{{ item.name }}</td>
                    <td class="px-3 py-3">
                        <span v-if="item.roles.length">
                            {{ item.roles.map((role) => role.name).join('、') }}
                        </span>
                        <span v-else>—</span>
                    </td>
                    <td class="px-3 py-3">{{ item.email }}</td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="item.status"
                                :aria-label="`${item.name}：${item.status ? '启用' : '停用'}`"
                                :disabled="statusUpdatingId === item.id || (item.status && !item.can_disable)"
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
                                :class="item.status ? 'bg-emerald-500' : 'bg-slate-300'"
                                @click="toggleAdminStatus(item)">
                                <span
                                    class="inline-block size-5 transform rounded-full bg-white shadow transition-transform duration-200"
                                    :class="item.status ? 'translate-x-5' : 'translate-x-0.5'"
                                ></span>
                            </button>

                            <span   class="text-sm font-medium"  :class="item.status ? 'text-emerald-700' : 'text-slate-500'">
                                {{ statusUpdatingId === item.id ? '处理中…'   : item.status   ? '启用'   : '停用' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-3 py-3">{{ item.created_at }}</td>

                    <td class="px-3 py-3">
                        <button
                            v-if="canEditAdmin(item)"
                            class="rounded border border-slate-300 px-3 py-1"
                            @click="startEditing(item)">
                            编辑
                        </button>

                        <button
                            v-if="canResetAdminPassword(item)"
                            type="button"
                            class="ml-2 rounded border border-amber-300 px-3 py-1 text-amber-700 hover:bg-amber-50"
                            @click="startResettingPassword(item)">
                            重置密码
                        </button>

                        <button
                            v-if="canEditRoles(item)"
                            type="button"
                            class="ml-2 rounded border border-blue-300 px-3 py-1 text-blue-700 hover:bg-blue-50"
                            @click="startEditingRoles(item)">
                            修改角色
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
                @current-change="loadAdmins"
                @size-change="changePageSize"
            />
        </div>
    </section>
</template>
