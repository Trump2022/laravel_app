<script setup>
import { computed, ref } from 'vue';
import { Expand, Fold, Key, Menu as MenuIcon, User } from '@element-plus/icons-vue';

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
    activePage: {
        type: String,
        required: true,
    },
});

const emit = defineEmits([
    'select-page',
    'change-password',
    'logout',
]);

const isCollapsed = ref(false);

const pageTitles = {
    admins: '管理员管理',
    roles: '角色管理',
    permissions: '菜单管理',
};

const activePageTitle = computed(
    () => pageTitles[props.activePage] ?? '后台首页',
);

function handleAccountCommand(command) {
    if (command === 'change-password') {
        emit('change-password');
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
            class="border-r border-slate-200 bg-white transition-all duration-200"
        >
            <div class="flex h-16 items-center justify-center border-b border-slate-200">
                <strong v-if="!isCollapsed" class="text-lg text-slate-800">
                    Laravel 学习后台
                </strong>
                <el-icon v-else :size="22" class="text-blue-600">
                    <MenuIcon />
                </el-icon>
            </div>

            <el-menu
                :default-active="activePage"
                :collapse="isCollapsed"
                :collapse-transition="false"
                class="border-r-0"
                @select="emit('select-page', $event)"
            >
                <el-menu-item index="admins">
                    <el-icon><User /></el-icon>
                    <template #title>管理员管理</template>
                </el-menu-item>

                <el-menu-item index="roles">
                    <el-icon><Key /></el-icon>
                    <template #title>角色管理</template>
                </el-menu-item>

                <el-menu-item index="permissions">
                    <el-icon><MenuIcon /></el-icon>
                    <template #title>菜单管理</template>
                </el-menu-item>
            </el-menu>
        </el-aside>

        <el-container>
            <el-header
                class="flex items-center justify-between border-b border-slate-200 bg-white"
            >
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
                <slot />
            </el-main>
        </el-container>
    </el-container>
</template>
