<script setup>
import { computed, ref } from 'vue';
import { menuIconComponents, menuIconOptions } from './menuIcons';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const visible = ref(false);
const search = ref('');

const filteredIcons = computed(() => {
    const query = search.value.trim().toLowerCase();

    return menuIconOptions.filter((icon) =>
        icon.name.toLowerCase().includes(query),
    );
});

function selectIcon(name) {
    emit('update:modelValue', name);
    visible.value = false;
    search.value = '';
}
</script>

<template>
    <el-popover v-model:visible="visible" :width="660" trigger="click">
        <template #reference>
            <el-button class="w-full justify-between">
                <span class="flex min-w-0 items-center gap-2">
                    <el-icon v-if="menuIconComponents[props.modelValue]">
                        <component :is="menuIconComponents[props.modelValue]" />
                    </el-icon>
                    <span class="truncate">{{ props.modelValue || '选择图标' }}</span>
                </span>
                <span>选择</span>
            </el-button>
        </template>

        <el-input
            v-model="search"
            clearable
            placeholder="搜索图标名称"
        />

        <div class="mt-3 grid max-h-72 grid-cols-12 gap-1 overflow-y-auto">
            <el-tooltip
                v-for="icon in filteredIcons"
                :key="icon.name"
                :content="icon.name"
            >
                <button
                    type="button"
                    class="flex aspect-square items-center justify-center rounded hover:bg-slate-100"
                    :aria-label="icon.name"
                    @click="selectIcon(icon.name)"
                >
                    <el-icon :size="20">
                        <component :is="icon.component" />
                    </el-icon>
                </button>
            </el-tooltip>
        </div>

        <p v-if="filteredIcons.length === 0" class="py-4 text-center text-sm text-slate-500">
            没有匹配的图标
        </p>

        <div v-if="props.modelValue" class="mt-3 border-t pt-3">
            <el-button text @click="selectIcon('')">清除图标</el-button>
        </div>
    </el-popover>
</template>
