<?php

declare(strict_types=1);

/*
| رسائل التحقق — academicreports::validation.*
*/

return [
    'session_id_required' => 'معرّف الحصة مطلوب.',
    'staff_profile_id_required' => 'معرّف المعلم مطلوب.',
    'student_profile_id_required' => 'معرّف الطالب مطلوب.',
    'enrollment_id_required' => 'معرّف التسجيل مطلوب.',
    'period_year_required' => 'سنة الفترة مطلوبة.',
    'period_month_invalid' => 'شهر الفترة يجب أن يكون بين 1 و 12.',
    'students_required' => 'تقييمات الطلاب مطلوبة.',
    'students_min' => 'يجب تقييم طالب واحد على الأقل في التقرير.',
    'students_distinct' => 'لا يجوز تقييم نفس الطالب أكثر من مرة.',
    'score_range' => 'كل تقييم يجب أن يكون عددًا صحيحًا من 1 إلى 5.',
    'reason_required' => 'سبب الاعتماد مطلوب لأغراض التدقيق.',
    'reason_min' => 'سبب الاعتماد قصير جدًا — اكتب سببًا واضحًا.',
];
