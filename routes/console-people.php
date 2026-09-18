<?php

declare(strict_types=1);

use App\Http\Controllers\Console\EnrollmentFreezeController;
use App\Http\Controllers\Console\GuardianLinkController;
use App\Http\Controllers\Console\PeopleController;
use App\Http\Controllers\Console\StudentLifecycleController;
use App\Http\Controllers\Console\StudentPlacementController;
use App\Http\Controllers\Console\StudentProgramController;
use App\Http\Controllers\Console\StudentTeacherController;
use App\Http\Controllers\Console\TeacherLifecycleController;
use App\Http\Controllers\Console\TeacherQualificationController;
use App\Http\Controllers\Console\TeacherRateController;
use Illuminate\Support\Facades\Route;

// Included inside the authenticated, enabled /manage group owned by the console shell.
foreach (['students', 'teachers', 'guardians'] as $kind) {
    $view = match ($kind) {
        'students' => 'student.view.any',
        'guardians' => 'guardian.view',
        default => 'staff.view.any',
    };
    $create = match ($kind) {
        'students' => 'student.create',
        'guardians' => 'guardian.link',
        default => 'staff.contract.update',
    };
    $update = match ($kind) {
        'students' => 'student.update',
        'guardians' => 'guardian.link',
        default => 'staff.contract.update',
    };

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

/*
 * إضافة الطالب لدورة أخرى ونقله بين المجموعات والمعلمين. الكورس هو نقطة
 * الاختيار ومنه يُستنبط البرنامج، والمجموعة تُتحقق على الخادم لا بالشكل.
 */
Route::middleware(['can:enrollment.create', 'can:group.manage'])->group(function (): void {
    Route::get('students/{profile}/placement-options', [StudentPlacementController::class, 'options'])
        ->whereUlid('profile')->name('students.placement-options');
    Route::post('students/{profile}/placements', [StudentPlacementController::class, 'store'])
        ->whereUlid('profile')->name('students.placements');
    Route::post('students/{profile}/transfer', [StudentPlacementController::class, 'transferStudent'])
        ->whereUlid('profile')->name('students.transfer');
});

/*
 * تغيير معلم الكورس الفردي: يمر على تعديل الجدول المعتمد فتُعاد الحصص
 * المستقبلية بالمعلم الجديد، ولا يُستخدم مسار المعلم البديل للحصة الواحدة.
 */
Route::middleware('can:schedule.manage')->group(function (): void {
    Route::get('students/{profile}/teacher-options', [StudentTeacherController::class, 'options'])
        ->whereUlid('profile')->name('students.teacher-options');
    Route::post('students/{profile}/teacher', [StudentTeacherController::class, 'store'])
        ->whereUlid('profile')->name('students.teacher.assign');
    Route::put('students/{profile}/teacher', [StudentTeacherController::class, 'update'])
        ->whereUlid('profile')->name('students.teacher');
    Route::delete('students/{profile}/teacher', [StudentTeacherController::class, 'destroy'])
        ->whereUlid('profile')->name('students.teacher.remove');
});

/*
 * اعتماد كورسات المعلم وسحبها من صفحة ملفه. التأهيل هو ما يُظهر المعلم
 * في قوائم الإسناد، وكان يُدخَل عند إنشاء الملف وحده فلا يلحق كورسًا يُضاف بعده.
 */
Route::middleware('can:staff.contract.update')->group(function (): void {
    Route::post('teachers/{profile}/qualifications', [TeacherQualificationController::class, 'store'])
        ->whereUlid('profile')->name('teachers.qualifications.store');
    Route::delete('teachers/{profile}/qualifications', [TeacherQualificationController::class, 'destroy'])
        ->whereUlid('profile')->name('teachers.qualifications.destroy');

    /*
     * سعر حصة المعلم: يُسجَّل من تاريخ سريان ويُقفل السابق عنده. لا تعديل
     * لسعر قائم ولا حذف، فقيود الحصص الماضية تبقى بسعرها وقت الحصة.
     */
    Route::post('teachers/{profile}/rates', [TeacherRateController::class, 'store'])
        ->whereUlid('profile')->name('teachers.rates.store');
});

/* قيد الطالب في برنامج إضافي — لا يحتاج مجموعة؛ المعلم يُسنَد بعده. */
Route::post('students/{profile}/programs', [StudentProgramController::class, 'store'])
    ->middleware('can:enrollment.create')->whereUlid('profile')->name('students.programs');

/*
 * ربط طالب بحساب ولي أمر قائم وفكّه، من صفحة ملف الوصي. لا حذف: فك الرابط
 * تعليق (SoftDeletes) يحتفظ بالسجل.
 */
Route::middleware('can:guardian.link')->group(function (): void {
    Route::post('guardians/{profile}/links', [GuardianLinkController::class, 'store'])
        ->whereUlid('profile')->name('guardians.links.store');
    Route::delete('guardians/{profile}/links/{link}', [GuardianLinkController::class, 'destroy'])
        ->whereUlid('profile')->whereUlid('link')->name('guardians.links.destroy');
});
