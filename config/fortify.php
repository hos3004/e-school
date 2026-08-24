<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    // تدفق استعادة كلمة المرور عبر البريد يستخدم مزوّد users.
    'passwords' => 'users',

    /*
     * حقل معرّف الدخول في النماذج: اسم المستخدم أو البريد أو الهاتف.
     * المطابقة الفعلية تتم في App\Providers\AuthServiceProvider عبر
     * Fortify::authenticateUsing — لا فحص أسماء أدوار هنا.
     */
    'username' => 'login',

    // حقل البريد في مسار استعادة كلمة المرور.
    'email' => 'email',

    'lowercase_usernames' => false,

    // وجهة ما بعد الدخول: '/' يعيد التوجيه حسب الصلاحيات والملف الشخصي.
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    /*
     * ميزات Fortify المفعّلة في المرحلة الأولى: الاستعادة عبر البريد فقط.
     * - لا تسجيل ذاتي هنا: الحساب يُنشأ بعد قبول طلب التسجيل العام (Task 02).
     * - 2FA يُفعَّل قبل الإنتاج وفق docs/15-security-model.md.
     */
    'features' => [
        Features::resetPasswords(),
    ],
];
