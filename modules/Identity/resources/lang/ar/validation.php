<?php

declare(strict_types=1);

/*
| رسائل تحقق موديول Identity — identity::validation.*
*/

return [
    'organization_id_required' => 'المؤسسة مطلوبة.',
    'organization_id_invalid' => 'معرّف المؤسسة غير صالح.',
    'name_required' => 'الاسم مطلوب.',
    'name_too_long' => 'الاسم طويل جدًا (الحد 191 حرفًا).',
    'email_required' => 'البريد الإلكتروني مطلوب.',
    'email_invalid' => 'صيغة البريد الإلكتروني غير صحيحة.',
    'contact_required' => 'أدخل بريدًا إلكترونيًا أو رقم هاتف.',
    'phone_required' => 'رقم الهاتف مطلوب.',
    'phone_invalid' => 'أدخل رقم الهاتف بالصيغة الدولية E.164.',
    'username_required' => 'اسم المستخدم مطلوب.',
    'username_reserved' => 'اسم المستخدم هذا محجوز ولا يمكن استخدامه.',
    'password_required' => 'كلمة المرور مطلوبة.',
    'current_password_required' => 'أدخل كلمة المرور الحالية للمتابعة.',

    'locale_invalid' => 'اللغة غير مدعومة.',
    'timezone_invalid' => 'المنطقة الزمنية غير صحيحة.',

    'status_required' => 'الحالة الجديدة مطلوبة.',
    'status_invalid' => 'قيمة الحالة غير معروفة.',
    'reason_too_short' => 'السبب قصير جدًا — اكتب سببًا واضحًا (5 أحرف على الأقل).',

    'reset_token_required' => 'رمز إعادة التعيين مطلوب.',
    'otp_required' => 'رمز التحقق مطلوب.',
    'otp_invalid' => 'صيغة رمز التحقق غير صحيحة.',
    'password_confirmation_mismatch' => 'تأكيد كلمة المرور غير مطابق.',

    'device_name_too_long' => 'اسم الجهاز طويل جدًا.',
    'push_token_too_long' => 'رمز الإشعارات طويل جدًا.',
];
