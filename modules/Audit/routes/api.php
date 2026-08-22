<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مسارات موديول Audit — الـ API
|--------------------------------------------------------------------------
| يُحمَّل هذا الملف تلقائيًا من ModuleRegistry::loadRoutes() ضمن مجموعة
| middleware «api» وبالبادئة api/ عند إقلاع التطبيق.
*/

use Illuminate\Support\Facades\Route;
use Modules\Audit\Presentation\Http\Controllers\ListAuditEntriesController;
use Modules\Audit\Presentation\Http\Controllers\StoreAuditEntryController;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('audit-entries', ListAuditEntriesController::class)
        ->name('audit.entries.index');

    Route::post('audit-entries', StoreAuditEntryController::class)
        ->name('audit.entries.store');
});
