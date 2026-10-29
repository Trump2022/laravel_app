<script setup>
defineProps({
    nodes: {
        type: Array,
        default: () => [],
    },
    availablePaths: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <template v-for="node in nodes" :key="node.id">
        <el-sub-menu
            v-if="Number(node.type) === 1"
            :index="`directory-${node.id}`" >
            <template #title>
                {{ node.name }}
            </template>

            <AdminMenuTree
                :nodes="node.children ?? []"
                :available-paths="availablePaths"
            />
        </el-sub-menu>

        <el-menu-item
            v-else-if="Number(node.type) === 2"
            :index="node.path || `page-${node.id}`"
            :disabled="!node.path || !availablePaths.includes(node.path)" >
            {{ node.name }}
        </el-menu-item>
    </template>
</template>
