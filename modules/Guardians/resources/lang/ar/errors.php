<?php

declare(strict_types=1);

/*
| رسائل خرق قواعد العمل في موديول Guardians.
| تُستهلك عبر Shared\Support\BusinessRuleViolation::make().
*/

return [
    'student_not_in_organization' => 'الطالب المحدد غير موجود داخل المؤسسة الحالية.',
    'existing_account_not_found' => 'الحساب المحدد غير موجود داخل المؤسسة الحالية.',
    'profile_already_exists' => 'هذا الحساب لديه ملف وصي بالفعل، ولا يمكن إنشاء ملف ثانٍ.',
    'nothing_to_update' => 'لا توجد حقول صالحة للتحديث في هذا الطلب.',
    'link_already_exists' => 'هذا الوصي مرتبط بهذا الطالب بالفعل.',
    'max_links_per_student_reached' => 'وصل الطالب إلى الحد الأقصى من الأوصياء (:max).',
    'max_students_per_guardian_reached' => 'وصل الوصي إلى الحد الأقصى من الطلاب المرتبطين به (:max).',
    'link_already_verified' => 'رابط الوصي موثّق بالفعل ولا يحتاج توثيقًا جديدًا.',
    'reason_required' => 'ربط أو فكّ رابط وصي يتطلب سببًا مكتوبًا.',
    'guardian_not_found' => 'ملف الوصي المطلوب غير موجود داخل المؤسسة الحالية.',
];
