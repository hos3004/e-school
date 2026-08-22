<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Identity.
| تُستهلك عبر __('identity::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'email_taken' => 'هذا البريد الإلكتروني مسجّل بالفعل.',
    'username_taken' => 'اسم المستخدم محجوز لمستخدم آخر.',

    'status_reason_required' => 'تغيير حالة الحساب يتطلب سببًا مكتوبًا.',
    'self_status_change' => 'لا يمكنك تغيير حالة حسابك بنفسك.',
    'invalid_status_transition' => 'لا يمكن الانتقال من حالة «:from» إلى «:to».',

    'current_password_wrong' => 'كلمة المرور الحالية غير صحيحة.',
    'password_unchanged' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.',

    'reset_token_invalid' => 'رابط إعادة التعيين غير صالح أو استُخدم مسبقًا.',
    'reset_token_expired' => 'انتهت صلاحية رابط إعادة التعيين، اطلب رابطًا جديدًا.',

    'push_token_in_use' => 'رمز الإشعارات مسجّل على جهاز آخر نشط.',
    'device_already_revoked' => 'الجهاز مسحوب مسبقًا.',
];
