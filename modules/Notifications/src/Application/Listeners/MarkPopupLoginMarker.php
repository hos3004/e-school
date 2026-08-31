<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * توليد علامة جلسة دخول جديدة عند كل تسجيل دخول ناجح.
 * تُستخدم لقاعدة OncePerLogin — تُخزَّن في الجلسة الآمنة لا في المتصفح،
 * فلا يمكن للمستخدم مسح LocalStorage لاسترجاع الظهور.
 */
final class MarkPopupLoginMarker
{
    public function handle(Login $event): void
    {
        if (session()->isStarted() || session()->start()) {
            session()->put('popup.login_marker', (string) Str::ulid());
        }
    }
}
