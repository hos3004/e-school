<?php

declare(strict_types=1);

/*
| رسائل تحقق موديول Guardians.
*/

return [
    'identifier_size' => 'المعرّف يجب أن يكون معرّف ULID مكوّنًا من 26 حرفًا.',
    'organization_id_required' => 'معرّف المؤسسة مطلوب.',
    'user_id_required' => 'معرّف حساب المستخدم مطلوب.',
    'student_profile_id_required' => 'معرّف ملف الطالب مطلوب.',
    'national_id_last4_digits' => 'آخر أربعة أرقام من الرقم القومي يجب أن تكون أرقامًا فقط.',
    'occupation_max' => 'المهنة يجب ألا تتجاوز 120 حرفًا.',
    'contact_channel_invalid' => 'قناة التواصل المختارة غير معروفة.',
    'relationship_required' => 'صلة القرابة بالطالب مطلوبة.',
    'relationship_invalid' => 'صلة القرابة المختارة غير معروفة.',
    'visible_section_invalid' => 'أحد الأقسام المرئية المطلوبة غير مسموح به.',
    'reason_required' => 'سبب الإجراء مطلوب للتدقيق.',
    'reason_min' => 'سبب الإجراء قصير جدًا — اكتب سببًا واضحًا.',
];
