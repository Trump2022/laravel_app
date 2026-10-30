import Dashboard from './components/admin/Dashboard.vue';
import { createRouter, createWebHistory } from 'vue-router';
import AdminManagement from './components/admin/AdminManagement.vue';
import RoleManagement from './components/admin/RoleManagement.vue';
import PermissionManagement from './components/admin/PermissionManagement.vue';

export const pageRouteNames = {
    dashboard: 'admin-dashboard',
    admins: 'admin-admins',
    roles: 'admin-roles',
    permissions: 'admin-permissions',
};

export default createRouter({
    history: createWebHistory('/manage/'),
    routes: [
        {
            path: '/',
            redirect: { name: pageRouteNames.dashboard },
        },
        {
            path: '/admin/dashboard',
            name: pageRouteNames.dashboard,
            component: Dashboard,
            meta: {
                page: 'dashboard',
                title: '后台仪表盘',
                withAdmin: true,
            },
        },
        {
            path: '/admin/admins',
            name: pageRouteNames.admins,
            component: AdminManagement,
            meta: { page: 'admins', title: '管理员管理', withAdmin: true },
        },
        {
            path: '/admin/roles',
            name: pageRouteNames.roles,
            component: RoleManagement,
            meta: { page: 'roles', title: '角色管理', withAdmin: true },
        },
        {
            path: '/admin/permissions',
            name: pageRouteNames.permissions,
            component: PermissionManagement,
            meta: {
                page: 'permissions',
                title: '菜单管理' ,
                withAdmin: true,
            },
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: pageRouteNames.admins },
        },
    ],
});
