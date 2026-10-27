<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\RoleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware(['auth:admin', EnsureAdminIsActive::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::middleware(CheckAdminPermission::class)->group(function () {
            Route::get('dashboard/index', function () {
                return response()->json([
                    'message' => '后台仪表盘',
                ]);
            })->name('dashboard.index');
            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
            Route::get('roles/{role}/permissions', [PermissionController::class, 'rolePermissions'])->name('roles.permissions.index');
            Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
            Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
            Route::patch('admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
            Route::patch('admins/{admin}/status', [AdminController::class, 'updateStatus'])->name('admins.status');
            Route::patch('admins/{admin}/password', [AdminController::class, 'updatePassword'])->name('admins.password');

            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
            Route::get('permissions/tree', [PermissionController::class, 'tree'])->name('permissions.tree');
            Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
        });

        Route::patch('me/password', [AuthController::class, 'updateOwnPassword'])->name('me.password');
    });
});

