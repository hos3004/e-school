<?php

declare(strict_types=1);

return [
    'password_confirmation' => 'تأكيد كلمة المرور',
    'role' => 'الدور التشغيلي',
    'role_scope' => 'نطاق الدور',
    'creation_reason_help' => 'الحساب الإداري لا يُنشأ بلا دور واضح وسبب مكتوب. استخدم معالجات الطالب والمعلم وولي الأمر لحساباتهم.',
    'overview' => 'ملخص الحساب',
    'hub' => 'مركز الحساب',
    'roles_tab' => 'الأدوار والصلاحيات',
    'devices_tab' => 'الأجهزة والجلسات',
    'change_status' => 'تغيير حالة الحساب',
    'status_changed' => 'تم تحديث حالة الحساب وتسجيل السبب.',
    'global_role' => 'دور نظامي عام',
    'organization_role' => 'دور خاص بالمؤسسة',
    'device_active' => 'نشط',
    'device_revoked' => 'مُلغى',
    'last_used_at' => 'آخر استخدام',
    'last_login_ip' => 'عنوان آخر دخول',
    'empty' => 'لا توجد بيانات في هذا القسم بعد.',
    'optional_access' => 'الصلاحيات الاختيارية',
    'optional_access_manage' => 'تعديل الصلاحيات الاختيارية',
    'optional_access_help' => 'صلاحيات حساسة تُمنح للحساب وحده لا لدوره. المشرف مثلًا يقرأ ويصدّر دائمًا، ولا يرى المال أو بيانات التواصل أو التسجيلات إلا بتشغيل مفتاحها هنا.',
    'optional_access_saved' => 'تم تحديث الصلاحيات الاختيارية وتسجيل السبب في التدقيق.',
    'optional_access_unchanged' => 'لم تتغير أي صلاحية؛ لم يُسجَّل شيء.',
    'optional_access_granted' => 'مُفعّلة',
    'optional_access_withheld' => 'محجوبة',
    'optional_access_permission' => 'الصلاحية',
    'optional_access_state' => 'الحالة',
    /*
     * المفاتيح هنا اسم الصلاحية بنقاطها مستبدلة بشرطات سفلية، لأن مترجم لارافيل
     * يفسّر النقطة كتفرّع. الاسم الخام يظهر كما هو عند غياب ترجمته.
     */
    'optional_access_labels' => [
        'payroll_view' => 'الجانب المالي ومستحقات المعلمين',
        'contact_pii_view' => 'بيانات التواصل الشخصية (هاتف وبريد)',
        'recording_view' => 'تسجيلات الحصص',
    ],
    'roles' => [
        'platform_admin' => 'مدير المنصة',
        'academic_supervisor' => 'مشرف أكاديمي',
        'finance_supervisor' => 'مشرف مالي',
        'registrar' => 'مسؤول تسجيل',
        'communications_officer' => 'مسؤول تواصل',
        'teacher' => 'معلم',
        'student' => 'طالب',
        'guardian' => 'ولي أمر',
        'supervisor' => 'مشرف الجودة والمتابعة',
        'auditor' => 'مراجع',
    ],
];
