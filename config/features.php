<?php

declare(strict_types=1);

/**
 * مفاتيح الميزات.
 *
 * الفرق بينها وبين config/modules.php:
 *  - modules.php  : هل الكود يُحمَّل أصلًا؟ (قرار معماري)
 *  - features.php : هل الميزة ظاهرة للمستخدم الآن؟ (قرار تشغيلي)
 *
 * تُقرأ عبر Feature::active('student_billing') ويمكن تجاوزها لكل مؤسسة
 * من جدول إعدادات المؤسسة.
 */
return [

    // ── المال ────────────────────────────────────────────────────────────
    /*
     * ADR-017: المنصة **تحسب وتعرض** أجر المعلم، ولا **تدفع**.
     *
     * المفعَّل: دفتر payroll_entries، والمنح والجوائز عبر payroll_adjustments،
     * وكشف الفترة. المؤجَّل: فوترة الطلاب وبوابات الدفع وتحويل المستحقات.
     * الفصل مقصود — تشغيل الاحتساب لا يفتح أي مسار مال حقيقي.
     */
    'payroll' => env('FEATURE_PAYROLL', true),
    // الطالب لا يدفع حاليًا. تُفعَّل مع خطة تنمية الموارد.
    'student_billing' => env('FEATURE_STUDENT_BILLING', false),
    'coupons' => env('FEATURE_COUPONS', false),
    'subscriptions' => env('FEATURE_SUBSCRIPTIONS', false),
    // صرف مستحقات المعلمين من خلال المنصة — مرحلة لاحقة.
    'teacher_payouts' => env('FEATURE_TEACHER_PAYOUTS', false),

    // ── التعلّم ──────────────────────────────────────────────────────────
    'assignments' => env('FEATURE_ASSIGNMENTS', true),
    'assessments' => env('FEATURE_ASSESSMENTS', false),
    'certificates' => env('FEATURE_CERTIFICATES', false),
    'badges' => env('FEATURE_BADGES', false),
    // الشهادات التلقائية مرتبطة بالدورات المسجَّلة — غير متاحة الآن.
    'auto_certificates' => env('FEATURE_AUTO_CERTIFICATES', false),

    // ── التواصل ─────────────────────────────────────────────────────────
    'messaging' => env('FEATURE_MESSAGING', true),
    'class_wall' => env('FEATURE_CLASS_WALL', true),
    'parent_portal' => env('FEATURE_PARENT_PORTAL', true),
    'whatsapp' => env('WHATSAPP_ENABLED', false),

    // ── التشغيل ─────────────────────────────────────────────────────────
    // زر الإلغاء مطفأ بقرار العميل — المتاح حاليًا هو التأجيل فقط.
    'student_cancellation' => env('FEATURE_STUDENT_CANCELLATION', false),
    'student_self_scheduling' => env('FEATURE_STUDENT_SELF_SCHEDULING', false),
    'voluntary_freeze' => env('FEATURE_VOLUNTARY_FREEZE', true),
    'webinars' => env('FEATURE_WEBINARS', true),
    'substitute_teachers' => env('FEATURE_SUBSTITUTE_TEACHERS', true),

    // ── المنصة ──────────────────────────────────────────────────────────
    'multi_organization' => env('FEATURE_MULTI_ORG', false),
    'mobile_app_api' => env('FEATURE_MOBILE_API', false),
];
