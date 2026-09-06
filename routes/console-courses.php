<?php

declare(strict_types=1);

use App\Http\Controllers\Console\CourseSetupController;
use App\Http\Controllers\Console\GroupProfileController;
use App\Http\Controllers\Console\GroupSetupController;
use Illuminate\Support\Facades\Route;

Route::get('/courses', [CourseSetupController::class, 'index'])->middleware('can:course.manage')->name('courses.index');
Route::get('/courses/programs', [CourseSetupController::class, 'index'])->middleware('can:program.manage')->name('courses.programs');
Route::get('/groups', [CourseSetupController::class, 'index'])->middleware('can:group.view')->name('groups.index');
foreach (['programs' => 'program.manage', 'levels' => 'program.manage', 'items' => 'course.manage'] as $kind => $permission) {
    Route::post('/courses/'.$kind, [CourseSetupController::class, 'save'])->defaults('kind', $kind)
        ->middleware('can:'.$permission)->name('courses.'.$kind.'.store');
    Route::patch('/courses/'.$kind.'/{id}', [CourseSetupController::class, 'save'])->defaults('kind', $kind)->whereUlid('id')
        ->middleware('can:'.$permission)->name('courses.'.$kind.'.update');
}
Route::get('/groups/{group}', GroupProfileController::class)->whereUlid('group')->middleware('can:group.view')->name('groups.show');
Route::post('/groups', [GroupSetupController::class, 'save'])->middleware('can:group.manage')->name('groups.store');
Route::patch('/groups/{group}', [GroupSetupController::class, 'save'])->whereUlid('group')->middleware('can:group.manage')->name('groups.update');
Route::post('/groups/{group}/teachers', [GroupSetupController::class, 'assign'])->whereUlid('group')->middleware('can:group.manage')->name('groups.teachers');
Route::post('/groups/{group}/activate', [GroupSetupController::class, 'activate'])->whereUlid('group')->middleware('can:group.manage')->name('groups.activate');
