<script setup>
import { apiRequest } from './api';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { pageRouteNames } from './router';
import AdminLayout from './components/admin/AdminLayout.vue';
import OwnPasswordForm from './components/admin/OwnPasswordForm.vue';

const adminManagementRef = ref(null);

async function editOwnProfile() {
    showOwnPasswordForm.value = false;

    if (route.name !== pageRouteNames.admins) {
        await router.push({ name: pageRouteNames.admins });
    }

    await nextTick();
    await adminManagementRef.value?.editOwnProfile();
}

const email = ref('');
const password = ref('');
const admin = ref(null);
const errorMessage = ref('');
const loading = ref(false);
const successMessage = ref('');
const showOwnPasswordForm = ref(false);
let successMessageTimer = null;

const route = useRoute();
const router = useRouter();

const routableMenuPaths = new Set(
    Object.values(pageRouteNames).map(
        (routeName) => router.resolve({ name: routeName }).path,
    ),
);

function selectPage(path) {
    if (routableMenuPaths.has(path)) {
        router.push(path);
    }
}

function showSuccessMessage(message) {
    successMessage.value = message;

    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
    }

    successMessageTimer = setTimeout(() => {
        clearSuccessMessage();
        successMessageTimer = null;
    }, 3000);
}

function clearSuccessMessage() {
    successMessage.value = '';

    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
        successMessageTimer = null;
    }
}

onUnmounted(() => {
    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
    }
});

onMounted(async () => {
    try {
        admin.value = await apiRequest('/api/admin/me');
    } catch (error) {
        if (error.status !== 401) {
            errorMessage.value = error.message;
        }
    }
});

async function login() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const csrfResponse = await fetch('/sanctum/csrf-cookie', {
            credentials: 'same-origin',
        });

        if (!csrfResponse.ok) {
            errorMessage.value = '获取 CSRF Cookie 失败，请检查 Sanctum 路由配置。';
            return;
        }

        admin.value = await apiRequest('/api/admin/login', {
            method: 'POST',
            body: JSON.stringify({
                email: email.value,
                password: password.value,
            }),
        });

        password.value = '';
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function logout() {
    errorMessage.value = '';

    try {
        await apiRequest('/api/admin/logout', {
            method: 'POST',
        });

        admin.value = null;
    } catch (error) {
        errorMessage.value = error.message;
    }
}
</script>

<template>
    <main class="min-h-screen bg-slate-100 p-4 sm:p-6 text-slate-900">
        <section class="rounded-xl bg-white p-8 shadow">
            <template v-if="admin">
                <AdminLayout
                    :admin="admin"
                    :active-path="route.path"
                    @select-page="selectPage"
                    @change-password="showOwnPasswordForm = true"
                    @edit-profile="editOwnProfile"
                    @logout="logout" >

                    <OwnPasswordForm
                        v-if="showOwnPasswordForm"
                        @close="showOwnPasswordForm = false"
                        @success="showSuccessMessage"
                    />

                    <p
                        v-if="successMessage"
                        role="status"
                        class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-lg"
                    >
                        {{ successMessage }}
                    </p>

                    <RouterView v-slot="{ Component }">
                        <component
                            :is="Component"
                            ref="adminManagementRef"
                            v-bind="route.meta.withAdmin ? { admin } : {}"
                            @success="showSuccessMessage"
                        />
                    </RouterView>
                </AdminLayout>
            </template>

            <form v-else @submit.prevent="login">
                <h1 class="text-2xl font-bold">管理员登录</h1>

                <label class="mt-6 block">
                    <span class="text-sm font-medium">邮箱</span>
                    <input
                        v-model="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <label class="mt-4 block">
                    <span class="text-sm font-medium">密码</span>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    {{ errorMessage }}
                </p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="mt-6 w-full rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ loading ? '登录中…' : '登录' }}
                </button>
            </form>

        </section>
    </main>
</template>
