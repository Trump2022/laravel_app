<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { apiRequest } from '../../api';

defineProps({
    admin: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['success']);

const settings = ref([]);
const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const loadingEditId = ref(null);
const errorMessage = ref('');
const showForm = ref(false);
const editingId = ref(null);

const keyword = ref('');
const typeFilter = ref('');
const currentPage = ref(1);
const perPage = ref(15);
const total = ref(0);
let searchTimer = null;

const typeOptions = [
    { value: 1, label: '字符串' },
    { value: 2, label: '整数' },
    { value: 3, label: '布尔值' },
    { value: 4, label: '小数' },
    { value: 5, label: 'JSON' },
];

function emptyForm() {
    return {
        setting_key: '',
        name: '',
        type: 1,
        setting_value: '',
    };
}

const form = ref(emptyForm());
const jsonFields = ref([]);
const jsonRows = ref([]);
const viewingSetting = ref(null);
const showJsonDialog = ref(false);

function formatValue(setting) {
    if (Number(setting.type) === 5) {
        return JSON.stringify(setting.json_value);
    }

    if (Number(setting.type) === 3) {
        return String(setting.setting_value) === '1' ? '是' : '否';
    }

    return setting.setting_value ?? '—';
}

function setJsonEditor(jsonValue) {
    const sourceFields = Array.isArray(jsonValue?.fields)
        ? jsonValue.fields
        : [];
    const sourceData = Array.isArray(jsonValue?.data)
        ? jsonValue.data
        : [];

    jsonFields.value = sourceFields.map((field) => ({
        title: String(field.title ?? ''),
        key: String(field.key ?? ''),
    }));

    jsonRows.value = sourceData.map((item) =>
        jsonFields.value.map((field) => String(item?.[field.key] ?? '')),
    );
}

function addJsonField() {
    let number = jsonFields.value.length + 1;
    let key = `field_${number}`;

    while (jsonFields.value.some((field) => field.key === key)) {
        number += 1;
        key = `field_${number}`;
    }

    jsonFields.value.push({
        title: `列 ${number}`,
        key,
    });

    jsonRows.value.forEach((row) => row.push(''));
}

function removeJsonField(index) {
    jsonFields.value.splice(index, 1);
    jsonRows.value.forEach((row) => row.splice(index, 1));
}

function addJsonRow() {
    jsonRows.value.push(jsonFields.value.map(() => ''));
}

function removeJsonRow(index) {
    jsonRows.value.splice(index, 1);
}

function viewJsonSetting(setting) {
    viewingSetting.value = setting;
    showJsonDialog.value = true;
}

async function loadSettings(page = currentPage.value) {
    loading.value = true;
    errorMessage.value = '';
    currentPage.value = page;

    const params = new URLSearchParams({
        page: String(page),
        per_page: String(perPage.value),
        keyword: keyword.value.trim(),
    });

    if (typeFilter.value !== '') {
        params.set('type', String(typeFilter.value));
    }

    try {
        const response = await apiRequest(`/api/admin/settings?${params}`);
        const paginator = response.data;

        settings.value = paginator.data;
        currentPage.value = paginator.current_page;
        total.value = paginator.total;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

function handleFilterChange() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        loadSettings(1);
    }, 300);
}

function changePageSize(size) {
    perPage.value = size;
    loadSettings(1);
}

function startCreating() {
    editingId.value = null;
    form.value = emptyForm();
    jsonFields.value = [
        { title: '列 1', key: 'field_1' },
        { title: '列 2', key: 'field_2' },
        { title: '列 3', key: 'field_3' },
        { title: '列 4', key: 'field_4' },
    ];
    jsonRows.value = [['', '']];
    errorMessage.value = '';
    showForm.value = true;
}

async function startEditing(setting) {
    loadingEditId.value = setting.id;
    errorMessage.value = '';

    try {
        const response = await apiRequest(`/api/admin/settings/${setting.id}`);
        const data = response.data;

        editingId.value = data.id;
        form.value = {
            setting_key: data.setting_key,
            name: data.name,
            type: Number(data.type),
            setting_value: data.setting_value ?? '',
        };

        if (Number(data.type) === 5) {
            setJsonEditor(data.json_value);
        }

        setJsonEditor(data.json_value);
        showForm.value = true;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loadingEditId.value = null;
    }
}

function cancelForm() {
    showForm.value = false;
    editingId.value = null;
    form.value = emptyForm();
    jsonFields.value = [];
    jsonRows.value = [];
    errorMessage.value = '';
}

async function saveSetting() {
    saving.value = true;
    errorMessage.value = '';

    const wasEditing = editingId.value !== null;
    const type = Number(form.value.type);
    const payload = {
        setting_key: form.value.setting_key.trim(),
        name: form.value.name.trim(),
        type,
    };

    if (type === 5) {
        const fields = jsonFields.value.map((field) => ({
            title: field.title.trim(),
            key: field.key.trim(),
        }));
        const keys = fields.map((field) => field.key);

        if (
            fields.length === 0
            || fields.some((field) => !field.title || !field.key)
            || new Set(keys).size !== keys.length
        ) {
            errorMessage.value = '请填写每列的标题和唯一 key。';
            saving.value = false;
            return;
        }

        payload.json_value = {
            fields,
            data: jsonRows.value.map((row) =>
                Object.fromEntries(
                    fields.map((field, index) => [
                        field.key,
                        row[index] ?? '',
                    ]),
                ),
            ),
        };
    } else if (type === 3) {
        payload.setting_value = form.value.setting_value === '1';
    } else {
        payload.setting_value = form.value.setting_value;
    }

    try {
        const url = wasEditing
            ? `/api/admin/settings/${editingId.value}`
            : '/api/admin/settings';

        await apiRequest(url, {
            method: wasEditing ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });

        const page = wasEditing ? currentPage.value : 1;

        cancelForm();
        await loadSettings(page);
        emit('success', wasEditing ? '设置已更新。' : '设置已创建。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        saving.value = false;
    }
}

async function deleteSetting(setting) {
    deletingId.value = setting.id;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/admin/settings/${setting.id}`, {
            method: 'DELETE',
        });

        const nextPage = settings.value.length === 1 && currentPage.value > 1
            ? currentPage.value - 1
            : currentPage.value;

        await loadSettings(nextPage);
        emit('success', '设置已删除。');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        deletingId.value = null;
    }
}

onMounted(() => {
    loadSettings();
});

onUnmounted(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <section class="mt-6 w-full rounded-xl bg-white p-6 shadow">
        <p v-if="errorMessage" role="alert" class="mb-4 text-sm text-red-600">
            {{ errorMessage }}
        </p>

        <div class="mb-4 flex items-center gap-3 overflow-x-auto">
            <el-button
                class="shrink-0 whitespace-nowrap"
                type="primary"
                @click="showForm ? cancelForm() : startCreating()"
            >
                {{ showForm ? '收起' : '新增设置' }}
            </el-button>

            <div class="ml-auto flex min-w-[27rem] shrink-0 items-center justify-end gap-3">
                <el-input
                    v-model="keyword"
                    clearable
                    class="w-64 shrink-0"
                    placeholder="搜索设置键或名称"
                    @input="handleFilterChange"
                />

                <el-select
                    v-model="typeFilter"
                    clearable
                    class="w-36 shrink-0"
                    placeholder="全部类型"
                    @change="loadSettings(1)"
                >
                    <el-option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>
            </div>
        </div>

        <form
            v-if="showForm"
            class="mb-6 grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2"
            @submit.prevent="saveSetting"
        >
            <label>
                <span class="text-sm font-medium">设置键</span>
                <input
                    v-model="form.setting_key"
                    type="text"
                    maxlength="100"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">名称</span>
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <label>
                <span class="text-sm font-medium">类型</span>
                <el-select v-model="form.type" class="mt-1 w-full">
                    <el-option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>
            </label>

            <label v-if="form.type === 3">
                <span class="text-sm font-medium">值</span>
                <el-select v-model="form.setting_value" class="mt-1 w-full">
                    <el-option label="是" value="1" />
                    <el-option label="否" value="0" />
                </el-select>
            </label>

            <div v-else-if="form.type === 5" class="sm:col-span-2">
                <div class="mb-3 flex gap-2">
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-1"
                        @click="addJsonField"
                    >
                        添加列
                    </button>
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-3 py-1"
                        :disabled="jsonFields.length === 0"
                        @click="addJsonRow"
                    >
                        添加行
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead>
                        <tr>
                            <th
                                v-for="(field, columnIndex) in jsonFields"
                                :key="columnIndex"
                                class="p-2"
                            >
                                <input
                                    v-model="field.title"
                                    maxlength="150"
                                    aria-label="列标题"
                                    placeholder="列标题"
                                    class="w-full rounded border border-slate-300 px-2 py-1"
                                >
                                <input
                                    v-model="field.key"
                                    maxlength="100"
                                    aria-label="字段 key"
                                    placeholder="字段 key"
                                    class="mt-1 w-full rounded border border-slate-300 px-2 py-1 font-mono"
                                >
                                <button
                                    type="button"
                                    class="mt-1 text-red-600"
                                    @click="removeJsonField(columnIndex)"
                                >
                                    删除列
                                </button>
                            </th>
                            <th class="w-20 p-2">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, rowIndex) in jsonRows" :key="rowIndex">
                            <td
                                v-for="(field, columnIndex) in jsonFields"
                                :key="field.key || columnIndex"
                                class="p-2"
                            >
                                <input
                                    v-model="row[columnIndex]"
                                    :aria-label="field.title || field.key"
                                    class="w-full rounded border border-slate-300 px-2 py-1"
                                >
                            </td>
                            <td class="p-2">
                                <button
                                    type="button"
                                    class="text-red-600"
                                    @click="removeJsonRow(rowIndex)"
                                >
                                    删除行
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <label v-else class="sm:col-span-2">
                <span class="text-sm font-medium">值</span>
                <input
                    v-model="form.setting_value"
                    :type="form.type === 2 || form.type === 4 ? 'number' : 'text'"
                    :step="form.type === 4 ? 'any' : form.type === 2 ? '1' : undefined"
                    :maxlength="form.type === 1 ? 4096 : undefined"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                >
            </label>

            <div class="flex gap-2 sm:col-span-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ saving ? '保存中…' : '保存' }}
                </button>

                <button
                    type="button"
                    :disabled="saving"
                    class="rounded border border-slate-300 px-4 py-2 text-slate-700 disabled:opacity-50"
                    @click="cancelForm"
                >
                    取消
                </button>
            </div>
        </form>

        <p v-if="loading" class="text-slate-600">正在加载设置…</p>
        <p v-else-if="settings.length === 0" class="text-slate-600">暂无设置。</p>

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b text-slate-500">
                <tr>
                    <th class="px-3 py-3">设置键</th>
                    <th class="px-3 py-3">名称</th>
                    <th class="px-3 py-3">类型</th>
                    <th class="px-3 py-3">值</th>
                    <th class="px-3 py-3">操作</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="setting in settings"
                    :key="setting.id"
                    class="border-b last:border-0"
                >
                    <td class="break-all px-3 py-3">{{ setting.setting_key }}</td>
                    <td class="px-3 py-3">{{ setting.name }}</td>
                    <td class="px-3 py-3">
                        {{ typeOptions.find((option) => option.value === Number(setting.type))?.label }}
                    </td>
                    <td class="max-w-md break-all px-3 py-3 font-mono text-xs">
                        <button
                            v-if="Number(setting.type) === 5"
                            type="button"
                            class="rounded border border-slate-300 px-3 py-1 font-sans hover:bg-slate-50"
                            @click="viewJsonSetting(setting)"
                        >
                            查看表格
                        </button>
                        <span v-else>{{ formatValue(setting) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3">
                        <button
                            type="button"
                            class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50"
                            :disabled="loadingEditId === setting.id"
                            @click="startEditing(setting)"
                        >
                            {{ loadingEditId === setting.id ? '读取中…' : '编辑' }}
                        </button>

                        <el-popconfirm
                            title="确定删除这个设置吗？"
                            confirm-button-text="删除"
                            cancel-button-text="取消"
                            @confirm="deleteSetting(setting)"
                        >
                            <template #reference>
                                <el-button
                                    class="ml-2"
                                    size="small"
                                    type="danger"
                                    :loading="deletingId === setting.id"
                                >
                                    删除
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </td>
                </tr>
                </tbody>
            </table>

            <el-pagination
                v-if="total > 0"
                class="mt-4 justify-end"
                :current-page="currentPage"
                :page-size="perPage"
                :page-sizes="[10, 15, 30, 50]"
                :total="total"
                layout="total, sizes, prev, pager, next, jumper"
                @current-change="loadSettings"
                @size-change="(size) => { perPage = size; loadSettings(1); }"
            />
        </div>

        <el-dialog
            v-model="showJsonDialog"
            :title="viewingSetting?.name ?? '设置数据'"
            width="90%"
        >
            <div class="overflow-x-auto">
                <el-table :data="viewingSetting?.json_value?.data ?? []" border>
                    <el-table-column
                        v-for="field in viewingSetting?.json_value?.fields ?? []"
                        :key="field.key"
                        :prop="field.key"
                        :label="field.title"
                        min-width="140"
                    />
                </el-table>
            </div>
        </el-dialog>

    </section>
</template>
