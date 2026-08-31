<?php

declare(strict_types=1);

/*
| أسماء أحداث الإشعارات كما تُعرض في شاشة قوالب الإشعارات.
| المفتاح (session.scheduled) هو ما يُخزَّن في القالب ولا يتغيّر؛ هذه تسمية عرض.
*/

return [

    'assignment.created' => 'واجب جديد',
    'assignment.submitted' => 'تسليم واجب',
    'submission.graded' => 'تصحيح واجب',

    'classroom.guest_invited' => 'دعوة ضيف للفصل الافتراضي',

    'discipline.action_applied' => 'تطبيق إجراء انضباطي',
    'discipline.student_frozen' => 'تجميد قيد طالب',

    'registration.submitted' => 'تقديم طلب تسجيل',
    'registration.approved' => 'قبول طلب تسجيل',
    'registration.rejected' => 'رفض طلب تسجيل',

    'session.scheduled' => 'جدولة حصة',
    'session.rescheduled' => 'تغيير موعد حصة',
    'session.approaching' => 'اقتراب موعد الحصة',
    'session.joinable' => 'فتح الانضمام للحصة',
    'session.report.due' => 'استحقاق تقرير الحصة',
    'session.report.late' => 'تأخر تقرير الحصة',
    'session.substitute.required' => 'الحاجة إلى معلم بديل',
    'session.substitute.assigned' => 'إسناد معلم بديل',
    'session.substitute.changed' => 'تغيير المعلم البديل',

    'student.assigned_to_group' => 'إسناد طالب إلى مجموعة',
    'student.assigned_to_teacher' => 'إسناد طالب إلى معلم',

    'teacher.apology.submitted' => 'تقديم اعتذار معلم',
    'teacher.apology.approved' => 'قبول اعتذار معلم',
    'teacher.apology.rejected' => 'رفض اعتذار معلم',
    'teacher.apology.second_warning' => 'الإنذار الثاني للمعلم',
    'teacher.apology.third_escalation' => 'التصعيد الثالث للمعلم',
    'teacher.availability.approved' => 'اعتماد أوقات إتاحة المعلم',
];
