<script setup>
defineProps({
    admin: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section class="mt-6 rounded-xl bg-white p-8 shadow">
        <h1 class="text-2xl font-semibold text-slate-800">
            后台仪表盘
        </h1>

        <p class="mt-3 text-slate-600">
            欢迎回来，{{ admin.name }}。请从左侧菜单选择要管理的内容。
        </p>
    </section>
</template>
