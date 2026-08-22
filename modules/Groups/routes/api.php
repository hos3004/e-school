<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Groups\Presentation\Http\Controllers\ActivateGroupController;
use Modules\Groups\Presentation\Http\Controllers\ArchiveGroupController;
use Modules\Groups\Presentation\Http\Controllers\AssignTeacherController;
use Modules\Groups\Presentation\Http\Controllers\AttachProgramController;
use Modules\Groups\Presentation\Http\Controllers\CompleteGroupController;
use Modules\Groups\Presentation\Http\Controllers\CreateGroupController;
use Modules\Groups\Presentation\Http\Controllers\DetachProgramController;
use Modules\Groups\Presentation\Http\Controllers\EnrollStudentController;
use Modules\Groups\Presentation\Http\Controllers\ListGroupsController;
use Modules\Groups\Presentation\Http\Controllers\ShowGroupController;
use Modules\Groups\Presentation\Http\Controllers\UnassignTeacherController;
use Modules\Groups\Presentation\Http\Controllers\UpdateGroupController;
use Modules\Groups\Presentation\Http\Controllers\WithdrawStudentController;

/*
|--------------------------------------------------------------------------
| مسارات موديول Groups — الـ API
|--------------------------------------------------------------------------
|
| تُحمَّل تلقائيًا ضمن مجموعة «api» بالبادئة api/. كل مسار كتابة يمر
| بسياسة عبر FormRequest، وكل مسار قراءة يفحص الصلاحية داخل المتحكم.
*/

Route::get('/groups', ListGroupsController::class)->name('groups.index');
Route::post('/groups', CreateGroupController::class)->name('groups.store');

Route::get('/groups/{group}', ShowGroupController::class)
    ->whereUlid('group')
    ->name('groups.show');

Route::patch('/groups/{group}', UpdateGroupController::class)
    ->whereUlid('group')
    ->name('groups.update');

Route::delete('/groups/{group}', ArchiveGroupController::class)
    ->whereUlid('group')
    ->name('groups.archive');

Route::post('/groups/{group}/activate', ActivateGroupController::class)
    ->whereUlid('group')
    ->name('groups.activate');

Route::post('/groups/{group}/complete', CompleteGroupController::class)
    ->whereUlid('group')
    ->name('groups.complete');

Route::post('/groups/{group}/students', EnrollStudentController::class)
    ->whereUlid('group')
    ->name('groups.students.enroll');

Route::post('/groups/{group}/teachers', AssignTeacherController::class)
    ->whereUlid('group')
    ->name('groups.teachers.assign');

Route::post('/groups/{group}/programs', AttachProgramController::class)
    ->whereUlid('group')
    ->name('groups.programs.attach');

Route::post('/group-memberships/{membership}/withdraw', WithdrawStudentController::class)
    ->whereUlid('membership')
    ->name('group-memberships.withdraw');

Route::delete('/group-teachers/{assignment}', UnassignTeacherController::class)
    ->whereUlid('assignment')
    ->name('group-teachers.unassign');

Route::delete('/group-programs/{link}', DetachProgramController::class)
    ->whereUlid('link')
    ->name('group-programs.detach');
