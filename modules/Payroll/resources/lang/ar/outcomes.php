<?php

declare(strict_types=1);

/*
| أسباب احتساب قيدة المستحقات — مفاتيح `payroll.outcomes` في الإعداد.
| تُعرض في عمود «سبب الاحتساب» بلوحة الأدمن. المفتاح نفسه يبقى في القيدة.
*/

return [
    'completed' => 'الحصة أُقيمت',
    'makeup_completed' => 'حصة تلافي أُقيمت',
    'student_no_show' => 'الطالب لم يحضر',
    'no_show' => 'الطالب لم يحضر',
    'student_excused' => 'غياب الطالب بعذر',
    'cancelled_accepted' => 'إلغاء مقبول',
    'cancelled_by_student' => 'إلغاء من الطالب',
    'cancelled_late_by_student' => 'إلغاء متأخر من الطالب',
    'cancelled_by_school' => 'إلغاء من المؤسسة',
    'teacher_absent' => 'المعلم لم يحضر',
    'postponed' => 'الحصة مؤجَّلة',
];
