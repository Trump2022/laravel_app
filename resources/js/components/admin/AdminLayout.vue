<script setup>
import { computed, ref, watch } from 'vue';
import { Expand, Fold, Menu as MenuIcon } from '@element-plus/icons-vue';
import { apiRequest } from '../../api';
import router, { pageRouteNames } from '../../router';
import AdminMenuTree from './AdminMenuTree.vue';

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
    activePath: {
        type: String,
        required: true,
    },
});

const isCollapsed = ref(false);

const menuTree = ref([]);
const menuTreeLoading = ref(false);
const menuTreeError = ref('');

const availablePagePaths = Object.values(pageRouteNames).map(
    (routeName) => router.resolve({ name: routeName }).path,
);

function findPageTitle(nodes, path) {
    for (const node of nodes) {
        if (Number(node.type) === 2 && node.path === path) {
            return node.name;
        }

        const childTitle = findPageTitle(node.children ?? [], path);

        if (childTitle) {
            return childTitle;
        }
    }

    return '后台首页';
}

const activePageTitle = computed(() =>
    findPageTitle(menuTree.value, props.activePath),
);

async function loadMenuTree() {
    menuTreeLoading.value = true;
    menuTreeError.value = '';

    try {
        const response = await apiRequest('/api/admin/menu/tree');
        menuTree.value = response.data;
    } catch (error) {
        menuTreeError.value = error.message;
    } finally {
        menuTreeLoading.value = false;
    }
}

watch(
    () => props.admin.id,
    (adminId) => {
        if (!adminId) {
            menuTree.value = [];
            return;
        }

        loadMenuTree();
    },
    { immediate: true },
);

const emit = defineEmits([
    'select-page',
    'change-password',
    'edit-profile',
    'logout',
]);

function handleAccountCommand(command) {
    if (command === 'change-password') {
        emit('change-password');
    }

    if (command === 'edit-profile') {
        emit('edit-profile');
    }

    if (command === 'logout') {
        emit('logout');
    }
}
</script>

<template>
    <el-container class="min-h-screen bg-slate-100">
        <el-aside
            :width="isCollapsed ? '64px' : '220px'"
            class="border-r border-slate-200 bg-white transition-all duration-200">
            <div class="flex h-16 items-center justify-center border-b border-slate-200">
                <strong v-if="!isCollapsed" class="text-lg text-slate-800">
                    Laravel后台
                </strong>
                <el-icon v-else :size="22" class="text-blue-600">
                    <MenuIcon />
                </el-icon>
            </div>

            <el-menu
                :default-active="props.activePath"
                :collapse="isCollapsed"
                :collapse-transition="false"
                class="border-r-0"
                @select="emit('select-page', $event)">
                <AdminMenuTree
                    :nodes="menuTree"
                    :available-paths="availablePagePaths"
                />
            </el-menu>

            <p v-if="menuTreeError" class="p-3 text-sm text-red-600">
                菜单加载失败：{{ menuTreeError }}
            </p>
            <p v-else-if="menuTreeLoading" class="p-3 text-sm text-slate-500">
                正在加载菜单…
            </p>
        </el-aside>

        <el-container>
            <el-header
                class="flex items-center justify-between border-b border-slate-200 bg-white">
                <div class="flex min-w-0 items-center gap-4">
                    <el-button
                        text
                        :icon="isCollapsed ? Expand : Fold"
                        aria-label="展开或收起侧边栏"
                        @click="isCollapsed = !isCollapsed"
                    />

                    <el-breadcrumb separator="/">
                        <el-breadcrumb-item>后台</el-breadcrumb-item>
                        <el-breadcrumb-item>{{ activePageTitle }}</el-breadcrumb-item>
                    </el-breadcrumb>
                </div>

                <el-dropdown @command="handleAccountCommand">
                    <button
                        type="button"
                        class="ml-4 flex max-w-48 items-center gap-2 truncate text-sm text-slate-700"
                        >
                        <span class="truncate">{{ props.admin.name }}</span>
                        <span aria-hidden="true">▾</span>
                    </button>

                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item
                                v-if="props.admin.can_update_admins"
                                command="edit-profile">
                                编辑我的资料
                            </el-dropdown-item>

                            <el-dropdown-item command="change-password">
                                修改我的密码
                            </el-dropdown-item>
                            <el-dropdown-item command="logout" divided>
                                退出登录
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </el-header>

            <el-main class="min-w-0">
                <div class="w-full">
                    <slot />
                </div>
            </el-main>
        </el-container>
    </el-container>
</template>
