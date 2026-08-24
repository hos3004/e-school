<?php

declare(strict_types=1);

/* رسائل التحقق من المدخلات — تُستهلك من FormRequests. */

return [

    'role_name_required' => 'اسم الدور مطلوب.',
    'permission_name_required' => 'اسم الصلاحية مطلوب.',
    'permission_name_format' => 'اسم الصلاحية يجب أن يبدأ بحرف صغير ويقتصر على أحرف لاتينية وأرقام ونقاط وشرطات.',
    'permissions_required' => 'قائمة الصلاحيات مطلوبة كمصفوفة (مصفوفة فارغة تعني إزالة الكل).',
    'role_required' => 'معرّف الدور مطلوب.',
    'model_type_required' => 'نوع النموذج المستهدف مطلوب.',
    'model_id_required' => 'معرّف النموذج المستهدف مطلوب.',
    'ulid_invalid' => 'المعرّف غير صالح؛ يجب أن يكون معرّف ULID مكوّنًا من 26 محرفًا.',
    'guard_invalid' => 'الحارس غير معروف؛ القيم المسموحة: web أو api.',
    'organization_managed_by_server' => 'تُحدد المؤسسة من الحساب المصادق عليه ولا يجوز إرسالها يدويًا.',
];
