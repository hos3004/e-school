<?php

declare(strict_types=1);

/*
| رسائل تحقق موديول Identity — identity::validation.*
*/

return [
    'organization_id_required' => 'المؤسسة مطلوبة.',
    'name_required' => 'الاسم مطلوب.',
    'name_too_long' => 'الاسم طويل جدًا (الحد 191 حرفًا).',
    'email_required' => 'البريد الإلكتروني مطلوب.',
    'email_invalid' => 'صيغة البريد الإلكتروني غير صحيحة.',
    'password_required' => 'كلمة المرور مطلوبة.',
    'current_password_required' => 'أدخل كلمة المرور الحالية للمتابعة.',

    'locale_invalid' => 'اللغة غير مدعومة.',
    'timezone_invalid' => 'المنطقة الزمنية غير صحيحة.',

    'status_required' => 'الحالة الجديدة مطلوبة.',
    'status_invalid' => 'قيمة الحالة غير معروفة.',
    'reason_too_short' => 'السبب قصير جدًا — اكتب سببًا واضحًا (5 أحرف على الأقل).',

    'reset_token_required' => 'رمز إعادة التعيين مطلوب.',

    'device_name_too_long' => 'اسم الجهاز طويل جدًا.',
    'push_token_too_long' => 'رمز الإشعارات طويل جدًا.',
];
