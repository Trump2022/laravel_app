import * as icons from '@element-plus/icons-vue';

export const menuIconOptions = Object.entries(icons)
    .filter(([, component]) =>
        component && ['object', 'function'].includes(typeof component)
    )
    .map(([name, component]) => ({ name, component }))
    .sort((left, right) => left.name.localeCompare(right.name));

export const menuIconComponents = Object.fromEntries(
    menuIconOptions.map(({ name, component }) => [name, component]),
);
