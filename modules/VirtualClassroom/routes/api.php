<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\VirtualClassroom\Presentation\Http\Controllers\ClassroomWebhookController;

/*
| يستقبل هذا المسار POST موقّعًا من BBB؛ لا يستخدم مصادقة مستخدم التطبيق.
| يجب أن يساوي BBB_WEBHOOK_CALLBACK_URL هذا العنوان المسجّل عند المزوّد.
*/
Route::post('webhooks/classroom', ClassroomWebhookController::class)
    ->middleware('throttle:classroom-webhook')
    ->name('classroom.webhook');
