<?php

declare(strict_types=1);

use App\Http\Controllers\Console\EnrollmentFreezeController;
use App\Http\Controllers\Console\PeopleController;
use App\Http\Controllers\Console\StudentLifecycleController;
use App\Http\Controllers\Console\TeacherLifecycleController;
use Illuminate\Support\Facades\Route;

// Included inside the authenticated, enabled /manage group owned by the console shell.
foreach (['students', 'teachers'] as $kind) {
    $view = $kind === 'students' ? 'student.view.any' : 'staff.view.any';
    $create = $kind === 'students' ? 'student.create' : 'staff.contract.update';
    $update = $kind === 'students' ? 'student.update' : 'staff.contract.update';

    Route::get($kind, [PeopleController::class, 'index'])->defaults('kind', $kind)
        ->middleware('can:'.$view)->name($kind.'.index');
    Route::get($kind.'/create', [PeopleController::class, 'create'])->defaults('kind', $kind)
        ->middleware('can:'.$create)->name($kind.'.create');
    Route::get($kind.'/form-options', [PeopleController::class, 'options'])->defaults('kind', $kind)
        ->middleware('can:'.$view)->name($kind.'.options');
    Route::get($kind.'/username-suggestions', [PeopleController::class, 'usernames'])->defaults('kind', $kind)
        ->middleware(['can:'.$create, 'throttle:60,1'])->name($kind.'.usernames');
    Route::post($kind, [PeopleController::class, 'store'])->defaults('kind', $kind)
        ->middleware('can:'.$create)->name($kind.'.store');
    Route::get($kind.'/{profile}/edit', [PeopleController::class, 'edit'])->defaults('kind', $kind)
        ->middleware('can:'.$update)->whereUlid('profile')->name($kind.'.edit');
    Route::put($kind.'/{profile}', [PeopleController::class, 'update'])->defaults('kind', $kind)
        ->middleware('can:'.$update)->whereUlid('profile')->name($kind.'.update');
    Route::get($kind.'/{profile}', [PeopleController::class, 'show'])->defaults('kind', $kind)
        ->middleware('can:'.$view)->whereUlid('profile')->name($kind.'.show');
}

/*
 * إجراءات دورة حياة الحساب من صفحة الملف. لا حذف نهائي: الإيقاف تعليق
 * تبقى معه البيانات والسجل، والتجميد يخص برنامج الطالب المحدد وحده.
 */
Route::put('students/{profile}/archive', [StudentLifecycleController::class, 'archive'])
    ->middleware('can:student.update')->whereUlid('profile')->name('students.archive');
Route::put('students/{profile}/restore', [StudentLifecycleController::class, 'restore'])
    ->middleware('can:student.update')->whereUlid('profile')->name('students.restore');
Route::put('teachers/{profile}/terminate', [TeacherLifecycleController::class, 'terminate'])
    ->middleware('can:staff.contract.update')->whereUlid('profile')->name('teachers.terminate');
Route::put('enrollments/{enrollment}/freeze', EnrollmentFreezeController::class)
    ->middleware('can:enrollment.freeze')->whereUlid('enrollment')->name('enrollments.freeze');
