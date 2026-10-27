<script setup>
import { ref } from 'vue';
import { apiRequest } from '../../api';

const emit = defineEmits(['close', 'success']);

const changingPassword = ref(false);
const errorMessage = ref('');
const form = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function clearForm() {
    form.value = {
        current_password: '',
        password: '',
        password_confirmation: '',
    };
}

function cancel() {
    clearForm();
    errorMessage.value = '';
    emit('close');
}

async function submit() {
    changingPassword.value = true;
    errorMessage.value = '';

    try {
        await apiRequest('/api/admin/me/password', {
            method: 'PATCH',
            body: JSON.stringify(form.value),
        });

        clearForm();
        emit('success', '自己的密码修改成功。');
        emit('close');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        changingPassword.value = false;
    }
}
</script>

<template>
    <form
        class="mx-auto mt-6 grid max-w-6xl gap-4 rounded-xl border border-blue-200 bg-blue-50 p-5 sm:grid-cols-3"
        @submit.prevent="submit"
    >
        <h2 class="font-semibold sm:col-span-3">修改我的密码</h2>

        <label>
            <span class="text-sm font-medium">当前密码</span>
            <input
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                required
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
            >
        </label>

        <label>
            <span class="text-sm font-medium">新密码（至少 8 位）</span>
            <input
                v-model="form.password"
                type="password"
                minlength="8"
                autocomplete="new-password"
                required
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
            >
        </label>

        <label>
            <span class="text-sm font-medium">确认新密码</span>
            <input
                v-model="form.password_confirmation"
                type="password"
                minlength="8"
                autocomplete="new-password"
                required
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
            >
        </label>

        <div class="flex flex-wrap items-center gap-3 sm:col-span-3">
            <p v-if="errorMessage" class="w-full text-sm text-red-600">
                {{ errorMessage }}
            </p>

            <button
                type="submit"
                :disabled="changingPassword"
                class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
            >
                {{ changingPassword ? '提交中…' : '确认修改密码' }}
            </button>

            <button
                type="button"
                :disabled="changingPassword"
                class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 disabled:opacity-50"
                @click="cancel"
            >
                取消
            </button>
        </div>
    </form>
</template>
