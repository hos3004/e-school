<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحدد لغة الواجهة لكل طلب بالترتيب:
 * 1. تفضيل المستخدم المسجَّل
 * 2. الجلسة (اختيار الزائر)
 * 3. لغة المؤسسة الافتراضية
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['ar']);

        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (in_array($locale, $supported, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
