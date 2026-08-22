<?php

declare(strict_types=1);

/* رسائل خرق قواعد العمل — تُستهلك عبر BusinessRuleViolation::make(). */

return [

    'role_name_taken' => 'اسم الدور «:name» مستخدم بالفعل في نفس النطاق.',
    'role_not_found' => 'الدور المطلوب غير موجود.',
    'role_system_locked' => 'الدور «:name» دور نظامي من المنصة ولا يجوز تعديله أو حذفه.',
    'role_in_use' => 'لا يمكن حذف الدور «:name» لأنه مسند إلى مستخدمين على الأقل.',
    'role_already_assigned' => 'هذا الدور مسند مسبقًا للنموذج المستهدف.',
    'role_not_assigned' => 'هذا الدور غير مسند للنموذج المستهدف أصلًا.',

    'permission_name_taken' => 'اسم الصلاحية «:name» مستخدم بالفعل لنفس الحارس.',
    'permission_not_found' => 'إحدى الصلاحيات المطلوبة غير موجودة.',
    'permission_in_use' => 'لا يمكن حذف الصلاحية «:name» لأنها مرتبطة بأدوار أو نماذج.',
    'permission_already_granted' => 'هذه الصلاحية منوحة مسبقًا للنموذج المستهدف.',
    'permission_not_granted' => 'هذه الصلاحية غير منوحة للنموذج المستهدف أصلًا.',

    'guard_mismatch' => 'الصلاحية «:name» لا تنتمي لحارس الدور.',
];
