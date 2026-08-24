<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Enrollments.
| تُستهلك عبر __('enrollments::errors.key') — ومفاتيح الأخطاء تصف المعنى لا النص.
*/

return [
    'organization_mismatch' => 'القيد ينتمي إلى مؤسسة أخرى.',
    'archived_enrollment' => 'لا يمكن إعادة استخدام قيد مؤرشف في التسكين.',
    'invalid_placement_transition' => 'لا يمكن نقل القيد من «:from» إلى «:to» أثناء التسكين.',
    'student_not_cleared' => 'الطالب غير مقبول للتسكين بعد.',
    'academic_context_invalid' => 'البرنامج أو الكورس المحدد غير نشط داخل هذه المؤسسة.',
    'eligibility_blocked' => 'الطالب لا يستوفي شروط أهلية البرنامج المحدد.',
];
