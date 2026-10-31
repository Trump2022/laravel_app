<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\EnsureAdminIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Web\SettingController as WebSettingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); // 获取当前 Sanctum 用户信息

Route::prefix('web')->name('web.')->group(function () {
    Route::get('site-settings', [WebSettingController::class, 'getWebSiteSettings'])
        ->name('settings');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login'); // 管理员登录,限制每分钟最多5次请求

    Route::middleware(['auth:admin', EnsureAdminIsActive::class])->group(function () {
        Route::patch('me/password', [AuthController::class, 'updateOwnPassword'])->name('me.password'); // 修改当前管理员密码
        Route::patch('me/profile', [AuthController::class, 'updateOwnProfile'])->name('me.profile'); //编辑我的资料
        Route::post('logout', [AuthController::class, 'logout'])->name('logout'); // 管理员退出登录
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh'); // 刷新 JWT Token
        Route::get('me', [AuthController::class, 'me'])->name('me'); // 获取当前管理员信息及能力
        Route::get('menu/tree', [PermissionController::class, 'menuTree'])->name('menu.tree'); // 获取当前管理员可见的菜单树

        Route::middleware(CheckAdminPermission::class)->group(function () {
            Route::get('dashboard/index', function () {
                return response()->json([
                    'message' => '后台仪表盘',
                ]);
            })->name('dashboard.index'); // 获取后台仪表盘数据
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index'); // 获取权限列表
            Route::get('roles/{role}/permissions', [PermissionController::class, 'rolePermissions'])->name('roles.permissions.index'); // 获取角色的权限及可管理权限
            Route::put('roles/{role}/permissions/{permission}', [RoleController::class, 'assignPermission'])->name('roles.permissions.assign'); // 为角色分配权限
            Route::delete('roles/{role}/permissions/{permission}', [RoleController::class, 'revokePermission'])->name('roles.permissions.revoke'); // 移除角色的权限
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index'); // 获取角色列表
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store'); // 创建角色
            Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update'); // 更新角色信息
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy'); // 删除角色

            Route::get('admins', [AdminController::class, 'index'])->name('admins.index'); // 获取管理员列表
            Route::post('admins', [AdminController::class, 'store'])->name('admins.store'); // 创建管理员
            Route::patch('admins/{admin}', [AdminController::class, 'update'])->name('admins.update'); // 更新管理员信息
            Route::patch('admins/{admin}/status', [AdminController::class, 'updateStatus'])->name('admins.status'); // 启用或停用管理员
            Route::patch('admins/{admin}/password', [AdminController::class, 'updatePassword'])->name('admins.password'); // 重置管理员密码
            Route::delete('admins/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy'); // 软删除管理员
            Route::put('admins/{admin}/roles/{role}', [AdminController::class, 'assignRole'])->name('admins.roles.assign'); // 为管理员分配角色
            Route::delete('admins/{admin}/roles/{role}', [AdminController::class, 'revokeRole'])->name('admins.roles.revoke'); // 移除管理员角色
            Route::get('admins/{admin}/role-options', [AdminController::class, 'roleOptions'])->name('admins.role-options'); // 获取管理员可分配及已拥有的角色

            Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store'); // 创建权限
            Route::get('permissions/tree', [PermissionController::class, 'tree'])->name('permissions.tree'); // 获取权限树及可选父级
            Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update'); // 更新权限
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy'); // 删除权限
            Route::patch('permissions/{permission}/move', [PermissionController::class, 'move'])->name('permissions.move'); // 调整权限节点顺序

            // 设置
            Route::get('settings', [SettingController::class, 'index'])->name('settings.index'); // 获取设置列表
            Route::post('settings', [SettingController::class, 'store'])->name('settings.store');   // 创建设置
            Route::get('settings/{setting}', [SettingController::class, 'show'])->name('settings.show'); // 获取单个设置项
            Route::put('settings/{setting}', [SettingController::class, 'update'])->name('settings.update'); // 更新设置
            Route::delete('settings/{setting}', [SettingController::class, 'destroy'])->name('settings.destroy'); // 删除设置
        });
    });
});
