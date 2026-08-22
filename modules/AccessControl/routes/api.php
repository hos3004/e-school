<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AccessControl\Presentation\Http\Controllers\AssignRoleController;
use Modules\AccessControl\Presentation\Http\Controllers\DeletePermissionController;
use Modules\AccessControl\Presentation\Http\Controllers\DeleteRoleController;
use Modules\AccessControl\Presentation\Http\Controllers\GrantModelPermissionController;
use Modules\AccessControl\Presentation\Http\Controllers\ListPermissionsController;
use Modules\AccessControl\Presentation\Http\Controllers\ListRolesController;
use Modules\AccessControl\Presentation\Http\Controllers\RevokeModelPermissionController;
use Modules\AccessControl\Presentation\Http\Controllers\RevokeRoleController;
use Modules\AccessControl\Presentation\Http\Controllers\StorePermissionController;
use Modules\AccessControl\Presentation\Http\Controllers\StoreRoleController;
use Modules\AccessControl\Presentation\Http\Controllers\SyncRolePermissionsController;
use Modules\AccessControl\Presentation\Http\Controllers\UpdatePermissionController;
use Modules\AccessControl\Presentation\Http\Controllers\UpdateRoleController;

/*
|--------------------------------------------------------------------------
| مسارات موديول AccessControl — API
|--------------------------------------------------------------------------
| تُحمَّل تلقائيًا من ModuleRegistry::loadRoutes() تحت البادئة «api».
*/

Route::middleware('auth')->prefix('access-control')->group(function (): void {
    Route::get('roles', ListRolesController::class)->name('accesscontrol.roles.index');
    Route::post('roles', StoreRoleController::class)->name('accesscontrol.roles.store');
    Route::put('roles/{roleId}', UpdateRoleController::class)->name('accesscontrol.roles.update');
    Route::delete('roles/{roleId}', DeleteRoleController::class)->name('accesscontrol.roles.destroy');
    Route::put('roles/{roleId}/permissions', SyncRolePermissionsController::class)->name('accesscontrol.roles.permissions.sync');

    Route::get('permissions', ListPermissionsController::class)->name('accesscontrol.permissions.index');
    Route::post('permissions', StorePermissionController::class)->name('accesscontrol.permissions.store');
    Route::put('permissions/{permissionId}', UpdatePermissionController::class)->name('accesscontrol.permissions.update');
    Route::delete('permissions/{permissionId}', DeletePermissionController::class)->name('accesscontrol.permissions.destroy');

    Route::post('assignments/roles', AssignRoleController::class)->name('accesscontrol.assignments.roles.store');
    Route::delete('assignments/roles', RevokeRoleController::class)->name('accesscontrol.assignments.roles.destroy');

    Route::post('assignments/permissions', GrantModelPermissionController::class)->name('accesscontrol.assignments.permissions.store');
    Route::delete('assignments/permissions', RevokeModelPermissionController::class)->name('accesscontrol.assignments.permissions.destroy');
});
