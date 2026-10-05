<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * وجهة الرجوع (logoutURL) اللي BBB يحوّل إليها المتصفح الداخلي (WebView) في
 * تطبيق الموبايل بعد مغادرة الفصل. التطبيق يعترض هذا الرابط بالذات ويقفل
 * شاشة الفصل تلقائيًا قبل ما يوصله؛ هذا الرد شبكة أمان فقط لو فشل الاعتراض.
 */
final class MobileClassroomReturnController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            <<<'HTML'
            <!doctype html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>الحصة انتهت</title>
                <style>
                    body { font-family: system-ui, sans-serif; text-align: center; padding: 3rem 1rem; color: #1f2937; }
                </style>
            </head>
            <body>
                <p>انتهت الحصة. تقدر تقفل هذه الصفحة وترجع للتطبيق.</p>
            </body>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
