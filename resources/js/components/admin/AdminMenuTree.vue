<script setup>
import { menuIconComponents } from './menuIcons';

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
            :index="`directory-${node.id}`"
        >
            <template #title>
                <el-icon v-if="menuIconComponents[node.icon]">
                    <component :is="menuIconComponents[node.icon]" />
                </el-icon>
                <span>{{ node.name }}</span>
            </template>

            <AdminMenuTree
                :nodes="node.children ?? []"
                :available-paths="availablePaths"
            />
        </el-sub-menu>

        <el-menu-item
            v-else-if="Number(node.type) === 2"
            :index="node.path || `page-${node.id}`"
            :disabled="!node.path || !availablePaths.includes(node.path)"
        >

            <el-icon v-if="menuIconComponents[node.icon]">
                <component :is="menuIconComponents[node.icon]" />
            </el-icon>
            <span>{{ node.name }}</span>
        </el-menu-item>
    </template>
</template>
